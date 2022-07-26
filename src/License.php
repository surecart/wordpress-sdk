<?php

namespace SureCart\Licensing;

/**
 * License model
 */
class License {
	/**
	 * The endpoint for the licenses.
	 *
	 * @var string
	 */
	protected $endpoint = 'v1/public/licenses';

	/**
	 * SureCart\Licensing\Client
	 *
	 * @var object
	 */
	protected $client;

	/**
	 * Set value for valid licnese
	 *
	 * @var bool
	 */
	private $is_valid_license = null;

	/**
	 * Initialize the class.
	 *
	 * @param SureCart\Licensing\Client $client The client.
	 */
	public function __construct( Client $client ) {
		$this->client = $client;
	}

	/**
	 * Retrieve license information by key.
	 *
	 * @param string $license_key The license key.
	 *
	 * @return Object|\WP_Error
	 */
	public function retrieve( $license_key ) {
		$route = trailingslashit( $this->endpoint ) . $license_key;
		return $this->client->send_request( 'GET', $route );
	}

	/**
	 * Activate a specific license key.
	 *
	 * @param string $key A license key.
	 *
	 * @return \WP_Error|Object
	 * @throws \Exception If something goes wrong.
	 */
	public function activate( $key = '' ) {
		try {
			// validate the license and store it.
			$license = $this->validate( $key, true );
			// create the activation.
			$activation = $this->client->activation()->create( $license->id );
			if ( is_wp_error( $activation ) ) {
				throw new \Exception( $activation->get_error_message() );
			}
			$this->client->settings()->activation_id = $activation->id;
			// validate the release.
			$this->validate_release();
		} catch ( \Exception $e ) {
			// undo activation.
			$activation = $this->client->activation()->get();
			if ( $activation ) {
				$this->client->activation()->delete();
			}
			// on error, clear options.
			$this->client->settings()->clear_options();
			// return \WP_Error.
			return new \WP_Error( 'error', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Deactivate a license.
	 *
	 * @param string $activation_id The activation id.
	 *
	 * @return \WP_Error|true
	 */
	public function deactivate( $activation_id = '' ) {
		if ( ! $activation_id ) {
			$activation_id = $this->client->settings()->activation_id;
		}

		$deactivated = $this->client->activation()->delete( sanitize_text_field( $activation_id ) );

		if ( is_wp_error( $deactivated ) ) {
			// it has been deleted remotely.
			if ( 'not_found' === $deactivated->get_error_code() ) {
				$this->client->settings()->clear_options();
				return true;
			}
			return $deactivated;
		}

		$this->client->settings()->clear_options();
		return true;
	}

	/**
	 * Ge the current release
	 *
	 * @param integer $expires_in The amount of time until it expires.
	 *
	 * @return Object|WP_Error
	 */
	public function get_current_release( $expires_in = 900 ) {
		$key = $this->client->settings()->license_key;
		if ( empty( $key ) ) {
			return new \WP_Error( 'license_key_missing', $this->client->__( 'Please enter a license key.' ) );
		}

		$activation_id = $this->client->settings()->activation_id;
		if ( empty( $activation_id ) ) {
			return new \WP_Error( 'activation_id_missing', $this->client->__( 'This license is not yet activated.' ) );
		}

		$route = add_query_arg(
			array(
				'activation_id' => $activation_id,
				'expose_for'    => $expires_in,
			),
			trailingslashit( $this->endpoint ) . $key . '/expose_current_release'
		);

		return $this->client->send_request( 'GET', $route );
	}

	/**
	 * Validate a license key.
	 *
	 * @param string  $key The license key.
	 * @param boolean $store Should we store the key and id.
	 * @return Object
	 * @throws \Exception If the license is not valid.
	 */
	public function validate( $key, $store = false ) {
		// get license.
		$license = $this->retrieve( sanitize_text_field( $key ) );
		if ( is_wp_error( $license ) ) {
			if ( 'not_found' === $license->get_error_code() ) {
				throw new \Exception( $this->client->__( 'This is not a valid license. Please double-check it and try again.' ) );
			}
			throw new \Exception( $license->get_error_message() );
		}
		if ( empty( $license->id ) ) {
			throw new \Exception( $this->client->__( 'This is not a valid license. Please double-check it and try again.' ) );
		}
		if ( 'revoked' === ( $license->status ?? 'revoked' ) ) {
			throw new \Exception( $this->client->__( 'This license has been revoked. Please re-purchase to obtain a new license.' ) );
		}

		if ( $store ) {
			$this->client->settings()->license_key = $license->key;
			$this->client->settings()->license_id  = $license->id;
		}

		return $license;
	}

	/**
	 * Validate the current release.
	 *
	 * @return Object
	 * @throws \Exception If the release is not valid.
	 */
	public function validate_release() {
		$current_release = $this->get_current_release();
		if ( is_wp_error( $current_release ) ) {
			throw new \Exception( $current_release->get_error_message() );
		}
			// if there is no slug or it does not match.
		if ( empty( $current_release->release_json->slug ) || $this->client->slug !== $current_release->release_json->slug ) {
			throw new \Exception( $this->client->__( 'This license is not valid for this product.' ) );
		}
		return $current_release;
	}

	/**
	 * Check this is a valid license.
	 *
	 * @param string $license_key The license key.
	 *
	 * @return boolean|\WP_Error
	 */
	public function is_valid( $license_key = '' ) {
		// already set.
		if ( null !== $this->is_valid_license ) {
			return $this->is_valid_license;
		}

		// check to see if a license is saved.
		if ( empty( $license_key ) ) {
			$license_key = $this->client->settings()->license_key;
			if ( empty( $license_key ) ) {
				$this->is_valid_license = false;
				return $this->is_valid_license;
			}
		}

		// get the license from the server.
		$license = $this->retrieve( $license_key );

		// validate the license response.
		$this->is_valid_license = $this->validate_license( $license );

		// return validity.
		return $this->is_valid_license;
	}

	/**
	 * Is this license active?
	 *
	 * @return boolean
	 */
	public function is_active() {
		if ( empty( $this->client->settings()->activation_id ) ) {
			return false;
		}

		$activation = $this->client->activation()->get( $this->client->settings()->activation_id );

		return ! empty( $activation->id );
	}

	/**
	 * Validate the license response
	 *
	 * @param Object|\WP_Error $license The license response.
	 *
	 * @return \WP_Error|boolean
	 */
	public function validate_license( $license ) {
		if ( is_wp_error( $license ) ) {
			if ( $license->get_error_code( 'not_found' ) ) {
				return new \WP_Error( $license->get_error_code(), $this->client->__( 'This license key is not valid. Please double check it and try again.' ) );
			}
			return $license;
		}

		// if we have a key and the status is not revoked.
		if ( ! empty( $license->key ) && isset( $license->status ) && 'revoked' !== $license->status ) {
			return true;
		}

		return false;
	}
}
