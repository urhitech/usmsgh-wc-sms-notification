<?php

/**
 * OTP Handler Class for USMS-GH WooCommerce Plugin
 * Handles OTP generation, storage, verification, and SMS sending
 */

class Usmsgh_OTP_Handler {

    private $table_name;
    private $log;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'usmsgh_otp_codes';
        $this->log = new Usmsgh_WooCoommerce_Logger();
        $this->init();
    }

    /**
     * Initialize OTP hooks and actions
     */
    public function init() {
        add_action('wp_ajax_usmsgh_send_otp', array($this, 'ajax_send_otp'));
        add_action('wp_ajax_nopriv_usmsgh_send_otp', array($this, 'ajax_send_otp'));
        add_action('wp_ajax_usmsgh_verify_otp', array($this, 'ajax_verify_otp'));
        add_action('wp_ajax_nopriv_usmsgh_verify_otp', array($this, 'ajax_verify_otp'));
        add_action('wp_ajax_usmsgh_resend_otp', array($this, 'ajax_resend_otp'));
        add_action('wp_ajax_nopriv_usmsgh_resend_otp', array($this, 'ajax_resend_otp'));
    }

    /**
     * Create OTP database table
     */
    public static function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'usmsgh_otp_codes';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            phone varchar(20) NOT NULL,
            otp_code varchar(10) NOT NULL,
            context varchar(50) NOT NULL DEFAULT 'general',
            attempts tinyint(3) unsigned DEFAULT 0,
            verified tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            verified_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY phone (phone),
            KEY otp_code (otp_code),
            KEY expires_at (expires_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Generate a random OTP code
     *
     * @param int $length
     * @return string
     */
    public function generate_otp($length = null) {
        if ($length === null) {
            $length = intval(usmsgh_get_options('usmsgh_woocommerce_otp_length', 'usmsgh_otp_setting', '6'));
        }
        $length = max(4, min(8, $length));
        
        $min = pow(10, $length - 1);
        $max = pow(10, $length) - 1;
        
        return strval(mt_rand($min, $max));
    }

    /**
     * Store OTP in database
     *
     * @param string $phone
     * @param string $otp
     * @param string $context
     * @return bool|int
     */
    public function store_otp($phone, $otp, $context = 'general') {
        global $wpdb;
        
        // Clean old OTPs for this phone
        $this->cleanup_old_otps($phone);
        
        $expiry_minutes = intval(usmsgh_get_options('usmsgh_woocommerce_otp_expiry', 'usmsgh_otp_setting', '10'));
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_minutes} minutes"));
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'phone' => $this->normalize_phone($phone),
                'otp_code' => $otp,
                'context' => sanitize_text_field($context),
                'expires_at' => $expires_at,
                'created_at' => current_time('mysql'),
                'attempts' => 0,
                'verified' => 0
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d')
        );
        
        if ($result === false) {
            $this->log->add('UsmsGH_OTP', 'Failed to store OTP: ' . $wpdb->last_error);
        }
        
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Verify OTP code
     *
     * @param string $phone
     * @param string $otp
     * @param string $context
     * @return array
     */
    public function verify_otp($phone, $otp, $context = 'general') {
        global $wpdb;
        
        $phone = $this->normalize_phone($phone);
        
        // Get the most recent OTP for this phone
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE phone = %s 
            AND context = %s 
            AND verified = 0 
            AND expires_at > %s 
            ORDER BY created_at DESC 
            LIMIT 1",
            $phone,
            sanitize_text_field($context),
            current_time('mysql')
        ));
        
        if (!$record) {
            return array(
                'success' => false,
                'message' => __('OTP expired or not found. Please request a new OTP.', 'usmsgh-wc-sms-notification')
            );
        }
        
        // Increment attempts
        $wpdb->update(
            $this->table_name,
            array('attempts' => $record->attempts + 1),
            array('id' => $record->id),
            array('%d'),
            array('%d')
        );
        
        // Check max attempts (5 attempts allowed)
        if ($record->attempts >= 5) {
            return array(
                'success' => false,
                'message' => __('Too many failed attempts. Please request a new OTP.', 'usmsgh-wc-sms-notification')
            );
        }
        
        // Verify OTP
        if ($record->otp_code !== $otp) {
            return array(
                'success' => false,
                'message' => __('Invalid OTP code. Please try again.', 'usmsgh-wc-sms-notification'),
                'attempts_remaining' => 5 - ($record->attempts + 1)
            );
        }
        
        // Mark as verified
        $wpdb->update(
            $this->table_name,
            array(
                'verified' => 1,
                'verified_at' => current_time('mysql')
            ),
            array('id' => $record->id),
            array('%d', '%s'),
            array('%d')
        );
        
        return array(
            'success' => true,
            'message' => __('OTP verified successfully.', 'usmsgh-wc-sms-notification')
        );
    }

    /**
     * Send OTP via SMS
     *
     * @param string $phone
     * @param string $context
     * @return array
     */
    public function send_otp($phone, $context = 'general') {
        // Check if OTP is enabled
        $otp_enabled = usmsgh_get_options('usmsgh_woocommerce_enable_otp', 'usmsgh_otp_setting', 'off');
        if ($otp_enabled !== 'on') {
            return array(
                'success' => false,
                'message' => __('OTP verification is not enabled.', 'usmsgh-wc-sms-notification')
            );
        }
        
        $otp = $this->generate_otp();
        $store_otp = $this->store_otp($phone, $otp, $context);
        
        if (!$store_otp) {
            return array(
                'success' => false,
                'message' => __('Failed to generate OTP. Please try again.', 'usmsgh-wc-sms-notification')
            );
        }
        
        // Get message template
        $template = usmsgh_get_options(
            'usmsgh_woocommerce_otp_message_template',
            'usmsgh_otp_setting',
            '[shop_name]: Your verification code is [otp_code]. Valid for [expiry] minutes.'
        );
        
        $expiry = usmsgh_get_options('usmsgh_woocommerce_otp_expiry', 'usmsgh_otp_setting', '10');
        $shop_name = get_bloginfo('name');
        
        // Replace placeholders
        $message = str_replace(
            array('[otp_code]', '[shop_name]', '[expiry]'),
            array($otp, $shop_name, $expiry),
            $template
        );
        
        // Send SMS using USMS-GH
        try {
            $api_key = usmsgh_get_options('usmsgh_woocommerce_api_key', 'usmsgh_setting');
            $sender_id = usmsgh_get_options('usmsgh_woocommerce_sms_from', 'usmsgh_setting');
            
            if (empty($api_key) || empty($sender_id)) {
                return array(
                    'success' => false,
                    'message' => __('SMS API not configured. Please contact the administrator.', 'usmsgh-wc-sms-notification')
                );
            }
            
            $usmsgh = new UsmsGH($api_key);
            $phones = array($this->normalize_phone($phone));
            
            $response = $usmsgh->sendSMS($phones, $message, $sender_id);
            $response = json_decode($response, true);
            
            if (isset($response['status']) && $response['status'] == 0) {
                return array(
                    'success' => true,
                    'message' => __('OTP sent successfully. Please check your phone.', 'usmsgh-wc-sms-notification'),
                    'otp_id' => $store_otp
                );
            } else {
                $error_msg = isset($response['comment']) ? $response['comment'] : 'Failed to send SMS';
                $this->log->add('UsmsGH_OTP', 'SMS send failed: ' . $error_msg);
                return array(
                    'success' => false,
                    'message' => __('Failed to send OTP. Please try again later.', 'usmsgh-wc-sms-notification')
                );
            }
        } catch (Exception $e) {
            $this->log->add('UsmsGH_OTP', 'Exception: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('An error occurred. Please try again later.', 'usmsgh-wc-sms-notification')
            );
        }
    }

    /**
     * AJAX handler for sending OTP
     */
    public function ajax_send_otp() {
        check_ajax_referer('usmsgh_otp_nonce', 'nonce');
        
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'general';
        
        if (empty($phone)) {
            wp_send_json_error(array('message' => __('Phone number is required.', 'usmsgh-wc-sms-notification')));
            return;
        }
        
        $result = $this->send_otp($phone, $context);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler for verifying OTP
     */
    public function ajax_verify_otp() {
        check_ajax_referer('usmsgh_otp_nonce', 'nonce');
        
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $otp = isset($_POST['otp']) ? sanitize_text_field($_POST['otp']) : '';
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'general';
        
        if (empty($phone) || empty($otp)) {
            wp_send_json_error(array('message' => __('Phone number and OTP are required.', 'usmsgh-wc-sms-notification')));
            return;
        }
        
        $result = $this->verify_otp($phone, $otp, $context);
        
        if ($result['success']) {
            // Set session/cookie to indicate verified OTP
            session_start();
            $_SESSION['usmsgh_otp_verified_' . $context] = true;
            $_SESSION['usmsgh_otp_phone_' . $context] = $this->normalize_phone($phone);
            
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler for resending OTP
     */
    public function ajax_resend_otp() {
        check_ajax_referer('usmsgh_otp_nonce', 'nonce');
        
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'general';
        
        if (empty($phone)) {
            wp_send_json_error(array('message' => __('Phone number is required.', 'usmsgh-wc-sms-notification')));
            return;
        }
        
        // Check if there's a recent OTP (within last 60 seconds)
        global $wpdb;
        $recent_otp = $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM {$this->table_name} 
            WHERE phone = %s 
            AND context = %s 
            AND created_at > %s 
            ORDER BY created_at DESC 
            LIMIT 1",
            $this->normalize_phone($phone),
            sanitize_text_field($context),
            date('Y-m-d H:i:s', strtotime('-60 seconds'))
        ));
        
        if ($recent_otp) {
            wp_send_json_error(array(
                'message' => __('Please wait 60 seconds before requesting a new OTP.', 'usmsgh-wc-sms-notification'),
                'wait_time' => 60
            ));
            return;
        }
        
        $result = $this->send_otp($phone, $context);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Check if OTP is verified for a phone number
     *
     * @param string $phone
     * @param string $context
     * @return bool
     */
    public function is_otp_verified($phone, $context = 'general') {
        if (!session_id()) {
            session_start();
        }
        
        $session_key = 'usmsgh_otp_verified_' . $context;
        $session_phone_key = 'usmsgh_otp_phone_' . $context;
        
        if (isset($_SESSION[$session_key]) && $_SESSION[$session_key] === true) {
            if (isset($_SESSION[$session_phone_key]) && $_SESSION[$session_phone_key] === $this->normalize_phone($phone)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Clear OTP verification for a context
     *
     * @param string $context
     */
    public function clear_verification($context = 'general') {
        if (!session_id()) {
            session_start();
        }
        
        unset($_SESSION['usmsgh_otp_verified_' . $context]);
        unset($_SESSION['usmsgh_otp_phone_' . $context]);
    }

    /**
     * Normalize phone number
     *
     * @param string $phone
     * @return string
     */
    private function normalize_phone($phone) {
        // Remove all non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Get country code
        $country_code = usmsgh_get_options('usmsgh_woocommerce_country_code', 'usmsgh_setting', '233');
        
        // If starts with 0, replace with country code
        if (strpos($phone, '0') === 0) {
            $phone = $country_code . substr($phone, 1);
        }
        // If doesn't start with country code, prepend it
        elseif (strpos($phone, $country_code) !== 0) {
            $phone = $country_code . $phone;
        }
        
        return $phone;
    }

    /**
     * Clean up old/expired OTPs
     *
     * @param string $phone
     */
    private function cleanup_old_otps($phone = null) {
        global $wpdb;
        
        if ($phone) {
            // Delete all unverified OTPs for this phone
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE phone = %s AND verified = 0",
                $this->normalize_phone($phone)
            ));
        }
        
        // Delete all expired OTPs
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE expires_at < %s",
            current_time('mysql')
        ));
    }

    /**
     * Check if OTP feature is enabled for a specific context
     *
     * @param string $context 'login' or 'checkout'
     * @return bool
     */
    public static function is_otp_enabled($context = null) {
        $otp_enabled = usmsgh_get_options('usmsgh_woocommerce_enable_otp', 'usmsgh_otp_setting', 'off');
        
        if ($otp_enabled !== 'on') {
            return false;
        }
        
        if ($context === 'login') {
            $login_enabled = usmsgh_get_options('usmsgh_woocommerce_enable_otp_login', 'usmsgh_otp_setting', 'off');
            return $login_enabled === 'on';
        }
        
        if ($context === 'checkout') {
            $checkout_enabled = usmsgh_get_options('usmsgh_woocommerce_enable_otp_checkout', 'usmsgh_otp_setting', 'off');
            return $checkout_enabled === 'on';
        }
        
        return true;
    }
}
