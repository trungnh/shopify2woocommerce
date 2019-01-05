<?php
/**
 * Plugin Name:       Trung
 * Description:       Trung!
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

include_once ABSPATH . "wp-admin/includes/class-wp-filesystem-base.php";
include_once ABSPATH . "wp-admin/includes/class-wp-filesystem-direct.php";

$upload_dir = wp_upload_dir();
define('WOO_IMPORT', $upload_dir['basedir'] . '/woo_import/');
define('WOO_IMPORT_PROCESS', WOO_IMPORT . 'process/');
define('WOO_IMPORT_ARCHIVE', WOO_IMPORT . 'archive/');
define('WOO_IMPORT_REST_DIR', dirname(__FILE__) . '/');

if ( ! is_dir( WOO_IMPORT ) ) {
	wp_mkdir_p( WOO_IMPORT );
}
if ( ! is_dir( WOO_IMPORT_PROCESS ) ) {
	wp_mkdir_p( WOO_IMPORT_PROCESS );
}
if ( ! is_dir( WOO_IMPORT_ARCHIVE ) ) {
	wp_mkdir_p( WOO_IMPORT_ARCHIVE );
}

include "includes/functions.php";

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

