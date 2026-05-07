<?php

class UsmsJetpackCRM implements Usmsgh_PluginInterface, Usmsgh_Register_Interface {
    /*
    Plugin Name: Jetpack CRM – Clients, Invoices, Leads, & Billing for WordPress
    Plugin Link: https://wordpress.org/plugins/zero-bs-crm/
    */

    public static $plugin_identifier = 'jetpack-crm';
    private $plugin_name;
    private $plugin_medium;
    private $log;
    private $option_id;

    public function __construct() {
        $this->log = new Usmsgh_WooCoommerce_Logger();
        $this->option_id = "usmsgh_{$this::$plugin_identifier}";
        $this->plugin_name = 'Jetpack CRM';
        $this->plugin_medium = 'wp_' . str_replace( ' ', '_', strtolower($this->plugin_name));
    }

    public static function plugin_activated()
    {
        $log = new Usmsgh_WooCoommerce_Logger();
        if( ! is_plugin_active('zero-bs-crm/ZeroBSCRM.php')) { return false; }
        return true;
    }

    public function register()
    {
        add_action( 'zbs_new_customer', array( $this, 'send_sms_on'), 10, 1);
    }

    public function get_option_id()
    {
        return $this->option_id;
    }

    public function get_setting_section_data()
    {
        return array(
            'id'    => $this->get_option_id(),
            'title' => __( $this->plugin_name, USMSGH_TEXT_DOMAIN ),
        );
    }

    public function get_setting_field_data()
    {
        $setting_fields = array(
			$this->get_enable_notification_fields(),
			$this->get_send_from_fields(),
			$this->get_send_on_fields(),
		);
        foreach($this->get_sms_template_fields() as $sms_templates) {
            $setting_fields[] = $sms_templates;
        }
        return $setting_fields;
    }

    public function get_plugin_settings($with_identifier = false)
    {
        $settings = array(
            "usmsgh_automation_enable_notification"      => usmsgh_get_options("usmsgh_automation_enable_notification", $this->get_option_id()),
            "usmsgh_send_from"                           => usmsgh_get_options('usmsgh_automation_send_from', $this->get_option_id()),
            "usmsgh_automation_send_on"                  => usmsgh_get_options("usmsgh_automation_send_on", $this->get_option_id()),
            "usmsgh_automation_sms_template_customer"    => usmsgh_get_options("usmsgh_automation_sms_template_customer", $this->get_option_id()),
            "usmsgh_automation_sms_template_lead"        => usmsgh_get_options("usmsgh_automation_sms_template_lead", $this->get_option_id()),
            "usmsgh_automation_sms_template_refused"     => usmsgh_get_options("usmsgh_automation_sms_template_refused", $this->get_option_id()),
            "usmsgh_automation_sms_template_blacklisted" => usmsgh_get_options("usmsgh_automation_sms_template_blacklisted", $this->get_option_id()),
        );

        if ($with_identifier) {
            return array(
                self::$plugin_identifier => $settings,
            );
        }

        return $settings;
    }

    private function get_enable_notification_fields() {
        return array(
            'name'    => 'usmsgh_automation_enable_notification',
            'label'   => __( 'Enable SMS notifications', USMSGH_TEXT_DOMAIN ),
            'desc'    => ' ' . __( 'Enable', USMSGH_TEXT_DOMAIN ),
            'type'    => 'checkbox',
            'default' => 'off'
        );
    }

    private function get_send_from_fields() {
        return array(
            'name'  => 'usmsgh_automation_send_from',
            'label' => __( 'Send from', USMSGH_TEXT_DOMAIN ),
            'desc'  => __( 'Sender of the SMS when a message is received at a mobile phone', USMSGH_TEXT_DOMAIN ),
            'type'  => 'text',
        );
    }

    private function get_send_on_fields() {
        return array(
            'name'    => 'usmsgh_automation_send_on',
            'label'   => __( 'Send notification on', USMSGH_TEXT_DOMAIN ),
            'desc'    => __( 'Choose when to send a SMS notification message to your new contact', USMSGH_TEXT_DOMAIN ),
            'type'    => 'multicheck',
            'options' => array(
                'customer'    => 'New Customer',
                'lead'        => 'New Lead',
                'refused'     => 'New customer refused',
                'blacklisted' => 'New customer blacklisted',
            )
        );
    }

