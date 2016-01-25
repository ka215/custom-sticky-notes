<?php
/*
  Plugin Name: Custom Sticky Notes
  Plugin URI: https://ka2.org/
  Description: This plugin will add simple sticky notes in the WordPress admin bar.
  Version: 1.0.1
  Author: ka2
  Author URI: https://ka2.org/
  Copyright: 2015-2016 MonauralSound (email : ka2@ka2.org)
  License: GPL2 - http://www.gnu.org/licenses/gpl.txt
  Text Domain: custom-sticky-notes
  Domain Path: /langs
*/
?>
<?php
/*  Copyright 2016 ka2 (https://ka2.org/)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
?>
<?php
define( 'CSNP_PLUGIN_VERSION', '1.0.1' );
define( 'CSNP_DB_VERSION', '1.0' );
define( 'CSNP', 'custom-sticky-notes' ); // This plugin domain name

if ( ! class_exists( 'CustomStickyNotes' ) ) :
/**
 * Custom Sticky Notes class
 *
 * @since 1.0.0
 *
 * @see -
 */
final class CustomStickyNotes {
  
  /**
   * Member definitions
   */
  public $plugin_ajax_action = 'save_sticky_notes';
  
  public $errors = false;
  
  public $logger_cache;
  
  
  /**
   * Instance factory
   *
   * @since 1.0.0
   */
  public static function instance() {
    
    static $instance = null;
    
    if ( null === $instance ) {
      $instance = new self;
      $instance->init();
      $instance->setup_actions();
    }
    
    return $instance;
  }
  
