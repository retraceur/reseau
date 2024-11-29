<?php

function retraceur_reseau_init() {
	require dirname( __FILE__ ) . '/inc/class-wp-site-query.php';
	require dirname( __FILE__ ) . '/inc/class-wp-network-query.php';
	require dirname( __FILE__ ) . '/inc/ms-blogs.php';
	require dirname( __FILE__ ) . '/inc/ms-settings.php';
}
add_action( 'retraceur_init_multisite', 'retraceur_reseau_init' );

function retraceur_reseau_setup() {
	require dirname( __FILE__ ) . '/inc/ms-functions.php';
	require dirname( __FILE__ ) . '/inc/ms-default-filters.php';
	require dirname( __FILE__ ) . '/inc/ms-deprecated.php';
}
add_action( 'retraceur_setup_multisite', 'retraceur_reseau_setup' );
