<?php

if ( is_multisite() ) {
	if ( ! current_user_can( 'create_users' ) && ! current_user_can( 'promote_users' ) ) {
		wp_die(
			'<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
			'<p>' . __( 'Sorry, you are not allowed to add users to this network.' ) . '</p>',
			403
		);
	}
}

add_filter( 'wpmu_signup_user_notification_email', 'admin_created_user_email' );

if ( isset( $_REQUEST['action'] ) && 'adduser' === $_REQUEST['action'] ) {
	check_admin_referer( 'add-user', '_wpnonce_add-user' );

	$user_details = null;
	$user_email   = wp_unslash( $_REQUEST['email'] );

	if ( str_contains( $user_email, '@' ) ) {
		$user_details = get_user_by( 'email', $user_email );
	} else {
		if ( current_user_can( 'manage_network_users' ) ) {
			$user_details = get_user_by( 'login', $user_email );
		} else {
			wp_redirect( add_query_arg( array( 'update' => 'enter_email' ), 'user-new.php' ) );
			die();
		}
	}

	if ( ! $user_details ) {
		wp_redirect( add_query_arg( array( 'update' => 'does_not_exist' ), 'user-new.php' ) );
		die();
	}

	if ( ! current_user_can( 'promote_user', $user_details->ID ) ) {
		wp_die(
			'<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
			'<p>' . __( 'Sorry, you are not allowed to add users to this network.' ) . '</p>',
			403
		);
	}

	// Adding an existing user to this blog.
	$new_user_email = array();
	$redirect       = 'user-new.php';
	$username       = $user_details->user_login;
	$user_id        = $user_details->ID;

	if ( array_key_exists( $blog_id, get_blogs_of_user( $user_id ) ) ) {
		$redirect = add_query_arg( array( 'update' => 'addexisting' ), 'user-new.php' );
	} else {
		if ( isset( $_POST['noconfirmation'] ) && current_user_can( 'manage_network_users' ) ) {
			$result = add_existing_user_to_blog(
				array(
					'user_id' => $user_id,
					'role'    => $_REQUEST['role'],
				)
			);

			if ( ! is_wp_error( $result ) ) {
				$redirect = add_query_arg(
					array(
						'update'  => 'addnoconfirmation',
						'user_id' => $user_id,
					),
					'user-new.php'
				);
			} else {
				$redirect = add_query_arg( array( 'update' => 'could_not_add' ), 'user-new.php' );
			}
		} else {
			$newuser_key = wp_generate_password( 20, false );
			add_option(
				'new_user_' . $newuser_key,
				array(
					'user_id' => $user_id,
					'email'   => $user_details->user_email,
					'role'    => $_REQUEST['role'],
				)
			);

			$roles = get_editable_roles();
			$role  = $roles[ $_REQUEST['role'] ];

			/**
			 * Fires immediately after an existing user is invited to join the site, but before the notification is sent.
			 *
			 * @since WP 4.4.0
			 *
			 * @param int    $user_id     The invited user's ID.
			 * @param array  $role        Array containing role information for the invited user.
			 * @param string $newuser_key The key of the invitation.
			 */
			do_action( 'invite_user', $user_id, $role, $newuser_key );

			$switched_locale = switch_to_user_locale( $user_id );

			if ( '' !== get_option( 'blogname' ) ) {
				$site_title = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
			} else {
				$site_title = parse_url( home_url(), PHP_URL_HOST );
			}

			/* translators: 1: Site title, 2: Site URL, 3: User role, 4: Activation URL. */
			$message = __(
				'Hi,

You\'ve been invited to join \'%1$s\' at
%2$s with the role of %3$s.

Please click the following link to confirm the invite:
%4$s'
			);

			$new_user_email['to']      = $user_details->user_email;
			$new_user_email['subject'] = sprintf(
				/* translators: Joining confirmation notification email subject. %s: Site title. */
				__( '[%s] Joining Confirmation' ),
				$site_title
			);
			$new_user_email['message'] = sprintf(
				$message,
				get_option( 'blogname' ),
				home_url(),
				wp_specialchars_decode( translate_user_role( $role['name'] ) ),
				home_url( "/newbloguser/$newuser_key/" )
			);
			$new_user_email['headers'] = '';

			/**
			 * Filters the contents of the email sent when an existing user is invited to join the site.
			 *
			 * @since WP 5.6.0
			 *
			 * @param array $new_user_email {
			 *     Used to build wp_mail().
			 *
			 *     @type string $to      The email address of the invited user.
			 *     @type string $subject The subject of the email.
			 *     @type string $message The content of the email.
			 *     @type string $headers Headers.
			 * }
			 * @param int    $user_id     The invited user's ID.
			 * @param array  $role        Array containing role information for the invited user.
			 * @param string $newuser_key The key of the invitation.
			 *
			 */
			$new_user_email = apply_filters( 'invited_user_email', $new_user_email, $user_id, $role, $newuser_key );

			wp_mail(
				$new_user_email['to'],
				$new_user_email['subject'],
				$new_user_email['message'],
				$new_user_email['headers']
			);

			if ( $switched_locale ) {
				restore_previous_locale();
			}

			$redirect = add_query_arg( array( 'update' => 'add' ), 'user-new.php' );
		}
	}

	wp_redirect( $redirect );
	die();
}



