<?php
// It's needed to hook into `wp_get_db_schema()``

function retraceur_reseau_db_schema( $tables = array(), $charset_collate = '' ) {
	// Engage multisite if in the middle of turning it on from network.php.
	if (  is_multisite() || ( defined( 'WP_INSTALLING_NETWORK' ) && WP_INSTALLING_NETWORK ) ) {
		// Multisite users table.
		$tables['users_table'] = "CREATE TABLE $wpdb->users (
			ID bigint(20) unsigned NOT NULL auto_increment,
			user_login varchar(60) NOT NULL default '',
			user_pass varchar(255) NOT NULL default '',
			user_nicename varchar(50) NOT NULL default '',
			user_email varchar(100) NOT NULL default '',
			user_url varchar(100) NOT NULL default '',
			user_registered datetime NOT NULL default '0000-00-00 00:00:00',
			user_activation_key varchar(255) NOT NULL default '',
			user_status int(11) NOT NULL default '0',
			display_name varchar(250) NOT NULL default '',
			spam tinyint(2) NOT NULL default '0',
			deleted tinyint(2) NOT NULL default '0',
			PRIMARY KEY  (ID),
			KEY user_login_key (user_login),
			KEY user_nicename (user_nicename),
			KEY user_email (user_email)
		) $charset_collate;\n";

		// Multisite global tables.
		$tables['ms_global'] = "CREATE TABLE $wpdb->blogs (
			blog_id bigint(20) unsigned NOT NULL auto_increment,
			site_id bigint(20) unsigned NOT NULL default '0',
			domain varchar(200) NOT NULL default '',
			path varchar(100) NOT NULL default '',
			registered datetime NOT NULL default '0000-00-00 00:00:00',
			last_updated datetime NOT NULL default '0000-00-00 00:00:00',
			public tinyint(2) NOT NULL default '1',
			archived tinyint(2) NOT NULL default '0',
			mature tinyint(2) NOT NULL default '0',
			spam tinyint(2) NOT NULL default '0',
			deleted tinyint(2) NOT NULL default '0',
			lang_id int(11) NOT NULL default '0',
			PRIMARY KEY  (blog_id),
			KEY domain (domain(50),path(5)),
			KEY lang_id (lang_id)
		) $charset_collate;
		CREATE TABLE $wpdb->blogmeta (
			meta_id bigint(20) unsigned NOT NULL auto_increment,
			blog_id bigint(20) unsigned NOT NULL default '0',
			meta_key varchar(255) default NULL,
			meta_value longtext,
			PRIMARY KEY  (meta_id),
			KEY meta_key (meta_key($max_index_length)),
			KEY blog_id (blog_id)
		) $charset_collate;
		CREATE TABLE $wpdb->registration_log (
			ID bigint(20) unsigned NOT NULL auto_increment,
			email varchar(255) NOT NULL default '',
			IP varchar(30) NOT NULL default '',
			blog_id bigint(20) unsigned NOT NULL default '0',
			date_registered datetime NOT NULL default '0000-00-00 00:00:00',
			PRIMARY KEY  (ID),
			KEY IP (IP)
		) $charset_collate;
		CREATE TABLE $wpdb->site (
			id bigint(20) unsigned NOT NULL auto_increment,
			domain varchar(200) NOT NULL default '',
			path varchar(100) NOT NULL default '',
			PRIMARY KEY  (id),
			KEY domain (domain(140),path(51))
		) $charset_collate;
		CREATE TABLE $wpdb->sitemeta (
			meta_id bigint(20) unsigned NOT NULL auto_increment,
			site_id bigint(20) unsigned NOT NULL default '0',
			meta_key varchar(255) default NULL,
			meta_value longtext,
			PRIMARY KEY  (meta_id),
			KEY meta_key (meta_key($max_index_length)),
			KEY site_id (site_id)
		) $charset_collate;";
	}

	return $tables;
}
add_filter( 'retraceur_db_schema', 'retraceur_reseau_db_schema', 10, 2 );

