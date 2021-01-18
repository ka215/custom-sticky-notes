<?php
/**
 * Custom Sticky Notes
 *
 * @package           CustomStickyNotes
 * @author            Ka2
 * @copyright         2016 Ka2
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Custom Sticky Notes
 * Plugin URI:        https://ka2.org/custom-sticky-notes/
 * Description:       This plugin will add simple sticky notes in the WordPress admin bar.
 * Version:           1.1.3
 * Requires at least: 3.7
 * Requires PHP:      5.3
 * Author:            ka2
 * Author URI:        https://ka2.org/
 * Copyright:         2015-2020 MonauralSound
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       custom-sticky-notes
 * Domain Path:       /langs
 */
/*
Custom Sticky Notes is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
Custom Sticky Notes is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with Custom Sticky Notes. If not, see http://www.gnu.org/licenses/gpl-2.0.txt.
*/

defined( 'CSNP_PLUGIN_VERSION' ) or define( 'CSNP_PLUGIN_VERSION', '1.1.3' );
defined( 'CSNP' ) or define( 'CSNP', 'custom-sticky-notes' ); // This plugin domain name
defined( 'CSNP_DEBUG' ) or define( 'CSNP_DEBUG', false );

if ( ! class_exists( 'CustomStickyNotes' ) ) :
/**
 * Custom Sticky Notes class
 *
 * @since 1.0.0
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

        // Plugin Directory Path and URL
        $this->dir_path = plugin_dir_path( __FILE__ );
        $this->dir_url  = plugin_dir_url( __FILE__ );

        // Languages
        $this->plugin_lang_dir = plugin_basename( $this->dir_path ) . '/langs';
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

        add_action( 'init', array( $this, 'initialization') ); // Both admin panel and frontend.
        if ( is_admin() ) {
            add_action( 'admin_footer', array( $this, 'add_footer' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'load_csnp_resources' ) );
            add_action( 'wp_ajax_' . $this->plugin_ajax_action, array( &$this, 'ajax_handler' ) );
        } else {
            add_action( 'wp_footer', array( $this, 'add_footer' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'load_csnp_resources' ) );
            add_action( 'wp_ajax_nopriv_' . $this->plugin_ajax_action, array( &$this, 'ajax_handler' ) );
        }
        add_action( 'wp_before_admin_bar_render', array( &$this, 'custom_toolbar' ), 999 );
        add_action( 'login_footer', array( $this, 'add_footer' ) );
        add_action( 'wp_logout', array( $this, 'csnp_logout_action' ) );
    }

    /**
     * Plugin first common actions
     *
     * @since 1.0.1
     */
    public function initialization() { /* Currently no nothing */ }

    /**
     * Load resources for this plugin
     *
     * @since 1.1.0
     */
    public function load_csnp_resources() {
        $style_file_path = $this->dir_path . 'assets/css/csnp.css';
        $style_file_url = $this->dir_url . 'assets/css/csnp.css';
        if ( @file_exists( $style_file_path ) ) {
            $_hash = hash( 'CRC32b', filemtime( $style_file_path ) );
            wp_enqueue_style( 'csnp', $style_file_url . '?h=' . $_hash, [], $this->version );
        }
        $script_file_path = $this->dir_path . 'assets/js/csnp.js';
        $script_file_url = $this->dir_url . 'assets/js/csnp.js';
        if ( @file_exists( $script_file_path ) ) {
            $_hash = hash( 'CRC32b', filemtime( $script_file_path ) );
            wp_enqueue_script( 'csnp', $script_file_url . '?h=' . $_hash, [], $this->version, true );
        }
    }

    /**
     * Fire this hook when append into <head> tag for this plugin
     *
     * @since 1.0.0 -> 1.0.2
     */
    public function add_header() {
        //
    }

    /**
     * Fire this hook when append into <body> tag (just before </body>)
     *
     * @since 1.0.0 -> 1.0.2
     */
    public function add_footer() {
        global $current_user;
        get_currentuserinfo();

        if ( is_user_logged_in() )
            return;

        if ( ! get_transient( 'csnp_clear_storage' ) )
            return;

        $internal_js = <<<JS
var _ls = window.localStorage,_ss = window.sessionStorage;
_ls.removeItem('csnp-local-cache');_ls.removeItem('csnp-options');
_ss.removeItem('csnp-local-cache');_ss.removeItem('csnp-options');
JS;
        echo "<script>{$internal_js}</script>";
        delete_transient( 'csnp_clear_storage' );
    }

    /**
     * Retrieve the URL for calling Ajax
     *
     * @since 1.0.0
     *
     * @param  array  $args     [require]
     * @return string $ajax_url 
     **/
    public function ajax_url( $args = array() ) {
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
        
        // Variables for outputting HTML
        $_adjust_style    = ! is_admin() ? ' style="padding-bottom:10px;"' : '';
        $_title           = __( 'Sticky Notes', CSNP );
        $_ajax_url        = $this->ajax_url( array( 'event' => 'save_usermeta' ) );
        $_lock_label      = __( 'Locked', CSNP );
        $_unlock_label    = __( 'Unlocked', CSNP );
        $_placeholder     = __( "Let's start Sticky Notes!", CSNP );
        $_stored_content  = esc_textarea( get_user_meta( $user_ID, 'csnp_sticky_notes', true ) );
        $_checkbox_label  = __( 'Cache to local only', CSNP );
        $_btn_label_save  = __( 'Save', CSNP );
        $_btn_label_clear = __( 'Clear', CSNP );
        $_btn_label_close = __( 'Close', CSNP );
        $_setting_label   = __( 'Setting', CSNP );
        $_setting_title   = __( 'Custom Sticky Notes Settings', CSNP );
        $_setting_item_1  = __( 'Apply Dark Theme', CSNP );
        $_setting_item_2  = __( 'Use Session-Storage as Caching', CSNP );
        $_setting_item_3  = __( 'Enable Auto Save to Local Cache', CSNP );
        $_html            = <<<EOD
<div id="csnp-panel" class="csnp-panel-container">
  <form method="post" action="{$_ajax_url}" id="csnp-action-form">
    <div class="csnp-panel-header">
      <label for="csnp-lock-panel">
        <input type="checkbox" id="csnp-lock-panel" value="1">
        <span id="csnp-lock-icon" class="dashicons-before dashicons-unlock"></span>
        <span id="csnp-lock-text" data-on="{$_lock_label}" data-off="{$_unlock_label}" class="text--muted">{$_unlock_label}</span>
      </label>
      <span id="cache-notes"></span>
      <a href="javascript:;" class="csnp-dismiss alignright"><span class="dashicons-before dashicons-no-alt"></span></a>
    </div>
    <div class="csnp-panel-body"{$_adjust_style}>
      <textarea id="csnp-content-body" name="csnp-content" placeholder="{$_placeholder}">{$_stored_content}</textarea>
    </div>
    <div class="csnp-panel-footer">
      <label for="local-only">
        <input type="hidden" name="local_only" value="0">
        <input type="checkbox" name="local_only" id="local-only" value="1">
        <span>{$_checkbox_label}</span>
      </label>
      <div class="spacer"></div>
      <div>
        <button type="button" id="csnp-save"    class="csnp-btn csnp-btn-primary"  >{$_btn_label_save}</button>
        <button type="button" id="csnp-clear"   class="csnp-btn csnp-btn-secondary">{$_btn_label_clear}</button>
        <button type="button" id="csnp-close"   class="csnp-btn csnp-btn-secondary hidden" hidden>{$_btn_label_close}</button>
        <a href="javascript:;" id="csnp-setting" aria-label="{$_setting_label}"><span class="dashicons-before dashicons-admin-generic"></span></a>
      </div>
    </div>
    <div id="csnp-config-block">
      <h5><span class="dashicons-before dashicons-admin-settings"></span> {$_setting_title}</h5>
      <div class="pl-1">
        <label class="tgl flat">{$_setting_item_1}
          <input type="hidden" name="dark_theme" value="0">
          <input type="checkbox" id="on-dark-theme" name="dark_theme" value="1">
          <span class="tgl-btn"></span>
        </label>
      </div>
      <div class="pl-1">
        <label class="tgl flat">{$_setting_item_2}
          <input type="hidden" name="use_session_storage" value="0">
          <input type="checkbox" id="on-session-storage" name="use_session_storage" value="1">
          <span class="tgl-btn"></span>
        </label>
      </div>
      <div class="pl-1">
        <label class="tgl flat">{$_setting_item_3}
          <input type="hidden" name="auto_save" value="0">
          <input type="checkbox" id="on-auto-save" name="auto_save" value="1">
          <span class="tgl-btn"></span>
        </label>
      </div>
    </div>
  </form>
</div>
EOD;
        
        $_args = array(
            'id'     => $this->domain_name,
            'title'  => '<div id="csnp-container"><span class="dashicons-before dashicons-pressthis"><span>' . $_title . '</span></span></div>',
            'parent' => 'top-secondary',
            'meta'   => array(
                'html'  => $_html,
                'class' => 'menupop',
            ),
        );
        
        $wp_admin_bar->add_node( $_args );
    }

    public function csnp_logout_action( $user_id ) {
        set_transient( 'csnp_clear_storage', $user_id, 0 );
    }

    /**
     * Method of the handling of Ajax call
     * Ajax controller calls the actual processing in accordance with the requested event value
     *
     * @since 1.0.0
     */
    public function ajax_handler() {
        if ( ! isset( $GLOBALS['_REQUEST']['_wpnonce'] ) ) {
            $this->ajax_error( __( 'Parameters for calling Ajax is not enough.', CSNP ) );
        }
        
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
        
        $event_method = 'ajax_event_' . sanitize_text_field( $GLOBALS['_REQUEST']['event'] );
        
        if ( ! method_exists( $this, $event_method ) ) 
            $this->ajax_error( __( 'Method handling of an Ajax event does not exist.', CSNP ) );
        
        $this->$event_method( $GLOBALS['_REQUEST'] );
    }

    /**
     * Error Handling of Ajax
     *
     * @since 1.0.0
     *
     * @param $string $error_message [optional]
     */
    public function ajax_error( $error_message = null ) {
        if ( empty( $error_message ) ) 
            $error_message = __( 'Error of Ajax.', CSNP );
        
        die( $error_message );
    }

    /**
     * Save the sticky notes to usermeta via Ajax
     *
     * @since 1.0.0
     *
     * @param  array $args [optional] Array of options for modal component
     * @return void                   Output the HTML document for callback on the frontend
     */
    public function ajax_event_save_usermeta( $args = array() ) {
        global $user_ID;
        get_currentuserinfo();
        
        if ( array_key_exists( 'sticky_notes', $args ) && '' !== $user_ID ) {
            $_sticky_notes = stripslashes_deep( $args['sticky_notes'] );
            
            update_user_meta( $user_ID, 'csnp_sticky_notes', $_sticky_notes );
            wp_die( __( 'Saved Successfully!', CSNP ) );
        } else {
            $this->ajax_error( __( 'Ajax call is invalid.', CSNP ) );
        }
    }

    /**
     * Fire an action at the time this plugin has activated.
     *
     * since 1.0.0 -> 1.1.0
     */
    public function plugin_activate() {
        if ( CSNP_DEBUG ) {
            $message = sprintf( __( 'Function called: %s; %s', CSNP ), __FUNCTION__, __( 'Custom Sticky Notes plugin has activated.', CSNP ) );
            $this->logger( $message );
        }
    }

    /**
     * Fire an action at the time this plugin was deactivation.
     *
     * since 1.0.0 -> 1.1.0
     */
    public function plugin_deactivation() {
        if ( CSNP_DEBUG ) {
            $message = sprintf( __( 'Function called: %s; %s', CSNP ), __FUNCTION__, __( 'Custom Sticky Notes plugin has been deactivation.', CSNP ) );
            $this->logger( $message );
        }
    }

    /**
     * Logger for this plugin
     *
     * @since 1.0.0
     *
     * @param  string  $message
     * @param  integer $logging_type 0: php system logger, 1: mail to $distination, 3: overwriting file of $distination (default), 4: to SAPI handler
     * @param  string  $distination
     * @return boolean
     */
    public function logger( $message = '', $logging_type = 3, $distination = '' ) {
        if ( ! defined( 'CSNP' ) ) 
            return;
        
        $options = get_option( $this->domain_name );
        $this->logger_cache = $message;
        
        if ( empty( $message ) || '' === trim( $message ) ) {
            $_ret = $this->errors->get_error_message();
            if ( ! is_wp_error( $this->errors ) || empty( $_ret ) ) 
                return;
            
            /**
             * Filter WP_Error error messages to log
             *
             * @since 1.0.0
             * @hook  filter
             */
            $message = apply_filters( 'csnp_log_message', $this->errors->get_error_message(), $this->errors );
        }
        
        if ( ! in_array( intval( $logging_type ), array( 0, 1, 3, 4 ) ) ) 
            $logging_type = 3;
        
        $current_datetime = date( 'Y-m-d H:i:s', time() );
        $message = preg_replace( '/(?:\n|\r|\r\n)/', ' ', trim( $message ) );
        $log_message = sprintf( "[%s] %s\n", $current_datetime, $message );
        
        if ( 3 == intval( $logging_type ) ) {
            $this->log_distination_path = empty( $message ) || '' === trim( $distination ) ? plugin_dir_path( __FILE__ ) . 'debug.log' : $distination;
            /**
             * Filter the log file path
             *
             * @since 1.0.0
             * @hook  filter
             */
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

// Allow custom functions file
if ( file_exists( __DIR__ . '/functions.php' ) ) {
    require_once( __DIR__ . '/functions.php' );
}
