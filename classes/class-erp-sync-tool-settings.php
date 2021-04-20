<?php

namespace App\Plugins\Pvtl\Classes;

class Erp_Sync_Tool_Settings {

    private $api_base = 'http://erp-sync-tool.php80.pub.localhost/api/v1/';

	/**
	 * Constructor
	 */
	public function __construct()
    {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Adds the view for the settings page.
     */
    public function add_settings_page() 
    {
        add_options_page(
            'ERP Sync Tool Settings',
            'ERP Sync Tool Settings',
            'manage_options',
            'erp-sync-tool',
            array( $this, 'render_settings_page' )
        );
    }

    public function render_settings_page()
    {
        ?>
        <h2>ERP Sync Tool Settings</h2>
        <form action="options.php" method="post">
            <?php 
            settings_fields( 'erp_sync_tool_settings' );
            do_settings_sections( 'erp_sync_tool' ); ?>
            <input name="submit" class="button button-primary" type="submit" value="<?php esc_attr_e( 'Save' ); ?>" />
        </form>
        <?php
    }

    public function convert_to_truthy( $array ) 
    {
        foreach($array as $key => &$value) {
            if (is_array($value)) {
                $value = $this->convert_to_truthy( $value );
            } elseif ( $value === 'true' ) {
                $value = true;
            } elseif ($value === 'false' ) {
                $value = false;
            }
        }

        return $array;
    }

    public function erp_sync_tool_options_validate( $input ) 
    {
        $options = get_option( 'erp_sync_tool_options' );

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach( $input['service_settings'] as $id => $service_settings) {
                foreach ($options['services'] as &$service) {
                    if ($service['id'] === $id) {
                        $updatable_service = &$service;
                        break;
                    }
                }

                // Convert our stringy truths to realy truths
                $service_settings = $this->convert_to_truthy( $service_settings );

                $updatable_service['settings'] = array_merge($updatable_service['settings'], $service_settings);

                $result = wp_remote_post( 
                    $this->api_base . 'services/' . $id, 
                    array(
                        'method' => 'PATCH',
                        'headers' => array( 
                            'Accept' => 'application/json',
                            'Authorization' => 'Bearer ' . $input['api_key'],
                            'Content-Type' => 'application/json',
                        ),
                        'body' => json_encode($updatable_service),
                    ) 
                );

                if ($result['response']['code'] !== 200) {
                    add_action('admin_notices', function () { 
                            echo '<div class="notice notice-error is-dismissible">
                                <p>Unable to update service settings. </p>
                            </div>';
                        }
                    );
                } else {
                    $newinput['services'][] = $updatable_service;
                }

            }
        } else {
            $newinput['services'] = $input['services'];
        }
        $newinput['api_key'] = trim( $input['api_key'] );
        $newinput['client_name'] = trim( $input['client_name'] );

        return $newinput;
    }

    /**
     * Registers the various available settings fields for the plugin
     */
    public function register_settings() 
    {
        register_setting( 'erp_sync_tool_settings', 'erp_sync_tool_options', array( $this, 'erp_sync_tool_options_validate') );
        $options = get_option( 'erp_sync_tool_options' );
        $this->addApiSettings( $options );

        $options = get_option( 'erp_sync_tool_options' );
        if ( $_SERVER['REQUEST_METHOD'] === 'GET' && $options && !empty( $options['api_key'] ) && !empty( $options['client_name'] ) ) {
            $this->refresh_service_settings( $options );
            $this->add_service_settings( $options );
        }
    }

    public function add_service_settings( $options )
    {
        if ( isset( $options['services'] ) && !empty( $options['services'] ) ) {
            foreach( $options['services'] as $service ) {

                add_settings_section( 
                    'service_settings_' . $service['id'],
                    $service['name'], 
                    function () use ( $service ) { 
                        echo 'Governs settings related to the ' . $service['service_type'] . ' service.'; 
                    }, 
                    'erp_sync_tool'
                );
                foreach ( $service['settings'] as $setting_name => $setting_value ) {
                    $this->add_service_settings_fields( $service['id'] , $setting_name, $setting_value );
                }
            }
       
        }
    }

