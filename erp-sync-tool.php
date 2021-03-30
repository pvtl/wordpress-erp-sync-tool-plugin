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
	public function __construct() {
		add_filter( 'woocommerce_rest_customer_query', array( $this, 'add_updated_since_filter_to_rest_api' ), 100, 2 );
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