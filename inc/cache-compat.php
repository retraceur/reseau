<?php
/**
 * Object Cache API functions missing from 3rd party object caches.
 *
 * @since 1.0.0 Retraceur réseau.
 *
 * @package Réseau
 * @subpackage Cache
 */

if ( ! function_exists( 'wp_cache_switch_to_blog' ) ) :
	/**
	 * Used when switch_to_blog() and restore_current_blog() are called, but
	 * only when a persistent object cache drop-in plugin has omitted the
	 * wp_cache_switch_to_blog() function that was introduced in 3.5.0.
	 *
	 * @since WP 7.0.0
	 *
	 * @global WP_Object_Cache $wp_object_cache Object cache global instance.
	 *
	 * @param int $blog_id Site ID.
	 */
	function wp_cache_switch_to_blog( $blog_id ) {
		global $wp_object_cache;

		// Attempt to use the drop-in object cache method if it exists.
		if ( method_exists( $wp_object_cache, 'switch_to_blog' ) ) {
			$wp_object_cache->switch_to_blog( $blog_id );
			return;
		}

		/*
		 * Perform a fallback blog switch, which will reinitialize the caches
		 * for the new blog ID.
		 */
		wp_cache_switch_to_blog_fallback( $blog_id );
	}
endif;
