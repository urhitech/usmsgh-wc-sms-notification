<?php

/**
 * OTP Checkout Integration for WooCommerce
 * Adds SMS OTP verification before order placement
 */

class Usmsgh_OTP_Checkout implements Usmsgh_Register_Interface {

    private $otp_handler;

    public function __construct() {
        $this->otp_handler = new Usmsgh_OTP_Handler();
    }

    /**
     * Register hooks and actions (required by interface)
     */
    public function register() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('woocommerce_checkout_before_order_review', array($this, 'add_otp_section_to_checkout'));
        add_action('woocommerce_checkout_process', array($this, 'validate_checkout_otp'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_otp_verified_status'));
        add_filter('woocommerce_checkout_fields', array($this, 'modify_billing_phone_field'));
    }

    /**
     * Check if OTP checkout is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        return Usmsgh_OTP_Handler::is_otp_enabled('checkout');
    }

    /**
     * Enqueue OTP scripts and styles for checkout
     */
    public function enqueue_scripts() {
        if (!is_checkout()) {
            return;
        }

        wp_enqueue_style(
            'usmsgh-otp-style',
            USMSGH_PLUGIN_URL . 'css/usmsgh-otp.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'usmsgh-otp-checkout',
            USMSGH_PLUGIN_URL . 'js/usmsgh-otp-checkout.js',
            array('jquery', 'wc-checkout'),
            '1.0.0',
            true
        );

        wp_localize_script('usmsgh-otp-checkout', 'usmsgh_otp_checkout_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('usmsgh_otp_nonce'),
            'strings' => array(
                'enter_phone' => __('Please enter your phone number', 'usmsgh-wc-sms-notification'),
                'sending' => __('Sending OTP...', 'usmsgh-wc-sms-notification'),
                'verify' => __('Verify OTP', 'usmsgh-wc-sms-notification'),
                'resend' => __('Resend OTP', 'usmsgh-wc-sms-notification'),
                'resend_in' => __('Resend in {seconds}s', 'usmsgh-wc-sms-notification'),
                'invalid_otp' => __('Invalid OTP code. Please try again.', 'usmsgh-wc-sms-notification'),
                'otp_sent' => __('OTP sent successfully! Check your phone.', 'usmsgh-wc-sms-notification'),
                'otp_verified' => __('Phone number verified successfully!', 'usmsgh-wc-sms-notification'),
                'required' => __('Please verify your phone number with OTP before placing order.', 'usmsgh-wc-sms-notification'),
                'verifying' => __('Verifying...', 'usmsgh-wc-sms-notification')
            )
        ));
    }

    /**
     * Modify billing phone field to be required
     *
     * @param array $fields
     * @return array
     */
    public function modify_billing_phone_field($fields) {
        if (!$this->is_enabled()) {
            return $fields;
        }

        // Make billing phone required
        if (isset($fields['billing']['billing_phone'])) {
            $fields['billing']['billing_phone']['required'] = true;
            $fields['billing']['billing_phone']['label'] = __('Phone Number (for OTP)', 'usmsgh-wc-sms-notification');
            $fields['billing']['billing_phone']['placeholder'] = __('Enter phone number for verification', 'usmsgh-wc-sms-notification');
        }

        return $fields;
    }

