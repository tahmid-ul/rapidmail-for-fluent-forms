<?php

namespace FluentFormRapidmail\Integrations;

use FluentForm\App\Services\Integrations\IntegrationManager;
use FluentForm\Framework\Foundation\Application;
use FluentForm\Framework\Helpers\ArrayHelper as Arr;

class Bootstrap extends IntegrationManager {
    public function __construct(Application $app) {
        parent::__construct(
            $app,
            'Rapidmail',
            'rapidmail',
            '_fluentform_rapidmail_settings',
            'rapidmail_feed',
            99
        );

        $this->logo = FFRAPIDMAIL_URL . 'assets/rapidmail.png';
        $this->description = 'Connect Rapidmail with WP Fluent Forms and subscribe a contact when a form is submitted.';
        $this->registerAdminHooks();
    }

    public function getGlobalFields($fields) {
        return [
            'logo' => $this->logo,
            'menu_title' => 'Rapidmail Settings',
            'menu_description' => $this->description,
            'valid_message' => 'Your Rapidmail credentials are valid',
            'invalid_message' => 'Your Rapidmail credentials are not set yet',
            'save_button_text' => 'Save Settings',
            'config_instruction' => 'Enter your Rapidmail API credentials',
            'fields' => [
                'username' => [
                    'type' => 'text',
                    'placeholder' => 'Username',
                    'label_tips' => 'Enter your Rapidmail API Username, if you do not have <br>Please login to your Rapidmail account and go to<br>Settings -> API -> Create API credentials',
                    'label' => 'Rapidmail API Username'
                ],
                'password' => [
                    'type' => 'password',
                    'placeholder' => 'Password',
                    'label_tips' => 'Enter your Rapidmail API Password, if you do not have <br>Please login to your Rapidmail account and go to<br>Settings -> API -> Create API credentials',
                    'label' => 'Rapidmail API Password'
                ],
            ],
            'hide_on_valid' => true,
	        'discard_settings' => [
		        'section_description' => 'Your Rapidmail API integration is up and running',
		        'button_text'         => 'Disconnect Rapidmail',
		        'data'                => [
			        'username' => '',
			        'password' => ''
		        ],
		        'show_verify'         => true
	        ]
        ];
    }

    public function getGlobalSettings($settings) {
        $globalSettings = get_option($this->optionKey);
        if (!$globalSettings) {
            $globalSettings = [];
        }
        $defaults = [
            'username' => '',
            'password' => '',
	        'status'   => ''
        ];

        return wp_parse_args($globalSettings, $defaults);
    }

    public function saveGlobalSettings($settings) {
        if (empty($settings['username'])) {
            $integrationSettings = [
                'username' => '',
                'password' => '',
                'status' => false
            ];
            // Update the details with username & password.
            update_option($this->optionKey, $integrationSettings, 'no');
            wp_send_json_success([
                'message' => 'Your settings has been updated',
                'status' => false
            ], 200);
        }

        // Verify API key now
	    try {
		    $integrationSettings = [
			    'username' => sanitize_text_field($settings['username']),
			    'password' => sanitize_text_field($settings['password']),
			    'status' => false
		    ];

		    update_option($this->optionKey, $integrationSettings, 'no');

		    $api = $this->getRemoteClient();
		    $testCredentials = $api->testCredentials($settings['username'], $settings['password']);

		    if (is_wp_error($testCredentials)) {
			    throw new \Exception($testCredentials->get_error_message());
		    }

		    if (!empty($testCredentials['error']['message'])) {
			    throw new \Exception($testCredentials['error']['message']);
		    }

	    } catch (\Exception $exception) {
		    wp_send_json_error([
			    'message' => $exception->getMessage()
		    ], 400);
	    }

	    $integrationSettings = [
		    'username' => sanitize_text_field($settings['username']),
		    'password' => sanitize_text_field($settings['password']),
		    'status' => true
	    ];

	    update_option($this->optionKey, $integrationSettings, 'no');

	    wp_send_json_success([
		    'message' => __('Your Rapidmail credentials are valid', 'fluentformpro'),
		    'status' => true
	    ], 200);
    }

    public function pushIntegration($integrations, $formId) {
        $integrations[$this->integrationKey] = [
            'title' => $this->title . ' Integration',
            'logo' => $this->logo,
            'is_active' => $this->isConfigured(),
            'configure_title' => 'Configuration required!',
            'global_configure_url' => admin_url('admin.php?page=fluent_forms_settings#general-rapidmail-settings'),
            'configure_message' => 'Rapidmail is not configured yet! Please configure your Rapidmail credentials first',
            'configure_button_text' => 'Configure Rapidmail integration'
        ];
        return $integrations;
    }

