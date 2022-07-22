<?php

namespace SureCart\Licensing;

class Settings {
    /**
     * SureCart\Licensing\Client
     *
     * @var object
     */
    protected $client;

    /**
     * Holds the option key
     *
     * @var string
     */
    private $option_key;

    /**
     * Holds the option name
     *
     * @var string
     */
    private $name;

    /**
     * Holds the menu arguments
     *
     * @var array
     */
    private $menu_args;

    /**
     * Create the pages.
     */
	public function __construct( Client $client ) {
        $this->client = $client;
        $this->name = strtolower( $this->client->name );
        $this->option_key = $this->name . '_license_options';
	}

    /**
     * Add the settings page
     *
     * @return void
     */
	public function add_page( $args ) {
        $this->menu_args = wp_parse_args( $args, [
            'type'        => 'menu', // Can be: menu, options, submenu
            'page_title'  => 'Manage License',
            'menu_title'  => 'Manage License',
            'capability'  => 'manage_options',
            'menu_slug'   => $this->client->slug . '-manage-license',
            'icon_url'    => '',
            'position'    => null,
            'activated_redirect' => null,
            'parent_slug' => '',
        ] );
        add_action( 'admin_menu', [ $this, 'admin_menu' ], 99 );
	}

     /**
     * Form action URL
     */
    private function form_action_url() {
        return apply_filters( 'surecart_client_license_form_action', '' );
    }

    /**
     * Set the license option key.
     *
     * If someone wants to override the default generated key.
     *
     * @param string $key
     *
     * @since 1.0.0
     *
     * @return License
     */
    public function set_option_key( $key ) {
        $this->option_key = $key;
        return $this;
    }

    /**
     * Add the admin menu
     *
     * @return void
     */
    public function admin_menu() {
        switch ( $this->menu_args['type'] ) {
            case 'menu':
                $this->create_menu_page();
                break;
            case 'submenu':
                $this->create_submenu_page();
                break;
            case 'options':
                $this->create_options_page();
                break;
        }
    }

    /**
     * Add license menu page
     */
    private function create_menu_page() {
        call_user_func(
            'add_' . 'menu' . '_page',
            $this->menu_args['page_title'],
            $this->menu_args['menu_title'],
            $this->menu_args['capability'],
            $this->menu_args['menu_slug'],
            [ $this, 'settings_output' ],
            $this->menu_args['icon_url'],
            $this->menu_args['position']
        );
    }

    /**
     * Add submenu page
     */
    private function create_submenu_page() {
        call_user_func(
            'add_' . 'submenu' . '_page',
            $this->menu_args['parent_slug'],
            $this->menu_args['page_title'],
            $this->menu_args['menu_title'],
            $this->menu_args['capability'],
            $this->menu_args['menu_slug'],
            [ $this, 'settings_output' ],
            $this->menu_args['position']
        );
    }

    /**
     * Add submenu page
     */
    private function create_options_page() {
        call_user_func(
            'add_' . 'options' . '_page',
            $this->menu_args['page_title'],
            $this->menu_args['menu_title'],
            $this->menu_args['capability'],
            $this->menu_args['menu_slug'],
            [ $this, 'settings_output' ],
            $this->menu_args['position']
        );
    }

    /**
     * Get all options
     *
     * @return array
     */
    public function get_options() {
        return (array) get_option( $this->option_key, [] );
    }

    /**
     * Clear out the options.
     *
     * @return bool
     */
    public function clear_options() {
        return update_option( $this->option_key, [] );
    }

    /**
     * Get a specific option
     *
     * @param string $name Option name.
     *
     * @return mixed
     */
    public function get_option( $name  ) {
        $options = $this->get_options();
        return $options[$name] ?? null;
    }

    /**
     * Set the option.
     *
     * @param string $name The option name. 
     * @param mixed $value The option value.
     *
     * @return bool
     */
    public function set_option( $name, $value ) {
        $options = (array) $this->get_options();
        $options[$name] = $value;
        return update_option( $this->option_key, $options );
    }

    /**
     * The settings page menu output.
     *
     * @return void
     */
    public function settings_output() {
        if ( isset( $_POST['submit'] ) ) {
            $this->license_form_submit( $_POST );
        }

        $this->print_css();
        $activation_id = $this->activation_id;
        $action = $activation_id ? 'deactivate' : 'activate' 
        ?>

		<div class="wrap">
            <?php settings_errors(); ?>

            <div class="<?php echo esc_attr($this->name) . '-form-container'; ?>">
                <form method="post" action="<?php echo esc_attr( $this->form_action_url() ); ?>">
                    <input type="hidden" name="_action" value="<?php echo esc_attr( $action ); ?>">
                    <input type="hidden" name="_nonce" value="<?php echo wp_create_nonce( $this->client->name ); ?>">
                    <input type="hidden" name="activation_id" value="<?php echo esc_attr( $this->activation_id ); ?>">

                    <h2><?php echo esc_html( $this->menu_args['page_title'] ); ?></h2>
                    <label for="license_key"><?php echo esc_html( sprintf( $this->client->__('Enter your license key to activate %s.', 'surecart'), $this->client->name ) ); ?></label>
                    <input class="widefat" type="password" autocomplete="off" name="license_key" id="license_key" value="<?php echo esc_attr( $this->license_key ); ?>" autofocus>

                    <?php if ( !empty( $_GET['debug'] ) ) : ?>
                        <label for="license_id"><?php echo esc_html( sprintf( $this->client->__('License ID', 'surecart'), $this->client->name ) ); ?></label>
                        <input class="widefat" type="text" autocomplete="off" name="license_id" id="license_id" value="<?php echo esc_attr( $this->license_id ); ?>" autofocus>

                        <label for="activation_id"><?php echo esc_html( sprintf( $this->client->__('Activation ID', 'surecart'), $this->client->name ) ); ?></label>
                        <input class="widefat" type="text" autocomplete="off" name="activation_id" id="activation_id" value="<?php echo esc_attr( $this->activation_id ); ?>" autofocus>
                    <?php endif; ?>

                    <?php submit_button( 'activate' === $action  ? $this->client->__('Activate License') : $this->client->__('Deactivate License') ); ?>
                </form>
            </div>
		</div>
		<?php
    }

