<?php

class WooUtils
{
    function create_postmeta($post_id, $meta_key, $meta_value)
    {
        $wp_post_meta = new Postmeta();
        $wp_post_meta->post_id = $post_id;
        $wp_post_meta->meta_key = $meta_key;
        $wp_post_meta->meta_value = $meta_value;

        return $wp_post_meta->save();
    }

    function create_option($option_name, $option_value, $autoload = "no")
    {
        $wp_option = new Options();
        $wp_option->option_name = $option_name;
        $wp_option->option_value = $option_value;
        $wp_option->autoload = $autoload;

        return $wp_option->save();
    }

    function create_product_attribute_term($attribute_slug, $term_name, $term_slug)
    {
        // Create new term
        $new_term = new Terms();
        $new_term->name = $term_name;
        $new_term->slug = $term_slug;
        $new_term->term_group = 0;
        $new_term->save();

        // Create the term taxonomy
        $new_term_taxonomy = new Term_Taxonomy();
        $new_term_taxonomy->term_id = $new_term->term_id;
        $new_term_taxonomy->taxonomy = $attribute_slug;
        $new_term_taxonomy->description = "";
        $new_term_taxonomy->parent = 0;
        $new_term_taxonomy->count = 0;
        $new_term_taxonomy->save();

        return $new_term;
    }

    function create_product_attributes($product_object)
    {
        $added_attributes = Woocommerce_Attribute_Taxonomies::selectAll(['attribute_id', 'attribute_name', 'attribute_label']);

        $product_attributes = array();
        foreach ($added_attributes as $attr) {
            $slug = "pa_" . $attr->attribute_name;
            $product_attributes[$slug] = array();
            $product_attributes[$slug]['id'] = $attr->attribute_id;
            $product_attributes[$slug]['name'] = $attr->attribute_label;

            // GET TERMS
            // Select all taxonomy of the attribute
            $term_taxonomies = Term_Taxonomy::selectAll(['term_id'], "taxonomy = '{$slug}'");

            // Collection all term ids
            $term_id_arr = array();
            foreach ($term_taxonomies as $term_taxonomy) {
                $term_id_arr[] = $term_taxonomy->term_id;
            }

            // Select all term datas from collected term ids
            $term_id_arr_condition_string = implode("','", $term_id_arr);
            $term_datas = Terms::selectAll(['term_id', 'name', 'slug'], "term_id IN ('{$term_id_arr_condition_string}')");

            // Build term
            $terms = array();
            foreach ($term_datas as $term_data) {
                $terms[$term_data->slug] = array(
                    'id' => $term_data->term_id,
                    'name' => $term_data->name,
                    'slug' => $term_data->slug,
                );
            }
            $product_attributes[$slug]['terms'] = $terms;
        }

        // START - Create product attributes (if needed)
        foreach ($product_object->options as $option) {
            $slug = $this->sanitize($option->name);
            $slug = trim($slug);

            if (!array_key_exists("pa_" . $slug, $product_attributes)) { // new attribute
                $check_count = count(Woocommerce_Attribute_Taxonomies::selectAll([], "attribute_name = '{$slug}'"));

                if ($check_count > 0) {
                    echo "Attribute '" . $option->name . " is existed!\n'";
                    continue;
                }

                // Create new Woocommerce_Attribute_Taxonomies
                $newWoocommerceAttributeTaxonomies = new Woocommerce_Attribute_Taxonomies();
                $newWoocommerceAttributeTaxonomies->attribute_name = $slug;
                $newWoocommerceAttributeTaxonomies->attribute_label = $option->name;
                $newWoocommerceAttributeTaxonomies->attribute_type = "select";
                $newWoocommerceAttributeTaxonomies->attribute_orderby = "name_num";
                $newWoocommerceAttributeTaxonomies->attribute_public = 0;

                if ($newWoocommerceAttributeTaxonomies->save()) {
                    // Update wp_options
                    $attribute_taxonomies = Woocommerce_Attribute_Taxonomies::selectAll();
                    $transient_wc_attribute_taxonomies_array = array();
                    foreach ($attribute_taxonomies as $atx) {
                        $transient_wc_attribute_taxonomies_array[] = (object)array(
                            "attribute_id" => $atx->attribute_id,
                            "attribute_name" => $atx->attribute_name,
                            "attribute_label" => $atx->attribute_label,
                            "attribute_type" => $atx->attribute_type,
                            "attribute_orderby" => $atx->attribute_orderby,
                            "attribute_public" => $atx->attribute_public,
                        );
                    }

                    $transient_wc_attribute_taxonomies_row = Options::selectOne([], "option_name = '_transient_wc_attribute_taxonomies'");
                    if (isset($transient_wc_attribute_taxonomies_row)) {
                        $transient_wc_attribute_taxonomies_row->option_value = serialize($transient_wc_attribute_taxonomies_array);
                        $transient_wc_attribute_taxonomies_row->save();
                    } else {
                        $transient_wc_attribute_taxonomies_row = new Options();
                        $transient_wc_attribute_taxonomies_row->option_name = '_transient_wc_attribute_taxonomies';
                        $transient_wc_attribute_taxonomies_row->option_value = serialize($transient_wc_attribute_taxonomies_array);
                        $transient_wc_attribute_taxonomies_row->save();
                    }

                    // add new attribute to $product_attributes
                    $product_attributes["pa_" . $slug] = array();
                    $product_attributes["pa_" . $slug]['id'] = $newWoocommerceAttributeTaxonomies->attribute_id;
                    $product_attributes["pa_" . $slug]['name'] = $newWoocommerceAttributeTaxonomies->attribute_label;

                    foreach ($option->values as $option_value) {
                        $term_slug = $this->sanitize($option_value);

                        // Create new term
                        $new_term = $this->create_product_attribute_term("pa_" . $slug, $option_value, $term_slug);

                        // Add new term to array
                        $product_attributes["pa_" . $slug]['terms'][$term_slug] = array(
                            'id' => $new_term->term_id,
                            'name' => $new_term->name,
                            'slug' => $new_term->slug,
                        );
                    }
                }
            } else { // update old attribute
                // Check attribute terms
                foreach ($option->values as $option_value) {
                    $wc_attribute_terms = $product_attributes["pa_" . $slug]['terms'];

                    $term_slug = $this->sanitize($option_value);

                    if (!array_key_exists($term_slug, $wc_attribute_terms)) {
                        // Create new term
                        $new_term = $this->create_product_attribute_term("pa_" . $slug, $option_value, $term_slug);

                        // Add new term to array
                        $product_attributes["pa_" . $slug]['terms'][$term_slug] = array(
                            'id' => $new_term->term_id,
                            'name' => $new_term->name,
                            'slug' => $new_term->slug,
                        );
                    }
                }
            }

        }
        // END - Create product attributes

        return $product_attributes;
    }

