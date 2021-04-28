<?php

namespace App\Plugins\Pvtl\Classes;

/**
 * Pivotal Agency Single Sign On Plugin
 */
class Erp_Sync_Tool {

    public static $api_base = 'http://erp-sync-tool.php80.pub.localhost/api/v1/';

	/**
	 * The single instance of the class.
	 *
	 * @var ERP_Sync_Tool
	 */
	protected static $instance;

	/**
	 * Main ERP_Sync_Tool Instance.
	 *
	 * Ensures only one instance of ERP_Sync_Tool is loaded.
	 *
	 * @return ERP_Sync_Tool
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() 
    {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
        new Erp_Sync_Tool_Settings();
        
        add_action( 'woocommerce_new_order', array( $this, 'dispatch_order_sync_on_order' ), 10, 1 );
        add_action( 'woocommerce_update_order', array( $this, 'dispatch_order_sync_on_order' ), 10, 1 );

		add_filter( 'woocommerce_rest_customer_query', array( $this, 'add_updated_since_filter_to_rest_api' ), 100, 2 );
        add_action( 'woocommerce_created_customer', array ( $this, 'woocommerce_customer_creation'), 10, 2 );

        add_filter( "woocommerce_rest_shop_order_object_query", array ( $this, 'add_orders_updated_since_filter' ), 10, 2 );

        add_action( 'rest_api_init', array( $this, 'format_wholesale_user_pricing'), 10, 2 ); // TODO: Remove from this plugin, this is ST Food Co specific
        add_action( 'woocommerce_rest_insert_product_object', array( $this, 'add_wholesale_user_pricing' ), 10, 2 );
    }


    public function add_wholesale_user_pricing( $product, $request )
    {
        $meta = get_post_meta($product->get_id());
        $wholesale_pricing = isset($meta['wholesale_multi_user_pricing']) 
            ? unserialize($meta['wholesale_multi_user_pricing'][0])
            : array();

        $json = $request->get_json_params();

        $base = 'wholesale_user_pricing_';

        for ($i = 1; $i <= 9; $i++) { // We want 1 - 9 pricing tiers
            if (isset($json[$base . 'tier-' . $i])) {
                $tier_pricing = [
                    'slug' => 'tier-' . $i,
                    'discount_type' => 'fixed',
                    'min_quatity' => 1, // Yes, quatity. Not quantity.
                    'wholesale_price' => $json[$base . 'tier-' . $i]
                ];
                
                // Cycle through our wholesale pricing to see if we have this tier already
                $pricing_key = false;

                foreach($wholesale_pricing as $key => $pricing) {
                    if ($pricing['slug'] === 'tier-' . $i) {
                        $pricing_key = $key;
                    }
                }

                if ($pricing_key === false) {
                    // We need to find the ID of the pricing tier with this slug
                    $term = get_term_by( 'slug', 'tier-' . $i, 'wholesale_user_roles' );
                    if ($term) {
                        // Set the wholesale pricing key by the term id (this is how the plugin works)
                        $wholesale_pricing[$term->term_id] = $tier_pricing;
                    } else {
                        // We didn't find a matching slug term, so skip
                        continue;
                    }
                } else {
                    $wholesale_pricing[$pricing_key] = $tier_pricing;
                }
            }
        }

        update_post_meta( 
            $product->get_id(), 
            'wholesale_multi_user_pricing', 
            $wholesale_pricing
        );
    }

    /**
     * TODO: Remove from this plugin, this is ST Food Co specific
     */
    public function format_wholesale_user_pricing()
    {
        $base = 'wholesale_user_pricing_';
        for ($i = 1; $i <= 9; $i++) { // We want 1 - 9 pricing tiers
            register_rest_field( 
                'product', 
                $base . 'tier-' . $i, 
                array(
                    'get_callback' => function( $post_arr ) use ($i) {
                        foreach($post_arr['meta_data'] as $meta_data) {
                            // Find wholesaler meta data
                            if ($meta_data->key === 'wholesale_multi_user_pricing') {
                                foreach($meta_data->value as $multi_user_pricing) {
                                    if ($multi_user_pricing['slug'] === 'tier-' . $i) {
                                        switch ($multi_user_pricing['discount_type']) {
                                            case 'percent':
                                                $price = $post_arr['price'] * (1 - ($multi_user_pricing['wholesale_price'] / 100));
                                                break;
                                            case 'fixed':
                                            default:
                                                $price = $multi_user_pricing['wholesale_price'];
                                                break;
                                        }
                                        return (string) number_format($price, 2, '.', '');
                                    }
                                }
                            }
                        }
                        return $post_arr['price'];
                    },
                ) 
            );
        }

    }

    public function dispatch_order_sync_on_order( $order_id )
    {
        $options = get_option( 'erp_sync_tool_options' );

        foreach ($options['services'] as $service) {
            if (strpos($service['service_type'], 'OrderSync') !== false) {
                wp_remote_get( 
                    Erp_Sync_Tool::$api_base .'sync/' . $service['webhook'], 
                    array(
                        'headers' => array ( 
                            'Accept' => 'Application/json',
                            'Authorization' => 'Bearer ' . $options['api_key'],
                        )
                    ) 
                );
            }
        }
    }

    /**
     * Allows the 'updated_since filter on orders
     */
    public function add_orders_updated_since_filter( $prepared_args ) {
        if (isset($_GET['updated_since'])) {
            $prepared_args['date_query'][] = array(
                'after' => $_GET['updated_since'],
                'column' => 'post_modified' 
            );
        }
        return $prepared_args;
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