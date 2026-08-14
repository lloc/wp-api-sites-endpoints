<?php

class WP_Test_REST_Site_Controller extends WP_Test_REST_Controller_TestCase {

	protected static $superadmin_id;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$superadmin_id = $factory->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'superadmin',
			)
		);

		if ( is_multisite() ) {
			update_site_option( 'site_admins', array( 'superadmin' ) );
		}
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$superadmin_id );
	}

	/**
	 *
	 */
	public function set_up() {
		parent::set_up();
		$this->endpoint = new WP_REST_Sites_Controller();
	}

	/**
	 *
	 */
	public function test_register_routes() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/wp/v2/sites', $routes );
		$this->assertCount( 2, $routes['/wp/v2/sites'] );
		$this->assertArrayHasKey( '/wp/v2/sites/(?P<id>[\d]+)', $routes );
		$this->assertCount( 3, $routes['/wp/v2/sites/(?P<id>[\d]+)'] );
	}

	/**
	 *
	 */
	public function test_context_param() {
		wp_set_current_user( self::$superadmin_id );
		// Collection
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
		// Single
		$blog_id  = self::factory()->blog->create();
		$request  = new WP_REST_Request( 'OPTIONS', '/wp/v2/sites/' . $blog_id );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		$this->assertEquals( 'view', $data['endpoints'][0]['args']['context']['default'] );
		$this->assertEquals( array( 'view', 'embed', 'edit' ), $data['endpoints'][0]['args']['context']['enum'] );
	}


	/**
	 *
	 */
	public function test_get_items() {
		wp_set_current_user( self::$superadmin_id );
		$this->factory->blog->create_many( 6 );
		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$sites = $response->get_data();
		$this->assertCount( 7, $sites );
	}


	/**
	 *
	 */
	public function test_get_item() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/nulla/' ) );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$site = get_site( $blog_id );

		$this->assertEquals( $blog_id, $data['id'] );
		$this->assertEquals( $site->domain, $data['domain'] );
		$this->assertEquals( '/nulla/', $data['path'] );
		$this->assertEquals( 1, $data['network'] );
		$this->assertEquals( 1, $data['public'] );
	}

	/**
	 * An unknown ID is a 404, not an empty site.
	 */
	public function test_get_item_invalid_id() {
		wp_set_current_user( self::$superadmin_id );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites/' . REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_site_invalid_id', $response, 404 );
	}

	/**
	 *
	 */
	public function test_create_item() {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/tempor/' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( '/tempor/', $data['path'] );
		$this->assertEquals( WP_TESTS_DOMAIN, $data['domain'] );

		$site = get_site( $data['id'] );

		$this->assertNotNull( $site );
		$this->assertEquals( '/tempor/', $site->path );
	}

	/**
	 *
	 */
	public function test_update_item() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/eiusmod/' ) );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'path', '/incididunt/' );
		$request->set_param( 'mature', 1 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( '/incididunt/', $data['path'] );
		$this->assertEquals( 1, $data['mature'] );
		$this->assertEquals( '/incididunt/', get_site( $blog_id )->path );
	}

	/**
	 *
	 */
	public function test_delete_item() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/amet/' ) );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'force', true );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertTrue( $data['deleted'] );
		$this->assertEquals( $blog_id, $data['previous']['id'] );
		$this->assertNull( get_site( $blog_id ) );
	}

	/**
	 * Deleting a site drops its tables.
	 */
	public function test_delete_item_uninitializes_the_site() {
		global $wpdb;

		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/aliqua/' ) );
		$prefix  = $wpdb->get_blog_prefix( $blog_id );

		$this->assertNotEmpty( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prefix . 'posts' ) ) );

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'force', true );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prefix . 'posts' ) ) );
	}

	/**
	 * Sites have no trash, so deleting has to be explicit.
	 */
	public function test_delete_item_requires_force() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/consectetur/' ) );

		$request  = new WP_REST_Request( 'DELETE', '/wp/v2/sites/' . $blog_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_trash_not_supported', $response, 501 );
		$this->assertNotNull( get_site( $blog_id ) );
	}

	/**
	 * The main site of a network holds the network together.
	 */
	public function test_delete_main_site_is_not_allowed() {
		wp_set_current_user( self::$superadmin_id );

		$main_site_id = get_main_site_id();

		$request = new WP_REST_Request( 'DELETE', '/wp/v2/sites/' . $main_site_id );
		$request->set_param( 'force', true );

		$response = rest_get_server()->dispatch( $request );

		$this->assertErrorResponse( 'rest_cannot_delete_main_site', $response, 403 );
		$this->assertNotNull( get_site( $main_site_id ) );
	}

	/**
	 *
	 */
	public function test_prepare_item() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/labore/' ) );
		$site    = get_site( $blog_id );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'context', 'edit' );

		$data = $this->endpoint->prepare_item_for_response( $site, $request )->get_data();

		$this->assertEquals( (int) $site->blog_id, $data['id'] );
		$this->assertEquals( (int) $site->site_id, $data['network'] );
		$this->assertEquals( $site->domain, $data['domain'] );
		$this->assertEquals( $site->path, $data['path'] );
		$this->assertEquals( $site->registered, $data['registered'] );
		$this->assertEquals( $site->blogname, $data['blogname'] );
		$this->assertEquals( $site->home, $data['home'] );
		$this->assertEquals( $site->siteurl, $data['siteurl'] );
		$this->assertIsInt( $data['public'] );
		$this->assertIsInt( $data['post_count'] );
	}

	/**
	 *
	 */
	public function test_get_item_schema() {
		$request    = new WP_REST_Request( 'OPTIONS', '/wp/v2/sites' );
		$response   = rest_get_server()->dispatch( $request );
		$data       = $response->get_data();
		$properties = $data['schema']['properties'];

		$expected = array(
			'id',
			'network',
			'domain',
			'path',
			'registered',
			'last_updated',
			'public',
			'archived',
			'mature',
			'spam',
			'deleted',
			'lang_id',
			'blogname',
			'siteurl',
			'home',
			'post_count',
			'meta',
		);

		$this->assertEqualSets( $expected, array_keys( $properties ) );
		$this->assertTrue( $properties['id']['readonly'] );
		$this->assertEquals( 'integer', $properties['public']['type'] );
		$this->assertEquals( 'string', $properties['domain']['type'] );
	}

	/**
	 * Filtering by user narrows the total, not just the current page.
	 */
	public function test_get_items_me_filter_reports_the_filtered_total() {
		$blog_ids = self::factory()->blog->create_many( 3 );
		$user_id  = self::factory()->user->create();

		wp_set_current_user( $user_id );

		foreach ( $blog_ids as $blog_id ) {
			add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		}

		self::factory()->blog->create_many( 2 );

		$expected = count( get_blogs_of_user( $user_id ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', 'me' );

		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertLessThan( (int) get_sites( array( 'count' => true ) ), $expected );
		$this->assertCount( $expected, $response->get_data() );
		$this->assertEquals( $expected, (int) $headers['X-WP-Total'] );
	}

	/**
	 * A user without sites gets nothing, not everything.
	 */
	public function test_get_items_filter_user_without_sites() {
		wp_set_current_user( self::$superadmin_id );

		self::factory()->blog->create_many( 3 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', (string) REST_TESTS_IMPOSSIBLY_HIGH_NUMBER );

		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertCount( 0, $response->get_data() );
		$this->assertEquals( 0, (int) $headers['X-WP-Total'] );
	}

	/**
	 * Reading a site's options means switching to it, so avoid it when the
	 * fields that need it were not asked for.
	 */
	public function test_get_items_does_not_switch_blogs_for_table_columns() {
		wp_set_current_user( self::$superadmin_id );

		self::factory()->blog->create_many( 3 );

		$switches = 0;
		$counter  = static function () use ( &$switches ) {
			++$switches;
		};

		add_action( 'switch_blog', $counter );

		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( '_fields', 'id,domain,path' );

		$response = rest_get_server()->dispatch( $request );

		remove_action( 'switch_blog', $counter );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( 0, $switches );

		$site = $response->get_data()[0];

		foreach ( array( 'blogname', 'siteurl', 'home', 'post_count', 'meta' ) as $field ) {
			$this->assertArrayNotHasKey( $field, $site );
		}

		$this->assertArrayHasKey( 'domain', $site );
	}

	/**
	 * A HEAD request answers with the headers and an empty body.
	 */
	public function test_head_request_returns_no_body() {
		wp_set_current_user( self::$superadmin_id );

		self::factory()->blog->create_many( 2 );

		$request  = new WP_REST_Request( 'HEAD', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( array(), $response->get_data() );
		$this->assertEquals( (int) get_sites( array( 'count' => true ) ), (int) $headers['X-WP-Total'] );
	}

	/**
	 * The collection is ordered by ID, ascending.
	 */
	public function test_get_items_are_ordered_ascending() {
		wp_set_current_user( self::$superadmin_id );

		$blog_ids = self::factory()->blog->create_many( 3 );
		array_unshift( $blog_ids, 1 );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $blog_ids, wp_list_pluck( $response->get_data(), 'id' ) );
	}

	/**
	 * Ordering by an ID list falls back when there is no list.
	 */
	public function test_get_items_orderby_id_list_without_a_list() {
		wp_set_current_user( self::$superadmin_id );

		foreach ( array( 'site__in', 'network__in' ) as $orderby ) {
			$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
			$request->set_param( 'orderby', $orderby );

			$response = rest_get_server()->dispatch( $request );

			$this->assertEquals( 200, $response->get_status(), $orderby );
			$this->assertNotEmpty( $response->get_data(), $orderby );
		}
	}

	/**
	 * The data is stored as sent, without added slashes.
	 */
	public function test_update_item_does_not_slash_the_stored_data() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/sit/' ) );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'path', "/o'brien/" );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( "/o'brien/", get_site( $blog_id )->path );
	}

	/**
	 * The status fields are stored when a site is created.
	 */
	public function test_create_item_stores_the_status_fields() {
		wp_set_current_user( self::$superadmin_id );

		$request = new WP_REST_Request( 'POST', '/wp/v2/sites' );
		$request->set_param( 'domain', WP_TESTS_DOMAIN );
		$request->set_param( 'path', '/dolor/' );
		$request->set_param( 'public', 0 );
		$request->set_param( 'archived', 1 );
		$request->set_param( 'lang_id', 7 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 201, $response->get_status() );

		$data = $response->get_data();
		$site = get_site( $data['id'] );

		$this->assertEquals( 0, $site->public );
		$this->assertEquals( 1, $site->archived );
		$this->assertEquals( 7, $site->lang_id );
	}

	/**
	 * A partial update must not touch fields the request left out.
	 */
	public function test_update_item_keeps_fields_that_were_not_sent() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/lorem/' ) );

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'archived', 1 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertEquals( 1, $data['archived'] );
		$this->assertEquals( '/lorem/', $data['path'] );
		$this->assertEquals( '/lorem/', get_site( $blog_id )->path );
	}

	/**
	 * The domain is left alone when the request does not carry one.
	 */
	public function test_update_item_keeps_the_domain() {
		wp_set_current_user( self::$superadmin_id );

		$blog_id = self::factory()->blog->create( array( 'path' => '/ipsum/' ) );
		$domain  = get_site( $blog_id )->domain;

		$request = new WP_REST_Request( 'PUT', '/wp/v2/sites/' . $blog_id );
		$request->set_param( 'public', 0 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $domain, get_site( $blog_id )->domain );
		$this->assertEquals( 0, get_site( $blog_id )->public );
	}

	/**
	 * Site meta is exposed through the endpoint.
	 *
	 * Registering under the `blog` meta type is what `add_site_meta()` and
	 * `get_site_meta()` do, so the controller has to read the same type.
	 */
	public function test_get_item_exposes_site_meta() {
		if ( ! is_site_meta_supported() ) {
			$this->markTestSkipped( 'Site meta is not supported on this installation.' );
		}

		wp_set_current_user( self::$superadmin_id );

		register_meta(
			'blog',
			'rest_test_site_meta',
			array(
				'type'         => 'string',
				'single'       => true,
				'show_in_rest' => true,
			)
		);

		$blog_id = self::factory()->blog->create();
		update_site_meta( $blog_id, 'rest_test_site_meta', 'from blogmeta' );

		$request  = new WP_REST_Request( 'GET', '/wp/v2/sites/' . $blog_id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'meta', $data );
		$this->assertArrayHasKey( 'rest_test_site_meta', $data['meta'] );
		$this->assertEquals( 'from blogmeta', $data['meta']['rest_test_site_meta'] );

		unregister_meta_key( 'blog', 'rest_test_site_meta' );
	}

	/**
	 *
	 */
	public function test_invalid_user_input() {
		$this->assertEquals( array(), $this->endpoint->get_user_site_ids( false ) );
		$this->assertEquals( array(), $this->endpoint->get_user_site_ids( 0 ) );
		$this->assertEquals( array(), $this->endpoint->get_user_site_ids( '' ) );
		$this->assertEquals( array(), $this->endpoint->get_user_site_ids( REST_TESTS_IMPOSSIBLY_HIGH_NUMBER ) );
		$this->assertEquals( array(), $this->endpoint->get_user_site_ids( 999 ) );
	}

	/**
	 *
	 */
	public function test_valid_user_input() {

		$blog_ids = self::factory()->blog->create_many( 5 );
		$user_id  = self::factory()->user->create();
		array_unshift( $blog_ids, 1 );
		foreach ( $blog_ids as $blog_id ) {
			add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		}

		$this->assertEquals( $blog_ids, $this->endpoint->get_user_site_ids( $user_id ) );
	}

	/**
	 *
	 */
	public function test_get_items_filter_user() {
		wp_set_current_user( self::$superadmin_id );
		$blog_ids = self::factory()->blog->create_many( 5 );
		$user_id  = self::factory()->user->create();

		foreach ( $blog_ids as $blog_id ) {
			add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		}
		array_unshift( $blog_ids, 1 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', (string) $user_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$sites = $response->get_data();
		$this->assertCount( 6, $sites );
		$this->assertEquals( $blog_ids, wp_list_pluck( $sites, 'id' ) );
	}

	/**
	 *
	 */
	public function test_get_items_me_filter_user() {

		$blog_ids = self::factory()->blog->create_many( 5 );
		$user_id  = self::factory()->user->create();
		wp_set_current_user( $user_id );
		foreach ( $blog_ids as $blog_id ) {
			add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		}
		array_unshift( $blog_ids, 1 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', 'me' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$sites = $response->get_data();
		$this->assertCount( 6, $sites );
		$this->assertEquals( $blog_ids, wp_list_pluck( $sites, 'id' ) );
	}

	/**
	 *
	 */
	public function test_get_items_filter_user_no_access() {

		$blog_ids = self::factory()->blog->create_many( 5 );
		$user_id  = self::factory()->user->create();
		$user_id2 = self::factory()->user->create();
		wp_set_current_user( $user_id2 );

		foreach ( $blog_ids as $blog_id ) {
			add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		}
		array_unshift( $blog_ids, 1 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', (string) $user_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 *
	 */
	public function test_get_items_filter_with_includes_user() {
		wp_set_current_user( self::$superadmin_id );
		$blog_ids = self::factory()->blog->create_many( 5 );
		$user_id  = self::factory()->user->create();

		foreach ( $blog_ids as $blog_id ) {
			add_user_to_blog( $blog_id, $user_id, 'subscriber' );
		}
		$request = new WP_REST_Request( 'GET', '/wp/v2/sites' );
		$request->set_param( 'user', (string) $user_id );
		$request->set_param( 'include', $blog_ids[0] );
		$response = rest_get_server()->dispatch( $request );
		//$this->assertEquals( 200, $response->get_status() );
		$sites = $response->get_data();
		$this->assertCount( 1, $sites );
		$this->assertEquals( array( $blog_ids[0] ), wp_list_pluck( $sites, 'id' ) );
	}
}
