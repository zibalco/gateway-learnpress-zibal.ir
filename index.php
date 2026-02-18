<?php
/*
Plugin Name: افزونه پرداخت زیبال برای لرن پرس
Description: افزونه پرداخت امن زیبال برای لرن پرس
Author: zibal team
Link: https://zibal.com
Version: 2.1.1
Author URI: https://github.com/zibalco
Tags: learnpress,zibal,gateway,payment,زیبال,lms,لرن پرس
Text Domain: learnpress-zibal
Domain Path: /languages/
*/

// Prevent loading this file directly
defined('ABSPATH') || exit;

// Define constants
define('LP_ZIBAL_FILE', __FILE__);
define('LP_ZIBAL_PATH', plugin_dir_path(__FILE__));
define('LP_ZIBAL_URL', plugin_dir_url(__FILE__));
define('LP_ZIBAL_VERSION', '2.1.1');

/**
 * Main plugin class
 */
class LP_Zibal_Payment_Plugin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'), 15);
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('learnpress-zibal', false, basename(dirname(__FILE__)) . '/languages');
        
        // Check if LearnPress is active
        if (!class_exists('LearnPress')) {
            add_action('admin_notices', array($this, 'learnpress_missing_notice'));
            return;
        }
        
        // Include gateway class
        $this->includes();
        
        // Register gateway
        $this->register_gateway();
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Define additional constants
        if (!defined('LP_ZIBAL_TEMPLATE')) {
            define('LP_ZIBAL_TEMPLATE', LP_ZIBAL_PATH . 'templates/');
        }
        
        require_once LP_ZIBAL_PATH . 'inc/class-lp-gateway-zibal.php';
    }
    
    /**
     * Register payment gateway
     */
    private function register_gateway() {
        // Multiple hooks for maximum compatibility
        add_filter('learn-press/payment-gateways', array($this, 'add_gateway'));
        add_filter('learn_press_payment_method', array($this, 'add_gateway'));
        add_filter('learn-press/payment-methods', array($this, 'add_gateway'));
        
        // Direct registration if possible
        if (class_exists('LP_Gateways')) {
            add_action('init', array($this, 'direct_register'), 20);
        }
    }
    
    /**
     * Add gateway to list
     */
    public function add_gateway($gateways) {
        $gateways['zibal'] = 'LP_Gateway_Zibal';
        return $gateways;
    }
    
    /**
     * Direct registration method
     */
    public function direct_register() {
        if (class_exists('LP_Gateways')) {
            $instance = LP_Gateways::instance();
            if (method_exists($instance, 'register')) {
                $instance->register('zibal', 'LP_Gateway_Zibal');
            }
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up if needed
        flush_rewrite_rules();
    }
    
    /**
     * LearnPress missing notice
     */
    public function learnpress_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('افزونه پرداخت زیبال نیاز به فعال بودن افزونه LearnPress دارد.', 'learnpress-zibal'); ?></p>
        </div>
        <?php
    }
}

// Initialize plugin
new LP_Zibal_Payment_Plugin();