    private function get_sms_template_fields() {
        return array(
            array(
                'name'    => 'usmsgh_automation_sms_template_customer',
                'label'   => __( 'Customer status SMS message', USMSGH_TEXT_DOMAIN ),
                'desc'    => sprintf('Customize your SMS with <button type="button" id="usmsgh-open-keyword-%1$s-[dummy]" data-attr-type="customer" data-attr-target="%1$s[usmsgh_automation_sms_template_customer]" class="button button-secondary">Keywords</button>', $this->get_option_id() ),
                'type'    => 'textarea',
                'rows'    => '8',
                'cols'    => '500',
                'css'     => 'min-width:350px;',
                'default' => __( 'Hi [first_name], we would like to personally thank you for using our services.', USMSGH_TEXT_DOMAIN )
            ),
            array(
                'name'    => 'usmsgh_automation_sms_template_lead',
                'label'   => __( 'Lead status SMS message', USMSGH_TEXT_DOMAIN ),
                'desc'    => sprintf('Customize your SMS with <button type="button" id="usmsgh-open-keyword-%1$s-[dummy]" data-attr-type="lead" data-attr-target="%1$s[usmsgh_automation_sms_template_lead]" class="button button-secondary">Keywords</button>', $this->get_option_id() ),
                'type'    => 'textarea',
                'rows'    => '8',
                'cols'    => '500',
                'css'     => 'min-width:350px;',
                'default' => __( 'Hi [first_name], thank you for showing interest in our services. Our sales representative will contact you shortly.', USMSGH_TEXT_DOMAIN )
            ),
            array(
                'name'    => 'usmsgh_automation_sms_template_refused',
                'label'   => __( 'Contact refused status SMS message', USMSGH_TEXT_DOMAIN ),
                'desc'    => sprintf('Customize your SMS with <button type="button" id="usmsgh-open-keyword-%1$s-[dummy]" data-attr-type="refused" data-attr-target="%1$s[usmsgh_automation_sms_template_refused]" class="button button-secondary">Keywords</button>', $this->get_option_id() ),
                'type'    => 'textarea',
                'rows'    => '8',
                'cols'    => '500',
                'css'     => 'min-width:350px;',
                'default' => __( 'Hi [first_name], we sincerely apologise for not meeting your expectations. We promise to do better.', USMSGH_TEXT_DOMAIN )
            ),
            array(
                'name'    => 'usmsgh_automation_sms_template_blacklisted',
                'label'   => __( 'Contact blacklisted SMS message', USMSGH_TEXT_DOMAIN ),
                'desc'    => sprintf('Customize your SMS with <button type="button" id="usmsgh-open-keyword-%1$s-[dummy]" data-attr-type="blacklisted" data-attr-target="%1$s[usmsgh_automation_sms_template_blacklisted]" class="button button-secondary">Keywords</button>', $this->get_option_id() ),
                'type'    => 'textarea',
                'rows'    => '8',
                'cols'    => '500',
                'css'     => 'min-width:350px;',
                'default' => __( 'Hi [first_name], thank you for your interest all these time, however we will need to terminate your access from our services.', USMSGH_TEXT_DOMAIN )
            ),
        );
    }

    public function get_keywords_field()
    {
        return array(
            'customer' => array(
                'id',
                'status',
                'email',
                'prefix',
                'first_name',
                'last_name',
                'full_name',
                'address_1',
                'address_2',
                'city',
                'state',
                'country',
                'postcode',
                'sec_address_1',
                'sec_address_2',
                'sec_city',
                'sec_state',
                'sec_country',
                'sec_postcode',
                'home_tel',
                'work_tel',
                'mobile_tel',
                'twitter_link',
                'linkedin_link',
                'facebook_link',
            ),
        );

    }

    public function send_sms_on($cID)
    {
        $plugin_settings = $this->get_plugin_settings();
        $enable_notifications = $plugin_settings['usmsgh_automation_enable_notification'];
        $send_on = $plugin_settings['usmsgh_automation_send_on'];

        $cust = zeroBS_getCustomer($cID, true, true, true);

        $this->log->add("UsmsGH", "cust_id: {$cust['id']}");

        $status = strtolower($cust['status']);

        if($enable_notifications === "on") {
            $this->log->add("UsmsGH", "enable notifications: on");
            if(!empty($send_on) && is_array($send_on)) {
                if(array_key_exists($status, $send_on)) {
                    $this->log->add("UsmsGH", "enable {$status} notifications: on");
                    $this->send_customer_notification($cust, $status);
                }
            }
        }

        return false;
    }

