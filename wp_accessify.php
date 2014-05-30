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


ini_set( 'display_errors', 1 );
error_reporting( E_ALL );


require_once 'php/accessify_options_page.php';


class Wp_Accessify_Plugin extends Accessify_Options_Page {

  const API_URL  = '//accessifywiki.appspot.com/';  //No "http:"
  const WIKI_URL = 'http://accessify.wikia.com/wiki/WordPress';
  const DB_PREFIX= '_accessify_wiki_';
  const CACHE_JS = 'cache/accessify-site-fixes.js';
  const LOC_DOMAIN = 'wp-accessify';
  const APP_ID   = 'wp-accessify';
  const FIX_OPT  = '&min=1&callback=__accessify_IPG&app=';


  protected $site_id = '';
  protected $mode_cache = FALSE;
  protected $exclude_users;
  protected $do_exclude = FALSE;


  public function __construct() {
    parent::__construct();

    $this->setup_plugin_config();

    if (!$this->is_valid_site_id($this->site_id)) {
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

    if ($this->mode_cache) {
      add_action('wp_enqueue_scripts', array(&$this, 'enqueue_scripts'));
      add_action('admin_enqueue_scripts', array(&$this, 'enqueue_scripts'));
    } else {
      add_action('wp_footer', array(&$this, 'footer_scripts'), 1000);
      add_action('admin_footer', array(&$this, 'footer_scripts'), 1000);
    }
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
        'fix_url' => $this->fix_url(),
        'mode_cache' => $this->mode_cache,
        'exclude_users' => $this->exclude_users,
        'app' => self::APP_ID,
        'user_id' => get_current_user_id(),
        'is_debug' => $this->is_debug(),
    ));
  }

  public function admin_error_notice() {
    ?>
    <div class=error ><p><?php echo sprintf( __(
      'WP Accessify Wiki warning: The site ID is invalid or not configured – <a %s>Plugin help</a>.',
      self::LOC_DOMAIN), 'href="'. self::WIKI_URL .'"' ) ?></div>
    <?php
  }

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

  public function enqueue_scripts() {
    if ($this->do_exclude) return;

    wp_enqueue_script('wp-accessify-cache', plugins_url(
      self::CACHE_JS, WP_ACCESSIFY_REGISTER_FILE
    ), array(), false, $in_footer = TRUE);
  }

  public function head_scripts() {
    if ($this->do_exclude) return;

    ?><script>AC5U = { debug: true }</script>
<?php
  }

  public function footer_scripts() {
    if ($this->do_exclude) return;
    ?>
    <script src="<?php echo $this->lib_url() ?>" id="accessify-js"></script>
    <script><?php $this->print_glue_javascript() ?></script>
    <script src="<?php echo $this->fix_url() ?>"></script>
<?php
  }


  /** Utilities.
  */

  protected function is_valid_site_id($site_id) {
    return $site_id && preg_match('/^Fix:[\w\_]+$/', $site_id);
  } 

  protected function lib_url() {
    return self::API_URL .'browser/js/accessifyhtml5.js';
  }

  protected function fix_url() {
    return self::API_URL .'fix?q='. $this->site_id . self::FIX_OPT . self::APP_ID;
  }

  /**
  * @link http://accessify.wikia.com/wiki/Build_fix_js?q=Fix:Example_fixes
  */
  protected function print_glue_javascript() {
    ?>

  AC5U = window.AC5U || {};

  function __accessify_IPG(fixes) {
    "use strict";

    var G = AC5U,
      L = document.location,
      pat = /debug/,
      debug = G.debug || L.search.match(pat) || L.hash.match(pat);

    function log(s) {
      window.console && debug &&
        console.log(arguments.length > 1 ? arguments : s);
    }

    if (G.result) {
      return log("AccessifyHTML5: already run");
    }

    log("AccessifyHTML5: run");

    G.result = AccessifyHTML5(false, fixes);

    log(G.result);
  }
<?php
  }

  protected function build_cache_fix_javascript() {
    // TODO: Implement cached fix JS.
  }

}
$wp_accessify_wiki_plugin = new Wp_Accessify_Plugin();


/* That's all folks! */
