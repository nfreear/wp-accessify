<?php
/*
Plugin Name: WP Accessify Wiki
Plugin URI:  http://accessify.wikia.com/wiki/WordPress
Description: Collaboratively improve the accessibility of your WordPress site.
Author:      Nick Freear
Author URI:  https://github.com/nfreear
Version:     0.1-alpha
*/
// NDF, 9 April 2014.

#http://accessifywiki.appspot.com/fix?q=Fix:My_Site&min=1&callback=_accessify_IPG
#Error 500: "UnboundLocalError: local variable 'style' referenced before assignment"

/**
* File: wp-config.php
*
* Override the site ID configured in the 'wp_options' database table,
* via a define() in 'wp-config.php'
*/
#define('_ACCESSIFY_WIKI_SITE_ID', 'Fix:My_Site');
#define('_ACCESSIFY_WIKI_SITE_ID', 'Fix:Wordpress_com');


/* Disallow direct access to the plugin file */

if (basename($_SERVER['PHP_SELF']) == basename (__FILE__)) {
    die('Sorry, but you cannot access this page directly.');
}

define('WP_ACCESSIFY_REGISTER_FILE',
  preg_replace('@/Users/[^\/]+/[^\/]+/[^\/]+@', '',    # Mac OS X
    preg_replace('@\/var\/www\/[^\/]+@', '', __FILE__) # Linux
));


require_once 'accessify-client-php/accessify_client_php.php';
require_once 'php/accessify_options_page.php';



class Wp_Accessify_Plugin extends Accessify_Options_Page {

  const DB_PREFIX= '_accessify_wiki_';
  const CACHE_JS = 'cache/accessify-site-fixes.js';
  const LOC_DOMAIN = 'wp-accessify';
  const APP_ID   = 'wp-accessify';


  protected $site_id = '';
  protected $mode_cache = FALSE;
  protected $exclude_users;
  protected $do_exclude = FALSE;

  protected $client;


  public function __construct() {
    parent::__construct();

    $this->setup_plugin_config();


    $this->client = new Accessify_Client_Php($this->site_id, self::APP_ID);


    if (!$this->client->is_valid_site_id($this->site_id)) {
      // Error?
      add_action('admin_notices', array(&$this, 'admin_error_notice'));
      return;
    }

    add_filter( 'init', array( &$this, 'init' ));

    add_filter('body_class', array(&$this, 'body_class'));
    add_filter('admin_body_class', array(&$this, 'body_class'));

    ///$this->mode_cache = $this->mode_cache && file_exists(__DIR__ . self::CACHE_JS);

    if ($this->is_debug()) {
      add_action('wp_head', array(&$this, 'head_scripts'));
      add_action('admin_head', array(&$this, 'head_scripts'));
    }

    /*if ($this->mode_cache) {
      add_action('wp_enqueue_scripts', array(&$this, 'enqueue_scripts'));
      add_action('admin_enqueue_scripts', array(&$this, 'enqueue_scripts'));
    } else {*/
      add_action('wp_footer', array(&$this, 'footer_scripts'), 1000);
      add_action('admin_footer', array(&$this, 'footer_scripts'), 1000);
    //}

    add_action( 'wp_ajax_accessify_build_fixes', array( &$this, 'ajax_build_fixes' ));
  }


  protected function setup_plugin_config() {
    // Get the site ID configured in the database.
    $this->site_id   = $this->get_option( self::OP_SID );
    $this->mode_cache= (bool) $this->get_option( self::OP_CACHE );
    $this->exclude_users = $this->split_option( self::OP_EXC_USER );

    if (!$this->site_id && defined(strtoupper(self::DB_PREFIX . 'site_id'))) {
      $this->site_id = constant(strtoupper(self::DB_PREFIX . 'site_id'));
    }
  }


