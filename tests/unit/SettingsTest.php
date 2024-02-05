<?php

class SettingsTest extends \WP_UnitTestCase {
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
			'license id'        => array( 'license_id', 'test_id' ),
			'license key'       => array( 'license_key', 'test_key' ),
			'activation id'     => array( 'activation_id', 'test_activation_id' ),
			'activation object' => array( 'activation', $this->createActivationObject() ),
		);
	}

	private function createActivationObject() {
		// Create a product object.
		$product         = new stdClass();
		$product->id     = 'test_product_id';
		$product->object = 'product';
		$product->name   = 'Test Product Name';

		// Create a license object.
		$license          = new stdClass();
		$license->id      = 'test_id';
		$license->object  = 'license';
		$license->product = $product;

		// Create an activation object.
		$activation          = new stdClass();
		$activation->id      = 'test_activation_id';
		$activation->object  = 'activation';
		$activation->license = $license;

		return $activation;
	}
}