    /**
     * Add OTP verification section to checkout
     */
    public function add_otp_section_to_checkout() {
        if (!$this->is_enabled()) {
            return;
        }
        ?>
        <div id="usmsgh-checkout-otp-section" class="usmsgh-checkout-otp-section">
            <h3><?php _e('Phone Verification', 'usmsgh-wc-sms-notification'); ?></h3>
            <p class="usmsgh-otp-description">
                <?php _e('Please verify your phone number with OTP before placing your order.', 'usmsgh-wc-sms-notification'); ?>
            </p>
            
            <div class="usmsgh-otp-form">
                <p class="form-row form-row-wide">
                    <label for="usmsgh_checkout_phone">
                        <?php _e('Phone Number', 'usmsgh-wc-sms-notification'); ?> 
                        <span class="required">*</span>
                    </label>
                    <input type="tel" 
                           class="input-text" 
                           name="usmsgh_checkout_phone" 
                           id="usmsgh_checkout_phone" 
                           value=""
                           placeholder="<?php _e('Enter your phone number', 'usmsgh-wc-sms-notification'); ?>"
                    />
                    <button type="button" id="usmsgh_checkout_send_otp" class="button">
                        <?php _e('Send OTP', 'usmsgh-wc-sms-notification'); ?>
                    </button>
                </p>
                
                <div class="usmsgh-otp-verify-row" style="display:none;">
                    <p class="form-row form-row-wide">
                        <label for="usmsgh_checkout_otp_code">
                            <?php _e('Enter OTP Code', 'usmsgh-wc-sms-notification'); ?>
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               class="input-text" 
                               name="usmsgh_checkout_otp_code" 
                               id="usmsgh_checkout_otp_code" 
                               maxlength="8" 
                               autocomplete="off"
                               placeholder="<?php _e('Enter OTP code', 'usmsgh-wc-sms-notification'); ?>"
                        />
                        <button type="button" id="usmsgh_checkout_verify_otp" class="button">
                            <?php _e('Verify OTP', 'usmsgh-wc-sms-notification'); ?>
                        </button>
                        <button type="button" id="usmsgh_checkout_resend_otp" class="button" disabled>
                            <?php _e('Resend OTP', 'usmsgh-wc-sms-notification'); ?>
                        </button>
                    </p>
                    <span class="usmsgh-otp-status"></span>
                </div>
                
                <input type="hidden" name="usmsgh_checkout_otp_verified" id="usmsgh_checkout_otp_verified" value="0" />
                <input type="hidden" name="usmsgh_checkout_context" value="checkout" />
            </div>
            
            <div class="usmsgh-otp-message"></div>
        </div>
        <?php
    }

    /**
     * Validate checkout OTP before order placement
     */
    public function validate_checkout_otp() {
        if (!$this->is_enabled()) {
            return;
        }

        // Skip validation if not checkout action
        if (!isset($_POST['woocommerce_checkout_place_order'])) {
            return;
        }

        $otp_verified = isset($_POST['usmsgh_checkout_otp_verified']) ? sanitize_text_field($_POST['usmsgh_checkout_otp_verified']) : '0';
        $phone = isset($_POST['usmsgh_checkout_phone']) ? sanitize_text_field($_POST['usmsgh_checkout_phone']) : '';
        $billing_phone = isset($_POST['billing_phone']) ? sanitize_text_field($_POST['billing_phone']) : '';

        // Use billing phone if checkout phone is empty
        if (empty($phone) && !empty($billing_phone)) {
            $phone = $billing_phone;
        }

        // Check if phone number is provided
        if (empty($phone)) {
            wc_add_notice(
                __('Please enter your phone number for OTP verification.', 'usmsgh-wc-sms-notification'),
                'error'
            );
            return;
        }

        // Check if OTP is verified
        if ($otp_verified !== '1') {
            wc_add_notice(
                __('Please verify your phone number with OTP before placing your order.', 'usmsgh-wc-sms-notification'),
                'error'
            );
            return;
        }

        // Double-check verification
        $is_verified = $this->otp_handler->is_otp_verified($phone, 'checkout');
        
        if (!$is_verified) {
            wc_add_notice(
                __('OTP verification failed or expired. Please verify again.', 'usmsgh-wc-sms-notification'),
                'error'
            );
            return;
        }
    }

    /**
     * Save OTP verified status to order meta
     *
     * @param int $order_id
     */
    public function save_otp_verified_status($order_id) {
        if (!$this->is_enabled()) {
            return;
        }

        $otp_verified = isset($_POST['usmsgh_checkout_otp_verified']) ? sanitize_text_field($_POST['usmsgh_checkout_otp_verified']) : '0';
        $phone = isset($_POST['usmsgh_checkout_phone']) ? sanitize_text_field($_POST['usmsgh_checkout_phone']) : '';

        if ($otp_verified === '1') {
            update_post_meta($order_id, '_usmsgh_otp_verified', 'yes');
            update_post_meta($order_id, '_usmsgh_otp_verified_phone', $phone);
            update_post_meta($order_id, '_usmsgh_otp_verified_at', current_time('mysql'));
            
            // Clear the verification
            $this->otp_handler->clear_verification('checkout');
        }
    }

}
