<?php

/*
Plugin Name: USMS-GH WC SMS Notification
Plugin URI:  https://usmsgh.com/plugins/usmsgh-wc-sms-notification
Description: USMS-GH Order SMS Notification for WC
Version:     2.2.0
Author:      Urhitech
Author URI:  https://urhitech.com
License:     GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Text Domain: usmsgh-wc-sms-notification
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 9.5
*/



if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! function_exists( 'usms_fs' ) ) {
    // Create a helper function for easy SDK access.
    function usms_fs() {
        global $usms_fs;

        if ( ! isset( $usms_fs ) ) {
            // Include Freemius SDK.
            require_once dirname(__FILE__) . '/lib/freemius/start.php';

            $usms_fs = fs_dynamic_init( array(
                'id'                  => '10109',
                'slug'                => 'usmsgh-wc-sms-notification',
                'type'                => 'plugin',
                'public_key'          => 'pk_e43ddd98007b9678c00aee2ee98a2',
                'is_premium'          => false,
                'has_addons'          => false,
                'has_paid_plans'      => false,
                'menu'                => array(
                    'slug'           => 'usmsgh-woocoommerce-setting',
                    'override_exact' => true,
                    'account'        => false,
                    'contact'        => false,
                    'support'        => false,
                    'parent'         => array(
                        'slug' => 'options-general.php',
                    ),
                ),
            ) );
        }

        return $usms_fs;
    }

    // Init Freemius.
    usms_fs();
    // Signal that SDK was initiated.
    do_action( 'usms_fs_loaded' );

    function usms_fs_settings_url() {
        return admin_url( 'options-general.php?page=usmsgh-woocoommerce-setting' );
    }

    usms_fs()->add_filter('connect_url', 'usms_fs_settings_url');
    usms_fs()->add_filter('after_skip_url', 'usms_fs_settings_url');
    usms_fs()->add_filter('after_connect_url', 'usms_fs_settings_url');
    usms_fs()->add_filter('after_pending_connect_url', 'usms_fs_settings_url');
}

define("USMSGH_PLUGIN_URL", plugin_dir_url(__FILE__));
define("USMSGH_PLUGIN_DIR", plugin_dir_path(__FILE__));
define("USMSGH_INC_DIR", USMSGH_PLUGIN_DIR . "includes/");
define("USMSGH_ADMIN_VIEW", USMSGH_PLUGIN_DIR . "admin/");
define("USMSGH_TEXT_DOMAIN", "usmsgh-woocoommerce");
define("USMS_DB_TABLE_NAME", "usmsgh_wc_send_sms_outbox");

require_once USMSGH_PLUGIN_DIR . 'lib/action-scheduler/action-scheduler.php';

add_action( 'plugins_loaded', 'usmsgh_woocommerce_init', PHP_INT_MAX );

// Declare HPOS compatibility
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

function usmsgh_install() {

    global $create_sms_send, $create_otp_table;
    include_once USMSGH_PLUGIN_DIR . '/install.php';
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $create_sms_send );
    dbDelta( $create_otp_table );
}

register_activation_hook(__FILE__, 'usmsgh_install');

function usmsgh_cleanup() {
    delete_option("usmsgh_plugin_version");
    delete_option("usmsgh_domain_reachable");
}

register_deactivation_hook(__FILE__, 'usmsgh_cleanup');

