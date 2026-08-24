<?php

/**
 * Zibal payment gateway class.
 *
 * @author   zibal team
 * @link     https://zibal.com
 * @package  LearnPress/Zibal/Classes
 * @version  2.3.0
 */

// Prevent loading this file directly
defined('ABSPATH') || exit;

if (!class_exists('LP_Gateway_Zibal')) {
    /**
     * Class LP_Gateway_Zibal
     */
    class LP_Gateway_Zibal extends LP_Gateway_Abstract
    {
       
        public $id = 'zibal';

        private $form_data = array();

        private $startPay = 'https://gateway.zibal.ir/start/';

        private $restPaymentRequestUrl = 'https://gateway.zibal.ir/v1/request';

        private $restPaymentVerification = 'https://gateway.zibal.ir/v1/verify';

        private $merchant = null;

        protected $settings = null;

        protected $order = null;

        protected $posted = null;

        protected $trackId = null;

        public function __construct()
        {
            $this->method_title = __('Zibal', 'learnpress-zibal');
            $this->method_description = __('Make a payment with Zibal.', 'learnpress-zibal');
            
            // Set icon as HTML img tag
            $icon_url = LP_ZIBAL_URL . 'assets/images/zibal.png';
            $this->icon = '<img src="' . esc_url($icon_url) . '" alt="Zibal" style="max-width: 80px; height: auto;" />';

            // Call parent constructor
            parent::__construct();

            // Get settings
            $this->title = LP()->settings->get("{$this->id}.title", $this->method_title);
            $this->description = LP()->settings->get("{$this->id}.description", $this->method_description);

            // Initialize settings
            $this->init_settings();

            // Add hooks
            add_action("learn-press/before-checkout-order-review", array($this, 'error_message'));
        }

        /**
         * Initialize settings
         */
        private function init_settings()
        {
            $settings = LP()->settings;
            
            $this->settings = array();
            $this->settings['merchant'] = $settings->get("{$this->id}.merchant");
            
            $this->merchant = !empty($this->settings['merchant']) ? $this->settings['merchant'] : '';
            
            // Set icon after settings are loaded
            $this->icon = LP_ZIBAL_URL . 'assets/images/zibal.png';
        }

        public function is_available()
        {
            $is_enabled = LP()->settings->get("{$this->id}.enable") === 'yes';
            $has_merchant = !empty($this->merchant);
            
            return $is_enabled && $has_merchant;
        }

        public function get_icon()
        {
            $icon_url = LP_ZIBAL_URL . 'assets/images/zibal.png';
            
            // Return HTML img tag
            if (!empty($icon_url)) {
                return '<img src="' . esc_url($icon_url) . '" alt="' . esc_attr($this->get_title()) . '" />';
            }
            
            return '';
        }

 
        public function get_title()
        {
            return $this->title;
        }

        public function get_description()
        {
            return $this->description;
        }

    
        public function get_supported_currencies()
        {
            return array('IRR', 'IRT');
        }

        /**
         * Resolve the currency captured by the LearnPress order.
         *
         * @param object $order LearnPress order object.
         * @return string
         */
        private function resolve_zibal_order_currency($order)
        {
            $currency = '';

            if ($order && method_exists($order, 'get_currency')) {
                $currency = $order->get_currency();
            } elseif ($order && method_exists($order, 'get_order_currency')) {
                $currency = $order->get_order_currency();
            }

            if (empty($currency) && $order && method_exists($order, 'get_id')) {
                $currency = get_post_meta($order->get_id(), '_order_currency', true);
            }

            if (empty($currency) && function_exists('learn_press_get_currency')) {
                $currency = learn_press_get_currency();
            }

            return strtoupper(sanitize_text_field($currency));
        }

        /**
         * Convert the LearnPress order amount to the rial amount expected by Zibal.
         *
         * @param mixed  $amount   Order amount.
         * @param string $currency Order currency.
         * @return int
         * @throws Exception When the currency or amount cannot be represented safely.
         */
        private function convert_zibal_order_amount_to_rials($amount, $currency)
        {
            $currency = strtoupper(sanitize_text_field($currency));

            if (!in_array($currency, $this->get_supported_currencies(), true)) {
                throw new Exception('ارز سفارش برای پرداخت زیبال پشتیبانی نمی‌شود', 8000);
            }

            if (!is_scalar($amount) || !is_numeric($amount)) {
                throw new Exception('مبلغ سفارش برای پرداخت زیبال معتبر نیست', 8000);
            }

            $numeric_amount = (float) $amount;
            $rial_amount = $currency === 'IRT' ? $numeric_amount * 10 : $numeric_amount;
            $rounded_amount = round($rial_amount);

            if (!is_finite($rial_amount) || $rial_amount <= 0 || abs($rial_amount - $rounded_amount) > 0.000001 || $rounded_amount >= PHP_INT_MAX) {
                throw new Exception('مبلغ سفارش برای پرداخت زیبال معتبر نیست', 8000);
            }

            return (int) $rounded_amount;
        }

   
        public function get_settings()
        {
            return apply_filters(
                'learn-press/gateway-payment/zibal/settings',
                array(
                    array(
                        'type' => 'title',
                    ),
                    array(
                        'title'   => __('Enable', 'learnpress-zibal'),
                        'id'      => '[enable]',
                        'default' => 'no',
                        'type'    => 'checkbox',
                    ),
                    array(
                        'type'       => 'text',
                        'title'      => __('Title', 'learnpress-zibal'),
                        'default'    => __('Zibal', 'learnpress-zibal'),
                        'id'         => '[title]',
                        'class'      => 'regular-text',
                        'visibility' => array(
                            'state'       => 'show',
                            'conditional' => array(
                                array(
                                    'field'   => '[enable]',
                                    'compare' => '=',
                                    'value'   => 'yes',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'type'       => 'textarea',
                        'title'      => __('Description', 'learnpress-zibal'),
                        'default'    => __('Pay with Zibal', 'learnpress-zibal'),
                        'id'         => '[description]',
                        'editor'     => array(
                            'textarea_rows' => 5,
                        ),
                        'css'        => 'height: 100px;',
                        'visibility' => array(
                            'state'       => 'show',
                            'conditional' => array(
                                array(
                                    'field'   => '[enable]',
                                    'compare' => '=',
                                    'value'   => 'yes',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'title'      => __('Merchant ID', 'learnpress-zibal'),
                        'id'         => '[merchant]',
                        'type'       => 'text',
                        'visibility' => array(
                            'state'       => 'show',
                            'conditional' => array(
                                array(
                                    'field'   => '[enable]',
                                    'compare' => '=',
                                    'value'   => 'yes',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'type' => 'sectionend',
                    ),
                )
            );
        }

        /**
         * Payment form.
         */
        public function get_payment_form()
        {
            ob_start();
            
            // Try to locate template
            $template = learn_press_locate_template(
                'form.php',
                learn_press_template_path() . '/addons/zibal-payment/',
                LP_ZIBAL_PATH . 'templates/'
            );
            
            // If template not found, use default path
            if (!$template || !file_exists($template)) {
                $template = LP_ZIBAL_PATH . 'templates/form.php';
            }
            
            if (file_exists($template)) {
                include $template;
            } else {
                // Fallback form if template is missing
                echo '<div class="zibal-payment-form">';
                echo '<p>' . esc_html($this->get_description()) . '</p>';
                echo '<p><label>' . __('Email', 'learnpress-zibal') . '</label>';
                echo '<input type="email" name="learn-press-zibal[email]" placeholder="test@zibal.com" /></p>';
                echo '<p><label>' . __('Mobile', 'learnpress-zibal') . '</label>';
                echo '<input type="text" name="learn-press-zibal[mobile]" placeholder="09123456789" /></p>';
                echo '</div>';
            }
            
            return ob_get_clean();
        }

        /**
         * Error message.
         */
        public function error_message()
        {
            if (!isset($_SESSION)) {
                session_start();
            }
            if (isset($_SESSION['zibal_error']) && intval($_SESSION['zibal_error']) === 1) {
                $_SESSION['zibal_error'] = 0;
                $template = learn_press_locate_template(
                    'payment-error.php',
                    learn_press_template_path() . '/addons/zibal-payment/',
                    LP_ZIBAL_PATH . 'templates/'
                );
                include $template;
            }
        }

        /**
         * Get form data.
         */
        public function get_form_data()
        {
            if ($this->order) {
                $user = learn_press_get_current_user();

                $this->form_data = array(
                    'amount'      => $this->order->get_total(),
                    'description' => sprintf(
                        "خرید کاربر %s %s شماره سفارش : %s",
                        $user->get_first_name() ? $user->get_first_name() : 'کاربر',
                        $user->get_last_name() ? $user->get_last_name() : 'گرامی',
                        $this->order->get_id()
                    ),
                    'customer'    => array(
                        'name'          => trim($user->get_first_name() . " " . $user->get_last_name()),
                        'billing_email' => $user->get_data('email') ? $user->get_data('email') : '',
                    ),
                    'errors'      => isset($this->posted['form_errors']) ? $this->posted['form_errors'] : '',
                );
            }

            return $this->form_data;
        }

        /**
         * Validate form fields.
         */
        public function validate_fields()
        {
            $posted = learn_press_get_request('learn-press-zibal');
            
            if (!$posted) {
                $posted = array();
            }
            
            $email = !empty($posted['email']) ? sanitize_email($posted['email']) : "";
            $mobile = !empty($posted['mobile']) ? sanitize_text_field($posted['mobile']) : "";
            $error_message = array();
            
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message[] = __('Invalid email format.', 'learnpress-zibal');
            }
            
            if (!empty($mobile) && !preg_match("/^(09)(\d{9})$/", $mobile)) {
                $error_message[] = __('Invalid mobile format.', 'learnpress-zibal');
            }

            if ($error = sizeof($error_message)) {
                throw new Exception(sprintf('<div>%s</div>', join('</div><div>', $error_message)), 8000);
            }
            
            $this->posted = $posted;
            return true;
        }

        /**
         * Zibal payment process.
         */
        public function process_payment($order)
        {
            $this->order = learn_press_get_order($order);
            $trackId = $this->get_zibal_authority();
            $gateway_url = ($trackId && $this->trackId !== null)
                ? $this->startPay . rawurlencode((string) $this->trackId)
                : '';

            return array(
                'result'   => $trackId ? 'success' : 'fail',
                'redirect' => $trackId ? $gateway_url : '',
            );
        }

        /**
         * Get Zibal trackId.
         */
        public function get_zibal_authority()
        {
            if ($this->get_form_data()) {
                $order_id = absint($this->order->get_id());
                $currency = $this->resolve_zibal_order_currency($this->order);

                try {
                    $amount = $this->convert_zibal_order_amount_to_rials($this->form_data['amount'], $currency);
                } catch (Exception $exception) {
                    $this->add_order_message($order_id, $exception->getMessage(), 'failed');
                    throw $exception;
                }

                $callback_token = $this->generate_callback_token($order_id);

                // Use site URL for callback to match domain
                $callback_url = add_query_arg(
                    array(
                        'learn_press_zibal' => 1,
                        'order_id'          => $order_id,
                        'zibal_token'       => $callback_token,
                    ),
                    site_url('wp-content/plugins/' . basename(dirname(LP_ZIBAL_FILE)) . '/inc/callback.php')
                );
                
                $data = array(
                    "merchant"    => sanitize_text_field($this->merchant),
                    "amount"      => $amount,
                    "callbackUrl" => $callback_url,
                    "description" => sanitize_text_field($this->form_data['description']),
                );
                
                // Add optional fields
                if (!empty($this->posted['email'])) {
                    $data['email'] = sanitize_email($this->posted['email']);
                }
                if (!empty($this->posted['mobile'])) {
                    $data['mobile'] = sanitize_text_field($this->posted['mobile']);
                }

                try {
                    $result = $this->send_zibal_request($this->restPaymentRequestUrl, $data);
                } catch (Exception $exception) {
                    $this->add_order_message($order_id, $exception->getMessage(), 'failed');
                    throw $exception;
                }

                if (isset($result['result'])) {
                    if (intval($result['result']) === 100) {
                        $provider_track_id = isset($result['trackId']) && is_scalar($result['trackId'])
                            ? sanitize_text_field((string) $result['trackId'])
                            : '';

                        if ($provider_track_id !== '') {
                            // Zibal creates the unique trackId; the plugin only stores its response.
                            $this->trackId = $provider_track_id;
                            $this->store_pending_payment($order_id, $amount, $currency, $this->trackId, $callback_token);
                            return true;
                        }

                        $message = 'خطا: پاسخ موفق درگاه فاقد شناسه تراکنش معتبر است';
                        $this->add_order_message($order_id, $message, 'failed');
                        throw new Exception($message, 8000);
                    }

                    // Show Zibal error code
                    $error_code = intval($result['result']);
                    $error_message = $this->get_zibal_response_message($result, $error_code);
                    $this->add_order_message($order_id, $error_message, 'failed');
                    throw new Exception('خطا: ' . $error_message . ' (کد: ' . $error_code . ')', 8000);
                }

                $this->add_order_message($order_id, 'خطا: پاسخ نامعتبر از درگاه', 'failed');
                throw new Exception('خطا: پاسخ نامعتبر از درگاه', 8000);
            }

            return false;
        }

        /**
         * Send JSON request to Zibal with WordPress HTTP API.
         */
        protected function send_zibal_request($url, $data)
        {
            $user_agent = $this->get_plugin_user_agent();
            $response = wp_remote_post(
                esc_url_raw($url),
                array(
                    'timeout' => 20,
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'User-Agent'   => $user_agent,
                    ),
                    'user-agent' => $user_agent,
                    'body'    => wp_json_encode($data),
                )
            );

            if (is_wp_error($response)) {
                throw new Exception('خطای ارتباط با درگاه: ' . $response->get_error_message(), 8000);
            }

            $status_code = intval(wp_remote_retrieve_response_code($response));
            $body = wp_remote_retrieve_body($response);

            if ($status_code < 200 || $status_code >= 300 || empty($body)) {
                $decoded_error = json_decode($body, true);
                $message = is_array($decoded_error) ? $this->get_zibal_response_message($decoded_error, 0) : '';

                if (empty($message)) {
                    $message = 'خطای ارتباط با درگاه';
                }

                throw new Exception($message, 8000);
            }

            $decoded = json_decode($body, true);

            if (!is_array($decoded)) {
                throw new Exception('خطا: پاسخ نامعتبر از درگاه', 8000);
            }

            return $decoded;
        }

        /**
         * Identify this integration in Zibal request logs.
         *
         * @return string
         */
        private function get_plugin_user_agent()
        {
            $version = defined('LP_ZIBAL_VERSION') ? LP_ZIBAL_VERSION : '2.3.0';

            return 'LearnPress-Zibal-Gateway/' . sanitize_text_field($version)
                . ' (WordPress; plugin=gateway-learnpress-zibal.ir; gateway=zibal)';
        }

        /**
         * Prefer the exact provider message when Zibal sends one.
         */
        private function get_zibal_response_message($result, $code)
        {
            foreach (array('message', 'errorMessage', 'description') as $key) {
                if (!empty($result[$key])) {
                    return sanitize_text_field($result[$key]);
                }
            }

            if (!empty($result['errors']) && is_array($result['errors'])) {
                return sanitize_text_field(reset($result['errors']));
            }

            return $this->get_zibal_error_message($code);
        }

        /**
         * Store payment messages where LearnPress can show order activity.
         */
        private function add_order_message($order_id, $message, $state = 'failed')
        {
            $message = sanitize_text_field($message);
            $order = learn_press_get_order($order_id);
            $order_message = $state === 'completed' ? $message : 'پرداخت زیبال ناموفق: ' . $message;

            if ($order && method_exists($order, 'add_note')) {
                $order->add_note($order_message);
            } elseif ($order && method_exists($order, 'add_order_note')) {
                $order->add_order_note($order_message);
            }

            update_post_meta($order_id, '_zibal_last_message', $message);
            update_post_meta($order_id, '_zibal_order_message', $order_message);
            update_post_meta($order_id, '_zibal_payment_state', sanitize_key($state));

            if (!in_array($state, array('completed', 'pending', 'verification_pending', 'manual_review'), true)) {
                update_post_meta($order_id, '_zibal_customer_card_number', 'پرداخت ناموفق - ' . $message);
                $this->store_order_items_payment_details(
                    $order,
                    array(
                        'status'        => sanitize_key($state),
                        'failed_message' => $message,
                        'customer_card'  => 'پرداخت ناموفق - ' . $message,
                    )
                );
            }
        }

        private function store_order_items_payment_details($order, $details)
        {
            if (!$order || !method_exists($order, 'get_items')) {
                return;
            }

            $items = $order->get_items();

            if (empty($items)) {
                return;
            }

            foreach ($items as $item_key => $item) {
                $item_id = $this->get_order_item_id($item, $item_key);

                if (!$item_id) {
                    continue;
                }

                $this->update_order_item_meta($item_id, '_zibal_payment_state', $details['status']);

                if (!empty($details['customer_card'])) {
                    $this->update_order_item_meta($item_id, '_zibal_customer_card_number', $details['customer_card']);
                }

                if (!empty($details['failed_message'])) {
                    $this->update_order_item_meta($item_id, '_zibal_last_message', $details['failed_message']);
                }
            }
        }

        private function get_order_item_id($item, $fallback)
        {
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

        private function update_order_item_meta($item_id, $key, $value)
        {
            if (function_exists('learn_press_update_order_item_meta')) {
                learn_press_update_order_item_meta($item_id, $key, $value);
                return;
            }

            if (function_exists('learn_press_update_order_itemmeta')) {
                learn_press_update_order_itemmeta($item_id, $key, $value);
                return;
            }

            update_post_meta($item_id, $key, $value);
        }

        /**
         * Persist the payment binding before redirecting the customer.
         */
        private function store_pending_payment($order_id, $amount, $currency, $track_id, $callback_token)
        {
            update_post_meta($order_id, '_zibal_trackId', sanitize_text_field($track_id));
            update_post_meta($order_id, '_zibal_amount', absint($amount));
            update_post_meta($order_id, '_zibal_order_currency', strtoupper(sanitize_text_field($currency)));
            update_post_meta($order_id, '_zibal_amount_unit', 'IRR');
            update_post_meta($order_id, '_zibal_callback_token', sanitize_text_field($callback_token));
            update_post_meta($order_id, '_zibal_payment_state', 'pending');
            update_post_meta($order_id, '_zibal_requested_at', time());
        }

        /**
         * Create an unpredictable local binding value for the callback URL.
         */
        private function generate_callback_token($order_id)
        {
            if (function_exists('wp_generate_password')) {
                return wp_generate_password(32, false, false);
            }

            return hash('sha256', $order_id . '|' . microtime(true) . '|' . wp_salt('nonce'));
        }
        
        /**
         * Get Zibal error message by code
         */
        private function get_zibal_error_message($code)
        {
            $errors = array(
                100 => 'موفق',
                102 => 'شناسه درگاه اشتباه است',
                103 => 'شناسه درگاه غیرفعال است',
                104 => 'درگاه غیرفعال است',
                105 => 'مبلغ باید بیشتر از 1000 ریال باشد',
                106 => 'شناسه‌ای نوشته نشده است',
                113 => 'مبلغ تراکنش از سقف میزان تراکنش بیشتر است',
                201 => 'قبلا تایید شده',
                202 => 'سفارش پرداخت نشده یا ناموفق بوده است',
                203 => 'trackId نامعتبر است',
            );
            
            return isset($errors[$code]) ? $errors[$code] : 'خطای نامشخص';
        }
    }
}
