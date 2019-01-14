<?php 

add_filter('wp_get_attachment_url', 'staticize_attachment_src', null, 4);
function staticize_attachment_src($url, $post_id)
{
    $wp_upload_dir = wp_upload_dir();
    return str_replace( $wp_upload_dir['baseurl'] . '/' , '', $url );
}

add_filter( 'wp_calculate_image_srcset', 'ssl_srcset', null, 5 );
function ssl_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id)
{
    foreach ( $sources as &$source ) {
        $source['url'] = $image_src;
    }

    return $sources;
}

add_filter( 'woocommerce_ajax_variation_threshold', 'iconic_wc_ajax_variation_threshold', 10, 2 );
function iconic_wc_ajax_variation_threshold( $qty, $product ) {
    return VARIATION_THRESHOLD;
}

//remove_action( 'woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart', 30 );
//// define the woocommerce_variable_add_to_cart callback 
//function action_woocommerce_variable_add_to_cart( $woocommerce_variable_add_to_cart ) { 
//    global $product;
//        // Enqueue variation scripts
//        wp_enqueue_script( 'wc-add-to-cart-variation' );
//        // Get Available variations?
//        $get_variations = sizeof( $product->get_children() ) <= apply_filters( 'woocommerce_ajax_variation_threshold', VARIATION_THRESHOLD, $product );
//        // Load the template
//        wc_get_template( 'single-product/add-to-cart/variable.php', array(
//            'available_variations' => $get_variations ? $product->get_available_variations() : false,
//            'attributes'           => $product->get_variation_attributes(),
//            'selected_attributes'  => $product->get_default_attributes(),
//            /*
//             * Selection UX:
//             * - 'locking':     Attribute selections in the n-th attribute are constrained by selections in all atributes other than n.
//             * - 'non-locking': Attribute selections in the n-th attribute are constrained by selections in all atributes before n.
//             */
//            'selection_ux'         => apply_filters( 'woocommerce_variation_attributes_selection_ux', '<strong>non-locking</strong>', $product )
//        ) );
//}; 
//// add the action 
//add_action( 'woocommerce_variable_add_to_cart', 'action_woocommerce_variable_add_to_cart', 10, 2 );

function wc_deregister_javascript() 
{
    wp_deregister_script( 'wc-add-to-cart-variation' );
    wp_register_script( 'wc-add-to-cart-variation', plugins_url(). '/shopify2woocommerce/js/wc-add-to-cart-variation.js' , array( 'jquery','wp-util' ), false, true );
    wp_enqueue_script('wc-add-to-cart-variation');
}
add_action( 'wp_print_scripts', 'wc_deregister_javascript', 100 );

//add_filter( 'wc_get_template_part', function( $template, $slug, $name )
//{
//    // Look in plugin/woocommerce/slug-name.php or plugin/woocommerce/slug.php
//    if ( $name ) {
//        $path = plugin_dir_path( __FILE__ ) . WC()->template_path() . "{$slug}-{$name}.php";
//    } else {
//        $path = plugin_dir_path( __FILE__ ) . WC()->template_path() . "{$slug}.php";
//    }
//    return file_exists( $path ) ? $path : $template;
//}, 10, 3 );
// get path for all other templates.
add_filter( 'woocommerce_locate_template', function( $template, $template_name, $template_path )
{
    $path = WOO_IMPORT_REST_DIR . $template_path . $template_name;
    return file_exists( $path ) ? $path : $template;
}, 10, 3 );