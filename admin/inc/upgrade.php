<?php
function retraceur_reseau_install_default_post( $post = '' ) {
	$first_post = get_site_option( 'first_post' );

	if ( ! $first_post ) {
		$first_post = "<!-- wp:paragraph -->\n<p>" .
		/* translators: First post content. %s: Site link. */
		__( 'Welcome to %s. This is your first post. Edit or delete it, then start writing!' ) .
		"</p>\n<!-- /wp:paragraph -->";
	}

	$first_post = sprintf(
		$first_post,
		sprintf( '<a href="%s">%s</a>', esc_url( network_home_url() ), get_network()->site_name )
	);

	// Back-compat for pre-4.4.
	$first_post = str_replace( 'SITE_URL', esc_url( network_home_url() ), $first_post );
	$first_post = str_replace( 'SITE_NAME', get_network()->site_name, $first_post );

	return $first_post;
}
add_filter( 'retraceur_ms_default_post', 'retraceur_reseau_install_default_post' );

function retraceur_reseau_install_default_page( $page = '' ) {
	$first_page = get_site_option( 'first_page' );

	if ( $first_page ) {
		$page = $first_page;
	}

	return $page;
}
add_filter( 'retraceur_ms_default_page', 'retraceur_reseau_install_default_page' );

/**
 * Run additionnal code for multisite configs once initial default content was created.
 *
 * @since 1.0.0
 *
 * @global WP_Rewrite $wp_rewrite   Retraceur rewrite component.
 *
 * @param int $user_id User ID.
 */
function retraceur_reseau_installed_defaults( $user_id = 0 ) {
	global $wp_rewrite;

	// Flush rules to pick up the new page.
	$wp_rewrite->init();
	$wp_rewrite->flush_rules();

	$user = new WP_User( $user_id );
	$wpdb->update( $wpdb->options, array( 'option_value' => $user->user_email ), array( 'option_name' => 'admin_email' ) );

	// Remove all perms except for the login user.
	$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix . 'user_level' ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->usermeta WHERE user_id != %d AND meta_key = %s", $user_id, $table_prefix . 'capabilities' ) );

	/*
	 * Delete any caps that snuck into the previously active blog. (Hardcoded to blog 1 for now.)
	 * TODO: Get previous_blog_id.
	 */
	if ( ! is_super_admin( $user_id ) && 1 !== $user_id ) {
		$wpdb->delete(
			$wpdb->usermeta,
			array(
				'user_id'  => $user_id,
				'meta_key' => $wpdb->base_prefix . '1_capabilities',
			)
		);
	}
}
add_action( 'retraceur_installed_defaults', 'retraceur_reseau_installed_defaults' );
