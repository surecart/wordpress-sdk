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
     * Initialize the class
     *
     * @param SureCart\Licensing\Client
     * @param SureCart\Licensing\Activation
     */
    public function __construct( Client $client ) {
        $this->client = $client;
    }

    /**
     * Create an activation for the license.
     * 
     * @return object|\WP_Error
     */
    public function create() {
        $license_id = $this->client->license()->get_id();
        if ( empty( $license_id ) ) {
            return new \WP_Error( 'missing_key', $this->client->__('Please enter a license key') );
        }

        return $this->client->send_request( 
            'POST',
            trailingslashit( $this->endpoint ), 
            [
                'fingerprint' => esc_url_raw( get_site_url() ),
                'name'        => get_bloginfo(),
                'license'     => $license_id
            ] 
        );
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
            'GET',
            trailingslashit( $this->endpoint ) . $id, 
        );
    }
}