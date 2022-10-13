<?php

namespace FluentFormRapidmail\Integrations;

class API {
    protected $username = null;
    protected $password = null;
    protected $apiUrl = 'https://apiv3.emailsys.net';

    protected $settings = [];

    public function __construct($settings)
    {
        $this->username = $settings['username'];
        $this->password = $settings['password'];
        $this->apiUrl = 'https://apiv3.emailsys.net';
        $this->settings = $settings;
    }

    public function testCredentials($username, $password) {
        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $username . ':' . $password )
            )
        );

        $url = $this->apiUrl.'/apiusers';
        $response = wp_remote_get($url, $args);
        $code = wp_remote_retrieve_response_code($response);

        if($code != 200){
            return false;
        }
        return true;
    }

    public function getRecipientLists() {
        if(!empty($this->username) && !empty($this->password)) {
            $response = $this->makeRequest('/recipientlists');

            if(!is_wp_error($response)){
                $lists = $response['_embedded']['recipientlists'];
                $formattedLists = [];
                foreach ($lists as $list) {
                    $formattedLists[$list['id']] = $list['name'];
                }

                return $formattedLists;
            };
            
        }
        return [];
    }

    public function subscribe($list, $email, $first_name, $last_name, $send_confirmation_email) {
        $subscriber = [
            'body' => [
                'recipientlist_id' => $list,
                'email' => $email,
                'firstname' => $first_name,
                'lastname' => $last_name
            ],
            'send_activationmail' => $send_confirmation_email
        ];

        $response = $this->makeRequest('/recipients', $subscriber, 'POST');

        if (is_wp_error($response)) {
            return new \WP_Error('error', $response->errors);
        }

        if ($response['contact']["id"]) {
            return $response;
        }

        return new \WP_Error('error', $response->errors);    
    }

    public function makeRequest($endpoint, $data = array(), $method = 'GET') {
        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $this->username . ':' . $this->password )
            )
        );
        $url = $this->apiUrl.$endpoint;

        if ($method == 'GET') {
            $url = add_query_arg($data, $url);
            $response = wp_remote_get($url, $args);
        } else if ($method == 'POST') {
            $response = wp_remote_post($url, $data);
        }

        $code = wp_remote_retrieve_response_code($response);
        if (!$response) {
            return new \WP_Error('invalid', 'Request could not be performed');
        }

        if ($code != 200) {
            $message = wp_remote_retrieve_response_message($response);
            return new \WP_Error('invalid', $message);
        }

        if (is_wp_error($response)) {
            return new \WP_Error('wp_error', $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $body = \json_decode($body, true);

        return $body;
    }
}
