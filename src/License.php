<?php

namespace SureCart\Licensing;

/**
 * License model
 *
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
     * `option_name` of `wp_options` table
     *
     * @var string
     */
    protected $option_key;

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
        $this->option_key = 'surecart_' . md5( $this->client->slug ) . '_manage_license';
    }

    /**
     * Set the license option key.
     *
     * If someone wants to override the default generated key.
     *
     * @param string $key
     *
     * @since 1.3.0
     *
     * @return License
     */
    public function set_option_key( $key ) {
        $this->option_key = $key;
        return $this;
    }

    /**
     * Get the license key
     *
     * @since 1.3.0
     *
     * @return string|null
     */
    public function get_key() {
        return get_option( $this->option_key, null );
    }

    /**
     * Get the license key
     *
     * @since 1.3.0
     *
     * @return string|null
     */
    public function get_id() {
        return get_option( $this->option_key . '_id', null );
    }

    /**
     * Set the license id.
     *
     * @since 1.3.0
     *
     * @return string|null
     */
    public function set_id( $id ) {
        return update_option( $this->option_key . '_id', $id );
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
        // get the license by key
        $license = $this->retrieve( $key );

        // check to make sure it's valid.
        $is_valid = $this->validate_license( $license );
        if( is_wp_error( $is_valid ) ) {
            return $is_valid;
        }

        // if it's not, or license id is empty, it's not valid.
        if( ! $is_valid || empty( $license['id'] ) ) {
            return new \WP_Error( $license->get_error_code(), $this->client->__( 'This license key is not valid. Please double check it and try again.' ) );
        }

        // it's valid, store the license id.
        $this->set_id( $license['id'] );

        // activate the license for the domain.
        return $this->client->activation()->create();
    }

    /**
     * Ge the current release
     * 
     * @param string $license_key The license key.
     * @param string $activation_id The activation id for the license key.
     * @param integer $expires_in The amount of time until it expires.
     * @return bool
     */
    public function get_current_release( $license_key, $activation_id, $expires_in = 900 ) {
        $route    = trailingslashit( $this->endpoint ) . $license_key . '/expose_current_release';
        return $this->client->send_request( 'GET', $route, [
            'activation_id' => $activation_id,
            'expose_for' => $expires_in
        ] );
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
            $license_key = $this->get_key();
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
        if ( ! empty( $license['key'] ) && isset( $license['status'] ) && $license['status'] !== 'revoked' ) {
            return true;
        } 
        
        return false;
    }
}