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

        if (!$response) {
            return new \WP_Error('invalid', 'Request could not be performed');
        }

        if ($code != 200) {
            $message = wp_remote_retrieve_response_message($response);
            if($code == '401') {
                $message = 'API credentials are not correct';
            }
            return new \WP_Error('invalid', "An error occured: {$message}");
        }

        if (is_wp_error($response)) {
            return new \WP_Error('wp_error', $response->get_error_message());
        }
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

        if ($response['contact']['id']) {
            return $response;
        }

        return new \WP_Error('error', $response->errors);    
    }

    public function makeRequest($endpoint, $data = array(), $method = 'GET') {
        $header = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( $this->username . ':' . $this->password ),
                'content-type' => 'application/json'
            )
        );
        $url = $this->apiUrl.$endpoint;

        if ($method == 'GET') {
            $response = wp_remote_get($url, $header);
        } else if ($method == 'POST') {
            $args = array_merge($header, $data);
            $args['body'] = json_encode($args['body']);
            $response = wp_remote_post($url, $args);
        }

        $code = wp_remote_retrieve_response_code($response);
        if (!$response) {
            return new \WP_Error('invalid', 'Request could not be performed');
        }

        if ($code != 200) {
            $body = wp_remote_retrieve_body( $response );
            $body = json_decode($body);
            return new \WP_Error('invalid', $body->detail);
        }

        if (is_wp_error($response)) {
            return new \WP_Error('wp_error', $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $body = \json_decode($body, true);

        return $body;
    }
}