$do_both = false;
if ( is_multisite() && current_user_can( 'promote_users' ) && current_user_can( 'create_users' ) ) {
	$do_both = true;
}

if ( is_multisite() ) {
	$help .= '<p>' . __( 'Because this is a multisite installation, you may add accounts that already exist on the Network by specifying a username or email, and defining a role. For more options, such as specifying a password, you have to be a Network Administrator and use the hover link under an existing user&#8217;s name to Edit the user profile under Network Admin > All Users.' ) . '</p>' .
	'<p>' . __( 'New users will receive an email letting them know they&#8217;ve been added as a user for your site. This email will also contain their password. Check the box if you do not want the user to receive a welcome email.' ) . '</p>';
}

/**
 * Filters whether to enable user auto-complete for non-super admins in Multisite.
 *
 * @since WP 3.4.0
 *
 * @param bool $enable Whether to enable auto-complete for non-super admins. Default false.
 */
if ( is_multisite() && current_user_can( 'promote_users' ) && ! wp_is_large_network( 'users' )
	&& ( current_user_can( 'manage_network_users' ) || apply_filters( 'autocomplete_users_for_site_admins', false ) )
) {
	wp_enqueue_script( 'user-suggest' );
}


if ( is_multisite() && current_user_can( 'promote_users' ) ) {
	if ( $do_both ) {
		echo '<h2 id="add-existing-user">' . __( 'Add Existing User' ) . '</h2>';
	}
	if ( ! current_user_can( 'manage_network_users' ) ) {
		echo '<p>' . __( 'Enter the email address of an existing user on this network to invite them to this site. That person will be sent an email asking them to confirm the invite.' ) . '</p>';
		$label = __( 'Email' );
		$type  = 'email';
	} else {
		echo '<p>' . __( 'Enter the email address or username of an existing user on this network to invite them to this site. That person will be sent an email asking them to confirm the invite.' ) . '</p>';
		$label = __( 'Email or Username' );
		$type  = 'text';
	}
	?>
<form method="post" name="adduser" id="adduser" class="validate" novalidate="novalidate"
	<?php
	/**
	 * Fires inside the adduser form tag.
	 *
	 * @since WP 3.0.0
	 */
	do_action( 'user_new_form_tag' );
	?>
>
<input name="action" type="hidden" value="adduser" />
	<?php wp_nonce_field( 'add-user', '_wpnonce_add-user' ); ?>

<table class="form-table" role="presentation">
	<tr class="form-field form-required">
		<th scope="row"><label for="adduser-email"><?php echo esc_html( $label ); ?></label></th>
		<td><input name="email" type="<?php echo esc_attr( $type ); ?>" id="adduser-email" class="wp-suggest-user" value="" /></td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="adduser-role"><?php _e( 'Role' ); ?></label></th>
		<td><select name="role" id="adduser-role">
			<?php wp_dropdown_roles( get_option( 'default_role' ) ); ?>
			</select>
		</td>
	</tr>
	<?php if ( current_user_can( 'manage_network_users' ) ) { ?>
	<tr>
		<th scope="row"><?php _e( 'Skip Confirmation Email' ); ?></th>
		<td>
			<input type="checkbox" name="noconfirmation" id="adduser-noconfirmation" value="1" />
			<label for="adduser-noconfirmation"><?php _e( 'Add the user without sending an email that requires their confirmation' ); ?></label>
		</td>
	</tr>
	<?php } ?>
</table>
	<?php
	/**
	 * Fires at the end of the new user form.
	 *
	 * Passes a contextual string to make both types of new user forms
	 * uniquely targetable. Contexts are 'add-existing-user' (Multisite),
	 * and 'add-new-user' (single site and network admin).
	 *
	 * @since WP 3.7.0
	 *
	 * @param string $type A contextual string specifying which type of new user form the hook follows.
	 */
	do_action( 'user_new_form', 'add-existing-user' );
	?>
	<?php submit_button( __( 'Add Existing User' ), 'primary', 'adduser', true, array( 'id' => 'addusersub' ) ); ?>
</form>
	<?php
} // End if is_multisite().

function retraceur_network_validate_signup( $result ) {
	$limited_email_domains = get_site_option( 'limited_email_domains' );

	if ( is_email_address_unsafe( $result['user_email'] ) ) {
		if ( ! is_wp_error( $result['errors'] ) ) {
			$result['errors'] = new WP_Error();
		}

		$result['errors']->add( 'user_email', __( 'You cannot use that email address to signup. There are problems with them blocking some emails from WordPress. Please use another email provider.' ) );
	}

	if ( is_array( $limited_email_domains ) && ! empty( $limited_email_domains ) ) {
		$limited_email_domains = array_map( 'strtolower', $limited_email_domains );
		$emaildomain           = strtolower( substr( $result['user_email'], 1 + strpos( $result['user_email'], '@' ) ) );
		if ( ! in_array( $emaildomain, $limited_email_domains, true ) ) {
			if ( ! is_wp_error( $result['errors'] ) ) {
				$result['errors'] = new WP_Error();
			}

			$result['errors']->add( 'user_email', __( 'Sorry, that email address is not allowed!' ) );
		}
	}

	return $result;
}
add_filter( 'retraceur_validate_signup', 'retraceur_network_validate_signup' );
