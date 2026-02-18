<?php
/**
 * Zibal callback handler
 */

// Load WordPress
require_once '../../../../wp-load.php';

class Zibal_Callback_Handler
{
    public function __construct()
    {
        $this->handle_callback();
    }

    public function handle_callback()
    {
        $request = $_REQUEST;

        if (isset($request['learn_press_zibal']) && intval($request['learn_press_zibal']) === 1) {
            $order_id = isset($request['order_id']) ? intval($request['order_id']) : 0;
            
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
                $this->set_error_session('تنظیمات درگاه پرداخت ناقص است');
                $this->redirect_to_checkout();
                return;
            }

            if (isset($request['status']) && isset($request['trackId'])) {
                $data = array(
                    "merchant" => $merchant,
                    "trackId" => $request['trackId'],
                );

                $result = $this->rest_payment_verification($data);
                
                if (empty($result['errors'])) {
                    if ($result['result'] == 100) {
                        $request["RefID"] = isset($result['refNumber']) ? $result['refNumber'] : $request['trackId'];
                        $this->payment_status_completed($order, $request);
                        $this->redirect_to_return_url($order);
                    } elseif ($result['result'] == 101) {
                        // Already verified
                        $this->redirect_to_return_url($order);
                    } else {
                        // Verification failed - show error code
                        $error_code = isset($result['result']) ? $result['result'] : 'نامشخص';
                        $this->set_error_session('تراکنش ناموفق - کد خطا: ' . $error_code);
                        $this->redirect_to_checkout();
                    }
                } else {
                    // API error
                    $error_msg = isset($result['errors'][0]) ? $result['errors'][0] : 'خطای ارتباط با درگاه';
                    $this->set_error_session($error_msg);
                    $this->redirect_to_checkout();
                }
            } else {
                // Missing parameters or user cancelled
                $this->set_error_session('تراکنش توسط کاربر لغو شد');
                $this->redirect_to_checkout();
            }
        } else {
            $this->redirect_to_home();
        }
        exit();
    }

    public function rest_payment_verification($data)
    {
        $jsonData = json_encode($data);
        $ch = curl_init('https://gateway.zibal.ir/v1/verify');
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
        curl_close($ch);
        
        if ($err) {
            return array("result" => 0, "errors" => array($err));
        }
        
        return json_decode($result, true);
    }

    public function payment_status_completed($order, $request)
    {
        if ($order->has_status('completed')) {
            return;
        }

        $trans_id = !empty($request["RefID"]) ? $request["RefID"] : '';
        $note = __('Payment has been successfully completed', 'learnpress-zibal');
        
        $order->payment_complete($trans_id);
        
        // Save payment details
        update_post_meta($order->get_id(), '_zibal_RefID', $request['RefID']);
        update_post_meta($order->get_id(), '_zibal_trackId', $request['trackId']);
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

new Zibal_Callback_Handler();