    public function getIntegrationDefaults($settings, $formId) {
        return [
            'name' => '',
            'username' => '',
            'password' => '',
            'list_id' => '',
            'send_confirmation_email' => true,
            'conditionals' => [
                'conditions' => [],
                'status' => false,
                'type' => 'all'
            ],
            'enabled' => true
        ];
    }

    public function getSettingsFields($settings, $formId) {
        return [
            'fields' => [
                [
                    'key' => 'name',
                    'label' => 'Feed Name',
                    'required' => true,
                    'placeholder' => 'Your Feed Name',
                    'component' => 'text'
                ],
                [
                    'key' => 'fields',
                    'label' => 'Map Fields',
                    'tips' => 'Select which Fluent Form fields pair with their<br /> respective Rapidmail fields.',
                    'component' => 'map_fields',
                    'field_label_remote' => 'Rapidmail Fields',
                    'field_label_local' => 'Form Field',
                    'primary_fileds' => [
                        [
                            'key' => 'email',
                            'label' => 'Email Address',
                            'required' => true,
                            'input_options' => 'emails'
                        ],
                        [
                            'key' => 'first_name',
                            'label' => 'First Name'
                        ],
                        [
                            'key' => 'last_name',
                            'label' => 'Last Name'
                        ]
                    ]
                ],
                [
                    'key' => 'list_id',
                    'label' => 'Rapidmail List',
                    'placeholder' => 'Select Rapidmail List',
                    'tips' => 'Select the Rapidmail List you would like to add your contacts to.',
                    'component' => 'select',
                    'required' => true,
                    'options' => $this->getLists(),
                ],
                [
                    'key' => 'send_confirmation_email',
                    'require_list' => false,
                    'checkbox_label' => 'Send Confirmation Email',
                    'component' => 'checkbox-single'
                ],
                [
                    'require_list' => false,
                    'key' => 'conditionals',
                    'label' => 'Conditional Logics',
                    'tips' => 'Allow Rapidmail integration conditionally based on your submission values',
                    'component' => 'conditional_block'
                ],
                [
                    'require_list' => false,
                    'key' => 'enabled',
                    'label' => 'Status',
                    'component' => 'checkbox-single',
                    'checkbox_label' => 'Enable This feed'
                ]
            ],
            'button_require_list' => false,
            'integration_title' => $this->title
        ];
    }

    public function getLists() {
        $api = $this->getRemoteClient();
        $lists = $api->getRecipientLists();
        return $lists;
    }

    public function getMergeFields($list = false, $listId = false, $formId = false) {
        return [];
    }

    /*
     * Form Submission Hooks Here
     */
    public function notify($feed, $formData, $entry, $form) {
        $data = $feed['processedValues'];

        $contact = Arr::only($data, ['first_name', 'last_name', 'email']);
        if (!is_email($contact['email'])) {
            $contact['email'] = Arr::get($formData, $data['email']);
        }

        if (!is_email($contact['email'])) {
            return false;
        }

        $send_confirmation_email = Arr::isTrue($data, 'send_confirmation_email') ? 'yes' : 'no';
        $list = Arr::get($data, 'list_id');

        $api = $this->getRemoteClient();
        $response = $api->subscribe($list, $contact['email'], $contact['first_name'], $contact['last_name'], $send_confirmation_email);

        if ($response === TRUE) {
            do_action('ff_integration_action_result', $feed, 'success', 'Rapidmail feed has been successfully initialed and pushed data');
        } else {
            $message = 'Rapidmail feed has been failed to deliver feed';
            if (is_wp_error($response)) {
                $message = $response->get_error_message();
            }
            do_action('ff_integration_action_result', $feed, 'failed', $message);
        }
    }

    protected function addLog($title, $status, $description, $formId, $entryId) {
        do_action('ff_log_data', [
            'title' => $title,
            'status' => $status,
            'description' => $description,
            'parent_source_id' => $formId,
            'source_id' => $entryId,
            'component' => $this->integrationKey,
            'source_type' => 'submission_item'
        ]);
    }

    public function getRemoteClient() {
        $settings = $this->getGlobalSettings([]);
        return new API($settings);
    }
}
