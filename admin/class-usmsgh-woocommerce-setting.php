<?php

use UsmsAPI_WC\Migrations\MigrateSendSMSPlugin;
use UsmsAPI_WC\Migrations\MigrateWoocommercePlugin;

class Usmsgh_WooCommerce_Setting implements Usmsgh_Register_Interface {

	private $settings_api;
    private $log;

	function __construct() {
		$this->settings_api = new WeDevs_Settings_API;
        $this->log = new Usmsgh_WooCoommerce_Logger();
	}

	public function register() {
        // if ( class_exists( 'woocommerce' ) ) {
            add_action( 'admin_init', array( $this, 'admin_init' ) );
            add_action( 'admin_menu', array( $this, 'admin_menu' ) );
            add_action( 'usmsgh_setting_fields_custom_html', array( $this, 'usmsgh_wc_not_activated' ), 10, 1 );

            add_action( 'init', array( $this, 'schedule_check_domain' ) );
            add_action( 'usmsgh_check_domain', array( $this, 'check_domain_reachability' ) );

            add_filter( 'usmsgh_setting_fields', array( $this, 'add_custom_order_status' ) );

        // } else {
        //     add_action( 'admin_menu', array( $this, 'woocommerce_not_activated_menu_view' ) );
        // }
	}

	function admin_init() {

		//set the settings
		$this->settings_api->set_sections( $this->get_settings_sections() );
		$this->settings_api->set_fields( $this->get_settings_fields() );

		//initialize settings
		$this->settings_api->admin_init();
	}

	function admin_menu() {
		add_options_page( 'UsmsGH WooCommerce', 'UsmsGH-API SMS Settings', 'manage_options', 'usmsgh-woocoommerce-setting',
            array($this, 'plugin_page')
        );
	}

	function get_settings_sections() {
		$sections = array(
			array(
				'id'    => 'usmsgh_setting',
				'title' => __( 'UsmsGH-API SMS Settings', USMSGH_TEXT_DOMAIN )
			),
			array(
				'id'    => 'usmsgh_admin_setting',
				'title' => __( 'Admin Settings', USMSGH_TEXT_DOMAIN ),
                'submit_button' => class_exists("woocommerce") ? null : '',
			),
			array(
                'id'    => 'usmsgh_customer_setting',
				'title' => __( 'Customer Settings', USMSGH_TEXT_DOMAIN ),
                'submit_button' => class_exists("woocommerce") ? null : '',
			),
			array(
                'id'    => 'usmsgh_otp_setting',
				'title' => __( 'OTP Verification', USMSGH_TEXT_DOMAIN ),
                'submit_button' => class_exists("woocommerce") ? null : '',
			)
		);

		$sections = apply_filters( 'usmsgh_setting_section', $sections );

		return $sections;
	}