    public function print_css() { ?>
        <style>
            <?php echo '.' . esc_attr( $this->name ) . '-form-container'; ?> form {
                padding:30px;
                background: #fff;
                display: grid;
                gap: 1em;
                max-width: 600px;
                margin-top: 20px
            }
            h2 {
                padding: 0;
                margin: 0;
            }
            label {
                display: block;
                font-size: 1.1em;
                margin-bottom: 5px;
            }
            label[hidden] {
                display: none;
            }
            p.submit {
                margin: 0;
                padding: 0;
            }
        </style>
    <?php }

    /**
     * License form submit
     */
    public function license_form_submit( $form ) {
        if ( ! isset( $form['_nonce'], $form['_action'] ) ) {
            $this->add_error('missing_info', $this->client->__( 'Please add all information' ) );
            return;
        }

        if ( ! wp_verify_nonce( $form['_nonce'], $this->client->name ) ) {
            $this->add_error('unauthorized', $this->client->__( "You don't have permission to manage licenses." ) );
            return;
        }

        switch ( $form['_action'] ) {
            case 'activate':
                $activated = $this->client->license()->activate( sanitize_text_field( $form['license_key'] ) );
                if ( is_wp_error( $activated ) ) {
                    $this->add_error( $activated->get_error_code(), $activated->get_error_message() );
                    return;
                }

                if ( ! empty( $this->menu_args['activated_redirect'] ) ) {
                    wp_safe_redirect( $this->menu_args['activated_redirect'] );
                    die();
                }

                $this->add_success( 'activated', $this->client->__( 'This site was successfully activated.', 'surecart' ) );
                return;

            case 'deactivate':
                $deactivated = $this->client->license()->deactivate( sanitize_text_field( $form['activation_id'] ) );
                if ( is_wp_error( $deactivated ) ) {
                    $this->add_error($deactivated->get_error_code(), $deactivated->get_error_message() );
                }

                if ( ! empty( $this->menu_args['deactivated_redirect'] ) ) {
                    wp_safe_redirect( $this->menu_args['deactivated_redirect'] );
                    die();
                }

                $this->add_success( 'deactivated', $this->client->__( 'This site was successfully deactivated.', 'surecart' ) );
                return;
        }
    }

    /**
     * Sanitize the api key.
     *
     * @param array $input Array of input values.
     *
     * @return array sanitized values.
     */
	public function sanitize_settings( $input ) {
		$sanitary_values = array();
		if ( isset( $input['sc_license_key'] ) ) {
			$sanitary_values['sc_license_key'] = sanitize_text_field( $input['sc_license_key'] );
            
            $valid = $this->client->license()->activate( $sanitary_values['sc_license_key'] );

            if ( is_wp_error( $valid ) ) {
                $this->add_error( $valid->get_error_code(), $valid->get_error_message() );
                return;
            }
            if ( ! $valid ) {
                $this->add_error( 'not_found', $this->client->__( 'This is not a valid license. Please double check and try again.' ) );
                return;
            }            
		}

		return $sanitary_values;
	}

    /**
     * Add an error.
     *
     * @param string $code Error code.
     * @param string $message Error message.
     *
     * @return void
     */
    public function add_error( $code, $message ) {
        add_settings_error(
            $this->name . '_license_options', // matches what we registered in `register_setting
            $code, // the error code
            $message,
            'error',
        );
    }

    /**
     * Add an success message
     *
     * @param string $code Success code.
     * @param string $message Success message.
     *
     * @return void
     */
    public function add_success( $code, $message ) {
        add_settings_error(
            $this->name . '_license_options', // matches what we registered in `register_setting
            $code, // the succes code
            $message,
            'success',
        );
    }

	public function license_key_callback() {
        $key = $this->get_option('sc_license_key');
		printf(
			'<input class="regular-text" type="password" autocomplete="off" name="' . $this->option_key . '[sc_license_key]" id="sc_license_key" value="%s">',
			isset( $key ) ? esc_attr( $key ) : ''
		);
	}

    public function license_id_callback() {
        $key = $this->get_option('sc_license_id');
		printf(
			'<input class="regular-text" type="text" autocomplete="off" name="' . $this->option_key . '[sc_license_id]" id="sc_license_id" value="%s">',
			isset( $key ) ? esc_attr( $key ) : ''
		);
	}

    public function activation_id_callback() {
        $key = $this->get_option('sc_activation_id');
		printf(
			'<input class="regular-text" type="text" autocomplete="off" name="' . $this->option_key . '[sc_activation_id]" id="sc_activation_id" value="%s">',
			isset( $key ) ? esc_attr( $key ) : ''
		);
	}

    /**
	 * Set an option.
	 *
	 * @param string $name Name of option.
     * 
     * @return mixed
	 */
	public function __get( $name ) {
        return $this->get_option( 'sc_' . $name );
	}

    /**
     * Set an option
     *
     * @param string $name Name of option.
     * @param mixed $value Value.
     * 
     * @return bool
     */
    public function __set( $name, $value ) {
        return $this->set_option( 'sc_' . $name, $value );
    }
}