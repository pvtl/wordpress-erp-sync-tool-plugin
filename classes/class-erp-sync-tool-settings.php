<?php

namespace App\Plugins\Pvtl\Classes;

class Erp_Sync_Tool_Settings {
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

    function erp_sync_tool_options_validate( $input ) {
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
        
        add_settings_section( 'api_settings', 'API Settings', array( $this, 'api_section_text' ), 'erp_sync_tool' );
        add_settings_field( 'erp_sync_tool_setting_api_key', 'API Key', array( $this, 'api_key_setting_field'), 'erp_sync_tool', 'api_settings', $options );
        add_settings_field( 'erp_sync_tool_setting_client_name', 'Client Name', array( $this, 'client_name_setting_field'), 'erp_sync_tool', 'api_settings', $options );

        if ( get_option( 'erp_sync_tool_options' ) ) {
            //$result = wp_remote_get( 'http://erp-sync-tool.php80.pub.localhost/api/v1/services' );
            //var_dump($result);
            //exit;
        }
    }

    public function api_section_text()
    {
        echo 'Enter the API key from PVTL.';
    }

    public function api_key_setting_field($options) 
    {
        echo "<input id='erp_sync_tool_options_api_key' name='erp_sync_tool_options[api_key]' type='text' value='" . esc_attr( $options['api_key'] ) . "' />";
    }

    public function client_name_setting_field($options) 
    {
        echo "<input id='erp_sync_tool_options_client_name' name='erp_sync_tool_options[client_name]' type='text' value='" . esc_attr( $options['client_name'] ) . "' />";
    }
}

if ( ! defined( 'ABSPATH' ) ) {
	exit();  // Exit if accessed directly.
}
