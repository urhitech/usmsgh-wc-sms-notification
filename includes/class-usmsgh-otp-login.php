<?php

/**
 * OTP Login Integration for WooCommerce
 * Adds SMS OTP verification to WooCommerce customer login
 */

class Usmsgh_OTP_Login implements Usmsgh_Register_Interface {

    private $otp_handler;

    public function __construct() {
        $this->otp_handler = new Usmsgh_OTP_Handler();
    }

    /**
     * Register hooks and actions (required by interface)
     */
    public function register() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('woocommerce_login_form', array($this, 'add_otp_field_to_login'));
        add_action('woocommerce_login_form_end', array($this, 'add_otp_modal'));
        add_filter('wp_authenticate_user', array($this, 'authenticate_with_otp'), 10, 2);
        add_filter('woocommerce_process_login_errors', array($this, 'validate_login_otp'), 10, 3);
        add_action('wp_footer', array($this, 'add_login_otp_script'));
    }

    /**
     * Check if OTP login is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        return Usmsgh_OTP_Handler::is_otp_enabled('login');
    }

    /**
     * Enqueue OTP scripts and styles
     */
    public function enqueue_scripts() {
        if (!is_account_page()) {
            return;
        }

        wp_enqueue_style(
            'usmsgh-otp-style',
            USMSGH_PLUGIN_URL . 'css/usmsgh-otp.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'usmsgh-otp-login',
            USMSGH_PLUGIN_URL . 'js/usmsgh-otp-login.js',
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('usmsgh-otp-login', 'usmsgh_otp_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('usmsgh_otp_nonce'),
            'strings' => array(
                'enter_phone' => __('Enter your phone number to receive OTP', 'usmsgh-wc-sms-notification'),
                'sending' => __('Sending...', 'usmsgh-wc-sms-notification'),
                'verify' => __('Verify', 'usmsgh-wc-sms-notification'),
                'resend' => __('Resend OTP', 'usmsgh-wc-sms-notification'),
                'resend_in' => __('Resend in {seconds}s', 'usmsgh-wc-sms-notification'),
                'invalid_otp' => __('Invalid OTP code', 'usmsgh-wc-sms-notification'),
                'otp_sent' => __('OTP sent to your phone', 'usmsgh-wc-sms-notification'),
                'otp_verified' => __('OTP verified successfully', 'usmsgh-wc-sms-notification')
            )
        ));
    }

    /**
     * Add OTP verification field to login form
     */
    public function add_otp_field_to_login() {
        if (!$this->is_enabled()) {
            return;
        }
        ?>
        <p class="form-row form-row-wide">
            <label for="usmsgh_phone"><?php _e('Phone Number', 'usmsgh-wc-sms-notification'); ?> <span class="required">*</span></label>
            <input type="tel" class="input-text" name="usmsgh_phone" id="usmsgh_phone" value="" />
        </p>
        <p class="form-row form-row-wide">
            <button type="button" id="usmsgh_send_otp_btn" class="button">
                <?php _e('Send OTP', 'usmsgh-wc-sms-notification'); ?>
            </button>
        </p>
        <p class="form-row form-row-wide usmsgh-otp-verify-field" style="display:none;">
            <label for="usmsgh_otp_code"><?php _e('Enter OTP', 'usmsgh-wc-sms-notification'); ?> <span class="required">*</span></label>
            <input type="text" class="input-text" name="usmsgh_otp_code" id="usmsgh_otp_code" maxlength="8" autocomplete="off" />
            <button type="button" id="usmsgh_verify_otp_btn" class="button">
                <?php _e('Verify OTP', 'usmsgh-wc-sms-notification'); ?>
            </button>
            <button type="button" id="usmsgh_resend_otp_btn" class="button" disabled>
                <?php _e('Resend OTP', 'usmsgh-wc-sms-notification'); ?>
            </button>
            <span class="usmsgh-otp-status"></span>
        </p>
        <input type="hidden" name="usmsgh_otp_verified" id="usmsgh_otp_verified" value="0" />
        <input type="hidden" name="usmsgh_login_context" value="login" />
        <?php
    }

    /**
     * Add OTP modal HTML
     */
    public function add_otp_modal() {
        if (!$this->is_enabled()) {
            return;
        }
        ?>
        <div id="usmsgh-otp-modal" class="usmsgh-otp-modal" style="display:none;">
            <div class="usmsgh-otp-modal-content">
                <span class="usmsgh-otp-close">&times;</span>
                <h3><?php _e('OTP Verification Required', 'usmsgh-wc-sms-notification'); ?></h3>
                <p><?php _e('Please verify your phone number to continue.', 'usmsgh-wc-sms-notification'); ?></p>
                <div class="usmsgh-otp-input-group">
                    <input type="tel" id="usmsgh_modal_phone" placeholder="<?php _e('Phone Number', 'usmsgh-wc-sms-notification'); ?>" />
                    <button type="button" id="usmsgh_modal_send_otp" class="button"><?php _e('Send OTP', 'usmsgh-wc-sms-notification'); ?></button>
                </div>
                <div class="usmsgh-otp-verify-section" style="display:none;">
                    <input type="text" id="usmsgh_modal_otp" maxlength="8" placeholder="<?php _e('Enter OTP', 'usmsgh-wc-sms-notification'); ?>" />
                    <button type="button" id="usmsgh_modal_verify_otp" class="button"><?php _e('Verify', 'usmsgh-wc-sms-notification'); ?></button>
                    <button type="button" id="usmsgh_modal_resend_otp" class="button" disabled><?php _e('Resend', 'usmsgh-wc-sms-notification'); ?></button>
                </div>
                <div class="usmsgh-otp-message"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Authenticate user with OTP verification
     *
     * @param WP_User|WP_Error $user
     * @param string $password
     * @return WP_User|WP_Error
     */
    public function authenticate_with_otp($user, $password) {
        if (!$this->is_enabled()) {
            return $user;
        }

        // Check if it's a valid user object
        if (is_wp_error($user)) {
            return $user;
        }

        // Check if OTP is verified
        $otp_verified = isset($_POST['usmsgh_otp_verified']) ? sanitize_text_field($_POST['usmsgh_otp_verified']) : '0';
        $phone = isset($_POST['usmsgh_phone']) ? sanitize_text_field($_POST['usmsgh_phone']) : '';

        if ($otp_verified !== '1' || empty($phone)) {
            return new WP_Error(
                'otp_required',
                __('Please verify your phone number with OTP to login.', 'usmsgh-wc-sms-notification')
            );
        }

        // Verify that the OTP was actually verified
        $is_verified = $this->otp_handler->is_otp_verified($phone, 'login');
        
        if (!$is_verified) {
            return new WP_Error(
                'otp_invalid',
                __('OTP verification failed. Please try again.', 'usmsgh-wc-sms-notification')
            );
        }

        // Clear the verification after successful login
        $this->otp_handler->clear_verification('login');

        return $user;
    }

    /**
     * Validate login OTP for WooCommerce
     *
     * @param WP_Error $validation_error
     * @param string $username
     * @param string $password
     * @return WP_Error
     */
    public function validate_login_otp($validation_error, $username, $password) {
        if (!$this->is_enabled()) {
            return $validation_error;
        }

        $otp_verified = isset($_POST['usmsgh_otp_verified']) ? sanitize_text_field($_POST['usmsgh_otp_verified']) : '0';
        $phone = isset($_POST['usmsgh_phone']) ? sanitize_text_field($_POST['usmsgh_phone']) : '';

        if (empty($phone)) {
            $validation_error->add(
                'otp_phone_required',
                __('Phone number is required for OTP verification.', 'usmsgh-wc-sms-notification')
            );
            return $validation_error;
        }

        if ($otp_verified !== '1') {
            $validation_error->add(
                'otp_verification_required',
                __('Please complete OTP verification to login.', 'usmsgh-wc-sms-notification')
            );
            return $validation_error;
        }

        return $validation_error;
    }

    /**
     * Add inline script for login page
     */
    public function add_login_otp_script() {
        if (!$this->is_enabled() || !is_account_page()) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Intercept form submission
            var $loginForm = $('form.woocommerce-form-login');
            
            if ($loginForm.length) {
                $loginForm.on('submit', function(e) {
                    var otpVerified = $('#usmsgh_otp_verified').val();
                    
                    if (otpVerified !== '1') {
                        e.preventDefault();
                        
                        var phone = $('#usmsgh_phone').val();
                        
                        if (!phone) {
                            alert('<?php echo esc_js(__('Please enter your phone number.', 'usmsgh-wc-sms-notification')); ?>');
                            $('#usmsgh_phone').focus();
                            return false;
                        }
                        
                        // Show modal if not already showing
                        $('#usmsgh-otp-modal').show();
                        $('#usmsgh_modal_phone').val(phone);
                        
                        return false;
                    }
                    
                    return true;
                });
            }
        });
        </script>
        <?php
    }

}
