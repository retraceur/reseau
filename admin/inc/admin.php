<?php

function retraceur_reseau_setup_admin() {
	require_once dirname( __FILE__ ) . '/ms-admin-filters.php';
	require_once dirname( __FILE__ ) . '/ms.php';
	require_once dirname( __FILE__ ) . '/ms-deprecated.php';
}
add_action( 'retraceur_admin_setup_multisite', 'retraceur_reseau_setup_admin' );