  private function __construct() { /* Do nothing here */ }
  
  
  /**
   * Initialize plugin
   *
   * @since 1.0.0
   */
  private function init() {
    
    // Plugin Name
    $this->domain_name = CSNP;
    
    // Versions
    $this->version = CSNP_PLUGIN_VERSION;
    $this->db_version = CSNP_DB_VERSION;
    
    // Languages
    $this->plugin_lang_dir = plugin_basename( plugin_dir_path( __FILE__ ) ) . '/langs';
    load_plugin_textdomain( $this->domain_name )
    or load_plugin_textdomain( $this->domain_name, false, $this->plugin_lang_dir );
    
  }
  
  
  /**
   * Plugin activation and deactivation actions 
   *
   * @since 1.0.0
   */
  private function setup_actions() {
    
    register_activation_hook( __FILE__, array( &$this, 'plugin_activate' ) );
    register_deactivation_hook( __FILE__, array( &$this, 'plugin_deactivation' ) );
    
    add_action( 'init', array($this, 'initialization') ); // Both admin panel and frontend.
    if ( is_admin() ) {
      add_action( 'admin_head', array( $this, 'add_header' ) );
      add_action( 'admin_footer', array( $this, 'add_footer' ) );
      add_action( 'wp_ajax_' . $this->plugin_ajax_action, array( &$this, 'ajax_handler' ) );
    } else {
      add_action( 'wp_head', array( $this, 'add_header' ) );
      add_action( 'wp_footer', array( $this, 'add_footer' ) );
      add_action( 'wp_ajax_nopriv_' . $this->plugin_ajax_action, array( &$this, 'ajax_handler' ) );
    }
    
    add_action( 'wp_before_admin_bar_render', array( &$this, 'custom_toolbar' ), 999 );
    
  }
  
  
  /**
   * Plugin first common actions
   *
   * @since 1.0.1
   */
  public function initialization() {
    // Currently no nothing
  }
  
  
  /**
   * Fire this hook when append into <head> tag for this plugin
   *
   * @since 1.0.0
   */
  public function add_header() {
    
    $_add_styles = <<<EOS
<style>
#csnp-container { min-height: 28px; height: 28px; max-height: 28px; padding-top: 4px; padding-bottom: 0; }
#csnp-container.active { background-color: #23282d; color: #ffba00; }
#csnp-container .dashicons-before span, .csnp-panel-header .dashicons-before span { position: relative!important; top: -4px!important; }
#csnp-panel { box-sizing: border-box; -moz-box-sizing: border-box; /* cursor: move; */ display: none; position: absolute; left: -200%; background-color: #f4f4f4; padding: 8px 12px; border: 1px solid #e5e5e5; border-radius: 4px; box-shadow: 2px 2px 4px rgba(0,0,0,.1)!important; -webkit-box-shadow: 2px 2px 4px rgba(0,0,0,.1)!important; }
.csnp-panel-header { position: relative!important; min-height: 28px; height: 28px; max-height: 28px; padding-top: 4px!important; padding-bottom: 0!important; color: #555!important; }
#cache-notes { position: relative; top: -4px; left: 12px; color: #398f14; }
.csnp-dismiss { position: relative!important; right: -10px!important; top: 1px!important; color: #777!important; }
.csnp-dismiss:hover, .csnp-dismiss:focus { color: #0091cd!important; }
#csnp-content-body { border-radius: 3px; padding: 8px; min-width: 330px; font-size: 13px; line-height: 18px; color: #555; }
.csnp-panel-footer label { color: #777; }
#local-only { height: 18px!important; margin-top: -2px!important; margin-right: 2px!important; border-radius: 3px; }
#csnp-save, #csnp-clear, #csnp-close { margin-left: 6px; padding: 0 12px; border-radius: 3px; font-size: 13px; line-height: 26px; height: 28px; }
</style>
EOS;
    
    echo $_add_styles;
    
  }
  
  
  /**
   * Fire this hook when append into <body> tag (just before </body>)
   *
   * @since 1.0.0
   */
  public function add_footer() {
    
    $_message = __( 'Now Cached', CSNP );
    
    $_add_scripts = <<<EOS
<script>
jQuery(document).ready(function($){
  
  var saveLocalStorage = function(){
    var cacheData = array();
    cacheData.push($('#csnp-content-body').val());
    localStorage.setItem('csnp-local-cache', JSON.stringify(cacheData));
    $('#cache-notes').text('{$_message}');
  };
  
  var saveUserMeta = function(){
    
    var jqXHR = $.ajax({
      async: true,
      url: $('#csnp-action-form').attr('action'),
      type: 'get',
      data: { 'sticky_notes': $('#csnp-content-body').val() },
      dataType: 'text',
      cache: false,
      beforeSend: function(xhr, set) {
        // return;
      }
    });
    
    jqXHR.done(function(data, stat, xhr) {
      if ('' === data) {
        $('#cache-notes').text('{$_message}');
      }
    });
    
    jqXHR.fail(function(xhr, stat, err) {
      $('#cache-notes').text('');
    });
    
  };
  
  var loadLocalStorage = function(){
    var restoredCache = JSON.parse(localStorage.getItem('csnp-local-cache'));
    if (restoredCache !== null && restoredCache.length > 0) {
      $('#csnp-content-body').val( restoredCache[0] );
      $('#cache-notes').text('{$_message}');
    } else {
      $('#cache-notes').text('');
    }
  };
  
  $('#csnp-container').on('click', function(){
    $(this).toggleClass('active');
    if ($(this).hasClass('active')) {
      if ($('#local-only').prop('checked')) {
        loadLocalStorage();
      }
      $('#csnp-panel').css('display', 'block');
    } else {
      $('#csnp-panel').css('display', 'none');
    }
  });
  
  $('a.csnp-dismiss, button#csnp-close').on('click', function(){
    $('#csnp-container').trigger('click');
  });
  
  $('button#csnp-clear').on('click', function(){
    $('#csnp-content-body').val('');
    if ($('#local-only').prop('checked')) {
      saveLocalStorage();
    } else {
      saveUserMeta();
    }
    $('#cache-notes').text('');
  });
  
  $('button#csnp-save').on('click', function(e){
    e.preventDefault();
    if ($('#local-only').prop('checked')) {
      saveLocalStorage();
    } else {
      saveUserMeta();
    }
  });
  
  $('#local-only').on('click change', function(){
    localStorage.setItem('csnp-local-only', $('#local-only').prop('checked'));
  });
  
  if ('true' === localStorage.getItem('csnp-local-only')) {
    $('#local-only').prop('checked', true);
  }
  
});
</script>
EOS;
    
    echo $_add_scripts;
    
  }
  
  
  /**
   * Retrieve the URL for calling Ajax
   *
   * @since 1.0.0
   *
   * @param array $args [require]
   * @return string $ajax_url
   **/
  public function ajax_url( $args=array() ) {
    if ( ! is_array( $args ) ) 
      return;
    
    $base_url = esc_url_raw( admin_url( 'admin-ajax.php', is_ssl() ? 'https' : 'http' ) );
    
    $ajax_queries = array_merge( array( 'action' => $this->plugin_ajax_action ), $args );
    $base_url = esc_url_raw( add_query_arg( $ajax_queries, $base_url ) );
    
    return wp_nonce_url( $base_url, $this->domain_name . '_' . $this->plugin_ajax_action );
    
  }
  
  
  /**
   * Add sticky notes menu to admin bar (toolbar)
   *
   * @since 1.0.0
   */
  public function custom_toolbar() {
    global $wp_admin_bar, $user_ID;
    get_currentuserinfo();
    
    $_title = __( 'Sticky Notes', CSNP );
    $_ajax_url = $this->ajax_url( array( 'event' => 'save_usermeta' ) );
    $_stored_content = get_user_meta( $user_ID, 'csnp_sticky_notes', true );
    
    $_args = array(
      'id' => $this->domain_name, 
      'title' => '<div id="csnp-container">
        <span class="dashicons-before dashicons-pressthis"><span>' . $_title . '</span></span>
        </div>', 
      'parent' => 'top-secondary', 
      'meta' => array(
        'html' => '<div id="csnp-panel" class="wp-core-ui csnp-panel-container" style="display: none;"><form method="post" action="' . $_ajax_url . '" id="csnp-action-form">
          <div class="csnp-panel-header"><span class="dashicons-before dashicons-pressthis"><span>' . $_title . '</span></span> <span id="cache-notes"></span> <a href="#" class="csnp-dismiss alignright"><span class="dashicons-before dashicons-no"></span></a></div>
          <div class="csnp-panel-body"><textarea id="csnp-content-body" name="csnp-content" cols="42" rows="5" placeholder="'. __( "Let's start Sticky Notes!", CSNP ) .'">' . $_stored_content . '</textarea></div>
          <div class="csnp-panel-footer"><label for="local-only" class="alignleft"><input type="checkbox" name="local_only" id="local-only" value="1"> ' . __( 'Cache to local only', CSNP ) . '</label><span class="alignright">
          <button type="submit" id="csnp-save" class="button-primary">' . __( 'Save', CSNP ) . '</button><button type="button" id="csnp-clear" class="button-secondary">' . __( 'Clear', CSNP ) . '</button><button type="button" id="csnp-close" class="button-secondary">' . __( 'Close', CSNP ) . '</button></span></div>
          </form></div>', 
        'class' => 'menupop',
      ),
    );
    
    $wp_admin_bar->add_node( $_args );
    
  }
  
  
  /**
   * Method of the handling of Ajax call
   * Ajax controller calls the actual processing in accordance with the requested event value
   *
   * @since 1.0.0
   */
  public function ajax_handler() {
    if ( ! isset( $GLOBALS['_REQUEST']['_wpnonce'] ) ) 
      $this->ajax_error( __( 'Parameters for calling Ajax is not enough.', CSNP ) );
    
    if ( ! wp_verify_nonce( $GLOBALS['_REQUEST']['_wpnonce'], $this->domain_name . '_' . $this->plugin_ajax_action ) ) {
      $this->ajax_error( __( 'Failed authentication. Invalid Ajax call.', CSNP ) );
    }
    
    if ( ! isset( $GLOBALS['_REQUEST']['event'] ) ) {
      if ( is_admin() ) {
        $this->ajax_error( __( 'Ajax event is not specified.', CSNP ) );
      } else {
        wp_die();
      }
    }
    
    $event_method = 'ajax_event_' . rtrim( $GLOBALS['_REQUEST']['event'] );
    
    if ( ! method_exists( $this, $event_method ) ) {
      $this->ajax_error( __( 'Method handling of an Ajax event does not exist.', CSNP ) );
    }
    
    $this->$event_method( $GLOBALS['_REQUEST'] );
    
  }
  
  
  /**
   * Error Handling of Ajax
   *
   * @since 1.0.0
   *
   * @param $string $error_message [optional]
   */
  public function ajax_error( $error_message=null ) {
    
    if ( empty( $error_message ) ) 
      $error_message = __( 'Error of Ajax.', CSNP );
    
    die( $error_message );
    
  }
  
  
  /**
   * Save the sticky notes to usermeta via Ajax
   *
   * @since 1.0.0
   *
   * @param array $args [optional] Array of options for modal component
   * @return void Output the HTML document for callback on the frontend
   */
  public function ajax_event_save_usermeta( $args=array() ) {
    
    global $user_ID;
    get_currentuserinfo();
    
    if ( array_key_exists( 'sticky_notes', $args ) && '' !== $user_ID ) {
      
      $_sticky_notes = stripslashes_deep( $args['sticky_notes'] );
      
      update_user_meta( $user_ID, 'csnp_sticky_notes', $_sticky_notes );
      //$this->ajax_error( __( 'Failed to save.', CSNP ) );
      wp_die();
      
    } else {
      
      $this->ajax_error( __( 'Ajax call is invalid.', CSNP ) );
      
    }
    
  }
  
  
  /**
   * Fire an action at the time this plugin has activated.
   *
   * since 1.0.0
   */
  public function plugin_activate() {
    
    $message = sprintf( __( 'Function called: %s; %s', CSNP ), __FUNCTION__, __( 'Custom Sticky Notes plugin has activated.', CSNP ) );
    $this->logger( $message );
    
    // as you fun
  }
  
  /**
   * Fire an action at the time this plugin was deactivation.
   *
   * since 1.0.0
   */
  public function plugin_deactivation() {
    
    $message = sprintf( __( 'Function called: %s; %s', CSNP ), __FUNCTION__, __( 'Custom Sticky Notes plugin has been deactivation.', CSNP ) );
    $this->logger( $message );
    
    // as you fun
  }
  
  
  /**
   * Logger for this plugin
   *
   * @since 1.0.0
   *
   * @param string $message
   * @param integer $logging_type 0: php system logger, 1: mail to $distination, 3: overwriting file of $distination (default), 4: to SAPI handler
   * @param string $distination
   * @return boolean
   */
  public function logger( $message='', $logging_type=3, $distination='' ) {
    if ( ! defined( 'CSNP' ) ) 
      return;
    
    $options = get_option( $this->domain_name );
    $this->logger_cache = $message;
    
    if ( empty( $message ) || '' === trim( $message ) ) {
      $_ret = $this->errors->get_error_message();
      if ( ! is_wp_error( $this->errors ) || empty( $_ret ) ) 
        return;
      
      $message = apply_filters( 'csnp_log_message', $this->errors->get_error_message(), $this->errors );
    }
    
    if ( ! in_array( intval( $logging_type ), array( 0, 1, 3, 4 ) ) ) 
      $logging_type = 3;
    
    $current_datetime = date( 'Y-m-d H:i:s', time() );
    $message = preg_replace( '/(?:\n|\r|\r\n)/', ' ', trim( $message ) );
    $log_message = sprintf( "[%s] %s\n", $current_datetime, $message );
    
    if ( 3 == intval( $logging_type ) ) {
      $this->log_distination_path = empty( $message ) || '' === trim( $distination ) ? plugin_dir_path( __FILE__ ) . 'debug.log' : $distination;
      $this->log_distination_path = apply_filters( 'csnp_log_distination_path', $this->log_distination_path );
    }
    
    if ( false === error_log( $log_message, $logging_type, $this->log_distination_path ) ) {
      $this->errors = new WP_Error();
      $this->errors->add( 'logging_error', __( 'Failed to logging.', CSNP ) );
      return false;
    } else {
      return true;
    }
  }
  
  
}

CustomStickyNotes::instance();

endif; // end of class_exists()