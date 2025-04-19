<?php
/**
 * Plugin Name: Réseau
 * Plugin URI: https://github.com/retraceur/reseau/
 * Description: Retraceur's Network feature as a plugin.
 * Version: 1.0.0-aplpha
 * Requires PHP: 5.6
 * Requires Retraceur: 1.0.0
 * Up to Retraceur:    1.0.0
 * License: GNU/GPL 2
 * Author: imath
 * Author URI: https://imathi.eu/
 * Text Domain: retraceur-reseau
 * Domain Path: /languages/
 * Network: True
 * GitHub Plugin URI: https://github.com/retraceur/reseau/
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