    function create_product($product_object)
    {
        if (!property_exists($product_object, 'variants')) {
            return false;
        }
        $first_featured_image = null;
        // Get first featured image
        foreach ($product_object->variants as $variant) {
            if (isset($variant->featured_image->src)) {
                $first_featured_image = $variant->featured_image;
                break;
            }
        }

        if ($first_featured_image == null) {
            if (strpos($product_object->featured_image, "https:") === false) {
                $product_object->featured_image = "https:" . $product_object->featured_image;
            }

            $first_featured_image = (object)array('src' => $product_object->featured_image);
        }


        // Create product attributes
        $product_attributes = $this->create_product_attributes($product_object);
        $product_array = array();

        $product_array['type'] = 'variable';
        $product_array['name'] = $product_object->title;
        $product_array['description'] = $product_object->description;

        $product_array['catalog_visibility'] = "visible";
        $product_array['on_sale'] = true;
        $product_array['purchasable'] = true;
        $product_array['tax_status'] = "taxable";
        $product_array['status'] = "publish";
        $product_array['images'] = array();

        // Stock
        $product_array['manage_stock'] = false;
        $product_array['stock_status'] = "instock";

        // Shipping
        $product_array['shipping_required'] = true;
        $product_array['shipping_taxable'] = true;

        // Map product attributes, collect only attributes which being used by current product
        $i = 0;
        $product_array['default_attributes'] = [];
        $product_array['product_attributes'] = [];
        $product_array['product_term_taxonomy_ids'] = [];

        foreach ($product_object->options as $option) {
            foreach ($product_attributes as $slug => $attribute) {
                if (is_object($attribute)) $attribute = (array)$attribute;

                $attribute_options = [];
                if ($this->sanitize($option->name) == $this->sanitize($attribute['name'])) {
                    $i++;

                    $attribute_options = $option->values;

                    $product_array['attributes'][] = array(
                        'id' => $attribute['id'], // attribute_id
                        'name' => $attribute['name'], // attribute_label
                        'position' => $i,
                        'visible' => true,
                        'variation' => true,
                        'options' => $attribute_options
                    );

                    $product_array['product_attributes'][$slug] = array(
                        'name' => $slug,
                        'value' => "",
                        'position' => $i,
                        'is_visible' => "1",
                        'is_variation' => "1",
                        'is_taxonomy' => "1"
                    );

                    // Collect term_taxonomy ids
                    foreach ($option->values as $option_value) {
                        $option_value_slug = $this->sanitize($option_value);
                        if (array_key_exists($option_value_slug, $attribute['terms'])) {
                            $term_id = $attribute['terms'][$option_value_slug]['id'];
                            $wp_term_taxonomy = Term_Taxonomy::selectOne([], "taxonomy = '{$slug}' AND term_id = '{$term_id}'");
                            $product_array['product_term_taxonomy_ids'][] = $wp_term_taxonomy->term_taxonomy_id;
                        }
                    }

                    $product_array['default_attributes'][$slug] = $this->sanitize($attribute_options[0]);

                    break;
                }
            }
        }

        // Import: Products
        // Create wp_post for product
        $product_wp_post = new Posts();
        $product_wp_post->post_author = 1; // fix for first user
        $product_wp_post->post_date = date('Y-m-d H:i:s');
        $product_wp_post->post_date_gmt = date('Y-m-d H:i:s');
        $product_wp_post->post_content = $product_array['description'];
        $product_wp_post->post_title = $product_array['name'];
        $product_wp_post->post_excerpt = "";
        $product_wp_post->post_status = "publish";
        $product_wp_post->comment_status = "open";
        $product_wp_post->ping_status = "closed";
        $product_wp_post->post_name = $product_object->handle;
        //$product_wp_post->guid = WOO_PRODUCT_BASE_PERMALINKS . $product_object->handle;
        $product_wp_post->guid = $first_featured_image->src;
        $product_wp_post->post_password = "";
        $product_wp_post->to_ping = "";
        $product_wp_post->pinged = "";
        $product_wp_post->post_modified = date('Y-m-d H:i:s');
        $product_wp_post->post_modified_gmt = date('Y-m-d H:i:s');
        $product_wp_post->post_content_filtered = "";
        $product_wp_post->post_parent = 0;
        $product_wp_post->menu_order = 0;
        $product_wp_post->post_type = "product";
        $product_wp_post->post_mime_type = "";
        $product_wp_post->comment_count = 0;

        if ($product_wp_post->save()) {
            // Create product postmeta fields
            //$this->create_postmeta($product_wp_post->ID, "_sku", "");
            //$this->create_postmeta($product_wp_post->ID, "_regular_price", "");
            //$this->create_postmeta($product_wp_post->ID, "_sale_price", "");
            //$this->create_postmeta($product_wp_post->ID, "_sale_price_dates_from", "");
            //$this->create_postmeta($product_wp_post->ID, "_sale_price_dates_to", "");

            $this->create_postmeta($product_wp_post->ID, "total_sales", 0);
            $this->create_postmeta($product_wp_post->ID, "_tax_status", "taxable");

            //$this->create_postmeta($product_wp_post->ID, "_tax_class", "");

            $this->create_postmeta($product_wp_post->ID, "_manage_stock", "no");
            $this->create_postmeta($product_wp_post->ID, "_backorders", "no");

            //$this->create_postmeta($product_wp_post->ID, "_low_stock_amount", "");

            $this->create_postmeta($product_wp_post->ID, "_sold_individually", "no");

            //$this->create_postmeta($product_wp_post->ID, "_weight", "");
            //$this->create_postmeta($product_wp_post->ID, "_length", "");
            //$this->create_postmeta($product_wp_post->ID, "_width", "");
            //$this->create_postmeta($product_wp_post->ID, "_height", "");

            //$this->create_postmeta($product_wp_post->ID, "_upsell_ids", "a:0:{}");
            //$this->create_postmeta($product_wp_post->ID, "_crosssell_ids", "a:0:{}");

            //$this->create_postmeta($product_wp_post->ID, "_purchase_note", "");

            $this->create_postmeta($product_wp_post->ID, "_default_attributes", serialize($product_array['default_attributes']));
            $this->create_postmeta($product_wp_post->ID, "_virtual", "no");
            $this->create_postmeta($product_wp_post->ID, "_downloadable", "no");

            $this->create_postmeta($product_wp_post->ID, "_download_limit", "-1");
            $this->create_postmeta($product_wp_post->ID, "_download_expiry", "-1");

            $this->create_postmeta($product_wp_post->ID, "_stock_status", "instock");
            $this->create_postmeta($product_wp_post->ID, "_wc_average_rating", "0");
            $this->create_postmeta($product_wp_post->ID, "_wc_rating_count", "a:0:{}");
            $this->create_postmeta($product_wp_post->ID, "_wc_review_count", "0");
            $this->create_postmeta($product_wp_post->ID, "_downloadable_files", "a:0:{}");
            $this->create_postmeta($product_wp_post->ID, "_product_attributes", serialize($product_array['product_attributes']));
            $this->create_postmeta($product_wp_post->ID, "_product_version", "3.5.1");


            // BEGIN PRODUCT IMAGE POST META
            $product_image_src_2_post_id = array();
            // Create wp_post for the product image (first variant image)
            $product_image_wp_post = new Posts();
            $product_image_wp_post->post_author = (int)$product_wp_post->post_author;
            $product_image_wp_post->post_date = $product_wp_post->post_date;
            $product_image_wp_post->post_date_gmt = $product_wp_post->post_date_gmt;
            $product_image_wp_post->post_content = "";
            $product_image_wp_post->post_title = $product_wp_post->post_title;
            $product_image_wp_post->post_excerpt = "";
            $product_image_wp_post->post_status = "inherit";
            $product_image_wp_post->comment_status = "open";
            $product_image_wp_post->ping_status = "closed";
            $product_image_wp_post->post_name = $product_wp_post->post_name . "-" . $product_wp_post->ID;
            $product_image_wp_post->to_ping = "";
            $product_image_wp_post->pinged = "";
            $product_image_wp_post->post_modified = $product_wp_post->post_modified;
            $product_image_wp_post->post_modified_gmt = $product_wp_post->post_modified_gmt;
            $product_image_wp_post->post_content_filtered = "";
            $product_image_wp_post->post_parent = 0;
            $product_image_wp_post->guid = $first_featured_image->src;
            $product_image_wp_post->post_password = "";
            $product_image_wp_post->menu_order = 0;
            $product_image_wp_post->post_type = "attachment";
            $product_image_wp_post->post_mime_type = "image/jpeg";
            $product_image_wp_post->comment_count = 0;

            $first_featured_image_file_name = explode('/', $first_featured_image->src);
            $first_featured_image_file_name = end($first_featured_image_file_name);
            $_wp_attachment_metadata = [
                'width' => 801,
                'height' => 801,
                'file' => $first_featured_image_file_name,
                'sizes' => [
                    'thumbnail' => [
                        'width' => 150,
                        'height' => 150,
                        'file' => $first_featured_image_file_name,
                        'mime-type' => 'image/jpeg'
                    ],
                    'medium' => [
                        'width' => 300,
                        'height' => 300,
                        'file' => $first_featured_image_file_name,
                        'mime-type' => 'image/jpeg'
                    ],
                    'medium_large' => [
                        'width' => 768,
                        'height' => 768,
                        'file' => $first_featured_image_file_name,
                        'mime-type' => 'image/jpeg'
                    ]
                ],
                'image_meta' => [
                    'aperture' => '0',
                    'credit' => '',
                    'camera' => '',
                    'caption' => '',
                    'created_timestamp' => '0',
                    'copyright' => '',
                    'focal_length' => '0',
                    'iso' => '0',
                    'shutter_speed' => '0',
                    'title' => '',
                    'orientation' => '0',
                    'keywords' => []
                ]
            ];

            if ($product_image_wp_post->save()) {
                $this->create_postmeta($product_wp_post->ID, "_thumbnail_id", $product_image_wp_post->ID);

                // Create product image meta _wp_attached_file
                $this->create_postmeta($product_image_wp_post->ID, "_wp_attached_file", $product_image_wp_post->guid);

                // Create image meta _wp_attachment_metadata
                $this->create_postmeta($product_image_wp_post->ID, "_wp_attachment_metadata", serialize($_wp_attachment_metadata));

                // Create product image meta _starter_content_theme
                $this->create_postmeta($product_image_wp_post->ID, "_starter_content_theme", 'storefront');

                $product_image_src_2_post_id[$first_featured_image->src] = $product_image_wp_post->ID;
            }
            // END PRODUCT IMAGE POST META


            // Create term relationship for product
            $relationship_data = array();
            $collected_term_taxonomy_ids = array_unique($product_array['product_term_taxonomy_ids']);
            foreach ($collected_term_taxonomy_ids as $term_taxonomy_id) {
                $relationship_data[] = array(
                    $product_wp_post->ID, // object_id
                    $term_taxonomy_id // term_taxonomy_id
                );
            }
            $relationship_data[] = array(
                $product_wp_post->ID, // object_id
                4 // term_taxonomy_id (4 is for vaiable product type)
            );

            Term_Relationships::batchInsert(['object_id', 'term_taxonomy_id'], $relationship_data);

            // BEGIN - CREATE VARIATIONS
            // Map product variants
            $menu_order = 0;
            $wc_product_variant_ids = array(); // Collect all inserted variant's ids
            foreach ($product_object->variants as $variant) {
                $menu_order++;
                $product_variation = array();

                $random_sku = "woo-" . $product_wp_post->ID . "-" . $menu_order;
                $product_variation['sku'] = !empty($variant->sku) ? $variant->sku : $random_sku;

                $sale_price = $variant->price / 100;
                // Min price is 17.55 for mug
                if ($this->ends_with(strtolower($product_object->title), "mug")) {
                    if ($sale_price <= 17) {
                        $sale_price = 17.95;
                    }
                } else {
                    // Min price is 19.95 for t-shirt
                    if ($sale_price <= 19) {
                        $sale_price = 19.95;
                    }
                }

                // Up price to .95
                $money = ceil($sale_price);
                $sale_price = $money - 0.05;

                $regular_price = $sale_price + 10;

                $product_variation['sale_price'] = (string)$sale_price;
                $product_variation['regular_price'] = (string)$regular_price;

                $product_variation['on_sale'] = true;
                $product_variation['status'] = "publish";
                $product_variation['purchasable'] = true;
                $product_variation['virtual'] = false;
                $product_variation['downloadable'] = false;
                $product_variation['manage_stock'] = false;
                $product_variation['stock_quantity'] = null;
                $product_variation['stock_status'] = "instock";
                $product_variation['weight'] = "1";

                // Image: check dupplicate image
                if (isset($variant->featured_image->src)) { // Check variant has no image
                    if (!array_key_exists($variant->featured_image->src, $product_image_src_2_post_id)) {
                        $product_variant_image_wp_post = new Posts();
                        $product_variant_image_wp_post->post_author = (int)$product_wp_post->post_author;
                        $product_variant_image_wp_post->post_date = $product_wp_post->post_date;
                        $product_variant_image_wp_post->post_date_gmt = $product_wp_post->post_date_gmt;
                        $product_variant_image_wp_post->post_content = "";
                        $product_variant_image_wp_post->post_title = $variant->title;
                        $product_variant_image_wp_post->post_excerpt = "";
                        $product_variant_image_wp_post->post_status = "inherit";
                        $product_variant_image_wp_post->comment_status = "open";
                        $product_variant_image_wp_post->ping_status = "closed";
                        $product_variant_image_wp_post->post_name = $this->sanitize($product_variant_image_wp_post->post_title);
                        $product_variant_image_wp_post->to_ping = "";
                        $product_variant_image_wp_post->pinged = "";
                        $product_variant_image_wp_post->post_modified = $product_wp_post->post_modified;
                        $product_variant_image_wp_post->post_modified_gmt = $product_wp_post->post_modified_gmt;
                        $product_variant_image_wp_post->post_content_filtered = "";
                        $product_variant_image_wp_post->post_parent = 0;
                        $product_variant_image_wp_post->guid = $variant->featured_image->src;
                        $product_variant_image_wp_post->post_password = "";
                        $product_variant_image_wp_post->menu_order = 0;
                        $product_variant_image_wp_post->post_type = "attachment";
                        $product_variant_image_wp_post->post_mime_type = "image/jpeg";
                        $product_variant_image_wp_post->comment_count = 0;
                        if ($product_variant_image_wp_post->save()) {
                            // Create product image meta _wp_attached_file
                            $product_variant_image_wp_post_meta = new Postmeta();
                            $product_variant_image_wp_post_meta->post_id = $product_variant_image_wp_post->ID;
                            $product_variant_image_wp_post_meta->meta_key = '_wp_attached_file';
                            $product_variant_image_wp_post_meta->meta_value = $product_variant_image_wp_post->guid;
                            $product_variant_image_wp_post_meta->save();

                            // Create image meta _wp_attachment_metadata
                            $product_variant_image_wp_post_file_name = explode('/', $variant->featured_image->src);
                            $product_variant_image_wp_post_file_name = end($product_variant_image_wp_post_file_name);
                            $product_variant_image_wp_post_wp_attachment_metadata = [
                                'width' => 801,
                                'height' => 801,
                                'file' => $product_variant_image_wp_post_file_name,
                                'sizes' => [
                                    'thumbnail' => [
                                        'width' => 150,
                                        'height' => 150,
                                        'file' => $product_variant_image_wp_post_file_name,
                                        'mime-type' => 'image/jpeg'
                                    ],
                                    'medium' => [
                                        'width' => 300,
                                        'height' => 300,
                                        'file' => $product_variant_image_wp_post_file_name,
                                        'mime-type' => 'image/jpeg'
                                    ],
                                    'medium_large' => [
                                        'width' => 768,
                                        'height' => 768,
                                        'file' => $product_variant_image_wp_post_file_name,
                                        'mime-type' => 'image/jpeg'
                                    ]
                                ],
                                'image_meta' => [
                                    'aperture' => '0',
                                    'credit' => '',
                                    'camera' => '',
                                    'caption' => '',
                                    'created_timestamp' => '0',
                                    'copyright' => '',
                                    'focal_length' => '0',
                                    'iso' => '0',
                                    'shutter_speed' => '0',
                                    'title' => '',
                                    'orientation' => '0',
                                    'keywords' => []
                                ]
                            ];
                            $product_variant_image_wp_post_meta = new Postmeta();
                            $product_variant_image_wp_post_meta->post_id = $product_variant_image_wp_post->ID;
                            $product_variant_image_wp_post_meta->meta_key = '_wp_attachment_metadata';
                            $product_variant_image_wp_post_meta->meta_value = serialize($product_variant_image_wp_post_wp_attachment_metadata);
                            $product_variant_image_wp_post_meta->save();

                            // Create product image meta _starter_content_theme
                            $product_variant_image_wp_post_meta = new Postmeta();
                            $product_variant_image_wp_post_meta->post_id = $product_variant_image_wp_post->ID;
                            $product_variant_image_wp_post_meta->meta_key = '_starter_content_theme';
                            $product_variant_image_wp_post_meta->meta_value = 'storefront';
                            $product_variant_image_wp_post_meta->save();
                            // END PRODUCT IMAGE POST META

                            $product_image_src_2_post_id[$variant->featured_image->src] = $product_variant_image_wp_post->ID;
                            $product_variation['image'] = array(
                                'id' => $product_variant_image_wp_post->ID,
                                'name' => $product_variant_image_wp_post->post_name
                            );
                        }
                    } else {
                        $product_variation['image'] = array(
                            'id' => $product_image_src_2_post_id[$variant->featured_image->src]
                        );
                    }
                }

                // Attribute
                // Option 1
                if (count($product_array['attributes']) > 0) {
                    $product_variation['attributes'][] = array(
                        'id' => $product_attributes["pa_" . $this->sanitize($product_array['attributes'][0]['name'])]['id'],
                        'name' => $product_array['attributes'][0]['name'],
                        'option' => $variant->option1
                    );
                }

                // Option 2
                if (count($product_array['attributes']) > 1) {
                    $product_variation['attributes'][] = array(
                        'id' => $product_attributes["pa_" . $this->sanitize($product_array['attributes'][1]['name'])]['id'],
                        'name' => $product_array['attributes'][1]['name'],
                        'option' => $variant->option2
                    );
                }

                // Option 3
                if (count($product_array['attributes']) > 2) {
                    $product_variation['attributes'][] = array(
                        'id' => $product_attributes["pa_" . $this->sanitize($product_array['attributes'][2]['name'])]['id'],
                        'name' => $product_array['attributes'][2]['name'],
                        'option' => $variant->option3
                    );
                }

                $product_variation['menu_order'] = $menu_order;

                // Create variaton
                $product_variant_wp_post = new Posts();
                $product_variant_wp_post->post_author = (int)$product_wp_post->post_author;
                $product_variant_wp_post->post_date = $product_wp_post->post_date;
                $product_variant_wp_post->post_date_gmt = $product_wp_post->post_date_gmt;
                $product_variant_wp_post->post_content = "";
                $product_variant_wp_post->post_title = $product_object->title;
                $product_variant_wp_post->post_excerpt = "";
                $product_variant_wp_post->post_status = $product_variation['status'];
                $product_variant_wp_post->comment_status = "closed";
                $product_variant_wp_post->ping_status = "closed";
                $product_variant_wp_post->post_name = $this->sanitize($product_variant_wp_post->post_title) . "-" . $menu_order;
                $product_variant_wp_post->guid = WOO_PRODUCT_BASE_PERMALINKS . $this->sanitize($product_variant_wp_post->post_title) . "-" . $menu_order;
                $product_variant_wp_post->post_password = "";
                $product_variant_wp_post->to_ping = "";
                $product_variant_wp_post->pinged = "";
                $product_variant_wp_post->post_modified = $product_wp_post->post_modified;
                $product_variant_wp_post->post_modified_gmt = $product_wp_post->post_modified_gmt;
                $product_variant_wp_post->post_content_filtered = "";
                $product_variant_wp_post->post_parent = $product_wp_post->ID;
                $product_variant_wp_post->guid = $product_wp_post->guid;
                $product_variant_wp_post->menu_order = $product_variation['menu_order'];
                $product_variant_wp_post->post_type = "product_variation";
                $product_variant_wp_post->post_mime_type = "";
                $product_variant_wp_post->comment_count = 0;

                if ($product_variant_wp_post->save()) {
                    $wc_product_variant_ids[] = $product_variant_wp_post->ID;
                    $variation_post_meta_data = array();

                    $variation_post_meta_data[] = array(
                        $product_variant_wp_post->ID, // post_id (variation id)
                        "_sku", // meta_key
                        $product_variation['sku'], // meta_value
                    );

                    $variation_post_meta_data[] = array(
                        $product_variant_wp_post->ID, // post_id (variation id)
                        "_regular_price", // meta_key
                        $product_variation['regular_price'], // meta_value
                    );

                    $variation_post_meta_data[] = array(
                        $product_variant_wp_post->ID, // post_id (variation id)
                        "_sale_price", // meta_key
                        $product_variation['sale_price'], // meta_value
                    );

                    if (isset($product_variation['image'])) {
                        $variation_post_meta_data[] = array(
                            $product_variant_wp_post->ID, // post_id (variation id)
                            "_thumbnail_id", // meta_key
                            $product_variation['image']['id'], // meta_value
                        );
                    }

                    // Variant's attributes
                    foreach ($product_variation['attributes'] as $v_attribute) {
                        $variation_post_meta_data[] = array(
                            $product_variant_wp_post->ID, // post_id (variation id)
                            "attribute_pa_" . $this->sanitize($v_attribute['name']), // meta_key
                            $this->sanitize($v_attribute['option']), // meta_value
                        );
                    }

                    Postmeta::batchInsert(['post_id', 'meta_key', 'meta_value'], $variation_post_meta_data);
                }

            }

            // Update product: Add variations to products
            $this->create_option("_transient_wc_product_children_" . $product_wp_post->ID, serialize(array(
                "all" => $wc_product_variant_ids,
                "visible" => $wc_product_variant_ids
            )));

            // Update _product_image_gallery to add rest of product images
            $product_image_ids = array();
            $image_id_arr = array_values($product_image_src_2_post_id);
            foreach ($image_id_arr as $image_id) {
                // Do not add product thumbnail image
                if ($image_id != $product_image_wp_post->ID) {
                    $product_image_ids[] = $image_id;
                }
            }
            $this->create_postmeta($product_wp_post->ID, "_product_image_gallery", implode(",", $product_image_ids));

//			$product_images = [];
//			foreach($product_object->images as $imgSrc) {
//				if(strpos($imgSrc, "https:") === false){
//					$imgSrc = "https:" . $imgSrc;
//				}
//				$product_images[] = $imgSrc;
//			}
            // Save product images
            //$this->create_postmeta($product_wp_post->ID, "_fgfu_image_url", serialize($product_images));

        }

        return $product_wp_post->ID;
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

    function sanitize($string)
    {
        return wc_sanitize_taxonomy_name(stripslashes($string));
    }

    function ends_with($str, $end)
    {
        return substr_compare($str, $end, -strlen($end)) === 0;
    }

}