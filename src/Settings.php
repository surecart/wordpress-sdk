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
     * Holds our settings options
     *
     * @var array
     */
	private $settings_options;

    /**
     * Create the pages.
     */
	public function __construct( Client $client ) {
        $this->client = $client;
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
     * The settings page menu output.
     *
     * @return void
     */
    public function settings_output() {
        $this->settings_options = get_option( $this->client->name . '_option_name' ); ?>

		<div class="wrap">
			<h2><?php echo esc_html( $this->menu_args['page_title'] ); ?></h2>

            <?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php
					settings_fields( $this->client->name . '_option_group' );
					do_settings_sections( $this->client->name . '-admin' );
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
			$this->client->name . '_option_group', // option_group
			$this->client->name . '_option_name', // option_name
			[ $this, 'sanitize_settings' ] // sanitize_callback
		);

		add_settings_section(
			$this->client->name . '_setting_section', // id
			'', // title
            '__return_false',
			$this->client->name . '-admin' // page
		);

		add_settings_field(
			'sc_license_key', // id
			$this->client->__('Enter Your License Key'), // title
			[ $this, 'license_key_callback' ], // callback
			$this->client->name . '-admin', // page
			$this->client->name . '_setting_section' // section
		);
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
                add_settings_error(
                    $this->client->name . '_option_name', // whatever you registered in `register_setting
                    $valid->get_error_code(), // doesn't really mater
                    $valid->get_error_message(),
                    'error',
                );
                return;
            }

            if ( ! $valid ) {
                add_settings_error(
                    $this->client->name . '_option_name', // whatever you registered in `register_setting
                    'not_found', // doesn't really mater
                    __('This is not a valid license. Please double check and try again.', 'surecart'),
                    'error',
                );
                return;
            }            
		}

		return $sanitary_values;
	}

	public function license_key_callback() {
		printf(
			'<input class="regular-text" type="text" name="' . $this->client->name . '_option_name[sc_license_key]" id="sc_license_key" value="%s">',
			isset( $this->settings_options['sc_license_key'] ) ? esc_attr( $this->settings_options['sc_license_key'] ) : ''
		);
	}

}