if ( ! function_exists( 'install_network' ) ) :
	/**
	 * Install Network.
	 *
	 * @since WP 3.0.0
	 */
	function install_network() {
		if ( ! defined( 'WP_INSTALLING_NETWORK' ) ) {
			define( 'WP_INSTALLING_NETWORK', true );
		}

		dbDelta( wp_get_db_schema( 'global' ) );
	}
endif;

/**
 * Creates Reacteur network meta and sets the default values.
 *
 * @since WP 5.1.0
 *
 * @global wpdb $wpdb          Reacteur database abstraction object.
 * @global int  $wp_db_version Reacteur database version.
 *
 * @param int   $network_id Network ID to populate meta for.
 * @param array $meta       Optional. Custom meta $key => $value pairs to use. Default empty array.
 */
function populate_network_meta( $network_id, array $meta = array() ) {
	global $wpdb, $wp_db_version;

	$network_id = (int) $network_id;

	$email             = ! empty( $meta['admin_email'] ) ? $meta['admin_email'] : '';
	$subdomain_install = isset( $meta['subdomain_install'] ) ? (int) $meta['subdomain_install'] : 0;

	// If a user with the provided email does not exist, default to the current user as the new network admin.
	$site_user = ! empty( $email ) ? get_user_by( 'email', $email ) : false;
	if ( false === $site_user ) {
		$site_user = wp_get_current_user();
	}

	if ( empty( $email ) ) {
		$email = $site_user->user_email;
	}

	$template       = get_option( 'template' );
	$stylesheet     = get_option( 'stylesheet' );
	$allowed_themes = array( $stylesheet => true );

	if ( $template !== $stylesheet ) {
		$allowed_themes[ $template ] = true;
	}

	if ( WP_DEFAULT_THEME !== $stylesheet && WP_DEFAULT_THEME !== $template ) {
		$allowed_themes[ WP_DEFAULT_THEME ] = true;
	}

	// If WP_DEFAULT_THEME doesn't exist, also include the latest core default theme.
	if ( ! wp_get_theme( WP_DEFAULT_THEME )->exists() ) {
		$core_default = WP_Theme::get_core_default_theme();
		if ( $core_default ) {
			$allowed_themes[ $core_default->get_stylesheet() ] = true;
		}
	}

	if ( function_exists( 'clean_network_cache' ) ) {
		clean_network_cache( $network_id );
	} else {
		wp_cache_delete( $network_id, 'networks' );
	}

	if ( ! is_multisite() ) {
		$site_admins = array( $site_user->user_login );
		$users       = get_users(
			array(
				'fields' => array( 'user_login' ),
				'role'   => 'administrator',
			)
		);
		if ( $users ) {
			foreach ( $users as $user ) {
				$site_admins[] = $user->user_login;
			}

			$site_admins = array_unique( $site_admins );
		}
	} else {
		$site_admins = get_site_option( 'site_admins' );
	}

	/* translators: Do not translate USERNAME, SITE_NAME, BLOG_URL, PASSWORD: those are placeholders. */
	$welcome_email = __(
		'Howdy USERNAME,

Your new SITE_NAME site has been successfully set up at:
BLOG_URL

You can log in to the administrator account with the following information:

Username: USERNAME
Password: PASSWORD
Log in here: BLOG_URLwp-login.php

We hope you enjoy your new site. Thanks!

--The Team @ SITE_NAME'
	);

	$allowed_file_types = array();
	$all_mime_types     = get_allowed_mime_types();

	foreach ( $all_mime_types as $ext => $mime ) {
		array_push( $allowed_file_types, ...explode( '|', $ext ) );
	}
	$upload_filetypes = array_unique( $allowed_file_types );

	$sitemeta = array(
		'site_name'                   => __( 'My Network' ),
		'admin_email'                 => $email,
		'admin_user_id'               => $site_user->ID,
		'registration'                => 'none',
		'upload_filetypes'            => implode( ' ', $upload_filetypes ),
		'blog_upload_space'           => 100,
		'fileupload_maxk'             => 1500,
		'site_admins'                 => $site_admins,
		'allowedthemes'               => $allowed_themes,
		'illegal_names'               => array( 'www', 'web', 'root', 'admin', 'main', 'invite', 'administrator', 'files' ),
		'wpmu_upgrade_site'           => $wp_db_version,
		'welcome_email'               => $welcome_email,
		/* translators: %s: Site link. */
		'first_post'                  => __( 'Welcome to %s. This is your first post. Edit or delete it, then start writing!' ),
		// @todo - Network admins should have a method of editing the network siteurl (used for cookie hash).
		'siteurl'                     => get_option( 'siteurl' ) . '/',
		'add_new_users'               => '0',
		'upload_space_check_disabled' => is_multisite() ? get_site_option( 'upload_space_check_disabled' ) : '1',
		'subdomain_install'           => $subdomain_install,
		'ms_files_rewriting'          => is_multisite() ? get_site_option( 'ms_files_rewriting' ) : '0',
		'user_count'                  => get_site_option( 'user_count' ),
		'initial_db_version'          => get_option( 'initial_db_version' ),
		'active_sitewide_plugins'     => array(),
		'WPLANG'                      => get_locale(),
	);
	if ( ! $subdomain_install ) {
		$sitemeta['illegal_names'][] = 'blog';
	}

	$sitemeta = wp_parse_args( $meta, $sitemeta );

	/**
	 * Filters meta for a network on creation.
	 *
	 * @since WP 3.7.0
	 *
	 * @param array $sitemeta   Associative array of network meta keys and values to be inserted.
	 * @param int   $network_id ID of network to populate.
	 */
	$sitemeta = apply_filters( 'populate_network_meta', $sitemeta, $network_id );

	$insert = '';
	foreach ( $sitemeta as $meta_key => $meta_value ) {
		if ( is_array( $meta_value ) ) {
			$meta_value = serialize( $meta_value );
		}
		if ( ! empty( $insert ) ) {
			$insert .= ', ';
		}
		$insert .= $wpdb->prepare( '( %d, %s, %s)', $network_id, $meta_key, $meta_value );
	}
	$wpdb->query( "INSERT INTO $wpdb->sitemeta ( site_id, meta_key, meta_value ) VALUES " . $insert ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Populate network settings.
 *
 * @since WP 3.0.0
 *
 * @global wpdb       $wpdb         Reacteur database abstraction object.
 * @global object     $current_site
 * @global WP_Rewrite $wp_rewrite   Reacteur rewrite component.
 *
 * @param int    $network_id        ID of network to populate.
 * @param string $domain            The domain name for the network. Example: "example.com".
 * @param string $email             Email address for the network administrator.
 * @param string $site_name         The name of the network.
 * @param string $path              Optional. The path to append to the network's domain name. Default '/'.
 * @param bool   $subdomain_install Optional. Whether the network is a subdomain installation or a subdirectory installation.
 *                                  Default false, meaning the network is a subdirectory installation.
 * @return true|WP_Error True on success, or WP_Error on warning (with the installation otherwise successful,
 *                       so the error code must be checked) or failure.
 */
function populate_network( $network_id = 1, $domain = '', $email = '', $site_name = '', $path = '/', $subdomain_install = false ) {
	global $wpdb, $current_site, $wp_rewrite;

	$network_id = (int) $network_id;

	/**
	 * Fires before a network is populated.
	 *
	 * @since WP 6.9.0
	 *
	 * @param int    $network_id        ID of network to populate.
	 * @param string $domain            The domain name for the network.
	 * @param string $email             Email address for the network administrator.
	 * @param string $site_name         The name of the network.
	 * @param string $path              The path to append to the network's domain name.
	 * @param bool   $subdomain_install Whether the network is a subdomain installation or a subdirectory installation.
	 */
	do_action( 'before_populate_network', $network_id, $domain, $email, $site_name, $path, $subdomain_install );

	$errors = new WP_Error();
	if ( '' === $domain ) {
		$errors->add( 'empty_domain', __( 'You must provide a domain name.' ) );
	}
	if ( '' === $site_name ) {
		$errors->add( 'empty_sitename', __( 'You must provide a name for your network of sites.' ) );
	}

	// Check for network collision.
	$network_exists = false;
	if ( is_multisite() ) {
		if ( get_network( $network_id ) ) {
			$errors->add( 'siteid_exists', __( 'The network already exists.' ) );
		}
	} else {
		if ( $network_id === (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM $wpdb->site WHERE id = %d", $network_id )
		) ) {
			$errors->add( 'siteid_exists', __( 'The network already exists.' ) );
		}
	}

	if ( ! is_email( $email ) ) {
		$errors->add( 'invalid_email', __( 'You must provide a valid email address.' ) );
	}

	if ( $errors->has_errors() ) {
		return $errors;
	}

	if ( 1 === $network_id ) {
		$wpdb->insert(
			$wpdb->site,
			array(
				'domain' => $domain,
				'path'   => $path,
			)
		);
		$network_id = $wpdb->insert_id;
	} else {
		$wpdb->insert(
			$wpdb->site,
			array(
				'domain' => $domain,
				'path'   => $path,
				'id'     => $network_id,
			)
		);
	}

	populate_network_meta(
		$network_id,
		array(
			'admin_email'       => $email,
			'site_name'         => $site_name,
			'subdomain_install' => $subdomain_install,
		)
	);

	// Remove the cron event since Recovery Mode is not used in Multisite.
	if ( wp_next_scheduled( 'recovery_mode_clean_expired_keys' ) ) {
		wp_clear_scheduled_hook( 'recovery_mode_clean_expired_keys' );
	}

	/*
	 * When upgrading from single to multisite, assume the current site will
	 * become the main site of the network. When using populate_network()
	 * to create another network in an existing multisite environment, skip
	 * these steps since the main site of the new network has not yet been
	 * created.
	 */
	if ( ! is_multisite() ) {
		$current_site            = new stdClass();
		$current_site->domain    = $domain;
		$current_site->path      = $path;
		$current_site->site_name = ucfirst( $domain );
		$wpdb->insert(
			$wpdb->blogs,
			array(
				'site_id'    => $network_id,
				'blog_id'    => 1,
				'domain'     => $domain,
				'path'       => $path,
				'registered' => current_time( 'mysql' ),
			)
		);
		$current_site->blog_id = $wpdb->insert_id;

		$site_user_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value
				FROM $wpdb->sitemeta
				WHERE meta_key = %s AND site_id = %d",
				'admin_user_id',
				$network_id
			)
		);

		update_user_meta( $site_user_id, 'source_domain', $domain );
		update_user_meta( $site_user_id, 'primary_blog', $current_site->blog_id );

		// Unable to use update_network_option() while populating the network.
		$wpdb->insert(
			$wpdb->sitemeta,
			array(
				'site_id'    => $network_id,
				'meta_key'   => 'main_site',
				'meta_value' => $current_site->blog_id,
			)
		);

		if ( $subdomain_install ) {
			$wp_rewrite->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		} else {
			$wp_rewrite->set_permalink_structure( '/blog/%year%/%monthnum%/%day%/%postname%/' );
		}

		flush_rewrite_rules();

		/**
		 * Fires after a network is created when converting a single site to multisite.
		 *
		 * @since WP 6.9.0
		 *
		 * @param int    $network_id        ID of network created.
		 * @param string $domain            The domain name for the network.
		 * @param string $email             Email address for the network administrator.
		 * @param string $site_name         The name of the network.
		 * @param string $path              The path to append to the network's domain name.
		 * @param bool   $subdomain_install Whether the network is a subdomain installation or a subdirectory installation.
		 */
		do_action( 'after_upgrade_to_multisite', $network_id, $domain, $email, $site_name, $path, $subdomain_install );

		if ( ! $subdomain_install ) {
			return true;
		}

		$vhost_ok = false;
		$errstr   = '';
		$hostname = substr( md5( time() ), 0, 6 ) . '.' . $domain; // Very random hostname!
		$page     = wp_remote_get(
			'http://' . $hostname,
			array(
				'timeout'     => 5,
				'httpversion' => '1.1',
			)
		);
		if ( is_wp_error( $page ) ) {
			$errstr = $page->get_error_message();
		} elseif ( 200 === wp_remote_retrieve_response_code( $page ) ) {
				$vhost_ok = true;
		}

		if ( ! $vhost_ok ) {
			$msg = '<p><strong>' . __( 'Warning! Wildcard DNS may not be configured correctly!' ) . '</strong></p>';

			$msg .= '<p>' . sprintf(
				/* translators: %s: Host name. */
				__( 'The installer attempted to contact a random hostname (%s) on your domain.' ),
				'<code>' . $hostname . '</code>'
			);
			if ( ! empty( $errstr ) ) {
				/* translators: %s: Error message. */
				$msg .= ' ' . sprintf( __( 'This resulted in an error message: %s' ), '<code>' . $errstr . '</code>' );
			}
			$msg .= '</p>';

			$msg .= '<p>' . sprintf(
				/* translators: %s: Asterisk symbol (*). */
				__( 'To use a subdomain configuration, you must have a wildcard entry in your DNS. This usually means adding a %s hostname record pointing at your web server in your DNS configuration tool.' ),
				'<code>*</code>'
			) . '</p>';

			$msg .= '<p>' . __( 'You can still use your site but any subdomain you create may not be accessible. If you know your DNS is correct, ignore this message.' ) . '</p>';

			return new WP_Error( 'no_wildcard_dns', $msg );
		}
	}

	/**
	 * Fires after a network is fully populated.
	 *
	 * @since WP 6.9.0
	 *
	 * @param int    $network_id        ID of network created.
	 * @param string $domain            The domain name for the network.
	 * @param string $email             Email address for the network administrator.
	 * @param string $site_name         The name of the network.
	 * @param string $path              The path to append to the network's domain name.
	 * @param bool   $subdomain_install Whether the network is a subdomain installation or a subdirectory installation.
	 */
	do_action( 'after_populate_network', $network_id, $domain, $email, $site_name, $path, $subdomain_install );

	return true;
}

