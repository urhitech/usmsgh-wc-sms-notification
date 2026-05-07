<?php
/**
 * Created by PhpStorm.
 * User: Majesty Scofield
 * Date: 12/4/2021
 * Time: 4:30 PM.
 */

class USMSGHAPI_Multivendor_Yith_Manager extends Abstract_UsmsGHAPI_Multivendor {
	public function __construct( UsmsGH_WooCoommerce_Logger $log = null ) {
		parent::__construct( $log );
	}

	public function setup_mobile_number_setting_field( $user ) {
		?>
        <h3 class="heading">USMS-GH WooCommerce SMS Notification</h3>
        <table class="form-table">
            <tr>
                <th><label for="usmsgh_phone_field">Phone</label></th>
                <td>
                    <input type="text" class="input-text" id="usmsgh_phone_field" name="usmsgh_phone_field"
                           value="<?php _e(esc_attr( get_the_author_meta( 'usmsgh_phone', $user->ID ) )) ?>"/>
                    <p class="description">Fill this field to enable sms feature for vendor</p>
                </td>
            </tr>
        </table>
		<?php
	}

	public function save_mobile_number_setting( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$usmsgh_phone_field = sanitize_text_field( $_POST['usmsgh_phone_field'] );

		update_user_meta( $user_id, 'usmsgh_phone', $usmsgh_phone_field );
	}

	public function get_vendor_mobile_number_from_vendor_data( $vendor_data ) {
		return get_user_meta( $this->get_vendor_id_from_item( $vendor_data['item'] ), 'usmsgh_phone', true );
	}

	public function get_vendor_country_from_vendor_data($vendor_data){
        //Get default country v1.1.17
		return usmsgh_get_options( 'usmsgh_woocommerce_country_code', 'usmsgh_setting', '' );
	}

	public function get_vendor_shop_name_from_vendor_data( $vendor_data ) {
		return $vendor_data['vendor_profile']->name;
	}

	public function get_vendor_id_from_item( WC_Order_Item $item ) {
		return $this->get_vendor_profile_from_item( $item )->get_owner();
	}

	public function get_vendor_profile_from_item( WC_Order_Item $item ) {
		return yith_get_vendor( $item->get_product(), 'product' );
	}

	public function get_vendor_data_list_from_order( $order_id ) {
		if ( get_post_field( 'post_parent', $order_id ) === 0 ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		$items = $order->get_items();

		$vendor_data_list = array();

		foreach ( $items as $item ) {
			$vendor_data_list[] = array(
				'item'           => $item,
				'vendor_user_id' => $this->get_vendor_id_from_item( $item ),
				'vendor_profile' => $this->get_vendor_profile_from_item( $item )
			);
		}

		$this->log->add( 'usmsgh_Multivendor', 'Raw data: ' . json_encode( $vendor_data_list ) );

		return $this->perform_grouping( $vendor_data_list );
	}
}
