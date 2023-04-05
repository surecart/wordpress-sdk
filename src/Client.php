<?php
namespace SureCart\Licensing;

/**
 * SureCart Client
 *
 * This class is necessary to set project data
 */
class Client {
	/**
	 * The client version
	 *
	 * @var string
	 */
	public $version = '1.0.0';

	/**
	 * Name of the plugin
	 *
	 * @var string
	 */
	public $name;

	/**
	 * The plugin/theme file path
	 *
	 * @example .../wp-content/plugins/test-slug/test-slug.php
	 *
	 * @var string
	 */
	public $file;

	/**
	 * Main plugin file
	 *
	 * @example test-slug/test-slug.php
	 *
	 * @var string
	 */
	public $basename;

	/**
	 * Slug of the plugin
	 *
	 * @example test-slug
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * The project version
	 *
	 * @var string
	 */
	public $project_version;

	/**
	 * The project type
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Textdomain
	 *
	 * @var string
	 */
	public $textdomain;

	/**
	 * The Object of Updater Class
	 *
	 * @var object
	 */
	private $updater;

	/**
	 * The Object of License Class
	 *
	 * @var object
	 */
	private $license;

	/**
	 * The Object of Activation Class
	 *
	 * @var object
	 */
	private $activation;

	/**
	 * The Object of Settings Class
	 *
	 * @var object
	 */
	private $settings;

	/**
	 * Initialize the class
	 *
	 * @param string $name Readable name of the plugin.
	 * @param string $file Main plugin file path.
	 */
	public function __construct( $name, $file ) {
		$this->name = $name;
		$this->file = $file;
		$this->set_basename_and_slug();

		$this->license();
		$this->activation();
		$this->updater();
	}

	/**
	 * Initialize plugin/theme updater
	 *
	 * @return SureCart\Updater
	 */
	public function updater() {
		if ( ! class_exists( __NAMESPACE__ . '\Updater' ) ) {
			require_once __DIR__ . '/Updater.php';
		}

		// if already instantiated, return the cached one.
		$this->updater = $this->updater ? $this->updater : new Updater( $this );

		return $this->updater;
	}

	/**
	 * Initialize license model
	 *
	 * @return SureCart\Licensing
	 */
	public function license() {
		if ( ! class_exists( __NAMESPACE__ . '\License' ) ) {
			require_once __DIR__ . '/License.php';
		}

		// if already instantiated, return the cached one.
		$this->license = $this->license ? $this->license : new License( $this );

		return $this->license;
	}

	/**
	 * Initialize activation model
	 *
	 * @return SureCart\Licensing
	 */
	public function activation() {
		if ( ! class_exists( __NAMESPACE__ . '\Activation' ) ) {
			require_once __DIR__ . '/Activation.php';
		}

		// if already instantiated, return the cached one.
		$this->activation = $this->activation ? $this->activation : new Activation( $this );

		return $this->activation;
	}

	/**
	 * Initialize settings page
	 *
	 * @return SureCart\Licensing
	 */
	public function settings() {
		if ( ! class_exists( __NAMESPACE__ . '\Settings' ) ) {
			require_once __DIR__ . '/Settings.php';
		}

		// if already instantiated, return the cached one.
		$this->settings = $this->settings ? $this->settings : new Settings( $this );

		return $this->settings;
	}

	/**
	 * API Endpoint
	 *
	 * @return string
	 */
	public function endpoint() {
		// allow a constant to be set.
		if ( defined( 'SURECART_LICENSING_ENDPOINT' ) ) {
			return trailingslashit( SURECART_LICENSING_ENDPOINT );
		}

		// filterable endpoint.
		return trailingslashit( apply_filters( 'surecart_licensing_endpoint', 'https://api.surecart.com' ) );
	}

	/**
	 * Set project basename, slug and version
	 *
	 * @return void
	 */
	protected function set_basename_and_slug() {
		// it's a plugin.
		if ( strpos( $this->file, WP_CONTENT_DIR . '/themes/' ) === false ) {
			$this->basename = plugin_basename( $this->file );

			list( $this->slug ) = explode( '/', $this->basename );

			require_once ABSPATH . 'wp-admin/includes/plugin.php';

			$plugin_data = get_plugin_data( $this->file );

			if ( empty( $plugin_data['Version'] ) ) {
				add_action(
					'admin_notices',
					function() {
						printf( '<div class="notice notice-error"><p>' . esc_html( $this->name ) . ' Licensing Configuration Error: The <code>__FILE__</code> must point to the main file of your plugin.</p></div>' );
					}
				);
			}

			$this->project_version = $plugin_data['Version'];
			$this->type            = 'plugin';

			// it's a theme.
		} else {
			$this->basename = str_replace( WP_CONTENT_DIR . '/themes/', '', $this->file );

			list( $this->slug ) = explode( '/', $this->basename );

			$theme = wp_get_theme( $this->slug );

			$this->project_version = $theme->version;

			if ( empty( $theme->version ) ) {
				add_action(
					'admin_notices',
					function() {
						printf( '<div class="notice notice-error"><p>' . esc_html( $this->name ) . ' Licensing Configuration Error: The <code>__FILE__</code> must point to the main file of your theme.</p></div>' );
					}
				);
			}

			$this->type = 'theme';
		}

		$this->textdomain = $this->slug;
	}

	/**
	 * Send request to remote endpoint
	 *
	 * @param  array  $method The method for the request.
	 * @param  string $route The route.
	 * @param array  $body The body to send.
	 * @param bool   $blocking Is this a blocking request.
	 *
	 * @return array|WP_Error   Array of results including HTTP headers or WP_Error if the request failed.
	 */
	public function send_request( $method = 'POST', $route = '', $body = null, $blocking = true ) {
		$response = wp_remote_request(
			$this->endpoint() . $route,
			array(
				'headers'  => array(
					'X-SURECART-WP-LICENSING-SDK-VERSION' => $this->version,
					'Accept'                              => 'application/json',
				),
				'method'   => $method,
				'timeout'  => 30,
				'blocking' => $blocking,
				'body'     => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! in_array( $response_code, array( 200, 201 ), true ) ) {
			if ( 404 === $response_code ) {
				return new \WP_Error( 'not_found', $this->__( 'Not found' ) );
			}

			if ( ! empty( $response_body->code ) && ! empty( $response_body->message ) ) {
				return new \WP_Error( $response_body->code, esc_html( $response_body->message ) );
			}
			return new \WP_Error( 'error', $this->__( 'Unknown error occurred, Please try again.' ) );
		}

		return $response_body;
	}

	/**
	 * Check if the current server is localhost
	 *
	 * @return boolean
	 */
	public function is_local_server() {
		$is_local = in_array( $_SERVER['REMOTE_ADDR'], array( '127.0.0.1', '::1' ), true );
		return apply_filters( 'surecart_licensing_is_local', $is_local );
	}

	/**
	 * Translate function __()
	 *
	 * @param string $text The text string.
	 */
	public function __( $text ) {
		return call_user_func( '__', $text, $this->textdomain );
	}

	/**
	 * Set project textdomain.
	 *
	 * @param string $textdomain The textdomain for translations.
	 */
	public function set_textdomain( $textdomain ) {
		$this->textdomain = $textdomain;
	}
}
