<?php

/*
 * Integration with UsmsGH RESTful API
 *
 * Class methods:
 *      sendSMS($to, $from, $message, $message_type, $dlr_url, $udh)
 *      receiveDLR()
 *      receiveMO()
 *      messageStatus($msgid)
 *      accountBalance()
 *      accountPricing($mcc, $mnc)
 */

class UsmsGH {

    // Account credentials
    private $api_key = '';
    private $api_secret = '';
    private $log;

    // REST API URL
    public $rest_ip_address = 'https://183.81.161.84:443/rest/2';
    public $rest_base_url = "https://rest.usmsapi.com/rest/2";
    public $actual_api_url;

    private $rest_commands = array (
            'send_sms' => array('url' => '/sms', 'method' => 'POST'),
            'get_message_status' => array('url' => '/report/message', 'method' => 'GET'),
            'get_balance' => array('url' => '/account/balance', 'method' => 'GET'),
            'get_pricing' => array('url' => '/account/pricing', 'method' => 'GET')
    );

    public $response_format = 'json';

    public $message_type_option = array('7-bit' => 1, '8-bit' => 2, 'Unicode' => 3);

    public function __construct($api_key = null, $api_secret = null)
    {
        $this->log = new Usmsgh_WooCoommerce_Logger();
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
        $this->actual_api_url = $this->rest_base_url;
    }

    public function setApiUrl($use_domain = true)
    {
        $this->actual_api_url = ($use_domain) ? $this->rest_base_url : $this->rest_ip_address;
    }

    function sendSMS($from, $to, $message, $medium, $message_type = null, $dlr_url = null, $udh = null)
    {
        $this->log->add("UsmsGH", "SMS Sent to {$to}: {$message} ");
        // Send request to UsmsGH gateway

        // return json_encode(array(
        //     'messages' => array(
        //         array(
        //             'status' => 0,
        //             'msgid' => sha1( random_bytes(128) ),
        //         )
        //     )
        // ));

        $params = array(
                'usms-from' => $from,
                'usms-to' => $to,
                'usms-text' => $message,
                'usms-dlr-mask' => 1,
                'usms-dlr-url' => $dlr_url,
                'usms-udh' => $udh,
                'usms-medium' => "wordpress_order_notification"
        );
        return $this->invokeApi ('send_sms', $params);
    }

    public function receiveDLR($data)
    {
        $delivery_status = array(1 => 'Success', 2 => 'Failed', 3 => 'Expired');

        $delivery_report_data = new stdClass();
        $delivery_report_data->from = $data['usms-from'];
        $delivery_report_data->to = $data['usms-to'];
        $delivery_report_data->dlr_status = $delivery_status[$data['usms-dlr-status']];
        $delivery_report_data->msgid = $data['usms-msgid'];
        $delivery_report_data->error_code = $data['usms-error-code'];
        $delivery_report_data->dlr_received_time = date('Y-m-d H:i:s');

        return $delivery_report_data;
    }

    public function receiveMO($data)
    {
        $mo_message = new stdClass();
        $mo_message->from = $data['usms-from'];
        $mo_message->to = $data['usms-to'];
        $mo_message->keyword = $data['usms-keyword'];
        $mo_message->text = $data['usms-text'];
        $mo_message->coding = $data['usms-coding'];
        $mo_message->time = $data['usms-time'];

        if($mo_message->coding == $this->message_type_option['Unicode']) {
            $mo_message->keyword = $this->utf16HexToUtf8($mo_message->keyword);
            $mo_message->text = $this->utf16HexToUtf8($mo_message->text);
        }

        return $mo_message;
    }

    public function messageStatus($msgid)
    {
        $params = array('usms-msgid' => $msgid);
        return $this->invokeApi ('get_message_status', $params);
    }

    public function accountBalance()
    {
        return $this->invokeApi ('get_balance');
    }

    public function accountPricing($mcc = null, $mnc = null)
    {
        $params = array();
        if($mcc) {
            $params['usms-mcc'] = $mcc;
        }
        if($mnc) {
            $params['usms-mnc'] = $mnc;
        }
        return $this->invokeApi ('get_pricing', $params);
    }

    private function invokeApi ($command, $params = array())
    {
        if(get_option("usmsgh_domain_reachable")) { $this->setApiUrl(true); }
        else { $this->setApiUrl(false); }
        // Get REST URL and HTTP method
        $command_info = $this->rest_commands[$command];
        $url = $this->actual_api_url . $command_info['url'];
        $method = $command_info['method'];

        $this->log->add("UsmsGH", "Using url: {$url}");

        // Build the post data
        $params = array_merge($params, array('usms-api-key' => $this->api_key, 'usms-api-secret' => $this->api_secret, 'usms-resp-format' => $this->response_format));

        $rest_request = curl_init();
        if($method == 'POST') {
            curl_setopt($rest_request, CURLOPT_URL, $url);
            curl_setopt($rest_request, CURLOPT_POST, $method == 'POST' ? true: false);
            curl_setopt($rest_request, CURLOPT_POSTFIELDS, http_build_query($params));
        } else {
            $query_string = '';
            foreach($params as $parameter_name => $parameter_value) {
                $query_string .= '&'.$parameter_name.'='.$parameter_value;
            }
            $query_string = substr($query_string, 1);
            curl_setopt($rest_request, CURLOPT_URL, $url.'?'.$query_string);
        }
        curl_setopt($rest_request, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($rest_request, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($rest_request, CURLOPT_SSL_VERIFYHOST, 0);

        $rest_response = curl_exec($rest_request);

        if($rest_response === false) {
            throw new Exception('curl error: ' . curl_error($rest_request));
        }

        curl_close ($rest_request);

        return $rest_response;
    }

    private function utf16HexToUtf8($string)
    {
        if(strlen($string) % 4) {
            $string = '00'.$string;
        }

        $converted_string = '';
        $string_length = strlen($string);
        for($counter = 0; $counter < $string_length; $counter += 4) {
            $converted_string .= "&#".hexdec(substr($string, $counter, 4)).";";
        }
        $converted_string = mb_convert_encoding($converted_string, "UTF-8", "HTML-ENTITIES");

        return $converted_string;
    }
}
?>
