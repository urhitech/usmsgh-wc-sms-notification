<?php

class Usmsgh_WooCommerce_Register {
	/* @var Usmsgh_Register_Interface[] $instances_to_be_register */
	protected $instances_to_be_register = array();

	public function add( Usmsgh_Register_Interface $instance ) {
		$this->instances_to_be_register[] = $instance;
		return $this;
	}

	public function load() {
		foreach ( $this->instances_to_be_register as $instance ) {
			$instance->register();
		}
	}
}

?>
