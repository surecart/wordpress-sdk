<?php

/**
 * Activation, rollback and deactivation against a stubbed licensing API.
 */
class LicenseActivationTest extends \WP_UnitTestCase {
	/**
	 * The client under test.
	 *
	 * @var \SureCart\Licensing\Client
	 */
	public $client;

	/**
	 * Requests sent, as "METHOD path".
	 *
	 * @var string[]
	 */
	public $requests = array();

	/**
	 * Responses by "METHOD path" prefix, as array( code, body ) or a WP_Error. First match wins.
	 *
	 * @var array
	 */
	public $responses = array();

	public function set_up() {
		parent::set_up();
		$this->client    = new \SureCart\Licensing\Client( 'SureCart', __FILE__ );
		$this->requests  = array();
		$this->responses = array();
		add_filter( 'pre_http_request', array( $this, 'stub' ), 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'stub' ), 10 );
		parent::tear_down();
	}

	/**
	 * Answer licensing API requests from $this->responses.
	 *
	 * @param false|array|\WP_Error $pre  Unused, required by the filter.
	 * @param array                 $args Request arguments.
	 * @param string                $url  Request URL.
	 *
	 * @return array|\WP_Error
	 */
	public function stub( $pre, $args, $url ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$path             = preg_replace( '#^https?://[^/]+/#', '', strtok( $url, '?' ) );
		$request          = $args['method'] . ' ' . $path;
		$this->requests[] = $request;

		foreach ( $this->responses as $prefix => $response ) {
			if ( 0 !== strpos( $request, $prefix ) ) {
				continue;
			}
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			return array(
				'headers'  => array(),
				'cookies'  => array(),
				'response' => array(
					'code'    => $response[0],
					'message' => '',
				),
				'body'     => wp_json_encode( $response[1] ),
			);
		}

		return new \WP_Error( 'unexpected_request', $request );
	}

	/**
	 * A valid license, a created activation, and a release for $slug.
	 *
	 * @param string $slug The release slug.
	 */
	private function stub_activation( $slug ) {
		$this->responses = array(
			// before 'GET .../KEY', which is its prefix.
			'GET v1/public/licenses/KEY/expose_current_release' => array(
				200,
				array(
					'url'          => 'https://example.com/package.zip',
					'release_json' => array(
						'slug'    => $slug,
						'version' => '9.9.9',
					),
				),
			),
			'GET v1/public/licenses/KEY'  => array(
				200,
				array(
					'id'     => 'lic_1',
					'key'    => 'KEY',
					'status' => 'active',
				),
			),
			'POST v1/public/activations/' => array( 201, array( 'id' => 'act_new' ) ),
		);
	}

	public function test_empty_key_fails_without_a_request() {
		$result = $this->client->license()->activate( '' );

		$this->assertWPError( $result );
		$this->assertSame( array(), $this->requests );
	}

	public function test_release_error_deletes_the_new_activation_by_id() {
		$this->stub_activation( 'another-product' );
		$this->responses['DELETE v1/public/activations/act_new'] = array( 200, array( 'deleted' => true ) );

		$result = $this->client->license()->activate( 'KEY' );

		$this->assertWPError( $result );
		$this->assertContains( 'DELETE v1/public/activations/act_new', $this->requests );
		$this->assertSame( array(), $this->client->settings()->get_options() );
	}

	public function test_undo_404_counts_as_removed() {
		$this->stub_activation( 'another-product' );
		$this->responses['DELETE v1/public/activations/act_new'] = array( 404, array() );

		$result = $this->client->license()->activate( 'KEY' );

		$this->assertWPError( $result );
		$this->assertSame( array(), $this->client->settings()->get_options() );
	}

	public function test_failed_undo_keeps_only_the_activation_id() {
		$this->stub_activation( 'another-product' );
		$this->responses['DELETE v1/public/activations/act_new'] = array( 500, array() );

		$result = $this->client->license()->activate( 'KEY' );

		$this->assertWPError( $result );
		$this->assertStringContainsString( 'Deactivate License', $result->get_error_message() );
		$this->assertSame( array( 'sc_activation_id' => 'act_new' ), $this->client->settings()->get_options() );
	}

	public function test_failed_request_restores_the_saved_key() {
		$previous = array(
			'sc_license_key'   => 'KEY',
			'sc_license_id'    => 'lic_1',
			'sc_activation_id' => 'act_removed',
		);
		$this->client->settings()->set_options( $previous );
		$this->responses['GET v1/public/licenses/KEY'] = new \WP_Error( 'http_request_failed', 'Timed out' );

		$result = $this->client->license()->activate( 'KEY' );

		$this->assertWPError( $result );
		$this->assertSame( $previous, $this->client->settings()->get_options() );
	}

	public function test_activation_and_deactivation_clear_update_caches() {
		$this->stub_activation( $this->client->slug );
		$this->responses['DELETE v1/public/activations/act_new'] = array( 200, array( 'deleted' => true ) );

		$this->prime_update_caches();
		$this->assertTrue( $this->client->license()->activate( 'KEY' ) );
		$this->assert_update_caches_cleared();

		$this->prime_update_caches();
		$this->assertTrue( $this->client->license()->deactivate() );
		$this->assert_update_caches_cleared();
	}

	/**
	 * The SDK's version info cache key.
	 *
	 * @return string
	 */
	private function cache_key() {
		return 'surecart_' . md5( $this->client->slug ) . '_version_info';
	}

	private function prime_update_caches() {
		set_transient( $this->cache_key(), (object) array( 'new_version' => '1.0.0' ) );
		set_site_transient( 'update_plugins', (object) array( 'response' => array() ) );
	}

	private function assert_update_caches_cleared() {
		$this->assertFalse( get_transient( $this->cache_key() ) );
		$this->assertFalse( get_site_transient( 'update_plugins' ) );
	}
}
