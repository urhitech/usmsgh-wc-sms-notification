<?php
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

class UsmsSupportedPlugin {

    public function __construct() {}

    public static function get_activated_plugins()
    {
        $supported_plugins = array();
        if(UsmsS2Member::plugin_activated())
            $supported_plugins[] = UsmsS2Member::class;
        if(UsmsARMemberLite::plugin_activated())
            $supported_plugins[] = UsmsARMemberLite::class;
        if(UsmsARMemberPremium::plugin_activated())
            $supported_plugins[] = UsmsARMemberPremium::class;
        if(UsmsMemberPress::plugin_activated())
            $supported_plugins[] = UsmsMemberPress::class;
        if(UsmsMemberMouse::plugin_activated())
            $supported_plugins[] = UsmsMemberMouse::class;
        if(UsmsSimpleMembership::plugin_activated())
            $supported_plugins[] = UsmsSimpleMembership::class;

        if(UsmsRestaurantReservation::plugin_activated())
            $supported_plugins[] = UsmsRestaurantReservation::class;
        if(UsmsQuickRestaurantReservation::plugin_activated())
        $supported_plugins[] = UsmsQuickRestaurantReservation::class;
        if(UsmsBookIt::plugin_activated())
            $supported_plugins[] = UsmsBookIt::class;
        if(UsmsLatePoint::plugin_activated())
            $supported_plugins[] = UsmsLatePoint::class;
        if(UsmsFATService::plugin_activated())
            $supported_plugins[] = UsmsFATService::class;

        if(UsmsWpERP::plugin_activated())
            $supported_plugins[] = UsmsWpERP::class;
        if(UsmsJetpackCRM::plugin_activated())
            $supported_plugins[] = UsmsJetpackCRM::class;
        if(UsmsFluentCRM::plugin_activated())
            $supported_plugins[] = UsmsFluentCRM::class;
        if(UsmsGroundhoggCRM::plugin_activated())
            $supported_plugins[] = UsmsGroundhoggCRM::class;

        return $supported_plugins;
    }


}
