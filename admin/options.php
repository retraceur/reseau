<?php

// This code is needed during page load.
if ( is_multisite() && ! current_user_can( 'manage_network_options' ) && 'update' !== $action ) {
	wp_die(
		'<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
		'<p>' . __( 'Sorry, you are not allowed to delete these items.' ) . '</p>',
		403
	);
}
