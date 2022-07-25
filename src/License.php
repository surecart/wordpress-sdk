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
     * Initialize the class
     *
     * @param SureCart\Licensing\Client
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
        $route    = trailingslashit( $this->endpoint ) . $license_key;
        return $this->client->send_request( 'GET', $route );
    }

    /**
     * Activate a specific license key.
     *
     * @return void
     */
    public function activate( $key = '' ) {
        // get license.
        $license = $this->retrieve( sanitize_text_field( $key ) );
        if ( is_wp_error($license) ) {
            return $license;
        }
        if ( empty( $license->id ) ) {
            return new \WP_Error( 'not_found', $this->client->__( 'This is not a valid license. Please double-check it and try again.' ) );
        }
        if ( 'revoked' === ( $license->status ?? 'revoked' ) ) {
            return new \WP_Error( 'revoked', $this->client->__( 'This license is revoked.' ) );
        } 

        // create the activation.
        $activation = $this->client->activation()->create( $license->id );
        if ( is_wp_error( $activation ) ) {
            return $activation;
            return;
        }
        if ( empty( $activation->id ) ) {
            return new \WP_Error( 'activation_failed', $this->client->__( 'Could not activate the license key.' )  );
        }

        // save activation data.
        $this->client->settings()->activation_id = $activation->id;
        $this->client->settings()->license_key = $license->key;
        $this->client->settings()->license_id = $license->id;

        return true;
    }

    public function deactivate( $activation_id = '' ) {
        if ( ! $activation_id ) {
            $activation_id = $this->client->settings()->activation_id;
        }

        $deactivated = $this->client->activation()->delete( sanitize_text_field( $activation_id ) );

        if ( is_wp_error( $deactivated ) ) {
            // it has been deleted remotely.
            if ( 'not_found' === $deactivated->get_error_code()) {
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
     * @param string $license_key The license key.
     * @param string $activation_id The activation id for the license key.
     * @param integer $expires_in The amount of time until it expires.
     * @return bool
     */
    public function get_current_release( $expires_in = 900 ) {
        $key = $this->client->settings()->license_key;
        if ( empty( $key ) ) {
            return;
        }

        $activation_id = $this->client->settings()->activation_id;
        if ( empty( $activation_id  ) ) {
            return;
        }

        $route  = add_query_arg( [
            'activation_id' => $activation_id,
            'expose_for' => $expires_in
        ], trailingslashit( $this->endpoint ) . $key . '/expose_current_release' );

        return $this->client->send_request( 'GET', $route );
    }

    /**
     * Check this is a valid license
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
     * @return void
     */
    public function validate_license( $license ) {
        if ( is_wp_error( $license ) ) {
            if ( $license->get_error_code( 'not_found' ) ) {
                return new \WP_Error($license->get_error_code(), $this->client->__( 'This license key is not valid. Please double check it and try again.' ) );
            }
            return $license;
        }

        // if we have a key and the status is not revoked
        if ( ! empty( $license->key ) && isset( $license->status ) && $license->status !== 'revoked' ) {
            return true;
        } 
        
        return false;
    }
}