/**
 * Creates Reacteur site meta and sets the default values.
 *
 * @since WP 5.1.0
 *
 * @global wpdb $wpdb Reacteur database abstraction object.
 *
 * @param int   $site_id Site ID to populate meta for.
 * @param array $meta    Optional. Custom meta $key => $value pairs to use. Default empty array.
 */
function populate_site_meta( $site_id, array $meta = array() ) {
	global $wpdb;

	$site_id = (int) $site_id;

	if ( ! is_site_meta_supported() ) {
		return;
	}

	if ( empty( $meta ) ) {
		return;
	}

	/**
	 * Filters meta for a site on creation.
	 *
	 * @since WP 5.2.0
	 *
	 * @param array $meta    Associative array of site meta keys and values to be inserted.
	 * @param int   $site_id ID of site to populate.
	 */
	$site_meta = apply_filters( 'populate_site_meta', $meta, $site_id );

	$insert = '';
	foreach ( $site_meta as $meta_key => $meta_value ) {
		if ( is_array( $meta_value ) ) {
			$meta_value = serialize( $meta_value );
		}
		if ( ! empty( $insert ) ) {
			$insert .= ', ';
		}
		$insert .= $wpdb->prepare( '( %d, %s, %s)', $site_id, $meta_key, $meta_value );
	}

	$wpdb->query( "INSERT INTO $wpdb->blogmeta ( blog_id, meta_key, meta_value ) VALUES " . $insert ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	wp_cache_delete( $site_id, 'blog_meta' );
	wp_cache_set_sites_last_changed();
}
