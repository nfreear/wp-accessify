<?php
/**
 * WP settings page for the Wp-Accessify plugin.
 *
 * @author Nick Freear, 21 May 2014.
 * Was: options.php
 * @link http://codex.wordpress.org/Creating_Options_Pages
 */

class Accessify_Options_Page {

    const MENU_SLUG = 'wp-accessify-admin';   #'my-setting-admin'
    const OPTION_NAME = 'wp_accessify_opt';   #'my_option_name'
    const OPTION_GROUP= 'wp_accessify_group'; #'my_option_group'
    const SECTION = 'wp_accessify_section';   #'setting_section_id'

    const OP_SID   = 'site_id';
    const OP_CACHE = 'mode_cache';
    const OP_EXC_USER = 'exclude_users';

    /** Holds the values to be used in the fields callbacks
     */
    private $options;

    protected $messages = array();

    /** Start up
     */
    public function __construct() {

        if( is_admin() ) {
            add_action( 'admin_menu', array( &$this, 'add_plugin_page' ) );
            add_action( 'admin_init', array( &$this, 'page_init' ) );
        }
        $this->options = get_option( self::OPTION_NAME );

        //$this->debug( $this->options );
    }


    /** WP Action - Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        $hook_suffix = add_options_page(
            'Accessify Wiki',
            'Accessify Wiki',
            'manage_options',
            self::MENU_SLUG,
            array( &$this, 'create_admin_page' )
        );

        $this->debug( __FUNCTION__ .'='. $hook_suffix );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( self::OPTION_NAME );
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2>Accessify Wiki Settings<!--My Settings--></h2>           
            <style>
            .row_mode_cache label { display:inline-block; margin:0 3em 0 0; }
            label i { font-size: small; }
            #acfy-fm .req:after { color: red; content: " *required"; font-size:small; }
            </style>
            <form id="acfy-fm" method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( self::MENU_SLUG );
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

        $this->add_settings_field(
            self::OP_SID,
            'Site ID', # 'Title'
            array( $this, 'site_id_callback' ), 
            self::MENU_SLUG,  #'my-setting-admin', 
            self::SECTION,  #'setting_section_id'
            array(), $required = TRUE
        );

        $this->add_settings_field(
            self::OP_CACHE, // ID
            'Use cached mode?', // Title
            array( $this, 'mode_cache_callback' ), // Callback
            self::MENU_SLUG,  // Page
            self::SECTION    // Section
        );

        $this->add_settings_field(
            self::OP_EXC_USER, // ID
            'Exclude user IDs?', // Title
            array( $this, 'exclude_users_callback' ), // Callback
            self::MENU_SLUG,  // Page
            self::SECTION    // Section
        );
    }

    /** Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
        #if( isset( $input['id_number'] ) )
        #    $new_input['id_number'] = absint( $input['id_number'] );

        if (isset( $input[self::OP_SID] )) {
            $new_input[self::OP_SID] = sanitize_text_field( $input[self::OP_SID] );
        }
        if (isset( $input[self::OP_CACHE] )) {
            $new_input[self::OP_CACHE] = absint( $input[self::OP_CACHE] );
        }
        if (isset( $input[self::OP_EXC_USER] )) {
            $new_input[self::OP_EXC_USER] = sanitize_text_field( $input[self::OP_EXC_USER] );
        }
        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        print 'Required settings are marked with a <span class=req >red asterisk</span> below:';
    }

    /** Get the settings option array and print one of its values
     */
    public function site_id_callback() {
        printf(
            '<input id="'. self::OP_SID .'" name="wp_accessify_opt[site_id]" value="%s" '. 
            'placeholder="Fix:Example" pattern="Fix:\w+" required aria-required="true" />',
            isset($this->options[self::OP_SID]) ? esc_attr($this->options[self::OP_SID]) : ''
        );
    }

    /** Get the settings option array and print one of its values
     */
    public function mode_cache_callback() {
        $b_cache = isset($this->options[self::OP_CACHE]) ? $this->options[self::OP_CACHE] : null;
        $cache_input = '<label><input type="radio" name="wp_accessify_opt[mode_cache]" ';
        printf(
            '<div class="row_mode_cache"> '.
            $cache_input .' value="1" %s > YES <i>(production)</i></label> '.
            $cache_input .' value="0" %s > NO <i>(test)</i></label> </div>',
            ($b_cache ? 'checked' : ''), ($b_cache ? '' : 'checked')
        );
    }

    /**  Get the settings option array and print one of its values
     */
    public function exclude_users_callback() {
        printf(
            '<input id="'. self::OP_EXC_USER .'" name="wp_accessify_opt[exclude_users]" '.
            'value="%s" placeholder="1, 3, 6" pattern="\d+([;,\s]+\d+)*" />',
            isset( $this->options[self::OP_EXC_USER] )
                ? esc_attr( $this->options[self::OP_EXC_USER]) : ''
        );
    }

    // ==========================================================

    /** Accessibility - wrap $title in a <label>.
    *
    * @link http://codex.wordpress.org/Function_Reference/add_settings_field
    */
    public function add_settings_field($id, $title, $callback, $page, $section=NULL, $args = array(), $required = false) {
        $req = $required ? "class='req'" : '';
        return add_settings_field( $id,
            "<label for='$id' $req>$title</label>", $callback, $page, $section, $args );
    }

    /** Utilities
    */
    protected function get_option( $key = NULL, $default = NULL ) {
        if (!$key) return $this->options;
        if (isset( $this->options[ $key ])) {
            return $this->options[ $key ];
        }
        return $default;
    }

    protected function get_split( $key = NULL ) {
        return preg_split( '/[;,\s]+/', $this->get_option( $key )); #, PREG_SPLIT_NO_EMPTY );
    }

    /** DEBUG: Safely output our configuration in a HTTP header.
    */
    protected function is_debug() {
        return isset($_GET['debug']) || (defined('WP_DEBUG') && constant('WP_DEBUG'));
    }
    protected function debug( $text ) {
        return $this->message( $text, 'debug' );
    }
    protected function error( $text ) {
        return $this->message( $text, 'error' );
    }
    protected function message( $text, $type = 'ok' ) {
        $message_r = array( 'type' => $type, 'msg' => $text );
        $this->messages[] = $message_r;
        @header('X-Wp-Accessify-Msg-'.
            sprintf('%02d', count($this->messages)) .': '. json_encode( $message_r ));
    }
}


