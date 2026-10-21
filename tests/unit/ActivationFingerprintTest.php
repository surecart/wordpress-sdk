<?php

/**
 * The activation fingerprint identifies a site, so URL variants of one site must map to one value.
 */
class ActivationFingerprintTest extends \WP_UnitTestCase {
	/**
	 * The client under test.
	 *
	 * @var \SureCart\Licensing\Client
	 */
	public $client;

	/**
	 * Bodies of the requests sent.
	 *
	 * @var array[]
	 */
	public $bodies = array();

	public function set_up() {
		parent::set_up();
		$this->client = new \SureCart\Licensing\Client( 'SureCart', __FILE__ );
		$this->bodies = array();
		add_filter( 'pre_http_request', array( $this, 'stub' ), 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'stub' ), 10 );
		remove_all_filters( 'surecart_licensing_fingerprint' );
		remove_all_filters( 'site_url' );
		parent::tear_down();
	}

	/**
	 * Record the request body and answer with a created activation.
	 *
	 * @param false|array|\WP_Error $pre  Unused, required by the filter.
	 * @param array                 $args Request arguments.
	 * @param string                $url  Request URL.
	 *
	 * @return array
	 */
	public function stub( $pre, $args, $url ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$this->bodies[] = is_array( $args['body'] ) ? $args['body'] : json_decode( $args['body'], true );
		return array(
			'headers'  => array(),
			'cookies'  => array(),
			'response' => array(
				'code'    => 201,
				'message' => '',
			),
			'body'     => wp_json_encode( array( 'id' => 'act_new' ) ),
		);
	}

	/**
	 * Fingerprint for a given site URL.
	 *
	 * @param string $site_url The site URL.
	 *
	 * @return string
	 */
	private function fingerprint_for( $site_url ) {
		update_option( 'siteurl', $site_url );
		return $this->client->activation()->get_fingerprint();
	}

	public function test_get_fingerprint_is_unchanged_for_a_canonical_https_url() {
		// The common case must keep its existing fingerprint, so its activation is still matched.
		$this->assertSame( 'https://example.com', $this->fingerprint_for( 'https://example.com' ) );
	}

	public function test_get_fingerprint_maps_www_scheme_case_and_trailing_slash_variants_to_one_value() {
		foreach ( array( 'https://www.example.com', 'http://example.com', 'http://www.example.com', 'https://WWW.Example.COM', 'https://example.com/' ) as $variant ) {
			$this->assertSame( 'https://example.com', $this->fingerprint_for( $variant ), $variant );
		}
	}

	public function test_get_fingerprint_keeps_the_path_and_port() {
		// Subdirectory installs and ports can be different sites.
		$this->assertSame( 'https://example.com/shop', $this->fingerprint_for( 'https://www.example.com/shop/' ) );
		$this->assertSame( 'https://example.com/Shop', $this->fingerprint_for( 'https://example.com/Shop' ) );
		$this->assertSame( 'https://localhost:8888', $this->fingerprint_for( 'http://localhost:8888' ) );
	}

	public function test_get_fingerprint_only_strips_a_leading_www_label() {
		$this->assertSame( 'https://wwwexample.com', $this->fingerprint_for( 'https://wwwexample.com' ) );
		$this->assertSame( 'https://shop.www.example.com', $this->fingerprint_for( 'https://shop.www.example.com' ) );
	}

	public function test_get_fingerprint_falls_back_to_the_raw_url_without_a_host() {
		add_filter(
			'site_url',
			function () {
				return 'not a url';
			}
		);
		$this->assertSame( esc_url_raw( 'not a url' ), $this->client->activation()->get_fingerprint() );
	}

	public function test_get_fingerprint_is_filterable() {
		update_option( 'siteurl', 'https://www.example.com' );
		add_filter(
			'surecart_licensing_fingerprint',
			function ( $fingerprint, $site_url ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return $site_url;
			},
			10,
			2
		);
		$this->assertSame( get_site_url(), $this->client->activation()->get_fingerprint() );
	}

	public function test_get_fingerprint_filter_receives_the_client() {
		update_option( 'siteurl', 'https://example.com' );
		add_filter(
			'surecart_licensing_fingerprint',
			function ( $fingerprint, $site_url, $client ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				return $client->slug;
			},
			10,
			3
		);
		$this->assertSame( $this->client->slug, $this->client->activation()->get_fingerprint() );
	}

	public function test_create_sends_the_normalized_fingerprint() {
		update_option( 'siteurl', 'https://www.example.com/' );

		$this->client->activation()->create( 'lic_1' );

		$this->assertSame( 'https://example.com', $this->bodies[0]['activation']['fingerprint'] );
	}
}