  /** WP action.
  */
  public function init() {
    if (in_array( self::GUEST, $this->exclude_users )
      && !is_user_logged_in()) {
      $this->debug( "user excluded; guest" );
      $this->do_exclude = TRUE;
    }
    $user_id = get_current_user_id();
    if (in_array( $user_id, $this->exclude_users )) {
      $this->debug( "user excluded; user_id=$user_id" );
      $this->do_exclude = TRUE;
    }

    // DEBUG: Safely output our configuration in a HTTP header.
    $this->debug(array(
        self::OP_SID => $this->site_id,
        'fix_url' => $this->client->jsonp_fix_url(),
        'mode_cache' => $this->mode_cache,
        'exclude_users' => $this->exclude_users,
        'app' => self::APP_ID,
        'user_id' => get_current_user_id(),
        'is_debug' => $this->is_debug(),
    ));
  }

  /** WP action. */
  public function admin_error_notice() {
    ?>
    <div class=error ><p><?php echo sprintf( __(
      'WP Accessify Wiki warning: The site ID is invalid or not configured – <a %s>Plugin help</a>.',
      self::LOC_DOMAIN), 'href="'. $this->wiki_page() .'"' ) ?></div>
    <?php
  }

  /** WP action. */
  public function body_class( $classes ) {
    return $classes;

    $site_id_class = 'site_id-'. str_replace(':', '-', $this->site_id);
    if (is_array($classes)) {
      // 'body_class' action.
      $classes[] = self::DB_PREFIX . $site_id_class;
    } else {
      // 'admin_body_class' action.
      $classes .= ' '. self::DB_PREFIX . $site_id_class;
    }
    return $classes;
  }

  /** WP action. */
  public function enqueue_scripts() {
    if ($this->do_exclude) return;

    wp_enqueue_script('wp-accessify-cache', plugins_url(
      self::CACHE_JS, WP_ACCESSIFY_REGISTER_FILE
    ), array(), false, $in_footer = TRUE);
  }

  /** WP action. */
  public function head_scripts() {
    if ($this->do_exclude) return;

    $this->client->debug_config_scripts();
  }

  /** WP action. */
  public function footer_scripts() {
    if ($this->do_exclude) return;

    if ($this->mode_cache) {
      $body = get_option( self::DB_PREFIX . 'fixes' );
      if (isset($body->compiledCode)):
        ?>
    <script id="accessify-js-cache"><?php echo $body->compiledCode ?></script>
    <?php
      endif;
    } else {
      $this->client->print_fix_test_scripts();
    }
  }


  /** WP action - AJAX.
  */
  public function ajax_build_fixes() {
    $action  = $this->_param( 'action' );
    $site_id = $this->_param( 'site_id', $this->site_id );
    //$compilation_level = $this->_param( 'compilation_level' );

    $response = wp_remote_post( $this->client->compiler_url(), array(
        'method'  => 'POST',
        'timeout' => 45,
        'redirection' => 2, //5,
        'httpversion' => '1.1',
        'blocking' => true,
        'headers' => array(),
        'body' => $this->client->compiler_query_params( $site_id ),
    ));

    if ( is_wp_error( $response ) ) {
      $result = array(
        'stat' => 'fail',
        'action'  => $action,
        'site_id' => $site_id,
        'response' => $response,
        'html' => '<p class="error"> Closure Compiler Error - build fixes.'.
            '<p class="error"> ' . $response->get_error_message(),
      );
    } else {
      $body = json_decode($response[ 'body' ]);
      $body->site_id = $site_id;
      $body->time = date( 'c' );

      $b_success = !isset($body->serverErrors) OR count($body->serverErrors) <= 0;
      if ($b_success) {
        $output_file_path = $body->outputFilePath;
        $statistics = $body->statistics;
      }

      $update_ok = update_option( self::DB_PREFIX . 'fixes', $body );

      $result = array(
        'stat' => $b_success ? 'ok' : 'fail',
        'action'  => $action,
        'site_id' => $site_id,
        'update_option' => $update_ok,
        'response' => $response,
        'html' => $b_success ? '<p class="ok"> Closure Compiler Success - build fixes.'
            : '<p class="error"> Closure Compiler Error - build fixes [2].',
      );
    }

    @header( 'Content-Type: application/json; charset=utf-8' );
    echo json_encode( $result );
    exit;
  }

}
$wp_accessify_wiki_plugin = new Wp_Accessify_Plugin();


/* That's all folks! */
