<?php

namespace SureCart\Licensing;

/**
 * Activation model
 *
 */
class Activation {
    /**
     * The endpoint for the activations.
     *
     * @var string
     */
    protected $endpoint = 'v1/public/activations';

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
     * Initialize the class
     *
     * @param SureCart\Licensing\Client
     * @param SureCart\Licensing\Activation
     */
    public function __construct( Client $client ) {
        $this->client = $client;
        $this->option_key = 'surecart_' . md5( $this->client->slug ) . '_license_activation_id';
    }

    /**
     * Create an activation for the license.
     * 
     * @return object|\WP_Error
     */
    public function create( $license_id ) {
        if ( empty( $license_id ) ) {
            return new \WP_Error( 'missing_key', $this->client->__('Please enter a license key') );
        }

        $activation = $this->client->send_request( 
            'POST',
            trailingslashit( $this->endpoint ), 
            [
                'activation' => [
                    'fingerprint' => esc_url_raw( get_site_url() ),
                    'name'        => get_bloginfo(),
                    'license'     => $license_id
                ]
            ] 
        );

        // error.
        if ( is_wp_error( $activation ) ) {
            return $activation;
        }

        // no id.
        if ( empty( $activation->id ) ) {
            return new \WP_Error( 'could_not_activate', $this->client->__( 'Could not activate the license.', 'surecart' ) );
        }

        // return the activation.
        return $activation;
    }

    /**
     * Get cached activation
     *
     * @return boolean
     */
    public function get_cached( $id ) {
        $activation = get_transient( $this->client->name . '_activation_cache' );
        if ( false === $activation) {
            $activation = $this->get( $id );
            set_transient( $this->client->name . '_activation_cache', $activation, 6 * HOUR_IN_SECONDS );
        }
        return $activation;
    }

    /**
     * Clear cached activation.
     *
     * @return boolean
     */
    public function clear_cached( ) {
        return delete_transient( $this->client->name . '_activation_cache' );
    } 

    /**
     * Retrieves details of a specific activation.
     * 
     * @param string $id The id of the activation.
     * 
     * @return object|\WP_Error
     */
    public function get( $id = '' ) {
        return $this->client->send_request( 
            'GET',
            trailingslashit( $this->endpoint ) . $id, 
        );
    }

    /**
     * Update an activation for the license.
     * 
     * @param string $id The id of the activation.
     * 
     * @return object|\WP_Error
     */
    public function update( $id = '' ) {
        $license_key = $this->client->license()->get_id();
        if ( empty( $license_key ) ) {
            return new \WP_Error( 'missing_key', $this->client->__('Please enter a license key') );
        }

        return $this->client->send_request( 
            'PATCH',
            trailingslashit( $this->endpoint ) . $id, 
            [
                'fingerprint' => esc_url_raw( get_site_url() ),
                'name'        => get_bloginfo(),
                'license'     => $license_key
            ] 
        );
    }

    /**
     * Deletes a specific activation.
     * 
     * @param string $id The id of the activation.
     * 
     * @return object|\WP_Error
     */
    public function delete( $id = '' ) {
        return $this->client->send_request( 
            'DELETE',
            trailingslashit( $this->endpoint ) . $id, 
        );
    }
}