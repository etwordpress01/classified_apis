<?php

if (!class_exists('ClassifiedAppAdDetailRoutes')) {

    class ClassifiedAppAdDetailRoutes extends WP_REST_Controller {

        /**
         * Register the routes for the objects of the controller.
         */
        public function register_routes() {
            $version = '1';
            $namespace = 'api/v' . $version;
            $base = 'ad_detail';
            register_rest_route($namespace, '/' . $base, array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_ad_detail'),
                    'args' => array(
                    ),
                ),
            ));
        }

        /**
         * Get ad detail data
         *
         * @param WP_REST_Request $request Full data about the request.
         * @return WP_Error|WP_REST_Response
         */
        public function get_ad_detail($request) {

            $params = $request->get_params();
            if (!empty($params['post_id'])) {
                $post_id = $params['post_id'];
                $post_data = get_post($post_id);

                $query_args = array(
                    'posts_per_page' => -1,
                    'post_type' => 'ca_classified_ads',
                    'p' => intval($post_id),
                    'post_status' => 'publish',
                    'ignore_sticky_posts' => 1
                );

                $query = new WP_Query($query_args);


                if (!empty($query->have_posts())) {
                    while ($query->have_posts()) {
                        $query->the_post();
                        global $post;

                        $post_view_count = 0;
                        $gallery = '';
                        $condition = '';
                        $phone = '';
                        $type = '';
                        $warranty = '';
                        $first_name = '';
                        $last_name = '';
                        $country = '';
                        $city = '';
                        $price = '';
                        $currency = '';
                        $address = '';
                        $address_latitude = '';
                        $address_longitude = '';

                        if (function_exists('fw_get_db_post_option')) {
                            $post_view_count = get_post_meta($post->ID, 'set_blog_view', true);
                            $gallery = fw_get_db_post_option($post->ID, 'gallery', true);
                            $condition = fw_get_db_post_option($post->ID, 'condition', true);
                            $type = fw_get_db_post_option($post->ID, 'type', true);
                            $price = fw_get_db_post_option($post->ID, 'price', true);
                            $currency = fw_get_db_post_option($post->ID, 'currency_symbol', true);
                            $phone = fw_get_db_post_option($post->ID, 'phone', true);
                            $warranty = fw_get_db_post_option($post->ID, 'warranty', true);
                            $first_name = fw_get_db_post_option($post->ID, 'first_name', true);
                            $last_name = fw_get_db_post_option($post->ID, 'last_name', true);
                            $country = fw_get_db_post_option($post->ID, 'country', true);
                            $city = fw_get_db_post_option($post->ID, 'city', true);
                            $address = fw_get_db_post_option($post->ID, 'profile_address', true);
                            $address_latitude = fw_get_db_post_option($post->ID, 'address_latitude', true);
                            $address_longitude = fw_get_db_post_option($post->ID, 'address_longitude', true);
                            $featured = fw_get_db_post_option($post->ID, 'address_longitude', true);
                        }

                        $currency_symbol = '';
                        if (!empty($currency)) {
                            $currency_symbol = classified_pro_prepare_currency_symbols()['PKR']['symbol'];
                        }
                        
                        $current_date = date('Y, m, d');
                        $featured = '';
                        $featured = get_post_meta($post->ID, 'featured', true);
                        $featured = !empty($featured) ? date('Y, m, d', $featured) : '';

                        if (!empty($gallery)) {
                            $gallery_data = array();
                            foreach ($gallery as $key => $value) {
                                $width = 120;
                                $height = 120;

                                $image_meta = '';
                                if (!empty($value['attachment_id'])) {
                                    $thumbnail = classified_pro_prepare_image_source($value['attachment_id'], intval($width), intval($height));
                                    $image_meta = classified_pro_get_image_metadata($value['attachment_id']);
                                }

                                $gallery_data[$key]['gallery_full_url'] = $value['url'];
                                $gallery_data[$key]['gallery_thumb'] = $thumbnail;
                                $gallery_data[$key]['gallery_image_meta'] = $image_meta;
                            }
                        }

                        $favourite_allowed = apply_filters('classified_pro_get_package_feature', 'fav');

//                $user_post_count = count_user_posts($user_ID, 'ca_classified_ads');
//                if (function_exists('fw_get_db_settings_option')) {
//                    $show_author_ads = fw_get_db_settings_option('show_author_ads');
//                    $show_more_ads = fw_get_db_settings_option('show_more_ads');
//                    $offer_policy = fw_get_db_settings_option('offer_price_policy');
//                } else {
//                    $show_author_ads = '';
//                    $show_more_ads = '';
//                    $offer_policy = '';
//                }

                        $item = array();
                        $item['ad_id'] = $post->ID;
                        $item['ad_custom_id'] = 'cp-' . $post->ID;
                        $item['ad_title'] = get_the_title($post->ID);
                        $item['ad_phone'] = $phone;
                        if (!empty($price)) {
                            $item['ad_price'] = esc_attr($currency_symbol) . esc_attr($price);
                        }
                        $item['ad_link'] = get_the_permalink($post->ID);
                        $item['ad_author'] = get_the_author();
                        $item['ad_author_url'] = esc_url(get_author_posts_url($post->post_author));
                        if (!empty($address)) {
                            $item['ad_address'] = esc_attr($address);
                        }
                        if (!empty($post_view_count)) {
                            $item['ad_post_count'] = esc_attr($post_view_count);
                        }
                        if (!empty($featured) && $featured > $current_date) {
                            $item['ad_is_featured'] = 'Yes';
                        } else {
                            $item['ad_is_featured'] = 'No';
                        }
                        if (!empty($gallery)) {
                            $item['ad_gallery_data'] = $gallery_data;
                        }
                        $item['ad_description'] = get_the_content();
                        if (!empty($favourite_allowed) && $favourite_allowed == 'allowed') {
                            $item['ad_is_favourite'] = 'yes';
                        } else {
                            $item['ad_is_favourite'] = 'no';
                        }
                        
                        $item['ad_seller_details'] = '';


                        $items[] = $item;

                        print_r($items);
                        die;
                    }
                }
            }
        }

    }

}

add_action('rest_api_init', function () {
    $controller = new ClassifiedAppAdDetailRoutes;
    $controller->register_routes();
});
