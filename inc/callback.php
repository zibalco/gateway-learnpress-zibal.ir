<?php
/**
 * Zibal callback handler
 */

// Load WordPress
if (!defined('ABSPATH')) {
    require_once '../../../../wp-load.php';
}

class Zibal_Callback_Handler
{
    private $plugin_user_agent = 'LearnPress-Zibal-Gateway/2.1.1 (WordPress; plugin=gateway-learnpress-zibal.ir; gateway=zibal)';

    public function __construct()
    {
        $this->handle_callback();
    }

    public function handle_callback()
    {
        $request = $this->sanitize_request($_REQUEST);

        if (isset($request['learn_press_zibal']) && absint($request['learn_press_zibal']) === 1) {
            $order_id = isset($request['order_id']) ? absint($request['order_id']) : 0;
            
            if (!$order_id) {
                $this->redirect_to_home();
                return;
            }
            
            $order = learn_press_get_order($order_id);
            
            if (!$order) {
                $this->redirect_to_home();
                return;
            }
            
            $setting = LP()->settings;
            $merchant = $setting->get('zibal.merchant');
            
            if (!$merchant) {
                $message = 'تنظیمات درگاه پرداخت ناقص است';
                $this->add_order_message($order, $message, 'failed');
                $this->set_error_session($message);
                $this->redirect_to_checkout();
                return;
            }

            if (!$this->validate_callback_binding($order, $request)) {
                $message = 'اطلاعات بازگشت از درگاه معتبر نیست';
                $this->add_order_message($order, $message, 'failed');
                $this->set_error_session($message);
                $this->redirect_to_checkout();
                return;
            }

            if (isset($request['status']) && isset($request['trackId'])) {
                $data = array(
                    "merchant" => sanitize_text_field($merchant),
                    "trackId" => sanitize_text_field($request['trackId']),
                );

                $result = $this->rest_payment_verification($data);
                
                if (is_array($result) && empty($result['errors'])) {
                    if (isset($result['result']) && intval($result['result']) === 100) {
                        if (!$this->verify_amount($order, $result)) {
                            $this->add_order_message($order, 'مبلغ تراکنش با سفارش تطابق ندارد', 'amount_mismatch');
                            $this->set_error_session('مبلغ تراکنش با سفارش تطابق ندارد');
                            $this->redirect_to_checkout();
                            return;
                        }

                        $request["RefID"] = isset($result['refNumber']) ? $result['refNumber'] : $request['trackId'];
                        $this->payment_status_completed($order, $request, $result, $merchant);
                        $this->redirect_to_return_url($order);
                    } elseif (isset($result['result']) && intval($result['result']) === 101) {
                        // Already verified
                        if ($this->is_order_paid($order)) {
                            $this->redirect_to_return_url($order);
                        }

                        $message = $this->get_zibal_response_message($result, 'این تراکنش قبلا تایید شده و نیاز به بررسی دارد');
                        $this->add_order_message($order, $message, 'manual_review');
                        $this->set_error_session($message);
                        $this->redirect_to_checkout();
                    } else {
                        // Verification failed - show error code
                        $error_code = isset($result['result']) ? $result['result'] : 'نامشخص';
                        $message = $this->get_zibal_response_message($result, 'تراکنش ناموفق - کد خطا: ' . $error_code);
                        $this->add_order_message($order, $message, 'failed');
                        $this->set_error_session($message);
                        $this->redirect_to_checkout();
                    }
                } else {
                    // API error
                    $error_msg = isset($result['errors'][0]) ? $result['errors'][0] : 'خطای ارتباط با درگاه';
                    $this->add_order_message($order, $error_msg, 'failed');
                    $this->set_error_session($error_msg);
                    $this->redirect_to_checkout();
                }
            } else {
                // Missing parameters or user cancelled
                $this->add_order_message($order, 'تراکنش توسط کاربر لغو شد', 'cancelled');
                $this->set_error_session('تراکنش توسط کاربر لغو شد');
                $this->redirect_to_checkout();
            }
        } else {
            $this->redirect_to_home();
        }
        exit();
    }

    public function sanitize_request($request)
    {
        return array(
            'learn_press_zibal' => isset($request['learn_press_zibal']) ? absint(wp_unslash($request['learn_press_zibal'])) : 0,
            'order_id'          => isset($request['order_id']) ? absint(wp_unslash($request['order_id'])) : 0,
            'status'            => isset($request['status']) ? sanitize_text_field(wp_unslash($request['status'])) : '',
            'trackId'           => isset($request['trackId']) ? sanitize_text_field(wp_unslash($request['trackId'])) : '',
            'zibal_token'       => isset($request['zibal_token']) ? sanitize_text_field(wp_unslash($request['zibal_token'])) : '',
        );
    }

