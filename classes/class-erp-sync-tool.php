<?php

namespace App\Plugins\Pvtl\Classes;

class Erp_Sync_Tool {

    public static $api_base = 'https://erptool.neon.accounts.pvtl.io/api/v1/';

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
        new Erp_Sync_Tool_Settings();
        
        add_action('woocommerce_new_order', array( $this, 'dispatch_order_sync_on_order' ), 10, 1);
        add_action('woocommerce_update_order', array( $this, 'dispatch_order_sync_on_order' ), 10, 1);

		add_filter( 'woocommerce_rest_customer_query', array( $this, 'add_updated_since_filter_to_rest_api' ), 100, 2 );
        add_action( 'woocommerce_created_customer', array ( $this, 'woocommerce_customer_creation'), 10, 2 );

        add_filter( "woocommerce_rest_shop_order_object_query", array ( $this, 'add_orders_updated_since_filter' ) , 10, 2 );
        
        // Add image if exists
        add_action( 'woocommerce_rest_insert_product_object', array( $this, 'upload_image_where_exists' ), 10, 2 );
    }


    public function upload_image_where_exists( $product, $request )
    {
        $json = $request->get_json_params();
        if (isset($json['image'])) {
            $post_id = $product->get_id();
            // Include files so we can sideload
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/image.php' );

            // Get an image name so we can avoid adding duplicates
            $image_name = isset($json['image_name']) ? $json['image_name'] : '';
            
            // Check - do we already have an image with this name?
            $args = array(
                'post_type' => 'attachment',
                'posts_per_page' => -1,
                'post_status' => 'any',
                'post_parent' => $post_id
            );

            $attachments = get_posts( $args ); 
            foreach( $attachments as $attachment ) {
                if ($attachment->post_title === $image_name && $image_name !== '') {
                    // If we do, abort
                    return;
                }
            } 
            
            // If we don't already have the image, continue to upload & set as thumb

            // Sideload image & add to media library
            $media = media_sideload_image( 
                $json['image'], 
                $post_id,
                $image_name
            );
            
            // If we uploaded an image let's set it as the thumbnail
            if(!empty($media) && !is_wp_error( $media )){
                // Grab the images
                $attachments = get_posts( $args );
                if( isset( $attachments ) && is_array( $attachments ) ){
                    foreach($attachments as $attachment){
                        // Grab source of full size images (so no 300x150 nonsense in path)
                        $image = wp_get_attachment_image_src( $attachment->ID, 'full' );
                        // Determine if in the $media image we created, the string of the URL exists
                        if( strpos( $media, $image[0]) !== false ){
                            // If so, we found our image. set it as thumbnail
                            set_post_thumbnail( $post_id, $attachment->ID );
                            // Break out because we have our image
                            break;
                        }
                    }
                }
            }
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