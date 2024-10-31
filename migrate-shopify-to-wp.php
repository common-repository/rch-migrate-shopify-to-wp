<?php
/*
 * Plugin Name: RCH - Migrate Shopify to WP
 * Description: Migrate Shopify to Wordpress helps you to import all products from your shopify store and import from csv file as well.
 * Author: shopifytowp107
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
define( 'RC_MIGRATE_SHOPIFY_TO_WORDPRESS_VERSION', '1.0.0' );
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if ( is_plugin_active( 'rch-migrate-shopify-to-wp/rch-migrate-shopify-to-wp.php' ) ) {
	return;
}
define( 'RC_MIGRATE_SHOPIFY_TO_WORDPRESS_DIR', WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . "migrate-shopify-to-wp" . DIRECTORY_SEPARATOR );
define( 'RC_MIGRATE_SHOPIFY_TO_WORDPRESS_INCLUDES', RC_MIGRATE_SHOPIFY_TO_WORDPRESS_DIR . "includes" . DIRECTORY_SEPARATOR );
if ( is_file( RC_MIGRATE_SHOPIFY_TO_WORDPRESS_INCLUDES . "class-rch-error-images-table.php" ) ) {
	require_once RC_MIGRATE_SHOPIFY_TO_WORDPRESS_INCLUDES . "class-rch-error-images-table.php";
}
if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
	$init_file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . "migrate-shopify-to-wp" . DIRECTORY_SEPARATOR . "includes" . DIRECTORY_SEPARATOR . "define.php";
	require_once $init_file;
} else {
	add_action( 'admin_notices', 'rch_global_note' );
	/**
	 * Notify if WooCommerce is not activated
	 */
	function rch_global_note() { ?>
        <div id="message" class="error">
            <p><?php esc_html_e( 'Please install and activate WooCommerce to use Migrate Shopify to Wordpress plugin.', 'migrate-shopify-to-wp' ); ?></p>
        </div>
		<?php
	}

	return;
}

//Load class for migrate-shopify-to-wp
add_action('plugins_loaded', 'init_migrate_shopify_to_wordpress');
function init_migrate_shopify_to_wordpress(){
	require 'class-migrate-shopify-to-wp.php';
}

//Include file for read and import products 
require_once( "admin/import-class/functions.php" );
require_once( "admin/import-class/ShopifyGeneral.php" );
require_once( "admin/import-class/ImportCustomer.php" );
require_once( "admin/import-class/ImportProducts.php" );
require_once( "admin/import-class/ImportProductFromCSV.php" );
//Objects
$ShopifyCustomer    = new ImportCustomer;
$ShopifyProduct		= new ImportProducts;