    public function add_service_settings_fields( $service_id, $setting_name, $setting_value, $sub_category = []  )
    {
        if ( is_array( $setting_value ) ) {
            if ($setting_name === 'match_criteria') {
                $sub_category[] = 'match_criteria';
                add_settings_field(
                    $setting_name . implode('', $sub_category), 
                    $setting_name,
                    array( $this, 'match_criteria_field'), 
                    'erp_sync_tool', 
                    'service_settings_' . $service_id, 
                    array( 'service' => $service_id, 'id' => $setting_name, 'value' => $setting_value ) 
                );
            } else {
                $sub_category[] = $setting_name;
                foreach ( $setting_value as $sub_setting_name => $sub_setting_value ) {
                    $this->add_service_settings_fields( $service_id, $sub_setting_name, $sub_setting_value, $sub_category );
                }
            }
        } else {
            add_settings_field( 
                $setting_name . implode('', $sub_category), 
                $setting_name,
                array( $this, 'service_settings_field'), 
                'erp_sync_tool', 
                'service_settings_' . $service_id, 
                array( 'service' => $service_id, 'id' => $setting_name, 'value' => $setting_value, 'category' => $sub_category ) 
            );
        }
    }

    public function match_criteria_field( $settings )
    {
        foreach($settings['value'] as $set_key => $criteria_set) {
            echo "<br> Criteria Set {$set_key}<br>";

            foreach ($criteria_set as $key => $criteria) {
                echo "<input id='{$settings['id']}[$set_key][$key]' name='erp_sync_tool_options[service_settings][{$settings['service']}][match_criteria][{$set_key}][]' type='text' value='{$criteria}' /><br>";
            }
        }
    }

    public function service_settings_field( $settings )
    {
        $category = !empty($settings['category']) ? '[' . implode('][', $settings['category']) . ']' : '';

        if (is_bool($settings['value'])) {
            $yes_selected = $settings['value'] === true ? 'selected' : '';
            $no_selected = $settings['value'] !== true ? 'selected' : '';
 
            echo "{$category} <br> 
                <select id='{$settings['id']}' name='erp_sync_tool_options[service_settings][{$settings['service']}]{$category}[{$settings['id']}]'>
                    <option {$yes_selected} value='true' > Yes </option>
                    <option {$no_selected} value='false' > No </option>
                </select>
            ";

        } else {
            echo "
            {$category} <br> 
            <input 
                id='erp_sync_tool_options_{$settings['id']}' 
                name='erp_sync_tool_options[service_settings][{$settings['service']}]{$category}[{$settings['id']}]' 
                type='text' 
                value='{$settings['value']}' 
            />";
        }
    }

    public function addApiSettings( $options )
    {
        add_settings_section( 'api_settings', 'API Settings', array( $this, 'api_section_text' ), 'erp_sync_tool' );
        add_settings_field( 'erp_sync_tool_setting_api_key', 'API Key', array( $this, 'api_key_setting_field'), 'erp_sync_tool', 'api_settings', $options );
        add_settings_field( 'erp_sync_tool_setting_client_name', 'Client Name', array( $this, 'client_name_setting_field'), 'erp_sync_tool', 'api_settings', $options );
    }

    public function refresh_service_settings( $options )
    {
        $result = wp_remote_get( 
            $this->api_base .'services?client=' . $options['client_name'], 
            array(
                'headers' => array ( 
                    'Accept' => 'Application/json',
                    'Authorization' => 'Bearer ' . $options['api_key'],
                )
            ) 
        );

        if ($result['response']['code'] === 200) {
            $options['services'] = json_decode( $result['body'], true );

            update_option( 'erp_sync_tool_options', $options, false );


            add_action('admin_notices', function () { 
                    echo '<div class="notice notice-success is-dismissible">
                        <p>Settings updated from sync tool.</p>
                    </div>';
                }
            );

            return true;
        } else {
            add_action('admin_notices', function () { 
                    echo '<div class="notice notice-warning is-dismissible">
                        <p>Unable to connect to the sync tool with the current API credentials.</p>
                    </div>';
                }
            );

            return false;
        }
    }

    public function api_section_text()
    {
        echo 'Enter the API key from PVTL.';
    }

    public function api_key_setting_field( $options ) 
    {
        echo "<input id='erp_sync_tool_options_api_key' name='erp_sync_tool_options[api_key]' type='text' value='" . esc_attr( $options['api_key'] ) . "' />";
    }

    public function client_name_setting_field( $options ) 
    {
        echo "<input id='erp_sync_tool_options_client_name' name='erp_sync_tool_options[client_name]' type='text' value='" . esc_attr( $options['client_name'] ) . "' />";
    }
}

if ( ! defined( 'ABSPATH' ) ) {
	exit();  // Exit if accessed directly.
}
