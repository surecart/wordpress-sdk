<?php
namespace SureCart\Licensing;

/**
 * This class will handle the updates.
 */
class Updater {
	/**
	 * SureCart\Licensing\Client
	 *
	 * @var object
	 */
	protected $client;

	/**
	 * Initialize the class
	 *
	 * @param SureCart\Licensing\Client $client The client.
	 */
	public function __construct( Client $client ) {
		$this->client    = $client;
		$this->cache_key = 'surecart_' . md5( $this->client->slug ) . '_version_info';

		// Run hooks.
		if ( 'plugin' === $this->client->type ) {
			$this->run_plugin_hooks();
		} elseif ( 'theme' === $this->client->type ) {
			$this->run_theme_hooks();
		}
	}

	/**
	 * Set up WordPress filter to hooks to get update.
	 *
	 * @return void
	 */
	public function run_plugin_hooks() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_plugin_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugins_api_filter' ), 10, 3 );
	}

	/**
	 * Set up WordPress filter to hooks to get update.
	 *
	 * @return void
	 */
	public function run_theme_hooks() {
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check_theme_update' ) );
	}

	/**
	 * Check for Update for this specific project.
	 *
	 * @param Object $transient_data Transient data for update.
	 */
	public function check_plugin_update( $transient_data ) {
		global $pagenow;

		if ( ! is_object( $transient_data ) ) {
			$transient_data = new \stdClass();
		}

		if ( 'plugins.php' === $pagenow && is_multisite() ) {
			return $transient_data;
		}

		if ( ! empty( $transient_data->response ) && ! empty( $transient_data->response[ $this->client->basename ] ) ) {
			return $transient_data;
		}

		$version_info = $this->get_version_info();

		if ( false !== $version_info && is_object( $version_info ) && isset( $version_info->new_version ) ) {

			unset( $version_info->sections );

			// If new version available then set to `response`.
			if ( version_compare( $this->client->project_version, $version_info->new_version, '<' ) ) {
				$transient_data->response[ $this->client->basename ] = $version_info;
			} else {
				// If new version is not available then set to `no_update`.
				$transient_data->no_update[ $this->client->basename ] = $version_info;
			}

			$transient_data->last_checked                       = time();
			$transient_data->checked[ $this->client->basename ] = $this->client->project_version;
		}

		return $transient_data;
	}

	/**
	 * Get version info from database
	 *
	 * @return Object or Boolean
	 */
	private function get_cached_version_info() {
		global $pagenow;
		// If updater page then force fetch.
		if ( 'update-core.php' === $pagenow ) {
			return false;
		}

		return get_transient( $this->cache_key );
	}

	/**
	 * Set version info to database.
	 *
	 * @param Object $value Version info to store in the transient.
	 */
	private function set_cached_version_info( $value ) {
		if ( ! $value ) {
			return;
		}
		// cache for 3 hours.
		set_transient( $this->cache_key, $value, 3 * HOUR_IN_SECONDS );
	}

	/**
	 * Get plugin info from SureCart\Licensing
	 */
	private function get_project_latest_version() {
		$current_release = $this->client->license()->get_current_release( 3 * HOUR_IN_SECONDS );

		if ( is_wp_error( $current_release ) || empty( $current_release ) ) {
			return false;
		}

		$release = $current_release->release_json;

		// must have a slug.
		if ( ! isset( $release->slug ) ) {
			return false;
		}

		// set the new version.
		$release->new_version = $release->version;

		if ( empty( $release->last_updated ) ) {
			$release->last_updated = date_i18n( get_option( 'date_format' ), $current_release->updated_at ?? time() );
		}

		if ( isset( $current_release->url ) ) {
			$release->package = $current_release->url;
		}

		if ( isset( $release->banners ) ) {
			$release->banners = (array) $release->banners;
		}

		if ( isset( $release->sections ) ) {
			$release->sections = (array) $release->sections;
		}

		return $release;
	}

	/**
	 * Updates information on the "View version x.x details" page with custom data.
	 *
	 * @param mixed  $data Plugin data.
	 * @param string $action The action type.
	 * @param object $args Arguments.
	 *
	 * @return object $data
	 */
	public function plugins_api_filter( $data, $action = '', $args = null ) {
		// must be requesting plugin info.
		if ( 'plugin_information' !== $action ) {
			return $data;
		}

		// slug must match.
		if ( ! isset( $args->slug ) || ( $args->slug !== $this->client->slug ) ) {
			return $data;
		}

		// get the version info.
		return $this->get_version_info();
	}

	/**
	 * Check theme update.
	 *
	 * @param Object $transient_data Transient data for the update.
	 */
	public function check_theme_update( $transient_data ) {
		global $pagenow;

		if ( ! is_object( $transient_data ) ) {
			$transient_data = new \stdClass();
		}

		if ( 'themes.php' === $pagenow && is_multisite() ) {
			return $transient_data;
		}

		if ( ! empty( $transient_data->response ) && ! empty( $transient_data->response[ $this->client->slug ] ) ) {
			return $transient_data;
		}

		$version_info = $this->get_version_info();

		if ( false !== $version_info && is_object( $version_info ) && isset( $version_info->new_version ) ) {

			// If new version available then set to `response`.
			if ( version_compare( $this->client->project_version, $version_info->new_version, '<' ) ) {
				$transient_data->response[ $this->client->slug ] = (array) $version_info;
			} else {
				// If new version is not available then set to `no_update`.
				$transient_data->no_update[ $this->client->slug ] = (array) $version_info;
			}

			$transient_data->last_checked                   = time();
			$transient_data->checked[ $this->client->slug ] = $this->client->project_version;
		}

		return $transient_data;
	}

	/**
	 * Get version information
	 */
	private function get_version_info() {
		$version_info = $this->get_cached_version_info();

		if ( false === $version_info ) {
			$version_info = $this->get_project_latest_version();
			$this->set_cached_version_info( $version_info );
		}

		return $version_info;
	}
}
