<?php

class UpdaterTest extends \WP_UnitTestCase {
	/**
	 * The licensing client.
	 *
	 * @var \SureCart\Licensing\Client
	 */
	public $client;

	/**
	 * The updater under test.
	 *
	 * @var \SureCart\Licensing\Updater
	 */
	public $updater;

	/**
	 * The plugin basename the client resolved to.
	 *
	 * @var string
	 */
	public $basename;

	/**
	 * Set up the client and updater.
	 */
	public function setUp() {
		parent::setUp();
		$this->client   = new \SureCart\Licensing\Client( 'SureCart', __FILE__ );
		$this->updater  = $this->client->updater();
		$this->basename = $this->client->basename;
	}

	/**
	 * Build a plugin update entry whose image assets are stdClass.
	 *
	 * Mirrors the shape a pre-1.2.0 SDK persisted into the transient.
	 *
	 * @return object The plugin update entry.
	 */
	protected function makeStaleEntry() {
		$entry          = new \stdClass();
		$entry->plugin  = $this->basename;
		$entry->icons   = (object) array(
			'1x' => 'https://example.com/icon-128x128.png',
			'2x' => 'https://example.com/icon-256x256.png',
		);
		$entry->banners = (object) array(
			'low'  => 'https://example.com/banner-772x250.png',
			'high' => 'https://example.com/banner-1544x500.png',
		);
		return $entry;
	}

	/**
	 * Wrap an entry in an `update_plugins` transient keyed by our basename.
	 *
	 * @param object $entry The plugin update entry.
	 * @return object       The transient.
	 */
	protected function makeTransient( $entry ) {
		$transient                              = new \stdClass();
		$transient->response                    = array();
		$transient->response[ $this->basename ] = $entry;
		return $transient;
	}

	/**
	 * The read filter recasts a stale stdClass entry's assets to arrays.
	 *
	 * This is the assertion that fatals pre-fix.
	 */
	public function test_read_filter_normalizes_stale_stdclass_entry() {
		$transient = $this->makeTransient( $this->makeStaleEntry() );

		$result = $this->updater->normalize_plugin_update_transient( $transient );
		$entry  = $result->response[ $this->basename ];

		$this->assertTrue( is_array( $entry->icons ) );
		$this->assertTrue( is_array( $entry->banners ) );

		// Keys and values survive the cast. Mirrors wp-admin/update-core.php:520, where the array deref fataled on a stdClass pre-fix.
		$this->assertSame( 'https://example.com/icon-128x128.png', $entry->icons['1x'] );
		$this->assertSame( 'https://example.com/icon-256x256.png', $entry->icons['2x'] );
		$this->assertSame( 'https://example.com/banner-772x250.png', $entry->banners['low'] );
		$this->assertSame( 'https://example.com/banner-1544x500.png', $entry->banners['high'] );
	}

	/**
	 * Normalizing an entry whose assets are already arrays is a no-op.
	 */
	public function test_read_filter_is_idempotent_on_array_assets() {
		$entry          = new \stdClass();
		$entry->plugin  = $this->basename;
		$entry->icons   = array(
			'1x' => 'https://example.com/icon-128x128.png',
			'2x' => 'https://example.com/icon-256x256.png',
		);
		$entry->banners = array(
			'low'  => 'https://example.com/banner-772x250.png',
			'high' => 'https://example.com/banner-1544x500.png',
		);

		$transient = $this->makeTransient( $entry );

		$result = $this->updater->normalize_plugin_update_transient( $transient );
		$out    = $result->response[ $this->basename ];

		$this->assertTrue( is_array( $out->icons ) );
		$this->assertTrue( is_array( $out->banners ) );
		$this->assertSame( $entry->icons, $out->icons );
		$this->assertSame( $entry->banners, $out->banners );
	}

	/**
	 * The read filter returns a non-object transient unchanged.
	 */
	public function test_read_filter_no_op_on_non_object() {
		$this->assertSame( false, $this->updater->normalize_plugin_update_transient( false ) );
	}

	/**
	 * The read filter returns a transient with no `response` unchanged.
	 */
	public function test_read_filter_no_op_when_response_missing() {
		$transient = new \stdClass();

		$result = $this->updater->normalize_plugin_update_transient( $transient );

		$this->assertSame( $transient, $result );
		$this->assertFalse( isset( $result->response ) );
	}

	/**
	 * The read filter leaves a transient that lacks our basename untouched.
	 */
	public function test_read_filter_no_op_when_basename_absent() {
		$other        = new \stdClass();
		$other->icons = (object) array( '1x' => 'https://example.com/other.png' );

		$transient           = new \stdClass();
		$transient->response = array();

		$transient->response['other-plugin/other.php'] = $other;

		$result = $this->updater->normalize_plugin_update_transient( $transient );
		$out    = $result->response['other-plugin/other.php'];

		// Foreign entries are not touched.
		$this->assertTrue( is_object( $out->icons ) );
	}

	/**
	 * `check_plugin_update` normalizes the existing entry on the re-set/early-return path.
	 *
	 * Covers the re-set path, not just the read filter.
	 */
	public function test_check_plugin_update_normalizes_existing_response_entry() {
		$transient = $this->makeTransient( $this->makeStaleEntry() );

		$result = $this->updater->check_plugin_update( $transient );
		$entry  = $result->response[ $this->basename ];

		$this->assertTrue( is_array( $entry->icons ) );
		$this->assertTrue( is_array( $entry->banners ) );
		// Mirrors wp-admin/update-core.php:520, where the array deref fataled on a stdClass pre-fix.
		$this->assertSame( 'https://example.com/icon-128x128.png', $entry->icons['1x'] );
		$this->assertSame( 'https://example.com/banner-1544x500.png', $entry->banners['high'] );
	}
}
