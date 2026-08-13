<?php
/**
 * REST API: WP_REST_Site_Meta_Fields class
 *
 * @package    WordPress
 * @subpackage REST_API
 * @since      x.x.x
 */

/**
 * Core class to manage site meta via the REST API.
 *
 * @since x.x.x
 *
 * @see   WP_REST_Meta_Fields
 */
class WP_REST_Site_Meta_Fields extends WP_REST_Meta_Fields {

	/**
	 * Retrieves the object type for site meta.
	 *
	 * Site meta is stored in the blogmeta table and uses the `blog` meta type,
	 * the same one `add_site_meta()` and `get_site_meta()` pass to the metadata
	 * API. The `site` meta type resolves to sitemeta, which holds network meta.
	 *
	 * @return string The meta type.
	 * @since x.x.x
	 *
	 */
	protected function get_meta_type() {
		return 'blog';
	}

	/**
	 * Retrieves the object meta subtype.
	 *
	 * @return string '' There are no subtypes.
	 * @since x.x.x
	 *
	 */
	protected function get_meta_subtype() {
		return '';
	}

	/**
	 * Retrieves the type for register_rest_field() in the context of sites.
	 *
	 * @return string The REST field type.
	 * @since x.x.x
	 *
	 */
	public function get_rest_field_type() {
		return 'site';
	}
}
