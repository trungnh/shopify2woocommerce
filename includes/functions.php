<?php

function batch_import($request)
{
    $sources = $request['sources'];
    register_process($sources);

    return rest_ensure_response(['data' => ['message' => 'Added process to queue']]);
}

function register_process ($sources)
{
    $wp_filesystem = new WP_Filesystem_Direct([]);
    $sources = is_array($sources) ? $sources : [$sources];
    $source_json = json_encode($sources);

    $process_name = date('YmdHis') . '.process';
    $file_path = WOO_IMPORT_PROCESS . process_name;

    $wp_filesystem->put_contents($file_path, $source_json);

    return;
}


/**
 * Import from list source
 *
 * @param object $request
 *
 * @return json
 */
function import_from_source_urls($request)
{
    if (!isset($request['sources'])) {
        return new WP_Error( 'error', 'Invalid source', ['status' => 400]);
    }

    $sources = $request['sources'];
    $sources = is_array($sources) ? $sources : [$sources];
    $total = count($sources);
    $count = 0;
    foreach ($sources as $source) {
        $json = parse_json($source);

        $utils = new WooUtils();

        if ($utils->create_product($json)) {
            $count++;
        }
    }

    $message['import_count'] = "{$count} / {$total}";

    return rest_ensure_response(['data' => $message]);
}

/**
 * Import product from json
 *
 * @param string $json
 *
 * @return
 */
function import_product($json)
{

    try {
        $attributes = get_attributes_from_json($json);
        save_product_attribute($attributes);
        save_product_attribute_terms($attributes);

        $product_data = get_products_from_json ($json);
        $product_id = create_variable_product($product_data);
        $parent_regular_price = $product_data['regular_price'];
        unset($product_data);
        if (!$product_id ) {
            return false;
        }

        assign_product_attributes($product_id, $attributes);

        $variations = get_variations_from_json($json, $product_id, $attributes);
        merge_product_and_variations($product_id, $parent_regular_price, $variations);
        unset($variations);

        return true;
    } catch(Exception $e)
    {
        return false;
    }
}

/**
 * Merge variable product and variations
 *
 * @param string $product_id
 * @param float $regular_price
 * @param array $variations
 *
 * @return bool
 */
function merge_product_and_variations($product_id, $regular_price, $variations)
{
    try{
        foreach($variations as $variation){
            $objVariation = new WC_Product_Variation();
            if ($variation["price"] < $regular_price) {
                $objVariation->set_price($variation["price"]);
                $objVariation->set_sale_price($variation["price"]);
            }
            $objVariation->set_regular_price($regular_price);
            $objVariation->set_parent_id($product_id);
            if(isset($variation["sku"]) && $variation["sku"]){
                $objVariation->set_sku($variation["sku"]);
            }
            $objVariation->set_manage_stock((bool)false);
            $objVariation->set_stock_status();
            $objVariation->set_attributes($variation['attributes']);
            $objVariation->save();

            unset($objVariation);
        }
    }
    catch(Exception $e){
        return false;
    }

    return true;
}

/**
 * Create variable product
 *
 * @param array $product_data
 *
 * @return string
 */
function create_variable_product ($product_data)
{
    try{
        $objProduct = new WC_Product_Variable();
        $objProduct->set_name($product_data['name']);
        $objProduct->set_description($product_data['description']);
        $objProduct->set_price($product_data['price']);
        $objProduct->set_regular_price($product_data['regular_price']);
        $objProduct->set_status("publish");
        $objProduct->set_catalog_visibility('visible');
        $objProduct->add_meta_data('fifu_image_url', $product_data['fifu_image_url']);
        $objProduct->add_meta_data('fgfu_image_url', $product_data['fgfu_image_url']);
        $objProduct->set_manage_stock((bool)false);
        $objProduct->set_stock_status();
        $objProduct->set_reviews_allowed(true);
        $objProduct->set_sold_individually(false);
        $product_id = $objProduct->save();
    }
    catch(Exception $e){
        return false;
    }
    unset($objProduct);

    return $product_id;
}

/**
 * Assign attribute to product
 *
 * @param string $product_id
 * @param array $attributes
 *
 * @return bool
 */