function usmsgh_woocommerce_init() {
    require_once(plugin_dir_path(__FILE__) . '/vendor/autoload.php');
	require_once ABSPATH . '/wp-admin/includes/plugin.php';
	require_once ABSPATH . '/wp-includes/pluggable.php';
	require_once USMSGH_PLUGIN_DIR . 'interfaces/Usmsgh_PluginInterface.php';
	require_once USMSGH_PLUGIN_DIR . 'includes/contracts/class-usmsgh-register-interface.php';
	require_once USMSGH_PLUGIN_DIR . 'includes/class-usmsgh-freemius.php';
	require_once USMSGH_PLUGIN_DIR . 'includes/class-usmsgh-helper.php';
	require_once USMSGH_PLUGIN_DIR . 'includes/class-usmsgh-woocommerce-frontend-scripts.php';
	require_once USMSGH_PLUGIN_DIR . 'includes/class-usmsgh-woocommerce-hook.php';
	require_once USMSGH_PLUGIN_DIR . 'includes/class-usmsgh-woocommerce-register.php';
	require_once USMSGH_PLUGIN_DIR . 'includes/class-usmsgh-woocommerce-logger.php';
	require_once USMSGH_PLUGIN_DIR . 'includes/class-usmsgh-woocommerce-notification.php';
	require_once USMSGH_PLUGIN_DIR . 'includes/class-usmsgh-otp-handler.php';
	require_once USMSGH_PLUGIN_DIR . 'includes/class-usmsgh-otp-login.php';
	require_once USMSGH_PLUGIN_DIR . 'includes/class-usmsgh-otp-checkout.php';
	require_once USMSGH_PLUGIN_DIR . 'includes/class-usmsgh-woocommerce-widget.php';
	require_once USMSGH_PLUGIN_DIR . 'includes/class-usmsgh-download-log.php';
	require_once USMSGH_PLUGIN_DIR . 'includes/class-usmsgh-sendsms.php';
	require_once USMSGH_PLUGIN_DIR . 'includes/multivendor/class-usmsgh-multivendor.php';
	require_once USMSGH_PLUGIN_DIR . 'lib/UsmsGH.php';
	require_once USMSGH_PLUGIN_DIR . 'lib/usmsgh/src/Usms.php';
	require_once USMSGH_PLUGIN_DIR . 'lib/class.settings-api.php';
	require_once USMSGH_PLUGIN_DIR . 'admin/class-usmsgh-woocommerce-setting.php';
	require_once USMSGH_PLUGIN_DIR . 'admin/sendsms.php';
	require_once USMSGH_PLUGIN_DIR . 'admin/smsoutbox.php';
	require_once USMSGH_PLUGIN_DIR . 'admin/automation.php';
	require_once USMSGH_PLUGIN_DIR . 'admin/logs.php';
	require_once USMSGH_PLUGIN_DIR . 'admin/help.php';
    require_once USMSGH_PLUGIN_DIR . 'includes/plugins/UsmsS2Member.php';
    require_once USMSGH_PLUGIN_DIR . 'includes/plugins/UsmsARMemberLite.php';
    require_once USMSGH_PLUGIN_DIR . 'includes/plugins/UsmsARMemberPremium.php';
    require_once USMSGH_PLUGIN_DIR . 'includes/plugins/UsmsMemberPress.php';
    require_once USMSGH_PLUGIN_DIR . 'includes/plugins/UsmsMemberMouse.php';
    require_once USMSGH_PLUGIN_DIR . 'includes/plugins/UsmsSimpleMembership.php';
    require_once USMSGH_PLUGIN_DIR . 'includes/plugins/UsmsRestaurantReservation.php';
    require_once USMSGH_PLUGIN_DIR . 'includes/plugins/UsmsQuickRestaurantReservation.php';
    require_once USMSGH_PLUGIN_DIR . 'includes/plugins/UsmsBookIt.php';
    require_once USMSGH_PLUGIN_DIR . 'includes/plugins/UsmsLatePoint.php';
    require_once USMSGH_PLUGIN_DIR . 'includes/plugins/UsmsFATService.php';
    require_once USMSGH_PLUGIN_DIR . 'includes/plugins/UsmsWpERP.php';
    require_once USMSGH_PLUGIN_DIR . 'includes/plugins/UsmsJetpackCRM.php';
    require_once USMSGH_PLUGIN_DIR . 'includes/plugins/UsmsFluentCRM.php';
    require_once USMSGH_PLUGIN_DIR . 'includes/plugins/UsmsGroundhoggCRM.php';
    require_once USMSGH_PLUGIN_DIR . 'includes/plugins/UsmsSupportedPlugin.php';

    // load all Forms integrations
    

	//create notification instance
	$usmsgh_notification = new Usmsgh_WooCommerce_Notification();

	//initialize OTP handler
	$usmsgh_otp_handler = new Usmsgh_OTP_Handler();

	//register hooks and settings
	$registerInstance = new Usmsgh_WooCommerce_Register();
	$registerInstance->add( new UsmsGH_Freemius() )
	                 ->add( new Usmsgh_WooCommerce_Hook( $usmsgh_notification ) )
	                 ->add( new Usmsgh_WooCommerce_Setting() )
	                 ->add( new Usmsgh_OTP_Login() )
	                 ->add( new Usmsgh_OTP_Checkout() )
	                 ->add( new Usmsgh_WooCommerce_Widget() )
	                 ->add( new Usmsgh_WooCommerce_Frontend_Scripts() )
	                 ->add( new Usmsgh_Multivendor() )
	                 ->add( new Usmsgh_Download_log() )
	                 ->add( new UsmsGH_SendSMS_View() )
	                 //->add( new UsmsGH_Automation_View() )
	                 ->add( new UsmsGH_SMSOutbox_View() )
	                 //->add( new UsmsGH_Logs_View() )
	                 ->add( new UsmsGH_Help_View() )
	                 ->load();
}

