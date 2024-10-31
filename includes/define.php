<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
define( 'RC_MIGRATE_SHOPIFY_TO_WORDPRESS_REST_ADMIN_VERSION', '2020-04' );
define( 'RC_MIGRATE_SHOPIFY_TO_WORDPRESS_ADMIN', RC_MIGRATE_SHOPIFY_TO_WORDPRESS_DIR . "admin" . DIRECTORY_SEPARATOR );
define( 'RC_MIGRATE_SHOPIFY_TO_WORDPRESS_CACHE', WP_CONTENT_DIR . "/cache/migrate-shopify-to-wp/" );//use the same cache folder to free version
$plugin_url = plugins_url( '', __FILE__ );
$plugin_url = str_replace( '/includes', '', $plugin_url );
define( 'RC_MIGRATE_SHOPIFY_TO_WORDPRESS_CSS', $plugin_url . "/css/" );
define( 'RC_MIGRATE_SHOPIFY_TO_WORDPRESS_CSS_DIR', RC_MIGRATE_SHOPIFY_TO_WORDPRESS_DIR . "css" . DIRECTORY_SEPARATOR );
define( 'RC_MIGRATE_SHOPIFY_TO_WORDPRESS_JS', $plugin_url . "/js/" );
define( 'RC_MIGRATE_SHOPIFY_TO_WORDPRESS_JS_DIR', RC_MIGRATE_SHOPIFY_TO_WORDPRESS_DIR . "js" . DIRECTORY_SEPARATOR );
if ( is_file( RC_MIGRATE_SHOPIFY_TO_WORDPRESS_INCLUDES . "functions.php" ) ) {
	require_once RC_MIGRATE_SHOPIFY_TO_WORDPRESS_INCLUDES . "functions.php";
}
if ( is_file( RC_MIGRATE_SHOPIFY_TO_WORDPRESS_INCLUDES . "shopify-to-wp.php" ) ) {
	require_once RC_MIGRATE_SHOPIFY_TO_WORDPRESS_INCLUDES . "shopify-to-wp.php";
}
if ( is_file( RC_MIGRATE_SHOPIFY_TO_WORDPRESS_INCLUDES . "wp-async-request.php" ) ) {
	require_once RC_MIGRATE_SHOPIFY_TO_WORDPRESS_INCLUDES . "wp-async-request.php";
}
if ( is_file( RC_MIGRATE_SHOPIFY_TO_WORDPRESS_INCLUDES . "wp-background-process.php" ) ) {
	require_once RC_MIGRATE_SHOPIFY_TO_WORDPRESS_INCLUDES . "wp-background-process.php";
}
if ( is_file( RC_MIGRATE_SHOPIFY_TO_WORDPRESS_INCLUDES . "class-rch-process-BG.php" ) ) {
	require_once RC_MIGRATE_SHOPIFY_TO_WORDPRESS_INCLUDES . "class-rch-process-BG.php";
}
rc_include_folder( RC_MIGRATE_SHOPIFY_TO_WORDPRESS_ADMIN, 'MIGRATE_SHOPIFY_TO_WORDPRESS_ADMIN_' );