	/**
	 * Returns all the settings fields
	 *
	 * @return array settings fields
	 */
	function get_settings_fields() {
		//WooCommerce Country
		global $woocommerce;
        // $countries_obj = $this->get_countries();
    	// $countries_obj   = new WC_Countries();
		// $countries   = $countries_obj->__get('countries');
        $countries =  $this->get_countries();

		$additional_billing_fields       = '';
		$additional_billing_fields_desc  = '';
		$additional_billing_fields_array = $this->get_additional_billing_fields();
		foreach ( $additional_billing_fields_array as $field ) {
			$additional_billing_fields .= ', [' . $field . ']';
		}
		if ( $additional_billing_fields ) {
			$additional_billing_fields_desc = '<br />Custom tags: ' . substr( $additional_billing_fields, 2 );
		}

		$settings_fields = array(
			'usmsgh_setting' => array(
				
				array(
					'name'  => 'usmsgh_woocommerce_api_key',
					'label' => __( 'API Token', USMSGH_TEXT_DOMAIN ),
					'desc'  => __( 'Your UsmsGH-API account key. Account can be registered <a  href="https://webapp.usmsgh.com" target="blank">here</a>', USMSGH_TEXT_DOMAIN ),
					'type'  => 'text',
				),
				array(
					'name'  => 'usmsgh_woocommerce_sms_from',
					'label' => __( 'Sender ID', USMSGH_TEXT_DOMAIN ),
					'desc'  => __( 'Sender of the SMS when a message is received at a mobile phone', USMSGH_TEXT_DOMAIN ),
					'type'  => 'text',
                    'sanitize_callback' => array($this, "validate_sender_id"),
				),
				array(//Get default country v1.1.17
					'name'    		=> 'usmsgh_woocommerce_country_code',
					'label'   		=> __( 'Default country', USMSGH_TEXT_DOMAIN ),
					'class'     	=> array('chzn-drop'),
					'placeholder'	=> __( 'Select a Country', USMSGH_TEXT_DOMAIN),
					'desc'    		=> 'Selected country will be use as default country info for mobile number when country info is not provided. ',
					'type'    		=> 'select',
					'options' 		=> $countries
				),
				array(
					'name'  => 'export_usmsgh_log',
					'label' => 'Export Log',
					'desc'  => '<a href="' . admin_url( 'admin.php?page=usmsgh-download-file&file=usmsgh' ) . '" class="button button-secondary">Export</a><div id="usms_gh[keyword-modal]" class="modal"></div>',
					'type'  => 'html'
				)
			),
			'usmsgh_admin_setting'     => array(
				array(
					'name'    => 'usmsgh_woocommerce_admin_suborders_send_sms',
					'label'   => __( 'Enable Suborders SMS Notifications', USMSGH_TEXT_DOMAIN ),
					'desc'    => ' ' . __( 'Enable', USMSGH_TEXT_DOMAIN ),
					'type'    => 'checkbox',
					'default' => 'off'
				),
				array(
					'name'    => 'usmsgh_woocommerce_admin_send_sms_on',
					'label'   => __( '	Send notification on', USMSGH_TEXT_DOMAIN ),
					'desc'    => __( 'Choose when to send a status notification message to your admin <br> Set <strong>low stock threshold</strong> for each product under <strong>WooCommerce Product -> Product Data -> Inventory -> Low Stock Threshold</strong>', USMSGH_TEXT_DOMAIN ),
					'type'    => 'multicheck',
					'default' => array(
						'on-hold'    => 'on-hold',
						'processing' => 'processing'
					),
					'options' => array(
						'pending'           => ' Pending',
						'on-hold'           => ' On-hold',
						'processing'        => ' Processing',
						'completed'         => ' Completed',
						'cancelled'         => ' Cancelled',
						'refunded'          => ' Refunded',
						'failed'            => ' Failed',
						'low_stock_product' => ' Low stock product ',
					)
				),
				array(
					'name'  => 'usmsgh_woocommerce_admin_sms_recipients',
					'label' => __( 'Mobile Number', USMSGH_TEXT_DOMAIN ),
					'desc'  => __( 'Mobile number to receive new order SMS notification. To send to multiple receivers, separate each entry with comma such as 0123456789, 0167888945', USMSGH_TEXT_DOMAIN ),
					'type'  => 'text',
				),
				array(
					'name'    => 'usmsgh_woocommerce_admin_sms_template',
					'label'   => __( 'Admin SMS Message', USMSGH_TEXT_DOMAIN ),
					'desc'    => 'Customize your SMS with <button type="button" id="usms_gh[open-keywords]" data-attr-type="admin" data-attr-target="usmsgh_admin_setting[usmsgh_woocommerce_admin_sms_template]" class="button button-secondary">Keywords</button>',
					'type'    => 'textarea',
					'rows'    => '8',
					'cols'    => '500',
					'css'     => 'min-width:350px;',
					'default' => __( '[shop_name] : You have a new order with order ID [order_id] and order amount [order_currency] [order_amount]. The order is now [order_status].', USMSGH_TEXT_DOMAIN )
                ),
				array(
					'name'    => 'usmsgh_woocommerce_admin_sms_template_low_stock_product',
					'label'   => __( 'Low Stock Product Admin SMS Message', USMSGH_TEXT_DOMAIN ),
					'desc'    => 'Customize your SMS with <button type="button" id="usms_gh[open-keywords-low-product-stock]" data-attr-type="admin" data-attr-target="usmsgh_admin_setting[usmsgh_woocommerce_admin_sms_template_low_stock_product]" class="button button-secondary">Keywords</button>',
					'type'    => 'textarea',
					'rows'    => '8',
					'cols'    => '500',
					'css'     => 'min-width:350px;',
					'default' => __( '[shop_name] : Your product [product_name] has low stock. Current quantity: [product_stock_quantity]. Please restock soon.', USMSGH_TEXT_DOMAIN )
                ),
			),
			'usmsgh_customer_setting'  => array(
				array(
					'name'    => 'usmsgh_woocommerce_suborders_send_sms',
					'label'   => __( 'Enable Suborders SMS Notifications', USMSGH_TEXT_DOMAIN ),
					'desc'    => ' ' . __( 'Enable', USMSGH_TEXT_DOMAIN ),
					'type'    => 'checkbox',
					'default' => 'off'
				),
				array(
					'name'    => 'usmsgh_woocommerce_send_sms',
					'label'   => __( '	Send notification on', USMSGH_TEXT_DOMAIN ),
					'desc'    => __( 'Choose when to send a status notification message to your customer', USMSGH_TEXT_DOMAIN ),
					'type'    => 'multicheck',
                    'default' => array(
						'on-hold'    => 'on-hold',
						'processing' => 'processing',
						'completed'  => 'completed',
					),
					'options' => array(
						'pending'    => ' Pending',
						'on-hold'    => ' On-hold',
						'processing' => ' Processing',
						'completed'  => ' Completed',
						'cancelled'  => ' Cancelled',
						'refunded'   => ' Refunded',
						'failed'     => ' Failed'
					)
				),
				array(
					'name'    => 'usmsgh_woocommerce_sms_template_default',
					'label'   => __( 'Default Customer SMS Message', USMSGH_TEXT_DOMAIN ),
					'desc'    => 'Customize your SMS with <button type="button" id="usms_gh[open-keywords]" data-attr-type="default" data-attr-target="usmsgh_customer_setting[usmsgh_woocommerce_sms_template_default]" class="button button-secondary">Keywords</button>',
					'type'    => 'textarea',
					'rows'    => '8',
					'cols'    => '500',
					'css'     => 'min-width:350px;',
					'default' => __( '[shop_name] : Thank you for purchasing. Your order ([order_id]) is now [order_status].', USMSGH_TEXT_DOMAIN )
				),
				array(
					'name'    => 'usmsgh_woocommerce_sms_template_pending',
					'label'   => __( 'Pending SMS Message', USMSGH_TEXT_DOMAIN ),
					'desc'    => 'Customize your SMS with <button type="button" id="usms_gh[open-keywords]" data-attr-type="pending" data-attr-target="usmsgh_customer_setting[usmsgh_woocommerce_sms_template_pending]" class="button button-secondary">Keywords</button>',
					'type'    => 'textarea',
					'rows'    => '8',
					'cols'    => '500',
					'css'     => 'min-width:350px;',
					'default' => __( '[shop_name] : Thank you for purchasing. Your order ([order_id]) is now [order_status].', USMSGH_TEXT_DOMAIN )
				),
				array(
					'name'    => 'usmsgh_woocommerce_sms_template_on-hold',
					'label'   => __( 'On-hold SMS Message', USMSGH_TEXT_DOMAIN ),
					'desc'    => 'Customize your SMS with <button type="button" id="usms_gh[open-keywords]" data-attr-type="on_hold" data-attr-target="usmsgh_customer_setting[usmsgh_woocommerce_sms_template_on-hold]" class="button button-secondary">Keywords</button>',
					'type'    => 'textarea',
					'rows'    => '8',
					'cols'    => '500',
					'css'     => 'min-width:350px;',
					'default' => __( '[shop_name] : Thank you for purchasing. Your order ([order_id]) is now [order_status].', USMSGH_TEXT_DOMAIN )
				),
				array(
					'name'    => 'usmsgh_woocommerce_sms_template_processing',
					'label'   => __( 'Processing SMS Message', USMSGH_TEXT_DOMAIN ),
					'desc'    => 'Customize your SMS with <button type="button" id="usms_gh[open-keywords]" data-attr-type="processing" data-attr-target="usmsgh_customer_setting[usmsgh_woocommerce_sms_template_processing]" class="button button-secondary">Keywords</button>',
					'type'    => 'textarea',
					'rows'    => '8',
					'cols'    => '500',
					'css'     => 'min-width:350px;',
					'default' => __( '[shop_name] : Thank you for purchasing. Your order ([order_id]) is now [order_status].', USMSGH_TEXT_DOMAIN )
				),
				array(
					'name'    => 'usmsgh_woocommerce_sms_template_completed',
					'label'   => __( 'Completed SMS Message', USMSGH_TEXT_DOMAIN ),
					'desc'    => 'Customize your SMS with <button type="button" id="usms_gh[open-keywords]" data-attr-type="completed" data-attr-target="usmsgh_customer_setting[usmsgh_woocommerce_sms_template_completed]" class="button button-secondary">Keywords</button>',
					'type'    => 'textarea',
					'rows'    => '8',
					'cols'    => '500',
					'css'     => 'min-width:350px;',
					'default' => __( '[shop_name] : Thank you for purchasing. Your order ([order_id]) is now [order_status].', USMSGH_TEXT_DOMAIN )
				),
				array(
					'name'    => 'usmsgh_woocommerce_sms_template_cancelled',
					'label'   => __( 'Cancelled SMS Message', USMSGH_TEXT_DOMAIN ),
					'desc'    => 'Customize your SMS with <button type="button" id="usms_gh[open-keywords]" data-attr-type="cancelled" data-attr-target="usmsgh_customer_setting[usmsgh_woocommerce_sms_template_cancelled]" class="button button-secondary">Keywords</button>',
					'type'    => 'textarea',
					'rows'    => '8',
					'cols'    => '500',
					'css'     => 'min-width:350px;',
					'default' => __( '[shop_name] : Thank you for purchasing. Your order ([order_id]) is now [order_status].', USMSGH_TEXT_DOMAIN )
				),
				array(
					'name'    => 'usmsgh_woocommerce_sms_template_refunded',
					'label'   => __( 'Refunded SMS Message', USMSGH_TEXT_DOMAIN ),
					'desc'    => 'Customize your SMS with <button type="button" id="usms_gh[open-keywords]" data-attr-type="refunded" data-attr-target="usmsgh_customer_setting[usmsgh_woocommerce_sms_template_refunded]" class="button button-secondary">Keywords</button>',
					'type'    => 'textarea',
					'rows'    => '8',
					'cols'    => '500',
					'css'     => 'min-width:350px;',
					'default' => __( '[shop_name] : Thank you for purchasing. Your order ([order_id]) is now [order_status].', USMSGH_TEXT_DOMAIN )
				),
				array(
					'name'    => 'usmsgh_woocommerce_sms_template_failed',
					'label'   => __( 'Failed SMS Message', USMSGH_TEXT_DOMAIN ),
					'desc'    => 'Customize your SMS with <button type="button" id="usms_gh[open-keywords]" data-attr-type="failed" data-attr-target="usmsgh_customer_setting[usmsgh_woocommerce_sms_template_failed]" class="button button-secondary">Keywords</button>',
					'type'    => 'textarea',
					'rows'    => '8',
					'cols'    => '500',
					'css'     => 'min-width:350px;',
					'default' => __( '[shop_name] : Thank you for purchasing. Your order ([order_id]) is now [order_status].', USMSGH_TEXT_DOMAIN )
				)
			),
			'usmsgh_otp_setting' => array(
				array(
					'name'    => 'usmsgh_woocommerce_enable_otp',
					'label'   => __( 'Enable OTP Verification', USMSGH_TEXT_DOMAIN ),
					'desc'    => ' ' . __( 'Enable SMS OTP verification for login and checkout', USMSGH_TEXT_DOMAIN ),
					'type'    => 'checkbox',
					'default' => 'off'
				),
				array(
					'name'    => 'usmsgh_woocommerce_enable_otp_login',
					'label'   => __( 'Enable OTP for Login', USMSGH_TEXT_DOMAIN ),
					'desc'    => ' ' . __( 'Require OTP verification for customer login', USMSGH_TEXT_DOMAIN ),
					'type'    => 'checkbox',
					'default' => 'off'
				),
				array(
					'name'    => 'usmsgh_woocommerce_enable_otp_checkout',
					'label'   => __( 'Enable OTP for Checkout', USMSGH_TEXT_DOMAIN ),
					'desc'    => ' ' . __( 'Require OTP verification before order placement', USMSGH_TEXT_DOMAIN ),
					'type'    => 'checkbox',
					'default' => 'off'
				),
				array(
					'name'    => 'usmsgh_woocommerce_otp_length',
					'label'   => __( 'OTP Length', USMSGH_TEXT_DOMAIN ),
					'desc'    => __( 'Number of digits in the OTP code (default: 6)', USMSGH_TEXT_DOMAIN ),
					'type'    => 'number',
					'default' => '6',
					'min'     => '4',
					'max'     => '8'
				),
				array(
					'name'    => 'usmsgh_woocommerce_otp_expiry',
					'label'   => __( 'OTP Expiry Time', USMSGH_TEXT_DOMAIN ),
					'desc'    => __( 'OTP validity in minutes (default: 10)', USMSGH_TEXT_DOMAIN ),
					'type'    => 'number',
					'default' => '10',
					'min'     => '1',
					'max'     => '60'
				),
				array(
					'name'    => 'usmsgh_woocommerce_otp_message_template',
					'label'   => __( 'OTP SMS Template', USMSGH_TEXT_DOMAIN ),
					'desc'    => __( 'Use [otp_code] for the OTP code, [shop_name] for store name', USMSGH_TEXT_DOMAIN ),
					'type'    => 'textarea',
					'rows'    => '4',
					'cols'    => '500',
					'css'     => 'min-width:350px;',
					'default' => __( '[shop_name]: Your verification code is [otp_code]. Valid for [expiry] minutes.', USMSGH_TEXT_DOMAIN )
				)
			)
		);

        if(!class_exists('woocommerce')) {
            unset($settings_fields['usmsgh_admin_setting']);
            unset($settings_fields['usmsgh_customer_setting']);
            unset($settings_fields['usmsgh_otp_setting']);
        }

		$settings_fields = apply_filters( 'usmsgh_setting_fields', $settings_fields );

		return $settings_fields;
	}

