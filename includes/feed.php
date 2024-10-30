<?php
if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(plugin_dir_path(__FILE__) . 'install.php')) {
    include_once(plugin_dir_path(__FILE__) . 'install.php');
}

function heyday_search_get_product_data($product_id,$include_meta_data = false) {
    // Ensure the product exists
    $product = wc_get_product($product_id);
    if (!$product) {
        return null;
    }

    // Pattern for removing unnecessary whitespace and special characters
    $pattern = '/[\r\n]+|&nbsp;/';
    
    $product_data = $product->get_data();
    
    $product_data['name'] = isset($product_data['name']) ? preg_replace($pattern, '', wp_strip_all_tags($product_data['name'])) : '';
    $product_data['short_description'] = preg_replace($pattern, '', wp_strip_all_tags($product->get_short_description()));
    $product_data['description'] = preg_replace($pattern, '', wp_strip_all_tags($product->get_description()));
    $product_data['link'] = urldecode(get_permalink($product_id));
    $product_data['image_link'] = $product->get_image_id() ? wp_get_attachment_url($product->get_image_id()) : '';
    $product_data['postType'] = 'product';
    $product_data['currency'] = html_entity_decode(get_woocommerce_currency_symbol(), ENT_COMPAT, 'UTF-8');
    
    $sale_price = null;

    $regular_price = $product->get_regular_price() ?: 0;
    $current_price = $product->get_price() ?: 0;

    $discount = apply_filters('advanced_woo_discount_rules_get_product_discount_price', $regular_price, $product);
    if ($discount !== false && (float)$discount < (float)$current_price) {
        $sale_price = $discount;
    }

    $product_data['sale_price'] = $sale_price ? $sale_price : null;

    $current_timestamp = time();

    $rightpress_price_meta = null;
    if (!empty($product_data['meta_data'])) {
        foreach ($product_data['meta_data'] as $meta) {
            if ($meta->key === '_rightpress_prices') {
                $rightpress_price_meta = $meta->value;
                $product_data['meta_data'] = [
                    (object)[
                        'key' => '_rightpress_prices',
                        'value' => $meta->value
                    ]
                ];
                break;
            }
        }
    }

    if (!$rightpress_price_meta) 
    {
        if (!empty($product_data['meta_data']) && !$include_meta_data)
        {
            unset($product_data['meta_data']);
        }
    }

    $gallery_image_ids = $product->get_gallery_image_ids();
    $gallery_image_urls = [];
    if (!empty($gallery_image_ids)) {
        foreach ($gallery_image_ids as $image_id) {
            $gallery_image_urls[] = wp_get_attachment_url($image_id);
        }
    }
    $product_data['gallery_images'] = $gallery_image_urls;

    $categories = get_the_terms($product_id, 'product_cat');
    $category_names = [];
    if (!empty($categories) && !is_wp_error($categories)) {
        foreach ($categories as $category) {
            $category_names[] = wp_strip_all_tags($category->name);
        }
    }
    $product_data['categories'] = $category_names;

    $attributes_data = [];
    $attributes = $product->get_attributes();
    if (!empty($attributes)) {
        foreach ($attributes as $attribute_slug => $attribute) {
            $normalized_name = wc_attribute_label($attribute_slug);
            $normalized_name = urldecode($normalized_name);

            if (strpos($normalized_name, 'pa_') === 0) {
                $normalized_name = str_replace('pa_', '', $normalized_name);
            }

            if ($attribute->is_taxonomy()) {
                $terms = wc_get_product_terms($product->get_id(), $attribute->get_taxonomy(), array('fields' => 'names'));
                $attributes_data[$normalized_name] = isset($attributes_data[$normalized_name]) 
                    ? array_merge($attributes_data[$normalized_name], $terms) 
                    : $terms;
            } else {
                $options = $attribute->get_options();
                $attributes_data[$normalized_name] = isset($attributes_data[$normalized_name]) 
                    ? array_merge($attributes_data[$normalized_name], $options) 
                    : $options;
            }
        }
        foreach ($attributes_data as $key => $values) {
            $attributes_data[$key] = array_unique($values);
        }
    }
    $product_data['attributes'] = $attributes_data;

    $brand_terms = wp_get_post_terms($product_id, 'product_brand');
    if (!empty($brand_terms) && !is_wp_error($brand_terms)) {
        $brand = $brand_terms[0];
        $product_data['brand_name'] = wp_strip_all_tags($brand->name);
        $product_data['brand_link'] = get_term_link($brand->term_id);
        $product_data['brand_image'] = get_term_meta($brand->term_id, 'brand_image', true);
    }

    if ($product->is_type('variable')) {
        $product_data['variations'] = [];
        $variations = $product->get_children();
        foreach ($variations as $variation_id) {
            $variation = wc_get_product($variation_id);
            if ($variation) {
                $variation_data = $variation->get_data();
                $variation_data['name'] = wp_strip_all_tags($variation_data['name']);
                $variation_data['variation_link'] = get_permalink($variation_id);
                $variation_image_id = $variation->get_image_id();
                if ($variation_image_id) {
                    $variation_data['image_url'] = wp_get_attachment_url($variation_image_id);
                }

                $variation_attributes = $variation->get_attributes();
                $labeled_attributes = [];
                foreach ($variation_attributes as $attr_slug => $attribute_value) {
                    $attribute_label = wc_attribute_label($attr_slug, $product);
                    $labeled_attributes[$attribute_label] = urldecode($attribute_value);
                }
                $variation_data['labeled_attributes'] = $labeled_attributes;

                $rightpress_price_meta = null;
                if (!empty($variation_data['meta_data'])) {
                    foreach ($variation_data['meta_data'] as $meta) {
                        if ($meta->key === '_rightpress_prices') {
                            $rightpress_price_meta = $meta->value;
                            $variation_data['meta_data'] = [
                                (object)[
                                    'key' => '_rightpress_prices',
                                    'value' => $meta->value
                                ]
                            ];
                            break;
                        }
                    }
                }

                if (!$rightpress_price_meta) 
                {
                    if (!empty($variation_data['meta_data']) && !$include_meta_data)
                    {
                        unset($variation_data['meta_data']);
                    }
                }

                $product_data['variations'][] = $variation_data;
            }
        }
    }


    return $product_data;
}


