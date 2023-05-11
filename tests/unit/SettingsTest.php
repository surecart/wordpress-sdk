<?php

class SettingsTest extends \WP_UnitTestCase {
	/**
	 * SureCart\Licensing\Client
	 *
	 * @var \SureCart\Licensing\Client
	 */
	public $client;

	public function setUp() {
		parent::setUp();
		$this->client = new \SureCart\Licensing\Client( 'SureCart', __FILE__ );
	}

	/**
	 * @dataProvider propertyProvider
	 */
	public function test_can_get_and_set_options( $property, $value ) {
		$this->assertEmpty( $this->client->settings()->get_options() );
		$this->client->settings()->$property = $value;
		$this->assertSame( $value, $this->client->settings()->get_options()[ 'sc_' . $property ] );
	}

	public function propertyProvider() {
		return array(
			'license id'    => array( 'license_id', 'test_id' ),
			'license key'   => array( 'license_key', 'test_key' ),
			'activation id' => array( 'activation_id', 'test_activation_id' ),
		);
	}

	public function test_get_activation_refreshed_or_not() {
		// Without refreshed parameter true and activation_id not set, would return false.
		$this->assertFalse( $this->client->settings()->get_activation() );

		// If passed refreshed = true, then it will try to hard refresh
		$this->client->settings()->activation_id = 'test-123';
		$this->assertNotFalse( $this->client->settings()->get_activation( true ) );
	}

	public function test_get_invalid_activation_wp_error() {
		$this->client->settings()->activation_id = 'test-123';
		$result = $this->client->settings()->get_activation( true );
		$this->assertInstanceOf( 'WP_Error', $result );
	}
}
