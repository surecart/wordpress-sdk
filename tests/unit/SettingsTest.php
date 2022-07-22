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
        $this->assertEmpty($this->client->settings()->get_options());
        $this->client->settings()->$property = $value;
        $this->assertSame($value, $this->client->settings()->get_options()['sc_' . $property] );
    }

    public function propertyProvider(): array
    {
        return [
            'license id' => ['license_id', 'test_id'],
            'license key' => ['license_key', 'test_key'],
            'activation id' => ['activation_id', 'test_activation_id'],
        ];
    }
}