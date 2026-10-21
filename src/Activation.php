<?php

namespace SureCart\Licensing;

/**
 * Activation model
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
	 * Initialize the class.
	 *
	 * @param SureCart\Licensing\Client $client The client.
	 */
	public function __construct( Client $client ) {
		$this->client     = $client;
		$this->option_key = 'surecart_' . md5( $this->client->slug ) . '_license_activation_id';
	}

	/**
	 * Get the fingerprint that identifies this site's activation.
	 *
	 * URL variants of one site map to one value: the scheme becomes https, the
	 * host is lowercased, a leading "www." label is dropped and a trailing slash
	 * is removed. The port and path are kept, since a subdirectory install or a
	 * different port can be a separate site. A canonical https, non-www URL keeps
	 * the exact fingerprint it had before normalization.
	 *
	 * @return string
	 */
	public function get_fingerprint() {
		$site_url    = get_site_url();
		$parts       = wp_parse_url( $site_url );
		$fingerprint = esc_url_raw( $site_url );

		if ( is_array( $parts ) && ! empty( $parts['host'] ) ) {
			$host = strtolower( $parts['host'] );
			if ( 0 === strpos( $host, 'www.' ) ) {
				$host = substr( $host, 4 );
			}

			$fingerprint = esc_url_raw(
				'https://' . $host
				. ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' )
				. ( isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '' )
			);
		}

		/**
		 * Filter the activation fingerprint.
		 *
		 * @param string $fingerprint The normalized fingerprint.
		 * @param string $site_url    The raw site URL.
		 */
		return apply_filters( 'surecart_licensing_fingerprint', $fingerprint, $site_url );
	}

	/**
	 * Create an activation for the license.
	 *
	 * @param string $license_id The license id.
	 *
	 * @return object|\WP_Error
	 */
	public function create( $license_id ) {
		if ( empty( $license_id ) ) {
			return new \WP_Error( 'missing_key', $this->client->__( 'Please enter a license key' ) );
		}

		// send the activation request.
		$activation = $this->client->send_request(
			'POST',
			trailingslashit( $this->endpoint ),
			array(
				'activation' => array(
					'fingerprint' => $this->get_fingerprint(),
					'name'        => get_bloginfo(),
					'license'     => $license_id,
				),
			)
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
	 * Retrieves details of a specific activation.
	 *
	 * @param string $id The id of the activation.
	 *
	 * @return object|\WP_Error
	 */
	public function get( $id = '' ) {
		return $this->client->send_request(
			'GET',
			trailingslashit( $this->endpoint ) . $id
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
			return new \WP_Error( 'missing_key', $this->client->__( 'Please enter a license key' ) );
		}

		return $this->client->send_request(
			'PATCH',
			trailingslashit( $this->endpoint ) . $id,
			array(
				'fingerprint' => $this->get_fingerprint(),
				'name'        => get_bloginfo(),
				'license'     => $license_key,
			)
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
			trailingslashit( $this->endpoint ) . $id
		);
	}
}