    public function validate_callback_binding($order, $request)
    {
        if (empty($request['trackId']) || empty($request['zibal_token'])) {
            return false;
        }

        $order_id = $order->get_id();
        $stored_track_id = (string) get_post_meta($order_id, '_zibal_trackId', true);
        $stored_token = (string) get_post_meta($order_id, '_zibal_callback_token', true);
        $stored_amount = absint(get_post_meta($order_id, '_zibal_amount', true));
        $payment_state = (string) get_post_meta($order_id, '_zibal_payment_state', true);

        if (empty($stored_track_id) || empty($stored_token) || !$stored_amount) {
            return false;
        }

        if ($payment_state && !in_array($payment_state, array('pending', 'completed'), true)) {
            return false;
        }

        if (!hash_equals($stored_track_id, (string) $request['trackId'])) {
            return false;
        }

        if (!hash_equals($stored_token, (string) $request['zibal_token'])) {
            return false;
        }

        return true;
    }

    public function verify_amount($order, $result)
    {
        $stored_amount = absint(get_post_meta($order->get_id(), '_zibal_amount', true));

        if (!$stored_amount) {
            return false;
        }

        $verified_amount = 0;
        foreach (array('amount', 'paidAtAmount', 'payableAmount') as $key) {
            if (isset($result[$key])) {
                $verified_amount = absint($result[$key]);
                break;
            }
        }

        if (!$verified_amount || $verified_amount !== $stored_amount) {
            return false;
        }

        return absint($order->get_total()) === $stored_amount;
    }

    public function rest_payment_verification($data)
    {
        $response = wp_remote_post(
            'https://gateway.zibal.ir/v1/verify',
            array(
                'timeout' => 20,
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'User-Agent'   => $this->plugin_user_agent,
                ),
                'user-agent' => $this->plugin_user_agent,
                'body'    => wp_json_encode($data),
            )
        );

        if (is_wp_error($response)) {
            return array('result' => 0, 'errors' => array($response->get_error_message()));
        }

        $status_code = intval(wp_remote_retrieve_response_code($response));
        $body = wp_remote_retrieve_body($response);

        if ($status_code < 200 || $status_code >= 300 || empty($body)) {
            $decoded_error = json_decode($body, true);
            $message = is_array($decoded_error) ? $this->get_zibal_response_message($decoded_error, 'خطای ارتباط با درگاه') : 'خطای ارتباط با درگاه';

            return array('result' => 0, 'errors' => array($message));
        }

        $result = json_decode($body, true);

        if (!is_array($result)) {
            return array('result' => 0, 'errors' => array('پاسخ نامعتبر از درگاه'));
        }
        