    public function add_custom_order_status($setting_fields)
    {
        $log = new Usmsgh_WooCoommerce_Logger();
        // $log->add("Usms", print_r($custom_wc_statuses, 1));
        $default_statuses = [
            'wc-pending',
            'wc-processing',
            'wc-on-hold',
            'wc-completed',
            'wc-cancelled',
            'wc-refunded',
            'wc-failed',
            'wc-checkout-draft'
        ];

        $fields_to_iterate = ['usmsgh_admin_setting', 'usmsgh_customer_setting', 'usmsgh_multivendor_setting'];

        $all_wc_statuses = function_exists("wc_get_order_statuses") ? wc_get_order_statuses() : [];

        $custom_wc_statuses = array_diff_key($all_wc_statuses, array_flip($default_statuses));

        $processed_wc_statuses = [];

        foreach($custom_wc_statuses as $key => $value) {
            $trimmed_key = ltrim($key, 'wc-');
            $processed_wc_statuses[$trimmed_key] = $value;
        }

        foreach($fields_to_iterate as $field) {
            if(array_key_exists($field, $setting_fields)) {
                for( $i=0; $i<count($setting_fields[$field]); $i++ ) {
                    if(array_key_exists('options', $setting_fields[$field][$i])) {
                        foreach($processed_wc_statuses as $processed_key => $processed_value) {
                            if( ! array_key_exists($processed_key, $setting_fields[$field][$i]['options']) ) {
                                $setting_fields[$field][$i]['options'][$processed_key] = " {$processed_value}";
                                if($field == 'usmsgh_customer_setting') {
                                    $setting_fields[$field][] = array(
                                        'name'    => "usmsgh_woocommerce_sms_template_{$processed_key}",
                                        'label'   => __( "{$processed_value} Customer SMS Message", USMSGH_TEXT_DOMAIN ),
                                        'desc'    => sprintf('Customize your SMS with <button type="button" id="usms_gh[open-keywords]" data-attr-type="default" data-attr-target="usmsgh_customer_setting[usmsgh_woocommerce_sms_template_%s]" class="button button-secondary">Keywords</button>', $processed_key),
                                        'type'    => 'textarea',
                                        'rows'    => '8',
                                        'cols'    => '500',
                                        'css'     => 'min-width:350px;',
                                        'default' => __( "Your {$processed_value} SMS template", USMSGH_TEXT_DOMAIN )
                                    );
                                }
                            }
                        }
                        break;
                    }
                }

                continue;
            }
        }

        return $setting_fields;
    }

