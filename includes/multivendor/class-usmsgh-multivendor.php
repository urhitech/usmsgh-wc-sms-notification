<?php
/**
 * Created by PhpStorm.
 * User: Neoson Lam
 * Date: 4/10/2019
 * Time: 2:47 PM.
 */

class Usmsgh_Multivendor implements Usmsgh_Register_Interface {
	public function register() {
		$this->required_files();
		//create notification instance
		$usmsgh_notification = new Usmsgh_Multivendor_Notification( 'Wordpress-Woocommerce-Multivendor-Extension-' . Usmsgh_Multivendor_Factory::$activatedPlugin );

		$registerInstance = new Usmsgh_WooCommerce_Register();
		$registerInstance->add( new Usmsgh_Multivendor_Hook( $usmsgh_notification ) )
		                 ->add( new Usmsgh_Multivendor_Setting() )
		                 ->load();
	}

	protected function required_files() {
		require_once __DIR__ . '/admin/class-usmsgh-multivendor-setting.php';
		require_once __DIR__ . '/abstract/abstract-usmsgh-multivendor.php';
		require_once __DIR__ . '/contracts/class-usmsgh-multivendor-interface.php';
		require_once __DIR__ . '/class-usmsgh-multivendor-factory.php';
		require_once __DIR__ . '/class-usmsgh-multivendor-hook.php';
		require_once __DIR__ . '/class-usmsgh-multivendor-notification.php';
	}
}
