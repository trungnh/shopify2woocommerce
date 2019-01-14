<?php
/**
 * Plugin Name:       Shopify2woocommerce
 * Description:       Shopify2woocommerce!
 * Version:           1.0.0
 * Author:            Trung
 * Author URI:        https://trungnh.com
 * Text Domain:       trungnh28
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/2Fwebd/feedier-wordpress
 */

#https://stackoverflow.com/questions/47518280/create-programmatically-a-woocommerce-product-variation-with-new-attribute-value
#https://stackoverflow.com/questions/47518333/create-programmatically-a-variable-product-and-two-new-attributes-in-woocommerce/47844054#47844054
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}
ini_set('display_errors',1);
global $wpdb;
global $wp_rewrite;

$upload_dir = wp_upload_dir();
$product_base_permalinks = explode('/', $wp_rewrite->extra_permastructs["product"]["struct"]);
$product_base_permalinks = reset($product_base_permalinks);

//define('UPLOADS', '');
define('DB_PREFIX', $wpdb->prefix);
define('WOO_IMPORT', $upload_dir['basedir'] . '/woo_import/');
define('WOO_IMPORT_UPLOAD', WOO_IMPORT . 'uploads/');
define('WOO_IMPORT_UPLOAD_ZIP', WOO_IMPORT_UPLOAD . 'zip/');
define('WOO_IMPORT_UPLOAD_ZIP_SOURCE', WOO_IMPORT_UPLOAD_ZIP . 'source/');
define('WOO_IMPORT_UPLOAD_ZIP_IMPORT', WOO_IMPORT_UPLOAD_ZIP . 'import/');
define('WOO_IMPORT_UPLOAD_ZIP_ARCHIVE', WOO_IMPORT_UPLOAD_ZIP . 'archive/');
define('WOO_IMPORT_PROCESS', WOO_IMPORT . 'process/');
define('WOO_IMPORT_ARCHIVE', WOO_IMPORT . 'archive/');
define('WOO_IMPORT_REST_DIR', dirname(__FILE__) . '/');
define('WOO_PRODUCT_BASE_PERMALINKS', site_url() . '/' . $product_base_permalinks);
define('VARIATION_THRESHOLD', 200);

foreach ([WOO_IMPORT, WOO_IMPORT_UPLOAD, WOO_IMPORT_UPLOAD_ZIP, WOO_IMPORT_UPLOAD_ZIP_SOURCE, WOO_IMPORT_UPLOAD_ZIP_IMPORT, WOO_IMPORT_UPLOAD_ZIP_ARCHIVE, WOO_IMPORT_PROCESS, WOO_IMPORT_ARCHIVE] as $dir) {
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
}

include_once WOO_IMPORT_REST_DIR . "includes/SimpleOrm.class.php";
include_once ABSPATH . "wp-admin/includes/class-wp-filesystem-base.php";
include_once ABSPATH . "wp-admin/includes/class-wp-filesystem-direct.php";
include_once WOO_IMPORT_REST_DIR . "includes/functions.php";
include_once WOO_IMPORT_REST_DIR . "includes/hooks.php";
foreach(glob(WOO_IMPORT_REST_DIR . "includes/classes/*.php") as $file) {
    include_once $file;
}

// Init orm
// Connect to the database using mysqli
$conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD);

if ($conn->connect_error)
    die(sprintf('Unable to connect to the database. %s', $conn->connect_error));

// Tell Simple ORM to use the connection you just created.
SimpleOrm::useConnection($conn, DB_NAME);

add_action( 'rest_api_init', function () {

    register_rest_route( 'woo-import/v1', 'import',array(

        'methods'  => 'POST',
        'callback' => 'import_from_source_urls'

    ) );

} );

add_action( 'rest_api_init', function () {

    register_rest_route( 'woo-import/v1', 'batch_import',array(

        'methods'  => 'POST',
        'callback' => 'batch_import'

    ) );

} );

add_action( 'rest_api_init', function () {

    register_rest_route( 'woo-import/v1', 'import_from_zip',array(

        'methods'  => 'POST',
        'callback' => 'import_from_zip'

    ) );

} );

add_action( 'rest_api_init', function () {

    register_rest_route( 'woo-import/v1', 'import_from_file',array(

        'methods'  => 'POST',
        'callback' => 'import_from_source_urls'

    ) );

} );

