<?php
// This menu needs to be added
if ( ! is_multisite() && defined( 'WP_ALLOW_MULTISITE' ) && WP_ALLOW_MULTISITE ) {
	$submenu['tools.php'][50] = array( __( 'Network Setup' ), 'setup_network', 'network.php' );
}

if ( is_multisite() ) {
	$submenu['index.php'][5] = array( __( 'My Sites' ), 'read', 'my-sites.php' );
}

if ( is_multisite() ) {
	$submenu['profile.php'][10] = array( __( 'Add New User' ), 'promote_users', 'user-new.php' );
}

if ( is_multisite() ) {
	$submenu['users.php'][10] = array( __( 'Add New User' ), 'promote_users', 'user-new.php' );
}

if ( is_multisite() && ! is_main_site() ) {
	$submenu['tools.php'][35] = array( __( 'Delete Site' ), 'delete_site', 'ms-delete-site.php' );
}
