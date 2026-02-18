<?php

/**
 * Template for displaying Zibal payment error message.
 *
 * This template can be overridden by copying it to yourtheme/learnpress/addons/zibal-payment/payment-error.php.
 *
 * @author   zibal team
 * @link 	 https://zibal.ir
 * @package  LearnPress/Zibal/Classes
 * @version  2.1.1
 */

/**
 * Prevent loading this file directly
 */
defined('ABSPATH') || exit();
?>

<?php 
$error_message = __('Transaction failed', 'learnpress-zibal');

// Get custom error message from session if available
if (!isset($_SESSION)) {
    session_start();
}

if (isset($_SESSION['zibal_error_message']) && !empty($_SESSION['zibal_error_message'])) {
    $error_message = $_SESSION['zibal_error_message'];
    unset($_SESSION['zibal_error_message']); // Clear after displaying
}
?>

<div class="learn-press-message error">
	<div><?php echo esc_html($error_message); ?></div>
</div>