        return $result;
    }

    public function payment_status_completed($order, $request, $result = array(), $merchant = '')
    {
        if ($this->is_order_paid($order)) {
            return;
        }

        $trans_id = !empty($request["RefID"]) ? sanitize_text_field($request["RefID"]) : '';
        
        $order->payment_complete($trans_id);
        
        // Save payment details
        update_post_meta($order->get_id(), '_zibal_RefID', $trans_id);
        update_post_meta($order->get_id(), '_zibal_trackId', sanitize_text_field($request['trackId']));
        update_post_meta($order->get_id(), '_zibal_payment_state', 'completed');
        update_post_meta($order->get_id(), '_zibal_verified_at', time());
        $details = $this->store_successful_transaction_details($order, $request, $result, $merchant);
        $this->add_order_message($order, $this->format_success_order_message($details), 'completed');
    }

    public function store_successful_transaction_details($order, $request, $result, $merchant = '')
    {
        $order_id = $order->get_id();
        $transaction_number = $this->first_response_value($result, array('trackId'));
        $ref_number = $this->first_response_value($result, array('refNumber', 'refId', 'referenceNumber', 'transactionId'));
        $transaction_date = $this->first_response_value($result, array('paidAt', 'verifiedAt', 'createdAt', 'transactionDate'));
        $paid_amount = $this->first_response_value($result, array('amount', 'paidAtAmount', 'payableAmount'));
        $card_number = $this->first_response_value($result, array('cardNumber', 'cardPan', 'maskedCardNumber', 'payerCardNumber'));

        if (empty($transaction_number)) {
            $transaction_number = $request['trackId'];
        }

        if (empty($card_number) && $this->is_test_merchant($merchant)) {
            $card_number = 'مرچنت تستی';
        }

        update_post_meta($order_id, '_zibal_transaction_number', sanitize_text_field($transaction_number));
        update_post_meta($order_id, '_zibal_ref_number', sanitize_text_field($ref_number));
        update_post_meta($order_id, '_zibal_transaction_date', sanitize_text_field($transaction_date));
        update_post_meta($order_id, '_zibal_paid_amount', absint($paid_amount));
        update_post_meta($order_id, '_zibal_customer_card_number', sanitize_text_field($card_number));

        $details = array(
            'status'             => 'completed',
            'transaction_number' => sanitize_text_field($transaction_number),
            'ref_number'         => sanitize_text_field($ref_number),
            'transaction_date'   => sanitize_text_field($transaction_date),
            'paid_amount'        => absint($paid_amount),
            'customer_card'      => sanitize_text_field($card_number),
        );

        $this->store_order_items_payment_details($order, $details);

        return $details;
    }

    public function first_response_value($result, $keys)
    {
        foreach ($keys as $key) {
            if (isset($result[$key]) && $result[$key] !== '') {
                return $result[$key];
            }
        }

        return '';
    }

    public function get_zibal_response_message($result, $fallback)
    {
        foreach (array('message', 'errorMessage', 'description') as $key) {
            if (!empty($result[$key])) {
                return sanitize_text_field($result[$key]);
            }
        }

        if (!empty($result['errors']) && is_array($result['errors'])) {
            return sanitize_text_field(reset($result['errors']));
        }

        return sanitize_text_field($fallback);
    }

    public function add_order_message($order, $message, $state = 'failed')
    {
        $message = sanitize_text_field($message);

        if (method_exists($order, 'add_note')) {
            $order->add_note($message);
        } elseif (method_exists($order, 'add_order_note')) {
            $order->add_order_note($message);
        }

        update_post_meta($order->get_id(), '_zibal_last_message', $message);
        update_post_meta($order->get_id(), '_zibal_order_message', $message);
        update_post_meta($order->get_id(), '_zibal_payment_state', sanitize_key($state));

        if ($state !== 'completed' && $state !== 'pending') {
            $this->store_card_number_placeholder($order, 'پرداخت ناموفق - ' . $message);
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

    public function store_card_number_placeholder($order, $message)
    {
        update_post_meta($order->get_id(), '_zibal_customer_card_number', sanitize_text_field($message));
    }

    public function is_test_merchant($merchant)
    {
        $merchant = strtolower(trim((string) $merchant));

        return in_array($merchant, array('zibal', 'test', 'sandbox', 'merchant-test', 'test-merchant', 'zibal-test'), true)
            || strpos($merchant, 'test') !== false
            || strpos($merchant, 'sandbox') !== false;
    }

    public function format_success_order_message($details)
    {
        $lines = array(
            'پرداخت زیبال: موفق',
            'شماره تراکنش زیبال: ' . (!empty($details['transaction_number']) ? $details['transaction_number'] : '-'),
            'تاریخ تراکنش: ' . (!empty($details['transaction_date']) ? $details['transaction_date'] : '-'),
            'مبلغ: ' . (!empty($details['paid_amount']) ? $details['paid_amount'] : '-'),
            'شماره کارت مشتری: ' . (!empty($details['customer_card']) ? $details['customer_card'] : '-'),
        );

        return implode(' | ', $lines);
    }

    public function store_order_items_payment_details($order, $details)
    {
        $items = $this->get_order_items($order);

        if (empty($items)) {
            return;
        }

        foreach ($items as $item_key => $item) {
            $item_id = $this->get_order_item_id($item, $item_key);

            if (!$item_id) {
                continue;
            }

            $this->update_order_item_meta($item_id, '_zibal_payment_state', $details['status']);

            if (!empty($details['transaction_number'])) {
                $this->update_order_item_meta($item_id, '_zibal_transaction_number', $details['transaction_number']);
            }

            if (!empty($details['ref_number'])) {
                $this->update_order_item_meta($item_id, '_zibal_ref_number', $details['ref_number']);
            }

            if (!empty($details['transaction_date'])) {
                $this->update_order_item_meta($item_id, '_zibal_transaction_date', $details['transaction_date']);
            }

            if (isset($details['paid_amount'])) {
                $this->update_order_item_meta($item_id, '_zibal_paid_amount', absint($details['paid_amount']));
            }

            if (!empty($details['customer_card'])) {
                $this->update_order_item_meta($item_id, '_zibal_customer_card_number', $details['customer_card']);
            }

            if (!empty($details['failed_message'])) {
                $this->update_order_item_meta($item_id, '_zibal_last_message', $details['failed_message']);
            }
        }
    }

    public function get_order_items($order)
    {
        if (method_exists($order, 'get_items')) {
            return $order->get_items();
        }

        if (method_exists($order, 'get_order_items')) {
            return $order->get_order_items();
        }

        return array();
    }

    public function get_order_item_id($item, $fallback)
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

    public function update_order_item_meta($item_id, $key, $value)
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

    public function is_order_paid($order)
    {
        if (method_exists($order, 'has_status') && $order->has_status('completed')) {
            return true;
        }

        return (string) get_post_meta($order->get_id(), '_zibal_payment_state', true) === 'completed';
    }

    public function set_error_session($message = null)
    {
        if (!isset($_SESSION)) {
            session_start();
        }
        $_SESSION['zibal_error'] = 1;
        
        if ($message) {
            $_SESSION['zibal_error_message'] = $message;
        }
    }

    public function redirect_to_return_url($order = null)
    {
        if ($order) {
            $return_url = $order->get_checkout_order_received_url();
        } else {
            $return_url = learn_press_get_endpoint_url('lp-order-received', '', learn_press_get_page_link('checkout'));
        }

        wp_redirect(apply_filters('learn_press_get_return_url', $return_url, $order));
        exit();
    }

    public function redirect_to_checkout()
    {
        wp_redirect(esc_url(learn_press_get_page_link('checkout')));
        exit();
    }

    public function redirect_to_home()
    {
        wp_redirect(home_url());
        exit();
    }
}

if (!defined('LP_ZIBAL_CALLBACK_TEST_MODE') || !LP_ZIBAL_CALLBACK_TEST_MODE) {
    new Zibal_Callback_Handler();
}
