<?php

namespace UsmsAPI_WC;

use UsmsAPI_WC\Forms\Handlers\ContactForm7;
use UsmsAPI_WC\Migrations\MigrateSendSMSPlugin;
use UsmsAPI_WC\Migrations\MigrateWoocommercePlugin;

class Loader {

    public static function load()
    {
        new ContactForm7();

        // load Migrations
        MigrateWoocommercePlugin::migrate();
        MigrateSendSMSPlugin::migrate();
    }
}
