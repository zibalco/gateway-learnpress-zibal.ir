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

        // Show Zibal payment data on single order screens.
        $this->register_order_display_hooks();
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
     * Register admin/front-end order detail displays.
     */
    private function register_order_display_hooks() {
        add_action('add_meta_boxes', array($this, 'add_zibal_order_meta_box'), 20, 2);
        add_action('learn-press/order/details', array($this, 'render_zibal_order_details_from_order'), 20);
        add_action('learn-press/order-received/after-order-table', array($this, 'render_zibal_order_details_from_order'), 20);
        add_action('learn-press/profile/order-details/after-order-table', array($this, 'render_zibal_order_details_from_order'), 20);
        add_action('learn-press/after-order-details', array($this, 'render_zibal_order_details_from_order'), 20);
    }

    /**
     * Add Zibal details meta box to LearnPress order edit screens.
     */
    public function add_zibal_order_meta_box($post_type, $post = null) {
        if (!in_array($post_type, array('lp_order', 'learnpress_order'), true)) {
            return;
        }

        add_meta_box(
            'lp_zibal_order_details',
            __('اطلاعات پرداخت زیبال', 'learnpress-zibal'),
            array($this, 'render_zibal_order_meta_box'),
            $post_type,
            'normal',
            'high'
        );
    }

    /**
     * Render Zibal details in the admin order meta box.
     */
    public function render_zibal_order_meta_box($post) {
        $order_id = isset($post->ID) ? absint($post->ID) : 0;
        $this->render_zibal_order_details($order_id, true);
    }

    /**
     * Render Zibal details from a LearnPress order object/id.
     */
    public function render_zibal_order_details_from_order($order = null) {
        static $rendered = array();

        $order_id = $this->get_order_id_from_mixed($order);

        if (!$order_id || isset($rendered[$order_id])) {
            return;
        }

        $rendered[$order_id] = true;
        $this->render_zibal_order_details($order_id, false);
    }

    /**
     * Display Zibal payment data for a single order.
     */
    private function render_zibal_order_details($order_id, $admin = false) {
        $details = $this->get_zibal_order_details($order_id);

        if (!$this->has_zibal_order_details($details)) {
            echo '<p>' . esc_html__('اطلاعات پرداخت زیبال برای این سفارش ثبت نشده است.', 'learnpress-zibal') . '</p>';
            return;
        }

        $wrapper_class = $admin ? 'lp-zibal-order-details-admin' : 'lp-zibal-order-details';
        echo '<div class="' . esc_attr($wrapper_class) . '">';
        echo '<h3>' . esc_html__('اطلاعات پرداخت زیبال', 'learnpress-zibal') . '</h3>';
        echo '<table class="widefat striped"><tbody>';

        foreach ($details as $label => $value) {
            if ($value === '') {
                $value = '-';
            }

            echo '<tr>';
            echo '<th style="width:220px;">' . esc_html($label) . '</th>';
            echo '<td>' . esc_html($value) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        $this->render_zibal_order_item_details($order_id);
        echo '</div>';
    }

    /**
     * Get stored Zibal order details.
     */
    private function get_zibal_order_details($order_id) {
        return array(
            __('وضعیت پرداخت', 'learnpress-zibal')       => get_post_meta($order_id, '_zibal_payment_state', true),
            __('پیام سفارش', 'learnpress-zibal')          => get_post_meta($order_id, '_zibal_order_message', true),
            __('شماره تراکنش زیبال', 'learnpress-zibal') => get_post_meta($order_id, '_zibal_transaction_number', true),
            __('شماره ارجاع بانکی', 'learnpress-zibal')  => get_post_meta($order_id, '_zibal_ref_number', true),
            __('تاریخ تراکنش', 'learnpress-zibal')       => get_post_meta($order_id, '_zibal_transaction_date', true),
            __('مبلغ', 'learnpress-zibal')               => get_post_meta($order_id, '_zibal_paid_amount', true),
            __('شماره کارت مشتری', 'learnpress-zibal')   => get_post_meta($order_id, '_zibal_customer_card_number', true),
            __('آخرین پیام زیبال', 'learnpress-zibal')   => get_post_meta($order_id, '_zibal_last_message', true),
        );
    }

    /**
     * Check whether any Zibal order detail exists.
     */
    private function has_zibal_order_details($details) {
        foreach ($details as $value) {
            if ($value !== '' && $value !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Display per-product/order-item Zibal metadata when available.
     */
    private function render_zibal_order_item_details($order_id) {
        $order = function_exists('learn_press_get_order') ? learn_press_get_order($order_id) : null;

        if (!$order || !method_exists($order, 'get_items')) {
            return;
        }

        $items = $order->get_items();

        if (empty($items)) {
            return;
        }

        echo '<h4>' . esc_html__('اطلاعات زیبال برای محصولات این سفارش', 'learnpress-zibal') . '</h4>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('محصول/دوره', 'learnpress-zibal') . '</th>';
        echo '<th>' . esc_html__('شماره تراکنش زیبال', 'learnpress-zibal') . '</th>';
        echo '<th>' . esc_html__('تاریخ', 'learnpress-zibal') . '</th>';
        echo '<th>' . esc_html__('مبلغ', 'learnpress-zibal') . '</th>';
        echo '<th>' . esc_html__('شماره کارت', 'learnpress-zibal') . '</th>';
        echo '<th>' . esc_html__('پیام', 'learnpress-zibal') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($items as $item_key => $item) {
            $item_id = $this->get_order_item_id($item, $item_key);

            if (!$item_id) {
                continue;
            }

            echo '<tr>';
            echo '<td>' . esc_html($this->get_order_item_name($item, $item_id)) . '</td>';
            echo '<td>' . esc_html($this->get_order_item_meta($item_id, '_zibal_transaction_number')) . '</td>';
            echo '<td>' . esc_html($this->get_order_item_meta($item_id, '_zibal_transaction_date')) . '</td>';
            echo '<td>' . esc_html($this->get_order_item_meta($item_id, '_zibal_paid_amount')) . '</td>';
            echo '<td>' . esc_html($this->get_order_item_meta($item_id, '_zibal_customer_card_number')) . '</td>';
            echo '<td>' . esc_html($this->get_order_item_meta($item_id, '_zibal_last_message')) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * Extract order id from different LearnPress hook payloads.
     */
    private function get_order_id_from_mixed($order) {
        if (is_numeric($order)) {
            return absint($order);
        }

        if (is_object($order)) {
            foreach (array('get_id', 'get_order_id') as $method) {
                if (method_exists($order, $method)) {
                    return absint($order->$method());
                }
            }

            if (isset($order->id)) {
                return absint($order->id);
            }

            if (isset($order->ID)) {
                return absint($order->ID);
            }
        }

        if (function_exists('get_the_ID')) {
            return absint(get_the_ID());
        }

        return 0;
    }

    /**
     * Resolve LearnPress order item id.
     */
    private function get_order_item_id($item, $fallback) {
        if (is_object($item)) {
            foreach (array('get_id', 'get_order_item_id', 'get_item_id') as $method) {
                if (method_exists($item, $method)) {
                    return absint($item->$method());
                }
            }

            if (isset($item->id)) {
                return absint($item->id);
            }

            if (isset($item->item_id)) {
                return absint($item->item_id);
            }
        }

        if (is_array($item)) {
            foreach (array('id', 'item_id', 'order_item_id') as $key) {
                if (!empty($item[$key])) {
                    return absint($item[$key]);
                }
            }
        }

        return absint($fallback);
    }

    /**
     * Resolve LearnPress order item display name.
     */
    private function get_order_item_name($item, $fallback) {
        if (is_object($item)) {
            foreach (array('get_name', 'get_title') as $method) {
                if (method_exists($item, $method)) {
                    return $item->$method();
                }
            }

            if (isset($item->name)) {
                return $item->name;
            }
        }

        if (is_array($item)) {
            foreach (array('name', 'title', 'course_name') as $key) {
                if (!empty($item[$key])) {
                    return $item[$key];
                }
            }
        }

        return '#' . absint($fallback);
    }

    /**
     * Read LearnPress order item meta with fallbacks.
     */
    private function get_order_item_meta($item_id, $key) {
        if (function_exists('learn_press_get_order_item_meta')) {
            return learn_press_get_order_item_meta($item_id, $key, true);
        }

        if (function_exists('learn_press_get_order_itemmeta')) {
            return learn_press_get_order_itemmeta($item_id, $key, true);
        }

        return get_post_meta($item_id, $key, true);
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
