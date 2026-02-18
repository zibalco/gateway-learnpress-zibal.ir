<?php

/**
 * Template for displaying Zibal payment form.
 *
 * This template can be overridden by copying it to yourtheme/learnpress/addons/zibal-payment/form.php.
 *
 * @author   zibal team
 * @link     https://zibal.ir
 * @package  LearnPress/Zibal/Classes
 * @version  2.0.0
 */

/**
 * Prevent loading this file directly
 */
defined('ABSPATH') || exit();
?>

<?php
$settings = LP()->settings; ?>

<p><?php
    echo $this->get_description(); ?></p>

<div id="learn-press-zibal-form" class="<?php
if (is_rtl()) {
    echo ' learn-press-form-zibal-rtl';
} ?>">
    <p class="learn-press-form-row">
        <label><?php
            echo wp_kses(__('Email', 'learnpress-zibal'), array('span' => array())); ?></label>
        <input type="text" name="learn-press-zibal[email]" id="learn-press-zibal-payment-email" maxlength="19"
               value="" placeholder="test@zibal.com"/>
    <div class="learn-press-zibal-form-clear"></div>
    </p>
    <div class="learn-press-zibal-form-clear"></div>
    <p class="learn-press-form-row">
        <label><?php
            echo wp_kses(__('Mobile', 'learnpress-zibal'), array('span' => array())); ?></label>
        <input type="text" name="learn-press-zibal[mobile]" id="learn-press-zibal-payment-mobile" value=""
               placeholder="09123456789 حتما با 09 شروع شود"/>
    <div class="learn-press-zibal-form-clear"></div>
    </p>
    <div class="learn-press-zibal-form-clear"></div>
</div>
<script>
    jQuery(document).ready(function($) {
        $('.payment_method_zibal').css('display', 'block');
    });
</script>