    public function send_customer_notification($cust, $status)
    {
        $this->log->add("UsmsGH", "send_customer_notification status: {$status}");
        $settings = $this->get_plugin_settings();
        $sms_from = $settings['usmsgh_automation_send_from'];

        // get number from args
        $phone_no = $cust['mobtel'];
        if( !ctype_digit($phone_no) ) {
            $this->log->add("UsmsGH", "phone_no is not a digit: {$phone_no}. Aborting...");
            return;
        }
        if( !empty($cust['country']) ) {
            $usms_setting = new Usmsgh_WooCommerce_Setting();
            $countries = $usms_setting->get_countries();

            $country = array_search($cust['country'], $countries);

            $phone_no = UsmsGH_SendSMS_Sms::get_formatted_number($cust['mobtel'], $country);
        } else {
            $phone_no = $cust['mobtel'];
        }

        $this->log->add("UsmsGH", "phone_no: {$phone_no}");

        // get message template from status
        $msg_template = $settings["usmsgh_automation_sms_template_{$status}"];
        $message = $this->replace_keywords_with_value($cust, $msg_template);

        UsmsGH_SendSMS_Sms::send_sms($sms_from, $phone_no, $message, $this->plugin_medium);
    }

    /*
        returns the message with keywords replaced to original value it points to
        eg: [name] => 'customer name here'
    */
    protected function replace_keywords_with_value($cust, $message)
    {
        // use regex to match all [stuff_inside]
        // return the message
        // preg_match_all('/\[(.*?)\]/', $message, $keywords);
        $usms_setting = new Usmsgh_WooCommerce_Setting();

        $keywords = array(
            '[id]'             => !empty($cust['id'])               ? $cust['id'] : '',
            '[status]'         => !empty($cust['status'])           ? $cust['status'] : '',
            '[email]'          => !empty($cust['email'])            ? $cust['email'] : '',
            '[prefix]'         => !empty($cust['prefix'])           ? $cust['prefix'] : '',
            '[first_name]'     => !empty($cust['fname'])            ? $cust['fname'] : '',
            '[last_name]'      => !empty($cust['lname'])            ? $cust['lname'] : '',
            '[full_name]'      => !empty($cust['fullname'])         ? $cust['fullname'] : '',
            '[address_1]'      => !empty($cust['addr1'])            ? $cust['addr1'] : '',
            '[address_2]'      => !empty($cust['addr2'])            ? $cust['addr2'] : '',
            '[city]'           => !empty($cust['city'])             ? $cust['city'] : '',
            '[state]'          => !empty($cust['county'])           ? $cust['county'] : '',
            '[country]'        => !empty($cust['country'])          ? $cust['country'] : '',
            '[postcode]'       => !empty($cust['postcode'])         ? $cust['postcode'] : '',
            '[sec_address_1]'  => !empty($cust['secaddr_addr1'])    ? $cust['secaddr_addr1'] : '',
            '[sec_address_2]'  => !empty($cust['secaddr_addr2'])    ? $cust['secaddr_addr2'] : '',
            '[sec_city]'       => !empty($cust['secaddr_city'])     ? $cust['secaddr_city'] : '',
            '[sec_state]'      => !empty($cust['secaddr_county'])   ? $cust['secaddr_county'] : '',
            '[sec_country]'    => !empty($cust['secaddr_country'])  ? $cust['secaddr_country'] : '',
            '[sec_postcode]'   => !empty($cust['secaddr_postcode']) ? $cust['secaddr_postcode'] : '',
            '[home_tel]'       => !empty($cust['hometel'])          ? $cust['hometel'] : '',
            '[work_tel]'       => !empty($cust['worktel'])          ? $cust['worktel'] : '',
            '[mobile_tel]'     => !empty($cust['mobtel'])           ? $cust['mobtel'] : '',
            '[twitter_link]'   => !empty($cust['tw'])               ? $cust['tw'] : '',
            '[linkedin_link]'  => !empty($cust['li'])               ? $cust['li'] : '',
            '[facebook_link]'  => !empty($cust['fb'])               ? $cust['fb'] : '',
        );

        return str_replace(array_keys($keywords), array_values($keywords), $message);

    }
}
