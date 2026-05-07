<?php
/**
 * Created by PhpStorm.
 * User: Neoson Lam
 * Date: 2/25/2019
 * Time: 9:59 AM.
 */

class Usmsgh_WooCommerce_Widget implements Usmsgh_Register_Interface {
	protected $log;

	public function __construct( Usmsgh_WooCoommerce_Logger $log = null ) {
		if ( $log === null ) {
			$log = new Usmsgh_WooCoommerce_Logger();
		}

		$this->log = $log;
	}

	public function register() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
	}

	public function register_widget() {
		wp_add_dashboard_widget( 'msmswc_dashboard_widget', 'UsmsGH', array( $this, 'display_widget' ) );
	}

	public function display_widget() {
		$api_key        = usmsgh_get_options( 'usmsgh_woocommerce_api_key', 'usmsgh_setting', '' );
		$api_secret     = usmsgh_get_options( 'usmsgh_woocommerce_api_secret', 'usmsgh_setting', '' );
		$usmsgh_rest = new UsmsGH( $api_key, $api_secret );
		try {
			$balance = json_decode( $usmsgh_rest->accountBalance() );

			if ( $api_key && $api_secret ) {
				?>

                <h3><?php echo $balance->status === 0 ? "Balance: $balance->value" : urldecode( $balance->err_msg ) ?></h3>

				<?php
			} else {
				?>

                <h3>
				Please setup API Token and Sender ID in
                    <a href="<?php echo admin_url( 'options-general.php?page=usmsgh-woocoommerce-setting' ) ?>">
                        UsmsGH settings
                    </a>
                </h3>

				<?php
			}
		} catch ( Exception $exception ) {
			//errors in curl
			$this->log->add( 'UsmsGH', 'Failed get balance: ' . $exception->getMessage() );
			?>

            <h3>
                There's some problem while showing balance, please refresh this page and try again.
            </h3>

			<?php
		}
	}
}