function heyday_search_get_post_data($post_id) {
    $pattern = '/[\r\n]+|&nbsp;/';
    $post = get_post($post_id);
    if (!$post) {
        return null;
    }

    $category_names = [];
    $categories = get_the_category($post_id);
    if (!empty($categories) && !is_wp_error($categories)) {
        foreach ($categories as $category) {
            $category_names[] = wp_strip_all_tags($category->name);
        }
    }

    $tags = [];
    $post_tags = get_the_tags($post_id);
    if (!empty($post_tags) && !is_wp_error($post_tags)) {
        foreach ($post_tags as $tag) {
            $tags[] = wp_strip_all_tags($tag->name);
        }
    }

    $author = get_the_author_meta('display_name', $post->post_author);

    $content = wp_strip_all_tags($post->post_content);
    $content = preg_replace($pattern, '', $content);
    $description = wp_strip_all_tags(get_the_excerpt($post));
    $description = preg_replace($pattern, '', $description);

    $post_data = [
        'title' => wp_strip_all_tags($post->post_title),
        'postType' => $post->post_type,
        'description' => $description,
        'modifyTime' => $post->post_modified,
        'creationTime' => strtotime($post->post_date),
        'link' => get_permalink($post->ID),
        'author' => $author,
        'status' => $post->post_status,
        'image_link' => get_the_post_thumbnail_url($post->ID),
        'category_names' => $category_names,
        'tags' => $tags,
        'content' => $content,
        'meta_data' => get_post_meta($post->ID),
        'docBody' => $content,
    ];

    return $post_data;
}

