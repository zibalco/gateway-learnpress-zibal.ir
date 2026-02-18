<?php

/**
 * Zibal payment gateway class.
 *
 * @author   zibal team
 * @link     https://zibal.com
 * @package  LearnPress/Zibal/Classes
 * @version  2.1.1
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
            $gateway_url = $this->startPay . $this->trackId;

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
                // Use site URL for callback to match domain
                $callback_url = site_url('wp-content/plugins/' . basename(dirname(LP_ZIBAL_FILE)) . '/inc/callback.php?learn_press_zibal=1&order_id=' . $this->order->get_id());
                
                $data = array(
                    "merchant"    => $this->merchant,
                    "amount"      => intval($this->form_data['amount']),
                    "callbackUrl" => $callback_url,
                    "description" => $this->form_data['description'],
                );
                
                // Add optional fields
                if (!empty($this->posted['email'])) {
                    $data['mobile'] = $this->posted['email'];
                }
                if (!empty($this->posted['mobile'])) {
                    $data['mobile'] = $this->posted['mobile'];
                }

                $jsonData = json_encode($data);
                $ch = curl_init('https://gateway.zibal.ir/v1/request');
                curl_setopt($ch, CURLOPT_USERAGENT, 'Zibal Rest Api v1');
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($jsonData),
                ));

                $result = curl_exec($ch);
                $err = curl_error($ch);
                $result = json_decode($result, true);
                curl_close($ch);

                if ($err) {
                    throw new Exception('خطای ارتباط با درگاه: ' . $err, 8000);
                } else {
                    if (isset($result['result'])) {
                        if ($result['result'] == 100) {
                            $this->trackId = $result['trackId'];
                            return true;
                        } else {
                            // Show Zibal error code
                            $error_code = $result['result'];
                            $error_message = $this->get_zibal_error_message($error_code);
                            throw new Exception('خطا: ' . $error_message . ' (کد: ' . $error_code . ')', 8000);
                        }
                    } else {
                        throw new Exception('خطا: پاسخ نامعتبر از درگاه', 8000);
                    }
                }
            }

            return false;
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
