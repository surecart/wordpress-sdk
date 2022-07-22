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

		add_action( 'admin_init', [ $this, 'init_settings_page' ] );
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
            'parent_slug' => '',
        ] );
        add_action( 'admin_menu', [ $this, 'admin_menu' ], 99 );
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
        ?>

		<div class="wrap">
			<h2><?php echo esc_html( $this->menu_args['page_title'] ); ?></h2>

            <?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php
					settings_fields( $this->name . '_option_group' );
					do_settings_sections( $this->name . '-admin' );
					submit_button( $this->client->__('Activate License') );
				?>
			</form>
		</div>
		<?php
    }

    /**
     * Initialize the settings page
     *
     * @return void
     */
	public function init_settings_page() {
		register_setting(
			$this->name . '_option_group', // option_group
			$this->name . '_license_options', // option_name
			[ $this, 'sanitize_settings' ] // sanitize_callback
		);

		add_settings_section(
			$this->name . '_setting_section', // id
			'', // title
            '__return_false',
			$this->name . '-admin' // page
		);

		add_settings_field(
			'sc_license_key', // id
			$this->client->__('Enter Your License Key'), // title
			[ $this, 'license_key_callback' ], // callback
			$this->name . '-admin', // page
			$this->name . '_setting_section' // section
		);

        if ( isset( $_GET['debug'] ) ) {
            add_settings_field(
                'sc_license_id', // id
                $this->client->__('License ID'), // title
                [ $this, 'license_id_callback' ], // callback
                $this->name . '-admin', // page
                $this->name . '_setting_section' // section
            );
            add_settings_field(
                'sc_activation_id', // id
                $this->client->__('Activation Id'), // title
                [ $this, 'activation_id_callback' ], // callback
                $this->name . '-admin', // page
                $this->name . '_setting_section' // section
            );
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

	public function license_key_callback() {
        $key = $this->get_option('license_key');
		printf(
			'<input class="regular-text" type="password" autocomplete="off" name="' . $this->option_key . '[sc_license_key]" id="sc_license_key" value="%s">',
			isset( $key ) ? esc_attr( $key ) : ''
		);
	}

    public function license_id_callback() {
        $key = $this->get_option('license_id');
		printf(
			'<input class="regular-text" type="text" autocomplete="off" name="' . $this->option_key . '[sc_license_id]" id="sc_license_id" value="%s">',
			isset( $key ) ? esc_attr( $key ) : ''
		);
	}

    public function activation_id_callback() {
        $key = $this->get_option('activation_id');
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