function heyday_search_get_all_items_pagination(WP_REST_Request $request) {
    global $wpdb;

    $set_meta_data_provided = $request->has_param('set_meta_data');
    $set_meta_data = $set_meta_data_provided ? filter_var($request->get_param('set_meta_data'), FILTER_VALIDATE_BOOLEAN) : false;
    
    $include_meta_data = $request->get_param('include_meta_data') ? filter_var($request->get_param('include_meta_data'), FILTER_VALIDATE_BOOLEAN) : false;
    $per_page = $request->get_param('per_page') ? intval($request->get_param('per_page')) : 512;
    $page = $request->get_param('page') ? intval($request->get_param('page')) : 1;
    $post_type = $request->get_param('post_type') ? sanitize_text_field($request->get_param('post_type')) : 'product';

    if ($post_type === 'product' && !in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        return new WP_REST_Response(array('message' => 'WooCommerce must be installed and activated to retrieve products.'), 200);
    }

    if ($set_meta_data_provided) {
        if ($set_meta_data) {
            heyday_force_update_option('heyday_set_meta_data', true);
        } else {
            heyday_force_delete_option('heyday_set_meta_data');
        }
    }

    $offset = ($page - 1) * $per_page;

    $query = $wpdb->prepare("
        SELECT ID FROM {$wpdb->posts}
        WHERE post_type = %s
        AND post_status = 'publish'
        LIMIT %d OFFSET %d
    ", $post_type, $per_page, $offset);

    $post_ids = $wpdb->get_col($query);

    if (empty($post_ids)) {
        return new WP_REST_Response(array('message' => 'No posts found on this page'), 200);
    }

    $items = [];
    foreach ($post_ids as $post_id) {
        if ($post_type === 'product') {
            $item_data = heyday_search_get_product_data($post_id,$include_meta_data);
        } else {
            $item_data = heyday_search_get_post_data($post_id);
        }
        if ($item_data) {
            $items[] = $item_data;
        }
    }

    wp_send_json($items, 200, JSON_UNESCAPED_UNICODE);
}

function heyday_search_register_api_routes() {
    register_rest_route('heyday-search/v1', '/items-pagination', array(
        'methods' => 'GET',
        'callback' => 'heyday_search_get_all_items_pagination',
        'args' => array(
            'page' => array(
                'required' => false,
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
            'per_page' => array(
                'required' => false,
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ),
            'post_type' => array(
                'required' => false,
                'validate_callback' => function($param, $request, $key) {
                    return is_string($param);
                }
            ),
            'include_meta_data' => array(
                'required' => false,
                'validate_callback' => function($param, $request, $key) {
                    return is_bool(filter_var($param, FILTER_VALIDATE_BOOLEAN));
                }
            ),
            'set_meta_data' => array(
                'required' => false,
                'validate_callback' => function($param, $request, $key) {
                    return is_bool(filter_var($param, FILTER_VALIDATE_BOOLEAN));
                }
            ),
        ),
    ));
}


function heyday_send_product_update_to_server($product_id) {
    $include_meta_data = get_option('heyday_set_meta_data', false);
    $product_data = heyday_search_get_product_data($product_id, $include_meta_data);

    $affId = get_option('heyday_merchant_feed_affid');
    $password = get_option('heyday_merchant_feed_pass');
    $site_url = get_site_url();
    $parsed_url = parse_url($site_url);
    $domain = $parsed_url['host'];

    if ($product_data) {
        $json_data = wp_json_encode($product_data, JSON_UNESCAPED_UNICODE);

        $args = array(
            'body'        => $json_data,
            'headers'     => array('Content-Type' => 'application/json'),
            'timeout'     => 20,
        );

        $server_url = 'https://api.heyday.io/api/appEnvens/'.$affId.'/' .$password . '/' .$domain;
        $response = wp_remote_post($server_url, $args);

        if (is_wp_error($response)) {
            error_log('Failed to send product update: ' . $response->get_error_message());
        } 
    } else {
        error_log('Failed to retrieve product data. Product ID: ' . $product_id);
    }
}

add_action('woocommerce_update_product', 'heyday_send_product_update_to_server');
add_action('woocommerce_new_product', 'heyday_send_product_update_to_server');
add_action('woocommerce_delete_product', 'heyday_send_product_update_to_server');
add_action('woocommerce_product_set_stock_status', 'heyday_send_product_update_to_server');

add_action('rest_api_init', 'heyday_search_register_api_routes');

?>
