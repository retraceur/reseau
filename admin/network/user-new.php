<?php
/**
 * Add User network administration panel.
 *
 * @since WP 3.1.0
 * @since 1.0.0 Retraceur fork.
 *
 * @package Retraceur
 * @subpackage Multisite
 */

/** Load WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'create_users' ) ) {
	wp_die( __( 'Sorry, you are not allowed to add contributors to this network.' ) );
}

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
			'<p>' . __( 'Add Contributor will set up a new user account on the network and send that person an email with username and password.' ) . '</p>' .
			'<p>' . __( 'Contributors who are signed up to the network without a site are added as contributors to the main or primary dashboard site, giving them profile pages to manage their accounts. These users will only see Dashboard and My Sites in the main navigation until a site is created for them.' ) . '</p>',
	)
);

if ( isset( $_REQUEST['action'] ) && 'add-user' === $_REQUEST['action'] ) {
	check_admin_referer( 'add-user', '_wpnonce_add-user' );

	if ( ! current_user_can( 'manage_network_users' ) ) {
		wp_die( __( 'Sorry, you are not allowed to access this page.' ), 403 );
	}

	if ( ! is_array( $_POST['user'] ) ) {
		wp_die( __( 'Cannot create an empty contributor.' ) );
	}

	$user = wp_unslash( $_POST['user'] );

	$user_details = wpmu_validate_user_signup( $user['username'], $user['email'] );

	if ( is_wp_error( $user_details['errors'] ) && $user_details['errors']->has_errors() ) {
		$add_user_errors = $user_details['errors'];
	} else {
		$password = wp_generate_password( 12, false );
		$user_id  = wpmu_create_user( esc_html( strtolower( $user['username'] ) ), $password, sanitize_email( $user['email'] ) );

		if ( ! $user_id ) {
			$add_user_errors = new WP_Error( 'add_user_fail', __( 'Cannot add contributor.' ) );
		} else {
			/**
			 * Fires after a new user has been created via the network user-new.php page.
			 *
			 * @since WP 4.4.0
			 *
			 * @param int $user_id ID of the newly created user.
			 */
			do_action( 'network_user_new_created_user', $user_id );

			wp_redirect(
				add_query_arg(
					array(
						'update'  => 'added',
						'user_id' => $user_id,
					),
					'user-new.php'
				)
			);
			exit;
		}
	}
}

$message = '';
if ( isset( $_GET['update'] ) ) {
	if ( 'added' === $_GET['update'] ) {
		$edit_link = '';
		if ( isset( $_GET['user_id'] ) ) {
			$user_id_new = absint( $_GET['user_id'] );
			if ( $user_id_new ) {
				$edit_link = esc_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), get_edit_user_link( $user_id_new ) ) );
			}
		}

		$message = __( 'Contributor added.' );

		if ( $edit_link ) {
			$message .= sprintf( ' <a href="%s">%s</a>', $edit_link, __( 'Edit contributor' ) );
		}
	}
}

// Used in the HTML title tag.
$title       = __( 'Add Contributor' );
$parent_file = 'users.php';

require_once ABSPATH . 'wp-admin/admin-header.php';
?>

<div class="wrap">
<h1 id="add-new-user"><?php _e( 'Add Contributor' ); ?></h1>
<?php
if ( '' !== $message ) {
	wp_admin_notice(
		$message,
		array(
			'type'        => 'success',
			'dismissible' => true,
			'id'          => 'message',
		)
	);
}

if ( isset( $add_user_errors ) && is_wp_error( $add_user_errors ) ) {
	$error_messages = '';
	foreach ( $add_user_errors->get_error_messages() as $error ) {
		$error_messages .= "<p>$error</p>";
	}

	wp_admin_notice(
		$error_messages,
		array(
			'type'           => 'error',
			'dismissible'    => true,
			'id'             => 'message',
			'paragraph_wrap' => false,
		)
	);
}
?>
	<form action="<?php echo esc_url( network_admin_url( 'user-new.php?action=add-user' ) ); ?>" id="adduser" method="post" novalidate="novalidate">
		<p><?php echo wp_required_field_message(); ?></p>
		<table class="form-table" role="presentation">
			<tr class="form-field form-required">
				<th scope="row"><label for="username"><?php _e( 'Username' ); ?> <?php echo wp_required_field_indicator(); ?></label></th>
				<td><input type="text" class="regular-text" name="user[username]" id="username" autocapitalize="none" autocorrect="off" maxlength="60" required="required" /></td>
			</tr>
			<tr class="form-field form-required">
				<th scope="row"><label for="email"><?php _e( 'Email' ); ?> <?php echo wp_required_field_indicator(); ?></label></th>
				<td><input type="email" class="regular-text" name="user[email]" id="email" required="required" /></td>
			</tr>
			<tr class="form-field">
				<td colspan="2" class="td-full"><?php _e( 'A password reset link will be sent to the user via email.' ); ?></td>
			</tr>
		</table>
	<?php
	/**
	 * Fires at the end of the new user form in network admin.
	 *
	 * @since WP 4.5.0
	 */
	do_action( 'network_user_new_form' );

	wp_nonce_field( 'add-user', '_wpnonce_add-user' );
	submit_button( __( 'Add Contributor' ), 'primary', 'add-user' );
	?>
	</form>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
