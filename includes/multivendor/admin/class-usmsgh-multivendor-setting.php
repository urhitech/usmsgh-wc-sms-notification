<?php

class Usmsgh_Multivendor_Setting implements Usmsgh_Register_Interface {
	public function register() {
		add_filter( 'usmsgh_setting_section', array( $this, 'set_multivendor_setting_section' ) );
		add_filter( 'usmsgh_setting_fields', array( $this, 'set_multivendor_setting_field' ) );
        add_action( 'usmsgh_setting_fields_custom_html', array( $this, 'usmsgh_wc_not_activated' ), 10, 1 );

        add_filter( 'usmsgh_setting_fields', array( new Usmsgh_WooCommerce_Setting(), 'add_custom_order_status' ) );
	}

	public function set_multivendor_setting_section( $sections ) {
		$sections[] = array(
			'id'    => 'usmsgh_multivendor_setting',
			'title' => __( 'Multivendor Settings', USMSGH_TEXT_DOMAIN ),
            'submit_button' => class_exists("woocommerce") ? null : '',
		);

		return $sections;
	}

	public function set_multivendor_setting_field( $setting_fields ) {
        if(!class_exists("woocommerce")) { return $setting_fields; }

		$setting_fields['usmsgh_multivendor_setting'] = array(
			array(
				'name'    => 'usmsgh_multivendor_vendor_send_sms',
				'label'   => __( 'Enable Vendor SMS Notifications', USMSGH_TEXT_DOMAIN ),
				'desc'    => 'Enable',
				'type'    => 'checkbox',
				'default' => 'off',
			),
			array(
				'name'    => 'usmsgh_multivendor_vendor_send_sms_on',
				'label'   => __( 'Send notification on', USMSGH_TEXT_DOMAIN ),
				'desc'    => __( 'Choose when to send a status notification message to your vendors', USMSGH_TEXT_DOMAIN ),
				'type'    => 'multicheck',
				'default' => array(
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
				'name'    => 'usmsgh_multivendor_selected_plugin',
				'label'   => __( 'Third Party Plugin', USMSGH_TEXT_DOMAIN ),
				'desc'    => 'Change this when auto detect multivendor plugin not working<br /><span id="usmsgh_multivendor_setting[multivendor_helper_desc]"></span>',
				'type'    => 'select',
				'default' => Usmsgh_Multivendor_Factory::$activatedPlugin ?? 'auto',
				'options' => array(
					'auto'             => 'Auto Detect',
					'product_vendors'  => 'Woocommerce Product Vendors',
					'wc_marketplace'   => 'MultivendorX',
					'wc_vendors'       => 'WC Vendors Marketplace',
					'wcfm_marketplace' => 'WooCommerce Multivendor Marketplace',
					'dokan'            => 'Dokan',
					'yith'             => 'YITH WooCommerce Multi Vendor'
				)
			),
			array(
				'name'    => 'usmsgh_multivendor_vendor_sms_template',
				'label'   => __( 'Vendor SMS Message', USMSGH_TEXT_DOMAIN ),
				'desc'    => 'Customize your SMS with <button type="button" id="usms_gh[open-keywords]" data-attr-type="multivendor" data-attr-target="usmsgh_multivendor_setting[usmsgh_multivendor_vendor_sms_template]" class="button button-secondary">Keywords</button>',
				'type'    => 'textarea',
				'rows'    => '8',
				'cols'    => '500',
				'css'     => 'min-width:350px;',
				'default' => __( '[shop_name] : You have a new order with order ID [order_id] and order amount [order_currency] [order_amount]. The order is now [order_status].', USMSGH_TEXT_DOMAIN )
			),
		);

		return $setting_fields;
	}

    public function usmsgh_wc_not_activated($form_id)
    {
        if(class_exists('woocommerce')) { return; }
        if($form_id != 'usmsgh_multivendor_setting') { return; }
        ?>
        <div class="wrap">
            <h1>UsmsGH-API Woocommerce Order Notification</h1>
            <p>This feature requires WooCommerce to be activated</p>
        </div>
        <?php
    }

}

?>
