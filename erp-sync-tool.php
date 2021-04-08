<?php
/**
 * Plugin Name:     ERP Sync Tool
 * Plugin URI:      https://github.com/pvtl
 * Description:     Links Pivotal's ERP Sync Tool to WooCommerce.
 * Author:          Pivotal Agency
 * Author URI:      http://pivotal.agency
 * Text Domain:     erp-sync-tool
 * Domain Path:     /languages
 * Version:         0.0.1
 *
 * @package         ERP Sync Tool
 */

namespace App\Plugins\Pvtl;

/**
 * Pivotal Agency Single Sign On Plugin
 */
class ErpSyncTool {
	/**
	 * The name of the plugin (for cosmetic purposes).
	 *
	 * @var string
	 */
	protected $plugin_name = 'ERP Sync Tool';

	/**
	 * Constructor
	 */
	public function __construct() 
    {
		add_filter( 'woocommerce_rest_customer_query', array( $this, 'add_updated_since_filter_to_rest_api' ), 100, 2 );
        add_action( 'woocommerce_created_customer', array ( $this, 'woocommerce_customer_creation'), 10, 2 );
    }

    public function woocommerce_customer_creation( $customer_id )
    {
        $this->send_email_on_customer_create();
        $this->set_customer_user_role_on_create( $customer_id );
    }

    /**
     * Sets the customer role on craete 
     */
    private function set_customer_user_role_on_create( $customer_id )
    {
        if (isset($_GET['user_role'])) {
            $user = new \WP_User( $customer_id );

            // Remove role
            $user->add_role( $_GET['user_role'] );
        }
    }

    /**
     * Restricts sending of emails for new customer accounts if the 
     * 'email_on_create' param is present on the get params of the request. 
     */
    public function send_email_on_customer_create()
    {
        if (!isset($_GET['email_on_create'])) {
            return;
        }

        add_filter( 'woocommerce_email_enabled_customer_new_account', function() {
            /**
             * @var bool $enabled
             * @var \WP_User $user
             * @var \WC_Email_Customer_Completed_Order $email
             */
             return $_GET['email_on_create'] === 'true' ? true : false;
        }, 10, 3 );
    }

    /**
     * Adds the 'updated since' get filter on rest api customer requests to woocommerce
     */
    public function add_updated_since_filter_to_rest_api( $prepared_args, $request )
    {
        if ($request->get_param('updated_since')) {
            $prepared_args['meta_query'] = array(
                array(
                    'key'     => 'last_update',
                    'value'   => (int) $request->get_param('updated_since'),
                    'compare' => '>='
                ),
            );
        }
        
        return $prepared_args;
    }

}

if ( ! defined( 'ABSPATH' ) ) {
	exit();  // Exit if accessed directly.
}

new ErpSyncTool();