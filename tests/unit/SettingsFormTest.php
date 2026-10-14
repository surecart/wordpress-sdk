<?php

/**
 * The license form: what it prints, what it posts, and where it redirects.
 */
class SettingsFormTest extends \WP_UnitTestCase {
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

	public function set_up() {
		parent::set_up();
		require_once ABSPATH . 'wp-admin/includes/template.php';

		$this->client   = new \SureCart\Licensing\Client( 'SureCart', __FILE__ );
		$this->requests = array();
		$this->client->settings()->add_page( array() );
		add_filter( 'pre_http_request', array( $this, 'stub' ), 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'stub' ), 10 );
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Record each request and answer it with a 200.
	 *
	 * @param false|array|\WP_Error $pre  Unused, required by the filter.
	 * @param array                 $args Request arguments.
	 * @param string                $url  Request URL.
	 *
	 * @return array
	 */
	public function stub( $pre, $args, $url ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$this->requests[] = $args['method'] . ' ' . preg_replace( '#^https?://[^/]+/#', '', strtok( $url, '?' ) );

		return array(
			'headers'  => array(),
			'cookies'  => array(),
			'response' => array(
				'code'    => 200,
				'message' => '',
			),
			'body'     => wp_json_encode( array( 'id' => 'act_mine' ) ),
		);
	}

	public function test_form_never_prints_the_saved_key() {
		$this->client->settings()->license_key = 'SECRET_KEY';

		ob_start();
		$this->client->settings()->settings_output();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'SECRET_KEY', $html );
		$this->assertStringContainsString( 'Leave blank to use the saved key', $html );
	}

	public function test_deactivation_ignores_a_posted_activation_id() {
		$this->client->settings()->license_key   = 'KEY';
		$this->client->settings()->activation_id = 'act_mine';

		$_POST = array(
			'submit'        => 'Deactivate License',
			'_action'       => 'deactivate',
			'_nonce'        => wp_create_nonce( $this->client->name ),
			'activation_id' => 'act_someone_else',
		);

		$this->client->settings()->license_form_submit();

		$this->assertSame( array( 'DELETE v1/public/activations/act_mine' ), $this->requests );
	}

	public function test_redirect_keeps_query_args() {
		ob_start();
		$this->client->settings()->redirect( 'https://example.com/wp-admin/admin.php?page=my-plugin&tab=license' );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'page=my-plugin&tab=license', $html );
		$this->assertStringNotContainsString( '&#038;', $html );
	}
}
