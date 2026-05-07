<?php

class UsmsGH_SMSOutbox_View implements Usmsgh_Register_Interface {

	private $settings_api;

	function __construct() {
		$this->settings_api = new WeDevs_Settings_API;
	}

	public function register() {
        add_filter( 'usmsgh_setting_section', array($this, 'set_smsoutbox_setting_section' ) );
		add_filter( 'usmsgh_setting_fields',  array($this, 'set_smsoutbox_setting_field' ) );
        add_action( 'usmsgh_setting_fields_custom_html', array($this, 'display_smsoutbox_page'), 10, 1);
	}

	public function set_smsoutbox_setting_section( $sections ) {
		$sections[] = array(
            'id'               => 'usmsgh_smsoutbox_setting',
            'title'            => __( 'SMS Outbox', USMSGH_TEXT_DOMAIN ),
            'submit_button'    => '',
            // 'action'           => 'usmsgh_sms_form',
            // 'action_url'       => admin_url('admin-post.php'),
		);

		return $sections;
	}

	/**
	 * Returns all the settings fields
	 *
	 * @return array settings fields
	 */
	public function set_smsoutbox_setting_field( $setting_fields ) {
		return $setting_fields;
	}

    public function display_smsoutbox_page($form_id) {
        if($form_id != 'usmsgh_smsoutbox_setting') { return; }
        global $wpdb;
        $db_table_name = USMS_DB_TABLE_NAME;
        $count_result = $wpdb->get_results ( "SELECT count(*) as count FROM {$db_table_name}" );

        $count = $count_result[0];
        $total = $count->count;
        $total_page = ceil($total / 10);
        $total_show_pages= 5;
        $middle_page_add_on_number = floor($total_show_pages/2);
        if(isset($_GET['pageno'])){
            $current_page = absint( sanitize_text_field( wp_unslash( $_GET['pageno'] ) ) );
            // Validate page number is within range
            if ( $current_page < 1 ) {
                $current_page = 1;
            } elseif ( $current_page > $total_page && $total_page > 0 ) {
                $current_page = $total_page;
            }
        }else{
            $current_page = 1;
        }

        if($total_page < $total_show_pages)
        {
            $start_page = 1;
            $end_page = $total_page;
        }
        else
        {
            if(($current_page + $middle_page_add_on_number) > $total_page)
            {
                $start_page = $total_page - $total_show_pages + 1;
                $end_page = $total_page;
            }
        else if($current_page > $middle_page_add_on_number)
            {
                $start_page = $current_page - $middle_page_add_on_number;
                $end_page = $start_page + $total_show_pages - 1;
            }
            else
            {
                $start_page = 1;
                $end_page = $total_show_pages;
            }
        }

        $first_page = 1;
        $last_page = ($total_page > 0 ? $total_page : $first_page);
        $previous_page = ($current_page>1 ? $current_page -1 : 1);
        $next_page = ($current_page<$total_page ? $current_page +1 : $last_page);

        $admin_url = admin_url('options-general.php?page=usmsgh-woocoommerce-setting');
        $pageno = ($current_page - 1) * 10;
    ?>
        <br>
        <div class="bootstrap-wrapper">

            <nav aria-label="Page navigation example">
            <ul class="pagination">
                <li class="page-item"><a class="page-link" href="<?php echo $admin_url.'&pageno='.$first_page ?>">First</a></li>
                <li class="page-item"><a class="page-link" href="<?php echo $admin_url.'&pageno='.$previous_page ?>">Previous</a></li>
                <?php for($i=$start_page; $i<=$end_page; $i++) { ?>
                    <li class="page-item"><a class="page-link" href="<?php echo $admin_url.'&pageno='.$i ?>"><?php echo $i; ?></a></li>
                <?php } ?>
                <li class="page-item"><a class="page-link" href="<?php echo $admin_url.'&pageno='.$next_page ?>">Next</a></li>
                <li class="page-item"><a class="page-link" href="<?php echo $admin_url.'&pageno='.$last_page ?>">Last</a></li>
            </ul>
            </nav>


            <span>Page : <?php echo $current_page; ?></span>
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col" id='date'>Date</th>
                        <th scope="col" id='sender'>Sender</th>
                        <th scope="col" id='recipient'>Recipient</th>
                        <th scope="col" id='message'>Message</th>
                        <th scope="col" id='message'>Status</th>
                    </tr>
                </thead>
            <tbody id="the-list" data-wp-lists='list:id'>
            <?php
                global $wpdb;
                $result = $wpdb->get_results ( "SELECT * FROM {$db_table_name} ORDER BY id DESC LIMIT ".$pageno.",10" );

                foreach ( $result as $print ) {
                ?>
                <tr>
                <td><?php echo esc_attr($print->date);?></td>
                <td><?php echo esc_attr($print->sender);?></td>
                <td><?php echo esc_attr($print->recipient);?></td>
                <td><?php echo esc_attr($print->message);?></td>
                <td><?php echo esc_attr($print->status);?></td>
                </tr>
                    <?php }
            ?>
            </tbody>

            </table>
        </div>

    <?php
    }


}

?>
