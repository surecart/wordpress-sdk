<?php

use SureCart\Licensing\Client;
use SureCart\Licensing\Versions;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'SureCartSdkLoader' ) ) {
	final class SureCartSdkLoader {
		/**
		 * SDK latest version.
		 *
		 * @var string
		 */
		public $current_version = '1.1.0';

		/**
		 * SDK Client.
		 *
		 * @var Client
		 */
		public $client;

		/**
		 * Client name
		 *
		 * @var string
		 */
		public $name;

		/**
		 * Client path
		 *
		 * @var string
		 */
		public $client_path;

		private function __construct() {
			require_once __DIR__ . '/vendor/autoload.php';

			add_action( 'plugins_loaded', array( $this, 'initialize_latest_version' ), 1, 0 );
		}

		/**
		 * Sdk loader instance
		 *
		 * @return self
		 */
		public static function instance() {
			static $instance = false;

			if ( ! $instance ) {
				$instance = new SureCartSdkLoader();
			}

			return $instance;
		}

		/**
		 * Initialize latest sdk version.
		 *
		 * @return void
		 */
		public function initialize_latest_version() {
			$versions = Versions::instance();
			$versions->initialize_latest_version();
		}

		/**
		 * Initialize and register the client.
		 *
		 * @param string $name        The name of the client.
		 * @param string $client_path The path of the client.
		 *
		 * @return void
		 */
		public function initialize_client( string $name, string $client_path ) {
			$this->name        = $name;
			$this->client_path = $client_path;

			$versions = Versions::instance();
			$versions->register( $this->current_version, array( $this, 'set_latest_client_sdk' ) );
		}

		/**
		 * Get the client.
		 *
		 * @return Client
		 */
		public function get_client(): Client {
			if ( empty( $this->client ) ) {
				$this->set_latest_client_sdk();
			}

			return $this->client;
		}

		/**
		 * Set the latest client.
		 *
		 * @return void
		 */
		public function set_latest_client_sdk() {
			$this->client = new Client( $this->name, $this->client_path );
		}
	}
}