function assign_product_attributes ($product_id, $attributes)
{
    if($attributes){
        $productAttributes = [];
        foreach($attributes as $attribute_name => $attribute){
            $attr = wc_sanitize_taxonomy_name(stripslashes($attribute_name));
            $attr = 'pa_' . $attr;
            if($attribute["terms"]){
                foreach($attribute["terms"] as $option){
                    wp_set_object_terms($product_id,$option,$attr,true);
                }
            }
            $productAttributes[sanitize_title($attr)] = array(
                'name' => sanitize_title($attr),
                'value' => $attribute["terms"],
                'position' => $attribute["position"],
                'is_visible' => 1,
                'is_variation' => 1,
                'is_taxonomy' => 1
            );
        }
        update_post_meta($product_id,'_product_attributes',$productAttributes);
        unset($productAttributes);
    }

    return true;
}



/**
 * Add terms (values) to attribute
 *
 * @param string $attribute_name
 * @param array $terms
 *
 * @return
 */
function add_product_attribute_terms ( $attribute_name, $terms )
{
    $taxonomy_name = wc_attribute_taxonomy_name($attribute_name);
    foreach ($terms['terms'] as $term) {
        $term_name = ucfirst($term);
        $term_slug = sanitize_title($term);

        if( ! term_exists( $term_name, $taxonomy_name ) ) {
            wp_insert_term( $term_name, $taxonomy_name, array('slug' => $term_slug ) );
        }
    }

    return;
}

/**
 * Create new product attribute term
 *
 * @param array $attributes
 *
 * @return bool
 */
function save_product_attribute_terms($attributes)
{
    foreach ($attributes as $attribute_name => $terms) {
        add_product_attribute_terms($attribute_name, $terms);
    }

    return true;
}

/**
 * Create new product attribute
 *
 * @param array $attributes
 *
 * @return bool
 */
function save_product_attribute($attributes)
{
    foreach ($attributes as $attribute_name => $terms) {
        create_product_attribute($attribute_name);
    }

    return true;
}

/**
 * Save a new product attribute
 *
 * @param string $raw_name
 *
 * @return string
 */
function create_product_attribute( $raw_name )
{
    $attribute_labels = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_label', 'attribute_name' );
    $attribute_name   = array_search( $raw_name, $attribute_labels, true );

    if ( ! $attribute_name ) {
        $attribute_name = wc_sanitize_taxonomy_name( $raw_name );
    }

    $attribute_id = wc_attribute_taxonomy_id_by_name( $attribute_name );

    // Get the ID from the name.
    if ( $attribute_id ) {
        return $attribute_id;
    }

    // If the attribute does not exist, create it.
    $attribute_id = wc_create_attribute( array(
        'name'         => $raw_name,
        'slug'         => $attribute_name,
        'type'         => 'select',
        'order_by'     => 'menu_order',
        'has_archives' => false,
    ) );

    if ( is_wp_error( $attribute_id ) ) {
        return false;
    }

    // Register as taxonomy while importing.
    $taxonomy_name = wc_attribute_taxonomy_name( $attribute_name );
    register_taxonomy(
        $taxonomy_name,
        apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy_name, array( 'product' ) ),
        apply_filters( 'woocommerce_taxonomy_args_' . $taxonomy_name, array(
            'labels'       => array(
                'name' => $raw_name,
            ),
            'hierarchical' => true,
            'show_ui'      => false,
            'query_var'    => true,
            'rewrite'      => false,
        ) )
    );

    return $attribute_id;
}

/**
 * Get the product attribute ID from the name.
 *
 * @param string $name | The name (slug).
 * @return string
 */
