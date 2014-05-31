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

    const GUEST = -1;

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

            <?php $this->print_form_style() ?>

            <form id="acfy-fm" method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( self::MENU_SLUG );
                submit_button();

                $this->compiler_ajax_script();
            ?>
            </form>
        </div>
        <?php
    }


    protected function compiler_ajax_script() {
        ?>
    <p><button id=acfy-build-btn class="button button-primary" type=button >Build</button>
    <pre id=acfy-build-log ></pre>
    <script>
jQuery(function ($) {
  var
    ajax_url = "<?php echo admin_url( 'admin-ajax.php' ) ?>",
    build_action  = 'accessify_build_fixes',
    site_id = $('#side_id').val(),
    $log_el  = $('#acfy-build-log'),
    $btn = $('#acfy-build-btn');


  $btn.on('click', function (ev) {
    ev.preventDefault();

    $.ajax({
      type: 'POST',
      url:  ajax_url,
      data: {
        action: build_action,
        //json: JSON.stringify(data)
        site_id: site_id
      },
      dataType: 'json',
      async:   false // for Safari
    })
    .done(function (data, stat, jqXHR) {
      $log_el.html(data.html);

      log(">> Ajax success! POST", action);
      log(data);
    })
    .fail(function () {
      log(">> Ajax failed", action);
    })
    .always( /*loading_end*/ );

  });


  function log(s) {
    console.log(arguments);
  }

});    
    </script>

  <?php
    }

    /** WP Action - Register and add settings
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
            self::MENU_SLUG   // Page
        );

        $this->add_settings_field(
            self::OP_SID,
            'Site ID',
            array( $this, 'site_id_callback' ), 
            self::MENU_SLUG,
            self::SECTION,
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
            sprintf('Exclude user IDs? <i>(Use %s to exclude guests)</i>', self::GUEST),
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
    public function print_section_info() {
        print 'Required settings are marked with a <span class=req >red asterisk</span> below.';
    }

    /** Get the settings option array and print one of its values
     */
    public function site_id_callback() {
        $site_id = $this->get_option( self::OP_SID );
        printf(
            '<input id="'. self::OP_SID .'" name="wp_accessify_opt[site_id]" value="%s" '. 
            'placeholder="Fix:Example" pattern="Fix:\w+" required aria-required="true" /> ',
            esc_attr( $site_id )
        );
        $site_id ? printf(
            '<a class=v href="%s">View</a> | <a href="%s">Build</a>',
            $this->wiki_page( $site_id ), $this->wiki_build_page( $site_id )
        ) : null;
    }

    /** Get the settings option array and print one of its values
     */
    public function mode_cache_callback() {
        $b_cache = $this->get_option( self::OP_CACHE );
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
            'value="%s" placeholder="1, 3, 6" pattern="\-?\d+([;,\s]+\-?\d+)*" />',
            $this->get_option( self::OP_EXC_USER )
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

    protected function print_form_style() {
        ?>
    <style>
        .row_mode_cache label { display:inline-block; margin:0 3em 0 0; }
        label i { font-size: small; display: block; }
        .row_mode_cache label i { display: inline-block; }
        #acfy-fm .req:after { color: red; content: " *required"; font-size:small; }
    </style>
<?php
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

    protected function split_option( $key, $default = array() ) {
        $value = trim($this->get_option( $key ));
        if ('' == $value) return $default;
        return preg_split( '/[;,\s]+/', $value); #, PREG_SPLIT_NO_EMPTY );
    }


    protected function wiki_page( $page = NULL ) {
        return $this->client->wiki_url( $page );
    }

    protected function wiki_build_page( $site_id ) {
        return $this->wiki_page( 'Build_fix_js?q=' . $site_id );
    }


    /** DEBUG: Safely output our configuration in a HTTP header.
    */
    protected function is_debug() {
        return isset($_GET['debug']) || (defined('WP_DEBUG') && constant('WP_DEBUG'));
    }
    protected function debug( $object ) {
        return $this->message( $object, 'debug' );
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

    protected function _param( $key, $default = NULL ) {
        return isset($_REQUEST[ $key ]) ? $_REQUEST[ $key ] : $default;
    }
}


