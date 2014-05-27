<?php
/**
 * WP settings page for the Wp-Accessify plugin.
 *
 * @author Nick Freear, 21 May 2014.
 * Was: options.php
 * @link http://codex.wordpress.org/Creating_Options_Pages
 */

class Accessify_Options_Page //MySettingsPage
{

    const MENU_SLUG = 'wp-accessify-admin';   #'my-setting-admin'
    const OPTION_NAME = 'wp_accessify_opt';   #'my_option_name'
    const OPTION_GROUP= 'wp_accessify_group'; #'my_option_group'
    const SECTION = 'wp_accessify_section';   #'setting_section_id'

    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        if( is_admin() ) {
            add_action( 'admin_menu', array( &$this, 'add_plugin_page' ) );
            add_action( 'admin_init', array( &$this, 'page_init' ) );
        }
        $this->options = get_option( self::OPTION_NAME );
    }


    public function get_option( $key = NULL, $default = NULL ) {
        if (!$key) return $this->options;
        if (isset( $this->options[ $key ])) {
            return $this->options[ $key ];
        }
        return $default;
    }


    /**
     * WP Action - Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        $hook_suffix = add_options_page(
            'Accessify Wiki', //'Settings Admin', 
            'Accessify Wiki', //'My Settings', 
            'manage_options',
            self::MENU_SLUG,  #'wp-accessify-admin', #'my-setting-admin', 
            array( &$this, 'create_admin_page' )
        );

        header( "X-Accessify-Wiki-Opts: $hook_suffix" );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( self::OPTION_NAME ); #'my_option_name' );
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2>Accessify Wiki Settings<!--My Settings--></h2>           
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( self::OPTION_GROUP );   #'my_option_group' );   
                do_settings_sections( self::MENU_SLUG ); #'my-setting-admin' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * WP Action - Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            self::OPTION_GROUP,  #'my_option_group', // Option group
            self::OPTION_NAME,  #'my_option_name', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            self::SECTION,  #'setting_section_id', // ID
            'My Custom Settings', // Title
            array( $this, 'print_section_info' ), // Callback
            self::MENU_SLUG  #'my-setting-admin' // Page
        );

        add_settings_field(
            'site_id',  #'title'
            '<label for=site_id >Site ID</label>', # 'Title'
            array( $this, 'site_id_callback' ), 
            self::MENU_SLUG,  #'my-setting-admin', 
            self::SECTION  #'setting_section_id'
        );

        add_settings_field(
            'id_number', // ID
            'ID Number', // Title 
            array( $this, 'id_number_callback' ), // Callback
            self::MENU_SLUG,  #'my-setting-admin', // Page
            self::SECTION  #'setting_section_id' // Section           
        );
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
        if( isset( $input['id_number'] ) )
            $new_input['id_number'] = absint( $input['id_number'] );

        if( isset( $input['site_id'] ) )  #'title'
            $new_input['site_id'] = sanitize_text_field( $input['site_id'] );

        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        print 'Enter your settings below:';
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function id_number_callback()
    {
        printf(
            '<input type="text" id="id_number" name="wp_accessify_opt[id_number]" value="%s" />',
            isset( $this->options['id_number'] ) ? esc_attr( $this->options['id_number']) : ''
        );
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function site_id_callback() #title_callback()
    {
        printf( #'title'
            '<input id="site_id" name="wp_accessify_opt[site_id]" value="%s" '. 
            'placeholder="Fix:Example" pattern="Fix:\w+" required />',
            isset( $this->options['site_id'] ) ? esc_attr( $this->options['site_id']) : ''
        );
    }
}

/*if( is_admin() ) {
    $wp_accessify_opts = new Wp_Accessify_Options_Page();  #MySettingsPage();
}*/

