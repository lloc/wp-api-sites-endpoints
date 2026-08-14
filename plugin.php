<?php
/**
 * Plugin Name: WP REST API - Sites Endpoints
 * Description: Sites Endpoints for the WP REST API
 * Author: WP REST API Team
 * Author URI: http://wp-api.org
 * Version: 0.1.0
 * Plugin URI: https://github.com/WP-API/wp-api-sites-endpoint
 * License: GPL2+
 * Network: true
 */

/**
 * Registers the sites endpoints.
 *
 * @return void
 */
function sites_rest_api_init() {
	$controller = new WP_REST_Sites_Controller();
	$controller->register_routes();
}

/*
 * Core loads its classes before the plugins, so an existing controller at this
 * point belongs to core or to another plugin.
 */
if ( is_multisite() && ! class_exists( 'WP_REST_Sites_Controller' ) ) {
	require_once __DIR__ . '/lib/class-wp-rest-site-meta-fields.php';
	require_once __DIR__ . '/lib/class-wp-rest-sites-controller.php';

	add_action( 'rest_api_init', 'sites_rest_api_init' );
}
