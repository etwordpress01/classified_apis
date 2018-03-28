<?php

if (!class_exists('ClassifiedAppListingRoutes')) {

    class ClassifiedAppListingRoutes extends WP_REST_Controller {

        /**
         * Register the routes for the objects of the controller.
         */
        public function register_routes() {
            $version = '1';
            $namespace = 'api/v' . $version;
            $base = 'search_listing';
            register_rest_route($namespace, '/' . $base, array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_search_listing_ads'),
                    'args' => array(
                    ),
                ),
            ));
        }

        /**
         * Get categories
         *
         * @param WP_REST_Request $request Full data about the request.
         * @return WP_Error|WP_REST_Response
         */
        public function get_search_listing_ads($request) {
            global $wpdb, $wp_query;

            $params = $request->get_params();

            //search filters
            if (!empty($params['category'])) {
                $category = classified_pro_get_term_by_type('slug', $params['category'], 'ca_category', 'id');
            } else {
                if (is_tax('ca_category')) {
                    $category = $wp_query->get_queried_object_id();
                } else {
                    $category = '';
                }
            }

            $last_sub_cat_val = '';
            if (!empty($params['sub-category'])) {
                $last_sub_cat_val = array_values(array_slice($params['sub-category'], -1))[0];
            }

            if (!empty($last_sub_cat_val)) {
                $sub_category = classified_pro_get_term_by_type('slug', $last_sub_cat_val, 'ca_category', 'id');
            } else {
                if (is_tax('ca_category')) {
                    $sub_category = $wp_query->get_queried_object_id();
                } else {
                    $sub_category = '';
                }
            }

            $orderby = !empty($params['sortby']) ? $params['sortby'] : 'ID';
            $order = !empty($params['orderby']) ? $params['orderby'] : 'DESC';
            $showposts = !empty($params['showposts']) ? $params['showposts'] : 10;
            $min_price = !empty($params['minprice']) ? $params['minprice'] : '';
            $max_price = !empty($params['maxprice']) ? $params['maxprice'] : '';
            $currency = !empty($params['currency_symbol']) ? $params['currency_symbol'] : '';
            $s = !empty($params['keyword']) ? sanitize_text_field($params['keyword']) : '';

            //Type
            if (!empty($params['type'])) {
                $type = $params['type'];
            } else {
                if (is_tax('ca_types')) {
                    $type = $wp_query->get_queried_object_id();
                } else {
                    $type = '';
                }
            }

            //Condition
            if (!empty($params['condition'])) {
                $condition = $params['condition'];
            } else {
                if (is_tax('ca_conditions')) {
                    $condition = $wp_query->get_queried_object_id();
                } else {
                    $condition = '';
                }
            }

            //Warranty
            if (!empty($params['warranty'])) {
                $warranty = $params['warranty'];
            } else {
                if (is_tax('ca_ad_warranty')) {
                    $warranty = $wp_query->get_queried_object_id();
                } else {
                    $warranty = '';
                }
            }

            //By category
            $taxonomy_query = array();
            if (!empty($params['category']) && empty($params['sub-category'])) {
                $taxonomy_query['tax_query'] = array(
                    array(
                        'taxonomy' => 'ca_category',
                        'field' => 'id',
                        'terms' => $category, // Where term_id of Term 1 is "1".
                    )
                );
            } else {
                $taxonomy_query['tax_query'] = array(
                    array(
                        'taxonomy' => 'ca_category',
                        'field' => 'id',
                        'terms' => $sub_category, // Where term_id of Term 1 is "1".
                    )
                );
            }


            //Location Type Search
            $location = !empty($params['location']) ? $params['location'] : '';
            if (!empty($location)) {
                $query_relation = array('relation' => 'OR',);
                $location_args = array();
                $location_args[] = array(
                    'key' => 'profile_address',
                    'value' => $location,
                    'compare' => 'LIKE'
                );
                $meta_query_args[] = array_merge($query_relation, $location_args);
            }

            //Price Range Search
            if (!empty($min_price) && !empty($max_price)) {
                $price_range = array($min_price, $max_price);
                $query_relation = array('relation' => 'OR',);
                $range_args = array();
                $range_args = array(
                    'key' => 'price',
                    'value' => $price_range,
                    'compare' => 'BETWEEN'
                );
                $meta_query_args[] = array_merge($query_relation, $range_args);
            }

            if (!empty($currency)) {
                $query_relation = array('relation' => 'OR',);
                $currency_args = array(
                    'key' => 'currency_symbol',
                    'value' => $currency,
                    'compare' => '='
                );
                $meta_query_args[] = array_merge($query_relation, $currency_args);
            }

            //Condition of item based Search
            if (!empty($condition)) {
                $condition_id = classified_pro_get_term_by_type($from = 'slug', $condition, 'ca_conditions', 'id');
                if (!empty($condition_id)) {
                    $query_relation = array('relation' => 'OR',);
                    $condition_args = array(
                        'key' => 'condition',
                        'value' => $condition_id,
                        'compare' => '='
                    );
                    $meta_query_args[] = array_merge($query_relation, $condition_args);
                }
            }

            //Type of item based Search
            if (!empty($type)) {
                $type_id = classified_pro_get_term_by_type($from = 'slug', $type, 'ca_types', 'id');
                if (!empty($type_id)) {
                    $query_relation = array('relation' => 'OR',);
                    $type_args = array(
                        'key' => 'type',
                        'value' => $type_id,
                        'compare' => '='
                    );
                    $meta_query_args[] = array_merge($query_relation, $type_args);
                }
            }

            //Type of item warranty Search
            if (!empty($warranty)) {
                $warranty_id = classified_pro_get_term_by_type($from = 'slug', $warranty, 'ca_ad_warranty', 'id');
                if (!empty($warranty_id)) {
                    $query_relation = array('relation' => 'OR',);
                    $warranty_args = array(
                        'key' => 'warranty',
                        'value' => $warranty_id,
                        'compare' => '='
                    );
                    $meta_query_args[] = array_merge($query_relation, $warranty_args);
                }
            }

            if ($orderby == 'recent') {
                $orderby = 'ID';
            } else if ($orderby == 'title') {
                $orderby = 'title';
            } else {
                $orderby = 'ID';
            }

            $query_args = array(
                'posts_per_page' => -1,
                'post_type' => 'ca_classified_ads',
                'order' => $order,
                'orderby' => $orderby,
                'post_status' => 'publish',
                'ignore_sticky_posts' => 1
            );

            if (!empty($meta_query_args)) {
                $query_relation = array('relation' => 'AND',);
                $meta_query_args = array_merge($query_relation, $meta_query_args);
                $query_args['meta_query'] = $meta_query_args;
            }

            //search by keyword
            if (!empty($s)) {
                $query_args['s'] = $s;
            }

            if (!empty($category) || !empty($sub_category)) {
                $query_args = array_merge($query_args, $taxonomy_query);
            }

            $ads_query = new WP_Query($query_args);
            if ($ads_query->have_posts()) {
                while ($ads_query->have_posts()) {
                    $item = array();
                    $ads_query->the_post();
                    global $post;
                    $height = intval(220);
                    $width = intval(220);
                    $thumbnail = classified_pro_prepare_thumbnail($post->ID, intval($width), intval($height));

                    if (empty($thumbnail)) {
                        $thumbnail = get_template_directory_uri() . '/images/ad-fallback.jpg';
                    }
                    if (function_exists('fw_get_db_post_option')) {
                        $address = fw_get_db_post_option($post->ID, 'profile_address', $default_value = null);
                    } else {
                        $address = '';
                    }

                    if (function_exists('fw_get_db_post_option')) {
                        $price = fw_get_db_post_option($post->ID, 'price', $default_value = null);
                        $currency = fw_get_db_post_option($post->ID, 'currency_symbol', $default_value = null);
                    } else {
                        $price = '';
                        $currency = '';
                    }

                    $currency_symbol = '';
                    if (!empty($currency)) {
                        $currency_symbol = classified_pro_prepare_currency_symbols()[$currency]['symbol'] . '&nbsp;';
                    }
                    if (function_exists('fw_get_db_post_option')) {
                        $phone = fw_get_db_post_option($post->ID, 'phone', $default_value = null);
                    } else {
                        $phone = '';
                    }

                    if (function_exists('fw_get_db_post_option')) {
                        $gallery = fw_get_db_post_option($post->ID, 'gallery', $default_value = null);
                        $featured = fw_get_db_post_option($post->ID, 'featured', true);
                    } else {
                        $gallery = array();
                        $featured = '';
                    }
                    $current_date = date('Y, m, d');
                    $featured = '';
                    $featured = get_post_meta($post->ID, 'featured', true);
                    $featured = !empty($featured) ? date('Y, m, d', $featured) : '';

                    $favourite_allowed = apply_filters('classified_pro_get_package_feature', 'fav');


                    if (!empty($gallery)) {
                        $count = count($gallery);
                    } else {
                        $count = false;
                    }

                    $item['ad_image_url'] = esc_url($thumbnail);
                    if (isset($count) && $count != 0) {
                        $item['ad_gallery_count'] = $count;
                    }

                    $item['ad_url'] = get_the_permalink($post->ID);
                    $item['ad_title'] = get_the_title($post->ID);
                    $item['ad_human_date'] = human_time_diff(get_post_time(), current_time('timestamp'));
                    $item['ad_timstamp'] = get_the_date('Y-m-d');
                    if (!empty($price)) {
                        $item['ad_price'] = esc_attr($currency_symbol) . esc_attr($price);
                    }
                    if (!empty($address)) {
                        $item['ad_address'] = esc_attr($address);
                    }
                    if (!empty($phone)) {
                        $item['ad_phone'] = esc_attr($phone);
                    }
                    if (!empty($featured) && $featured > $current_date) {
                        $item['ad_is_featured'] = 'yes';
                    } else {
                        $item['ad_is_featured'] = 'no';
                    }
                    if (!empty($favourite_allowed) && $favourite_allowed == 'allowed') {
                        $item['ad_is_favourite'] = 'yes';
                    } else {
                        $item['ad_is_favourite'] = 'no';
                    }

                    $items[] = $item;
                }
            }

            return new WP_REST_Response($items, 200);
        }

    }
}

add_action('rest_api_init', function () {
    $controller = new ClassifiedAppListingRoutes;
    $controller->register_routes();
});
