<?php

namespace SureCart\Licensing;

class Versions {
	/**
	 * @var Versions
	 */
	private static $instance = null;

	/**
	 * Registered versions.
	 *
	 * @var array
	 */
	private $versions = array();

	/**
	 * Register the version.
	 *
	 * @param string $version_string          Version string.
	 * @param mixed  $initialization_callback Client callback.
	 * @return bool
	 */
	public function register( string $version_string, $initialization_callback ): bool {
		if ( isset( $this->versions[ $version_string ] ) ) {
			return false;
		}

		$this->versions[ $version_string ] = $initialization_callback;

		return true;
	}

	/**
	 * Get the versions array.
	 *
	 * @return array
	 */
	public function get_versions(): array {
		return $this->versions;
	}

	/**
	 * Get the latest version number.
	 *
	 * @return false|string
	 */
	public function latest_version() {
		$keys = array_keys( $this->versions );
		if ( empty( $keys ) ) {
			return false;
		}
		uasort( $keys, 'version_compare' );
		return end( $keys );
	}

	/**
	 * Latest version callback.
	 *
	 * @return mixed|string
	 */
	public function latest_version_callback() {
		$latest = $this->latest_version();
		if ( empty( $latest ) || ! isset( $this->versions[ $latest ] ) ) {
			return '__return_null';
		}

		return $this->versions[ $latest ];
	}

	/**
	 * Get instance.
	 *
	 * @return Versions|null
	 */
	public static function instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the latest version.
	 *
	 * @return void
	 */
	public static function initialize_latest_version() {
		$self = self::instance();
		call_user_func( $self->latest_version_callback() );
	}
}
