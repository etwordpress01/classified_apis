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
                
                $query_args = array(
                    'posts_per_page' => -1,
                    'post_type' => 'ca_classified_ads',
                    'p' => intval($post_id),
                    'post_status' => 'publish',
                    'ignore_sticky_posts' => 1
                );

                $query = new WP_Query($query_args);

                if ($query->have_posts()) {
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
                        $featured = '';

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
                            $featured = fw_get_db_post_option($post->ID, 'featured', true);
                        }

                        $safety_tips = '';
                        $safety_tips_heading = '';
                        $safety_tips_list = array();
                        if (function_exists('fw_get_db_settings_option')) {
                            $safety_tips = fw_get_db_settings_option('safety_settings', $default_value = null);
                        }
                        
                        if(!empty($safety_tips)){
                            $safety_tips_heading = $safety_tips[0]['tips_heading'];
                            foreach($safety_tips[0]['tip_text'] as $key => $value){
                                $safety_tips_list[] = $value;
                            }
                        }

                        $currency_symbol = '';
                        if (!empty($currency)) {
                            $currency_symbol = classified_pro_prepare_currency_symbols()['PKR']['symbol'];
                        }

                        $user_avatar = apply_filters(
                                'classified_pro_get_media_filter', classified_pro_get_user_avatar(array('width' => 150, 'height' => 150), $post->post_author), array('width' => 150, 'height' => 150)
                        );

                        $user_data = get_userdata($post->post_author);

                        //Get the seller data
                        $seller_data = array();
                        $registered_date = $user_data->user_registered;
                        $registered_date_string = 'Member Since ' . date("M d, Y", strtotime($registered_date));

                        $seller_data['seller_avatar'] = $user_avatar;
                        $seller_data['seller_first_name'] = $first_name;
                        $seller_data['seller_last_name'] = $last_name;
                        $seller_data['seller_registered_date'] = $registered_date_string;

                        $current_date = date('Y, m, d');
                        $featured = get_post_meta($post_id, 'featured', true);
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

                        $user_post_count = count_user_posts($post->post_author, 'ca_classified_ads');
                        if (function_exists('fw_get_db_settings_option')) {
                            $show_author_ads = fw_get_db_settings_option('show_author_ads');
                            $show_more_ads = fw_get_db_settings_option('show_more_ads');
                            $offer_policy = fw_get_db_settings_option('offer_price_policy');
                        } else {
                            $show_author_ads = '';
                            $show_more_ads = '';
                            $offer_policy = '';
                        }

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

                        $item['ad_seller_details'] = $seller_data;
                        $item['ad_is_author_ads'] = $show_author_ads;
                        if (!empty($show_author_ads) && $show_author_ads == 'enable' && $user_post_count > 1) {
                            $item['ad_more_ads'] = $this->get_author_more_ads($post->post_author, $post->ID);
                        }
                        if(!empty($safety_tips)){
                            $item['ad_saftey_tips_heading'] = $safety_tips_heading;
                            $item['ad_saftey_tips_list'] = $safety_tips_list;
                        }
                        
                        $items[] = $item;

                    }
                }
                return new WP_REST_Response($items, 200);
            }
        }

        /**
         * Get Author more ads data
         */
        public function get_author_more_ads($author_id, $post_id) {
            //Get the author more ads data
            $more_ads_args = array(
                'post_type' => 'ca_classified_ads',
                'author' => $author_id,
                'post__not_in' => array($post_id),
                'posts_per_page' => 5,
                'post_status' => 'publish'
            );
            $user_data = get_userdata($author_id);
            $author_more_ads = new WP_Query($more_ads_args);
            if ($author_more_ads->have_posts()) {
                $more_ads = array();
                $more_ads['ad_more_ads_by'] = 'More Ads From ' . esc_attr($user_data->user_login);
                while ($author_more_ads->have_posts()) {
                    $author_more_ads->the_post();
                    global $post;
                    if (function_exists('fw_get_db_post_option')) {
                        $address = fw_get_db_post_option($post->ID, 'profile_address', $default_value = null);
                        $gallery = fw_get_db_post_option($post->ID, 'gallery', $default_value = null);
                        $featured = fw_get_db_post_option($post->ID, 'featured', true);
                        $price = fw_get_db_post_option($post->ID, 'price', $default_value = null);
                        $currency = fw_get_db_post_option($post->ID, 'currency_symbol', $default_value = null);
                    } else {
                        $address = '';
                        $gallery = array();
                        $featured = '';
                        $price = '';
                        $currency = '';
                    }

                    $currency_symbol = '';
                    if (!empty($currency)) {
                        $currency_symbol = classified_pro_prepare_currency_symbols()[$currency]['symbol'];
                    }

                    $current_date = date('Y, m, d');
                    $featured = get_post_meta($post_id, 'featured', true);
                    $featured = !empty($featured) ? date('Y, m, d', $featured) : '';


                    $height = 220;
                    $width = 220;
                    $thumbnail = classified_pro_prepare_thumbnail($post->ID, intval($width), intval($height));

                    if (empty($thumbnail)) {
                        $thumbnail = get_template_directory_uri() . '/images/ad-fallback.jpg';
                    }

                    if (!empty($gallery)) {
                        $count = count($gallery);
                    } else {
                        $count = false;
                    }

                    $more_ads[$post->ID]['ad_more_ads_is_featured'] = 'No';
                    if (!empty($featured) && $featured > $current_date) {
                        $more_ads[$post->ID]['ad_more_ads_is_featured'] = 'Yes';
                    }

                    $more_ads[$post->ID]['ad_more_ads_permalink'] = get_the_permalink($post->ID);
                    $more_ads[$post->ID]['ad_more_ads_thumbnail'] = esc_url($thumbnail);
                    $more_ads[$post->ID]['ad_more_ads_title'] = esc_attr(get_the_title($post->ID));
                    if (!empty($price)) {
                        $more_ads[$post->ID]['ad_more_ads_price'] = esc_attr($currency_symbol) . esc_attr($price);
                    }
                }
                wp_reset_postdata();
                return $more_ads;
            }
            return '';
        }

    }

}

add_action('rest_api_init', function () {
    $controller = new ClassifiedAppAdDetailRoutes;
    $controller->register_routes();
});
