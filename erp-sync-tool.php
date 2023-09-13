<?php
/**
 * Plugin Name:     ERP Sync Tool
 * Plugin URI:      https://github.com/pvtl/wordpress-erp-sync-tool-plugin
 * Description:     A link between Pivotal's ERP Sync Tool & Woocommerce
 * Author:          Pivotal Agency
 * Author URI:      http://pivotal.agency
 * Text Domain:     erp-sync-tool
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         ERP Sync Tool
 */

use App\Plugins\Pvtl\Classes;

defined( 'ABSPATH' ) || die();

// Path to plugin directory.
define( 'ERP_PATH', __DIR__ );

// Autoload plugin classes.
require_once ERP_PATH . '/autoload.php';


function ERP() { // phpcs:ignore WordPress.NamingConventions
	return Classes\Erp_Sync_Tool::instance();
}

ERP();
