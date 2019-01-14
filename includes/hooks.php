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