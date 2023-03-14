<?php
namespace SureCart\Licensing;

/**
 * SureCart Loader
 *
 * This class is necessary to set project data
 */
class Loader {

	/**
	 * Product data.
	 *
	 * @access private
	 * @var array Entities array.
	 */
	private $entities = array();

	/**
	 * Client version.
	 *
	 * @access private
	 * @var string client version.
	 */
	private $client_version = '';

	/**
	 * Client path.
	 *
	 * @access private
	 * @var string client path.
	 */
	private $client_path = '';

	/**
	 * Instance
	 *
	 * @access private
	 * @var object Class object.
	 */
	private static $instance = null;

	/**
	 * Get instance of class.
	 *
	 * @return object
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
	/**
	 * Initialize the class
	 *
	 * @param string $name Readable name of the plugin.
	 * @param string $file Main plugin file path.
	 */
	public function __construct() {

		add_action( 'plugins_loaded', array( $this, 'load_license_client' ), 1 );
	}

	/**
	 * Set entity for analytics.
	 *
	 * @param string $data Entity attributes data.
	 * @return void
	 */
	public function set_entity( $data ) {
		array_push( $this->entities, $data );
	}

	/**
	 * Load license client.
	 *
	 * @return void
	 */
	public function load_license_client() {

		if ( ! empty( $this->entities ) ) {
			foreach ( $this->entities as $data ) {
				if ( isset( $data['path'] ) ) {
					if ( file_exists( $data['path'] . 'src//version.json' ) ) {
						$file_contents     = file_get_contents( $data['path'] . 'src//version.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
						$client_version = json_decode( $file_contents, 1 );
						$client_version = $client_version['version'];

						if ( version_compare( $client_version, $this->client_version, '>' ) ) {
							$this->client_version = $client_version;
							$this->client_path    = $data['path'];
						}
					}
				}
			}
		}
	}

	/**
	 * Set entity for analytics.
	 *
	 * @param string $data Entity attributes data.
	 * @return void
	 */
	public function get_client( $name, $file ) {

		if ( ! class_exists( 'SureCart\Licensing\Client' ) ) {

			if ( file_exists( $this->client_path ) ) {
				require_once $this->client_path . 'src/Client.php';
			} else {
				require_once 'Client.php';
			}
		}

		// initialize client with your plugin name.
		return new \SureCart\Licensing\Client( $name, $file );
	}
}