	function plugin_page() {

		$this->settings_api->show_navigation();
		$this->settings_api->show_forms();
		echo '<input type="hidden" value="' . join(",", $this->get_additional_billing_fields()) . '" id="usmsgh_new_billing_field" />';

		echo '</div>';

        if(usms_fs()->is_tracking_allowed()) {
            ?>
                <!-- Yandex.Metrika counter -->
                <script type="text/javascript" >
                    (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
                    m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
                    (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

                    ym(88073519, "init", {
                            clickmap:true,
                            trackLinks:true,
                            accurateTrackBounce:true,
                            webvisor:true
                    });
                </script>
                <noscript><div><img src="https://mc.yandex.ru/watch/88073519" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
                <!-- /Yandex.Metrika counter -->

            <?php
        }
	}

	/**
	 * Get all the pages
	 *
	 * @return array page names with key value pairs
	 */
	function get_pages() {
		$pages         = get_pages();
		$pages_options = array();
		if ( $pages ) {
			foreach ( $pages as $page ) {
				$pages_options[ $page->ID ] = $page->post_title;
			}
		}

		return $pages_options;
	}

    public function check_domain_reachability()
    {
        try {
            $this->log->add("UsmsGH", "Running scheduled checking domain task.");
            $response_code = wp_remote_retrieve_response_code( wp_remote_get("'https://webapp.usmsgh.com/api/balance'") );
            // successfully reached our domain
            if($response_code === 400) {
                update_option("usmsgh_domain_reachable", true);
                $this->log->add("UsmsGH", "Domain is reachable. Will be using domain.");
            }
            else {
                $this->log->add("UsmsGH", "Exception thrown. Domain not reachable.");
                throw new Exception("Domain not reachable.");
            }
        } catch (Exception $e) {
            $this->log->add("UsmsGH", "Domain not reachable. Using IP address");
            $this->log->add("UsmsGH", "err msg: {$e->getMessage()}");
            update_option("usmsgh_domain_reachable", false);
        }
    }

    public function schedule_check_domain()
    {
        $latest_plugin_version = get_plugin_data(USMSGH_PLUGIN_DIR . "usmsgh-woocommerce.php")['Version'];
        $current_plugin_version = get_option("usmsgh_plugin_version");

        if(!empty($current_plugin_version)) {
            // if cur < lat = -1
            // if cur === lat = 0
            // if cur > lat = 1
            if(version_compare( $current_plugin_version, $latest_plugin_version ) < 0) {
                $this->log->add("UsmsGH", "current plugin version: {$current_plugin_version}.");
                $this->log->add("UsmsGH", "latest plugin version: {$latest_plugin_version}.");
                as_unschedule_all_actions("usmsgh_check_domain");
                $this->log->add("UsmsGH", "Successfully unscheduled domain reachability for initialization.");
                update_option("usmsgh_plugin_version", $latest_plugin_version);
            }
        } else {
            update_option("usmsgh_plugin_version", '1.3.0');
            $this->schedule_check_domain();
        }
        if ( false === as_has_scheduled_action( 'usmsgh_check_domain' ) ) {
            as_schedule_recurring_action( strtotime( 'now' ), DAY_IN_SECONDS, 'usmsgh_check_domain' );
        }
    }

    public function display_account_balance()
    {
        $log = new Usmsgh_WooCoommerce_Logger();
        try {
            $api_key = usmsgh_get_options("usmsgh_woocommerce_api_key", "usmsgh_setting");
            $api_secret = usmsgh_get_options("usmsgh_woocommerce_api_secret", "usmsgh_setting");

            $usmsgh_rest = new UsmsGH($api_key, $api_secret);
            $rest_response = $usmsgh_rest->accountBalance();

            $rest_response = json_decode($rest_response);

            if($rest_response->{'status'} == 0){
                $acc_balance = $rest_response->{'value'};
            } else {
                $acc_balance = "Invalid API Credentials";
            }

        } catch (Exception $e) {
            $log->add("UsmsGH", print_r($e->getMessage(), 1));
            $acc_balance = 'Failed to retrieve balance';
        }

        ?>
            <p><?php echo esc_html($acc_balance); ?></p>
        <?php
    }

	function get_additional_billing_fields() {
		$default_billing_fields   = array(
			'billing_first_name',
			'billing_last_name',
			'billing_company',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_country',
			'billing_postcode',
			'billing_phone',
			'billing_email'
		);
		$additional_billing_field = array();
		$billing_fields           = array_filter( get_option( 'wc_fields_billing', array() ) );
		foreach ( $billing_fields as $field_key => $field_info ) {
			if ( ! in_array( $field_key, $default_billing_fields ) && $field_info['enabled'] ) {
				array_push( $additional_billing_field, $field_key );
			}
		}

		return $additional_billing_field;
	}

    public function usmsgh_wc_not_activated($form_id)
    {
        if(class_exists('woocommerce')) { return; }
        if(!($form_id === 'usmsgh_admin_setting' || $form_id === 'usmsgh_customer_setting')) { return; }
        ?>
        <div class="wrap">
            <h1>UsmsGH-API Woocommerce Order Notification</h1>
            <p>This feature requires WooCommerce to be activated</p>
        </div>
        <?php
    }

    public function get_countries()
    {
        return array(
            "AF" => "Afghanistan",
            "AL" => "Albania",
            "DZ" => "Algeria",
            "AS" => "American Samoa",
            "AD" => "Andorra",
            "AO" => "Angola",
            "AI" => "Anguilla",
            "AQ" => "Antarctica",
            "AG" => "Antigua and Barbuda",
            "AR" => "Argentina",
            "AM" => "Armenia",
            "AW" => "Aruba",
            "AU" => "Australia",
            "AT" => "Austria",
            "AZ" => "Azerbaijan",
            "BS" => "Bahamas",
            "BH" => "Bahrain",
            "BD" => "Bangladesh",
            "BB" => "Barbados",
            "BY" => "Belarus",
            "BE" => "Belgium",
            "BZ" => "Belize",
            "BJ" => "Benin",
            "BM" => "Bermuda",
            "BT" => "Bhutan",
            "BO" => "Bolivia",
            "BA" => "Bosnia and Herzegovina",
            "BW" => "Botswana",
            "BV" => "Bouvet Island",
            "BR" => "Brazil",
            "IO" => "British Indian Ocean Territory",
            "BN" => "Brunei Darussalam",
            "BG" => "Bulgaria",
            "BF" => "Burkina Faso",
            "BI" => "Burundi",
            "KH" => "Cambodia",
            "CM" => "Cameroon",
            "CA" => "Canada",
            "CV" => "Cape Verde",
            "KY" => "Cayman Islands",
            "CF" => "Central African Republic",
            "TD" => "Chad",
            "CL" => "Chile",
            "CN" => "China",
            "CX" => "Christmas Island",
            "CC" => "Cocos (Keeling) Islands",
            "CO" => "Colombia",
            "KM" => "Comoros",
            "CG" => "Congo",
            "CD" => "Congo, the Democratic Republic of the",
            "CK" => "Cook Islands",
            "CR" => "Costa Rica",
            "CI" => "Cote D'Ivoire",
            "HR" => "Croatia",
            "CU" => "Cuba",
            "CY" => "Cyprus",
            "CZ" => "Czech Republic",
            "DK" => "Denmark",
            "DJ" => "Djibouti",
            "DM" => "Dominica",
            "DO" => "Dominican Republic",
            "EC" => "Ecuador",
            "EG" => "Egypt",
            "SV" => "El Salvador",
            "GQ" => "Equatorial Guinea",
            "ER" => "Eritrea",
            "EE" => "Estonia",
            "ET" => "Ethiopia",
            "FK" => "Falkland Islands (Malvinas)",
            "FO" => "Faroe Islands",
            "FJ" => "Fiji",
            "FI" => "Finland",
            "FR" => "France",
            "GF" => "French Guiana",
            "PF" => "French Polynesia",
            "TF" => "French Southern Territories",
            "GA" => "Gabon",
            "GM" => "Gambia",
            "GE" => "Georgia",
            "DE" => "Germany",
            "GH" => "Ghana",
            "GI" => "Gibraltar",
            "GR" => "Greece",
            "GL" => "Greenland",
            "GD" => "Grenada",
            "GP" => "Guadeloupe",
            "GU" => "Guam",
            "GT" => "Guatemala",
            "GN" => "Guinea",
            "GW" => "Guinea-Bissau",
            "GY" => "Guyana",
            "HT" => "Haiti",
            "HM" => "Heard Island and Mcdonald Islands",
            "VA" => "Holy See (Vatican City State)",
            "HN" => "Honduras",
            "HK" => "Hong Kong",
            "HU" => "Hungary",
            "IS" => "Iceland",
            "IN" => "India",
            "ID" => "Indonesia",
            "IR" => "Iran, Islamic Republic of",
            "IQ" => "Iraq",
            "IE" => "Ireland",
            "IL" => "Israel",
            "IT" => "Italy",
            "JM" => "Jamaica",
            "JP" => "Japan",
            "JO" => "Jordan",
            "KZ" => "Kazakhstan",
            "KE" => "Kenya",
            "KI" => "Kiribati",
            "KP" => "Korea, Democratic People's Republic of",
            "KR" => "Korea, Republic of",
            "KW" => "Kuwait",
            "KG" => "Kyrgyzstan",
            "LA" => "Lao People's Democratic Republic",
            "LV" => "Latvia",
            "LB" => "Lebanon",
            "LS" => "Lesotho",
            "LR" => "Liberia",
            "LY" => "Libyan Arab Jamahiriya",
            "LI" => "Liechtenstein",
            "LT" => "Lithuania",
            "LU" => "Luxembourg",
            "MO" => "Macao",
            "MK" => "Macedonia, the Former Yugoslav Republic of",
            "MG" => "Madagascar",
            "MW" => "Malawi",
            "MY" => "Malaysia",
            "MV" => "Maldives",
            "ML" => "Mali",
            "MT" => "Malta",
            "MH" => "Marshall Islands",
            "MQ" => "Martinique",
            "MR" => "Mauritania",
            "MU" => "Mauritius",
            "YT" => "Mayotte",
            "MX" => "Mexico",
            "FM" => "Micronesia, Federated States of",
            "MD" => "Moldova, Republic of",
            "MC" => "Monaco",
            "MN" => "Mongolia",
            "MS" => "Montserrat",
            "MA" => "Morocco",
            "MZ" => "Mozambique",
            "MM" => "Myanmar",
            "NA" => "Namibia",
            "NR" => "Nauru",
            "NP" => "Nepal",
            "NL" => "Netherlands",
            "AN" => "Netherlands Antilles",
            "NC" => "New Caledonia",
            "NZ" => "New Zealand",
            "NI" => "Nicaragua",
            "NE" => "Niger",
            "NG" => "Nigeria",
            "NU" => "Niue",
            "NF" => "Norfolk Island",
            "MP" => "Northern Mariana Islands",
            "NO" => "Norway",
            "OM" => "Oman",
            "PK" => "Pakistan",
            "PW" => "Palau",
            "PS" => "Palestinian Territory, Occupied",
            "PA" => "Panama",
            "PG" => "Papua New Guinea",
            "PY" => "Paraguay",
            "PE" => "Peru",
            "PH" => "Philippines",
            "PN" => "Pitcairn",
            "PL" => "Poland",
            "PT" => "Portugal",
            "PR" => "Puerto Rico",
            "QA" => "Qatar",
            "RE" => "Reunion",
            "RO" => "Romania",
            "RU" => "Russian Federation",
            "RW" => "Rwanda",
            "SH" => "Saint Helena",
            "KN" => "Saint Kitts and Nevis",
            "LC" => "Saint Lucia",
            "PM" => "Saint Pierre and Miquelon",
            "VC" => "Saint Vincent and the Grenadines",
            "WS" => "Samoa",
            "SM" => "San Marino",
            "ST" => "Sao Tome and Principe",
            "SA" => "Saudi Arabia",
            "SN" => "Senegal",
            "CS" => "Serbia and Montenegro",
            "SC" => "Seychelles",
            "SL" => "Sierra Leone",
            "SG" => "Singapore",
            "SK" => "Slovakia",
            "SI" => "Slovenia",
            "SB" => "Solomon Islands",
            "SO" => "Somalia",
            "ZA" => "South Africa",
            "GS" => "South Georgia and the South Sandwich Islands",
            "ES" => "Spain",
            "LK" => "Sri Lanka",
            "SD" => "Sudan",
            "SR" => "Suriname",
            "SJ" => "Svalbard and Jan Mayen",
            "SZ" => "Swaziland",
            "SE" => "Sweden",
            "CH" => "Switzerland",
            "SY" => "Syrian Arab Republic",
            "TW" => "Taiwan, Province of China",
            "TJ" => "Tajikistan",
            "TZ" => "Tanzania, United Republic of",
            "TH" => "Thailand",
            "TL" => "Timor-Leste",
            "TG" => "Togo",
            "TK" => "Tokelau",
            "TO" => "Tonga",
            "TT" => "Trinidad and Tobago",
            "TN" => "Tunisia",
            "TR" => "Turkey",
            "TM" => "Turkmenistan",
            "TC" => "Turks and Caicos Islands",
            "TV" => "Tuvalu",
            "UG" => "Uganda",
            "UA" => "Ukraine",
            "AE" => "United Arab Emirates",
            "GB" => "United Kingdom",
            "US" => "United States",
            "UM" => "United States Minor Outlying Islands",
            "UY" => "Uruguay",
            "UZ" => "Uzbekistan",
            "VU" => "Vanuatu",
            "VE" => "Venezuela",
            "VN" => "Viet Nam",
            "VG" => "Virgin Islands, British",
            "VI" => "Virgin Islands, U.s.",
            "WF" => "Wallis and Futuna",
            "EH" => "Western Sahara",
            "YE" => "Yemen",
            "ZM" => "Zambia",
            "ZW" => "Zimbabwe"
        );
    }
}

?>