function get_attribute_id_from_name( $name )
{
    global $wpdb;
    $attribute_id = $wpdb->get_col("SELECT attribute_name
    FROM {$wpdb->prefix}woocommerce_attribute_taxonomies
    WHERE attribute_name LIKE '$name'");

    return reset($attribute_id);
}

/**
 * Get product from JSON.
 *
 * @param string $json
 *
 * @return array
 */
function get_products_from_json ($json)
{
    $keys = [
        'name' => 'title',
        'description' => 'description',
        'sku' => 'id',
        'price' => 'price',
        'regular_price' => 'price_max',
        'sale_price' => 'price_min',
    ];
    $product = [];

    foreach ($keys as $k => $v) {
        $product[$k] = $json->{$v};
    }

    $images = get_images_from_json($json);
    $product['fifu_image_url'] = $images['fifu_image_url'];
    $product['fgfu_image_url'] = $images['fgfu_image_url'];

    return $product;
}

/**
 * Get variations from JSON.
 *
 * @param string $json
 * @param string $product_id
 * @param string $attributes
 *
 * @return array
 */
function get_variations_from_json ($json, $product_id, $attributes)
{
    $keys = [
        'sku' => 'sku',
        'price' => 'price',
    ];

    $variations = [];
    foreach ($json->variants as $variant) {
        $variation = [];
        $variation['parent_product_id'] = $product_id;
        foreach ($keys as $k => $v) {
            $variation[$k] = $variant->{$v};
        }

        $variations_attributes = [];
        $count = 1;
        foreach (array_keys($attributes) as $item) {
            $prop = "option{$count}";
            $count++;

            if (!property_exists($variant, $prop)) continue;
            if (is_null($variant->{$prop})) continue;

            $attribute_name = strtolower($item);
            $attribute_value = $variant->{$prop};

            $taxonomy = wc_attribute_taxonomy_name($attribute_name);
            $attr_val_slug =  wc_sanitize_taxonomy_name(stripslashes($attribute_value));
            $variations_attributes[$taxonomy] = $attr_val_slug;
        }

        $variation['attributes'] = $variations_attributes;

        $variations[] = $variation;
        unset($variation);
    }

    return $variations;
}

/**
 * Get attributes and terms from JSON.
 * Used to import product attributes.
 *
 * @param  array $json
 * @return array
 */
function get_images_from_json($json)
{
    $imageList = [];
    $images = $json->images;
    foreach ($images as $image) {
        $imageList[] = 'http:' . $image;
    }

    $result =
        [
            'fifu_image_url' => reset($imageList),
            'fgfu_image_url' => $imageList
        ];

    return $result;
}

/**
 * Get attributes and terms from JSON.
 * Used to import product attributes.
 *
 * @param  array $json
 * @return array
 */
function get_attributes_from_json($json)
{
    $attributes = [];
    $options = $json->options;
    foreach ($options as $option) {
        $attributes[$option->name]['terms'] = $option->values;
    }

    return $attributes;
}

/**
 * Parse JSON file.
 *
 * @param  string $source
 * @return array
 */
function parse_json($source)
{
    $userAgent = 'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.133 Safari/537.36';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($ch, CURLOPT_URL, $source);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($ch);
    curl_close($ch);

    return json_decode($data);

}

/**
 * Convert multi level object to multi dimension array
 *
 * @param mixed $input Input object
 * @return array
 */
function to_array($input)
{
    return json_decode(json_encode($input), true);
}



/* ====== HOOKS ====== */

//add_filter('woocommerce_placeholder_img_src', 'woocommerce_placeholder_img_src_custom');
//function woocommerce_placeholder_img_src_custom ( $src ) {
//    return '';
//    global $product;
//    $image_src = get_post_meta($product->id, '_fgfu_image_url');
//
//    return reset($image_src);
//}
//
//remove_action( 'woocommerce_product_thumbnails', 'woocommerce_show_product_thumbnails', 20 );
//add_action( 'woocommerce_product_thumbnails', 'woocommerce_product_thumbnails_custom', 10 );
//function woocommerce_product_thumbnails_custom() {
//    global $product;
//    $image_srcs = get_post_meta($product->id, '_fgfu_image_url');
//    $image_srcs = reset($image_srcs);
//    $count = 0;
//    foreach ($image_srcs as $src) {
//        $class = ($count == 0) ? 'wp-post-image' : '';
//        echo
//            '<div data-thumb="' . esc_url( $src ) . '" class="woocommerce-product-gallery__image">
//                <a href="' . esc_url( $src ) . '">' . '<img src="'.$src.'" class="'.$class.'">' . '</a>
//            </div>';
//        $count ++;
//    }
//}
//
//add_filter( 'wc_get_template_part', function( $template, $slug, $name )
//{
//
//    // Look in plugin/woocommerce/slug-name.php or plugin/woocommerce/slug.php
//    if ( $name ) {
//        $path = plugin_dir_path( __FILE__ ) . WC()->template_path() . "{$slug}-{$name}.php";
//    } else {
//        $path = plugin_dir_path( __FILE__ ) . WC()->template_path() . "{$slug}.php";
//    }
//
//    return file_exists( $path ) ? $path : $template;
//
//}, 10, 3 );
//
//
//// get path for all other templates.
//add_filter( 'woocommerce_locate_template', function( $template, $template_name, $template_path )
//{
//
//    $path = WOO_IMPORT_REST_DIR . $template_path . $template_name;
//    return file_exists( $path ) ? $path : $template;
//
//}, 10, 3 );

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