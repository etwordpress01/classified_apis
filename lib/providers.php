<?php

if (!class_exists('ClassifiedApp_Providers_Route')) {

    class ClassifiedApp_Providers_Route extends WP_REST_Controller {

        /**
         * Register the routes for the objects of the controller.
         */
        public function register_routes() {
            $version = '1';
            $namespace = 'api/v' . $version;
            $base = 'providers';

            register_rest_route($namespace, '/' . $base, array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_providers'),
                    'args' => array(
                    ),
                ),
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'create_provider')
                ),
                    )
            );

            register_rest_route($namespace, '/' . $base . '/appointment/slots', array(
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'get_appointment_slots'),
                ),
                    )
            );

            register_rest_route($namespace, '/' . $base . '/appointment', array(
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'create_appointment'),
                ),
                    )
            );

            register_rest_route($namespace, '/' . $base . '/confirm-appointment', array(
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'confirm_appointment'),
                ),
                    )
            );
            register_rest_route($namespace, '/' . $base . '/save-rating', array(
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'save_rating'),
                ),
                    )
            );


            register_rest_route($namespace, '/' . $base . '/reviews', array(
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'get_provider_reviews'),
                ),
                    )
            );
        }

        /**
         * Get provider list for application
         *
         * @param WP_REST_Request $request Full data about the request.
         * @return WP_Error|WP_REST_Response
         */
        public function get_providers($request) {

            global $paged, $wp_query, $total_users, $query_args, $limit;

            $dir_search_pagination = fw_get_db_settings_option('dir_search_pagination');
            if (!empty($_GET['showposts'])) {
                $per_page = $_GET['showposts'];
            } else {
                $per_page = !empty($dir_search_pagination) ? $dir_search_pagination : get_option('posts_per_page');
            }

            $limit = (int) $per_page;

            $pg_page = get_query_var('page') ? get_query_var('page') : 1; //rewrite the global var
            $pg_paged = get_query_var('paged') ? get_query_var('paged') : 1; //rewrite the global var
            //paged works on single pages, page - works on homepage
            $paged = max($pg_page, $pg_paged);

            $offset = ($paged - 1) * $limit;

            $json = array();
            $directories = array();
            $meta_query_args = array();

            if (!empty($_GET['category'])) {
                $category = classified_get_page_by_slug($_GET['category'], 'sp_categories', 'id');
            } else {
                if (is_singular('sp_categories')) {
                    $category = $wp_query->get_queried_object_id();
                } else {
                    $category = '';
                }
            }

//search filters

            $location = !empty($_GET['geo']) ? esc_attr($_GET['geo']) : '';
            $keyword = !empty($_GET['keyword']) ? esc_attr($_GET['keyword']) : '';
            $appointments = !empty($_GET['appointment']) ? esc_attr($_GET['appointment']) : '';
            $ratings = !empty($_GET['ratings']) ? esc_attr($_GET['ratings']) : '';
            $sort_by = !empty($_GET['sortby']) ? esc_attr($_GET['sortby']) : 'recent';
            $photos = !empty($_GET['photo']) ? $_GET['photo'] : '';
            $zip = !empty($_GET['zip']) ? esc_attr($_GET['zip']) : '';

//Category seearch
            if (is_tax('sub_category')) {
                $sub_cat = $wp_query->get_queried_object();
                if (!empty($sub_cat->slug)) {
                    $sub_category = array($sub_cat->slug);
                }
            } else {
                $sub_category = !empty($_GET['sub_categories']) ? $_GET['sub_categories'] : '';
            }

//Country search
            if (!empty($_GET['country'])) {
                $country = !empty($_GET['country']) ? esc_attr($_GET['country']) : '';
            } else {
                if (is_tax('countries')) {
                    $sub_cat = $wp_query->get_queried_object();
                    if (!empty($sub_cat->slug)) {
                        $country = $sub_cat->slug;
                    }
                } else {
                    $country = '';
                }
            }

//city search
            if (!empty($_GET['city'])) {
                $city = !empty($_GET['city']) ? esc_attr($_GET['city']) : '';
            } else {
                if (is_tax('cities')) {
                    $sub_cat = $wp_query->get_queried_object();
                    if (!empty($sub_cat->slug)) {
                        $city = $sub_cat->slug;
                    }
                } else {
                    $city = '';
                }
            }

//insurance search
            if (!empty($_GET['insurance'])) {
                $insurance = !empty($_GET['insurance']) ? $_GET['insurance'] : '';
            } else {
                if (is_tax('insurance')) {
                    $sub_cat = $wp_query->get_queried_object();
                    if (!empty($sub_cat->slug)) {
                        $insurance = array($sub_cat->slug);
                    }
                } else {
                    $insurance = '';
                }
            }

//languages search
            if (!empty($_GET['languages'])) {
                $languages = !empty($_GET['languages']) ? $_GET['languages'] : '';
            } else {
                if (is_tax('languages')) {
                    $sub_cat = $wp_query->get_queried_object();
                    if (!empty($sub_cat->slug)) {
                        $languages = array($sub_cat->slug);
                    }
                } else {
                    $languages = '';
                }
            }

//Order
            $order = 'DESC';
            if (!empty($_GET['orderby'])) {
                $order = esc_attr($_GET['orderby']);
            }

            $sorting_order = 'ID';
            if ($sort_by === 'recent') {
                $sorting_order = 'ID';
            } else if ($sort_by === 'title') {
                $sorting_order = 'display_name';
            }

            $query_args = array(
                'role__in' => array('professional', 'business'),
                'order' => $order,
                'orderby' => $sorting_order,
            );

//Search by featured
            if ($sort_by === 'featured') {
                $query_args['orderby'] = 'meta_value_num';
                $query_args['order'] = 'DESC';

                $query_relation = array('relation' => 'OR',);
                $featured_args = array();
                $featured_args[] = array(
                    'key' => 'subscription_featured_expiry',
                    'compare' => 'EXISTS'
                );

                $meta_query_args[] = array_merge($query_relation, $featured_args);
            }

//Search By likes
            if ($sort_by === 'likes') {
                $query_args['order'] = $order;
                $query_args['orderby'] = 'meta_value_num';

                $query_relation = array('relation' => 'OR',);
                $likes_args = array();
                $likes_args[] = array(
                    'key' => 'likes_count',
                    'compare' => 'EXISTS'
                );

                $meta_query_args[] = array_merge($query_relation, $likes_args);
            }


//Search By Keywords
            if (!empty($_GET['keyword'])) {
                $s = sanitize_text_field($_GET['keyword']);
                //$query_args['search'] = $s;
                $search_args = array(
                    'search' => '*' . esc_attr($s) . '*',
                    'search_columns' => array(
                        'ID',
                        'display_name',
                        'user_login',
                        'user_nicename',
                        'user_email',
                        'user_url',
                    )
                );

                $meta_keyword = array('relation' => 'OR',);
                $meta_keyword[] = array(
                    'key' => 'first_name',
                    'value' => $s,
                    'compare' => 'LIKE',
                );

                $meta_keyword[] = array(
                    'key' => 'last_name',
                    'value' => $s,
                    'compare' => 'LIKE',
                );

                $meta_keyword[] = array(
                    'key' => 'nickname',
                    'value' => $s,
                    'compare' => 'LIKE',
                );

                $meta_keyword[] = array(
                    'key' => 'username',
                    'value' => $s,
                    'compare' => 'LIKE',
                );

                $meta_keyword[] = array(
                    'key' => 'full_name',
                    'value' => $s,
                    'compare' => 'LIKE',
                );

                $meta_keyword[] = array(
                    'key' => 'company_name',
                    'value' => $s,
                    'compare' => 'LIKE',
                );

                $meta_keyword[] = array(
                    'key' => 'description',
                    'value' => $s,
                    'compare' => 'LIKE',
                );

                $meta_keyword[] = array(
                    'key' => 'professional_statements',
                    'value' => $s,
                    'compare' => 'LIKE',
                );

                if (!empty($meta_keyword)) {
                    $meta_query_args[] = array_merge($meta_keyword, $meta_query_args);
                }
            }

//Category Type Search
            if (!empty($category)) {
                $meta_query_args[] = array(
                    'key' => 'category',
                    'value' => $category,
                    'compare' => '=',
                );
            }
//Sub category Type Search
            if (!empty($sub_category) && !empty($sub_category[0]) && is_array($sub_category)) {
                $query_relation = array('relation' => 'OR',);
                $sub_category_args = array();
                foreach ($sub_category as $key => $value) {
                    $sub_category_args[] = array(
                        'key' => 'sub_category',
                        'value' => $value,
                        'compare' => '='
                    );
                }

                $meta_query_args[] = array_merge($query_relation, $sub_category_args);
            }


//Cities
            if (!empty($country)) {
                $meta_query_args[] = array(
                    'key' => 'country',
                    'value' => $country,
                    'compare' => '=',
                );
            }

//Cities
            if (!empty($city)) {
                $meta_query_args[] = array(
                    'key' => 'city',
                    'value' => $city,
                    'compare' => '=',
                );
            }

//Photos search
            if (!empty($photos) && $photos === 'true') {
                $meta_query_args[] = array(
                    'key' => 'profile_photo',
                    'value' => 'on',
                    'compare' => '='
                );

                $meta_query_args[] = array(
                    'key' => 'profile_avatar',
                    'value' => '',
                    'compare' => '!='
                );
            }

//online appointments Search
            if (!empty($appointments) && $appointments === 'true') {
                $meta_query_args[] = array(
                    'key' => 'profile_appointment',
                    'value' => 'on',
                    'compare' => '='
                );
            }

//Zip Search
            if (!empty($zip)) {
                $meta_query_args[] = array(
                    'key' => 'zip',
                    'value' => $zip,
                    'compare' => '='
                );
            }

//Language Search;
            if (!empty($languages) && !empty($languages[0]) && is_array($languages)) {
                $query_relation = array('relation' => 'OR',);
                $language_args = array();
                foreach ($languages as $key => $value) {
                    $language_args[] = array(
                        'key' => 'profile_languages',
                        'value' => serialize(strval($value)),
                        'compare' => 'LIKE'
                    );
                }

                $meta_query_args[] = array_merge($query_relation, $language_args);
            }
//Insurance
            if (!empty($insurance) && !empty($insurance[0]) && is_array($insurance)) {
                $query_relation = array('relation' => 'OR',);
                $insurance_args = array();
                foreach ($insurance as $key => $value) {
                    $insurance_args[] = array(
                        'key' => 'profile_insurance',
                        'value' => serialize(strval($value)),
                        'compare' => 'LIKE'
                    );
                }

                $meta_query_args[] = array_merge($query_relation, $insurance_args);
            }

//Speciality Search;
            if (!empty($speciality) && !empty($speciality[0]) && is_array($speciality)) {
                $query_relation = array('relation' => 'OR',);
                $speciality_args = array();
                foreach ($speciality as $key => $value) {
                    $speciality_args[] = array(
                        'key' => $value,
                        'value' => $value,
                        'compare' => '='
                    );
                }

                $meta_query_args[] = array_merge($query_relation, $speciality_args);
            }

//Verify user
            $meta_query_args[] = array(
                'key' => 'verify_user',
                'value' => 'on',
                'compare' => '='
            );
//active users filter
            $meta_query_args[] = array(
                'key' => 'activation_status',
                'value' => 'active',
                'compare' => '='
            );

            if (!empty($meta_query_args)) {
                $query_relation = array('relation' => 'AND',);
                $meta_query_args = array_merge($query_relation, $meta_query_args);
                $query_args['meta_query'] = $meta_query_args;
            }

//Radius Search
            if ((isset($_GET['geo']) && !empty($_GET['geo']))) {

                $Latitude = '';
                $Longitude = '';
                $prepAddr = '';
                $minLat = '';
                $maxLat = '';
                $minLong = '';
                $maxLong = '';

                if (isset($_GET['geo_distance']) && !empty($_GET['geo_distance'])) {
                    $radius = $_GET['geo_distance'];
                } else {
                    $radius = 300;
                }

                //Distance in miles or kilometers
                if (function_exists('fw_get_db_settings_option')) {
                    $dir_distance_type = fw_get_db_settings_option('dir_distance_type');
                } else {
                    $dir_distance_type = 'mi';
                }

                if ($dir_distance_type === 'km') {
                    $radius = $radius * 0.621371;
                }

                $address = sanitize_text_field($_GET['geo']);
                $prepAddr = str_replace(' ', '+', $address);

                $args = array(
                    'timeout' => 15,
                    'headers' => array('Accept-Encoding' => ''),
                    'sslverify' => false
                );

                $url = 'http://maps.google.com/maps/api/geocode/json?address=' . $prepAddr . '&sensor=false';
                $response = wp_remote_get($url, $args);
                $geocode = wp_remote_retrieve_body($response);

                $output = json_decode($geocode);

                if (isset($output->results) && !empty($output->results)) {
                    $Latitude = $output->results[0]->geometry->location->lat;
                    $Longitude = $output->results[0]->geometry->location->lng;

                    if (isset($Latitude) && $Latitude <> '' && isset($Longitude) && $Longitude <> '') {
                        $zcdRadius = new RadiusCheck($Latitude, $Longitude, $radius);
                        $minLat = $zcdRadius->MinLatitude();
                        $maxLat = $zcdRadius->MaxLatitude();
                        $minLong = $zcdRadius->MinLongitude();
                        $maxLong = $zcdRadius->MaxLongitude();
                    }

                    $meta_query_args = array(
                        'relation' => 'AND',
                        array(
                            'relation' => 'OR',
                            array(
                                'key' => 'address',
                                'value' => str_replace('+', ' ', $prepAddr),
                                'compare' => 'LIKE',
                            ),
                            array(
                                'relation' => 'AND',
                                array(
                                    'key' => 'latitude',
                                    'value' => array($minLat, $maxLat),
                                    'compare' => 'BETWEEN',
                                    'type' => 'DECIMAL(20,10)',
                                ),
                                array(
                                    'key' => 'longitude',
                                    'value' => array($minLong, $maxLong),
                                    'compare' => 'BETWEEN',
                                    'type' => 'DECIMAL(20,10)',
                                )
                            ),
                        ),
                    );

                    if (isset($query_args['meta_query']) && !empty($query_args['meta_query'])) {
                        $meta_query = array_merge($meta_query_args, $query_args['meta_query']);
                    } else {
                        $meta_query = $meta_query_args;
                    }

                    $query_args['meta_query'] = $meta_query;
                }
            }

//Count total users for pagination
            $total_query = new WP_User_Query($query_args);
            $total_users = $total_query->total_users;
            $query_args['number'] = $limit;
            $query_args['offset'] = $offset;

            $default_view = 'list';
            if (function_exists('fw_get_db_post_option')) {
                $default_view = fw_get_db_settings_option('dir_search_view');
            }

            $user_query = new WP_User_Query($query_args);
            $sp_userslist['status'] = 'none';
            $sp_usersdata = array();
            $sp_userslist = array();

            if (!empty($user_query->results)) {

                $sp_userslist['status'] = 'found';

                if (!empty($sp_category)) {
                    $title = get_the_title($sp_category);
                    $postdata = get_post($sp_category);
                    $slug = $postdata->post_name;
                } else {
                    $title = '';
                    $slug = '';
                }

                foreach ($user_query->results as $user) {

                    if (isset($media_type) && $media_type === 'banner') {
                        $thumb = apply_filters(
                                'classified_get_media_filter', classified_get_user_banner(array('width' => 370, 'height' => 270), $user->ID), array('width' => 370, 'height' => 270)
                        );
                    } else {
                        $thumb = apply_filters(
                                'classified_get_media_filter', classified_get_user_avatar(array('width' => 370, 'height' => 270), $user->ID), array('width' => 370, 'height' => 270)
                        );
                    }

                    $item['category_id'] = get_user_meta($user->ID, 'category', true);
                    $item['category'] = get_the_title($user->category);
                    $item['ID'] = $user->ID;
                    $item['latitude'] = $user->latitude;
                    $item['longitude'] = $user->longitude;
                    $item['phone'] = $user->phone;
                    $username = classified_get_username($user->ID);
                    $item['username'] = $username;
                    $item['avatar'] = $thumb;


                    $item['wp_capabilities'] = $user->wp_capabilities;
                    $item['wp_user_level'] = $user->wp_user_level;
                    $item['usertype'] = $user->usertype;

                    $item['email'] = $user->user_email;
                    $item['website'] = $user->user_url;


                    $item['full_name'] = $user->full_name;
                    $item['nickname'] = $user->nickname;
                    $item['r_id'] = $user->r_id;
                    //$item['category'] = $user->category;
                    $item['sub_category'] = $user->sub_category;
                    $item['company_name'] = $user->company_name;
                    $item['profile_avatar'] = empty($user->profile_avatar) ? new stdClass() : $user->profile_avatar;
                    if (!empty($user->profile_gallery_photos)) {
                        $a = $user->profile_gallery_photos;
                        $a['image_data'] = array_values($a['image_data']);
                    } else {
                        $a['image_data'] = array();
                    }

                    $db_category_type = get_user_meta($user->ID, 'category', true);


                    /* Get the rating headings */
                    $rating_titles = $this->classified_get_reviews_evaluation($db_category_type, 'leave_rating');
                    $item['rating_titles'] = $rating_titles;

                    $item['profile_gallery_photos'] = empty($user->profile_gallery_photos) ? new stdClass() : ( $a);
                    $item['facebook'] = $user->facebook;
                    $item['twitter '] = $user->twitter;
                    $item['linkedin'] = $user->linkedin;
                    $item['pinterest'] = $user->pinterest;
                    $item['google_plus'] = $user->google_plus;
                    $item['tumblr'] = $user->tumblr;
                    $item['instagram'] = $user->instagram;
                    $item['skype'] = $user->skype;
                    $item['activation_status'] = $user->activation_status;
                    $item['address'] = $user->address;
                    $item['latitude'] = $user->latitude;
                    $item['longitude'] = $user->longitude;
                    $item['tag_line'] = $user->tag_line;
                    $item['phone'] = $user->phone;
                    $item['fax'] = $user->fax;
                    $item['profile_languages'] = empty($user->profile_languages) ? new stdClass() : $user->profile_languages;
                    $item['zip'] = $user->zip;
                    $item['verify_user'] = $user->verify_user;
                    $item['privacy'] = empty($user->privacy) ? new stdClass() : $user->privacy;
                    $item['country'] = $user->country;
                    $item['city'] = $user->city;
                    $item['description'] = $user->description;
                    $item['first_name'] = $user->first_name;
                    $item['last_name'] = $user->last_name;
                    $item['professional_statements'] = $user->professional_statements;
                    $item['subscription_featured_expiry'] = $user->subscription_featured_expiry;
                    $item['subscription_expiry'] = $user->subscription_expiry;
                    $item['subscription_id'] = $user->subscription_id;
                    $item['sp_subscription'] = empty($user->sp_subscription) ? new stdClass() : $user->sp_subscription;
                    $item['awards'] = empty($user->awards) ? array() : $user->awards;
                    $item['business_hours'] = empty($user->business_hours) ? new stdClass() : $user->business_hours;
                    $item['business_hours_format'] = $user->business_hours_format;
                    $item['default_slots'] = empty($user->default_slots) ? new stdClass() : $user->default_slots;
                    $item['experience'] = $user->experience;
                    $item['profile_brochure'] = $user->profile_brochure;
                    $item['profile_insurance'] = empty($user->profile_insurance) ? array() : $user->profile_insurance;
                    $amenities = $user->profile_amenities;


                    $_amenities = array();
                    if (!empty($amenities))
                        foreach ($amenities as $key => $amenitie) {
                            $_amenities[] = get_term_by('slug', $amenitie, 'amenities');
                        }


                    $item['profile_amenities'] = empty($user->profile_amenities) ? array() : $_amenities;
                    $item['audio_video_urls'] = $user->audio_video_urls;
                    $item['qualification'] = $user->qualification;
                    $item['privacy_settings'] = empty($user->privacy_settings) ? new stdClass() : $user->privacy_settings;
                    $item['profile_photo'] = $user->profile_photo;
                    $item['profile_banner'] = $user->profile_banner;
                    $item['profile_appointment'] = $user->profile_appointment;
                    $item['profile_contact'] = $user->profile_contact;
                    $item['profile_hours'] = $user->profile_hours;
                    $item['profile_service'] = $user->profile_service;
                    $item['profile_team'] = $user->profile_team;
                    $item['profile_gallery'] = $user->profile_gallery;
                    $item['profile_videos'] = $user->profile_videos;
                    $item['appt_booking_approved'] = $user->appt_booking_approved;
                    $item['appt_booking_confirmed'] = $user->appt_booking_confirmed;
                    $item['appt_booking_cancelled'] = $user->appt_booking_cancelled;
                    $item['appt_cancelled_title'] = $user->appt_cancelled_title;
                    $item['appt_approved_title'] = $user->appt_approved_title;
                    $item['appt_confirmation_title'] = $user->appt_confirmation_title;
                    $item['appointment_currency'] = $user->appointment_currency;
                    $item['appointment_inst_desc'] = $user->appointment_inst_desc;
                    $item['appointment_inst_title'] = $user->appointment_inst_title;


                    $item['profile_services'] = empty($user->profile_services) ? array() : array_values($user->profile_services);
                    $item['appointment_reasons'] = empty($user->appointment_reasons) ? array() : array_values($user->appointment_reasons);
                    $item['appointment_types'] = empty($user->appointment_types) ? array() : array_values($user->appointment_types);
                    $item['teams_data'] = empty($user->teams_data) ? array() : (array) array_values($user->teams_data);

                    $review_data = get_user_meta($user->ID, 'review_data', true);
                    $item['review_data'] = empty($review_data) ? new stdClass() : $review_data;

                    $items[] = $item;
                }
            }

            // print_r($items);die;
            return new WP_REST_Response($items, 200);
        }

        /**
         * Get appoinment slots
         *
         * @param WP_REST_Request $request Full data about the request.
         * @return WP_Error|WP_REST_Request
         */
        public function get_appointment_slots($request) {
            $params = $request->get_params();
            $slot_date = $params['slot_date'];
            $author_id = $params['author_id'];


            $time_slots = array();
            $day_name = strtolower(date('l', strtotime($slot_date)));
            $time_slots = get_user_meta($author_id, 'default_slots', true);
            $time_format = get_option('time_format');

            ob_start();
            $i = 0;
            $response = array();
            if (!empty($time_slots[$day_name])) {
                foreach ($time_slots[$day_name] as $key => $value) {
                    $time = explode('-', $key);
                    $data = classified_check_booked_slot($key, $slot_date, $author_id);
                    if (isset($data['available']) && $data['available'] === 'yes') {
                        $response[$i]['time_slot'] = date_i18n($time_format, strtotime('2016-01-01 ' . $time[0])) . '-' . date_i18n($time_format, strtotime('2016-01-01 ' . $time[1]));
                        $response[$i]['remaining'] = esc_attr($data['remaining']);
                        $response[$i]['key'] = $key;
                        $i++;
                    }
                }
            }



            return new WP_REST_Response($response, 200);
        }

       public function classified_get_reviews_evaluation($category_type, $reviews_type) {

        $reviews_evaluation = array('leave_rating' => array(),'total_wait_time' =>array() );

        $reviews = '';
        if (function_exists('fw_get_db_settings_option')) {
            $reviews = fw_get_db_post_option($category_type, 'enable_reviews', true);
        }   
        // echo $reviews_type;
        // print_r($reviews);
        // print_r($reviews['enable'][$reviews_type]);
        // die;
        if( !empty( $reviews['enable'][$reviews_type] )){
                foreach ($reviews['enable'][$reviews_type] as $key => $value) {
                    $reviews_evaluation['leave_rating'][$key]['title'] = $value;
                    $reviews_evaluation['leave_rating'][$key]['slug'] = sanitize_title($value);
                }
                 foreach ($reviews['enable']['total_wait_time'] as $key => $value) {
                    $reviews_evaluation['total_wait_time'][] = $value;
                }
        }
        

        // $reviews_check = !empty($reviews['gadget']) ? $reviews['gadget'] : '';

        // if (!empty($reviews_check) && $reviews_check === 'enable' && $reviews_type === 'total_wait_time') {
        //     $reviews_evaluation = !empty($reviews['enable'][$reviews_type]) ? $reviews['enable'][$reviews_type] : array();
        // } else if (!empty($reviews_check) && $reviews_check === 'enable' && $reviews_type === 'leave_rating') {
        //     $reviews_evaluation = !empty($reviews['enable'][$reviews_type]) ? $reviews['enable'][$reviews_type] : array();
        // }

        // $reviews_evaluation = array_filter($reviews_evaluation);
        // $reviews_evaluation = array_combine(array_map('sanitize_title', $reviews_evaluation), $reviews_evaluation);
        return $reviews_evaluation;
    }

        /**
         * Create new appoinment from application 
         * @param type $request
         * @return \WP_REST_Response
         */
        public function create_appointment($request) {
            $params = $request->get_params();


            $json = array();
            $time_slot_link = '';

            if (empty($params['tg-timeslot'])) {
                $json['type'] = 'error';
                $json['message'] = esc_html__('Kindly choose the time slot first.', 'listingo');
            }

            if (!empty($params['tg-timeslot']) && !empty($params['author_id'])) {
                $protocol = is_ssl() ? 'https' : 'http';

                $time_slot = $params['tg-timeslot'];
                $slot_date = $params['slot_date'];
                $author_id = $params['author_id'];

                $merge_appointment_data = sprintf("%u%s%s%s%s", $author_id, '|', $time_slot, '|', $slot_date);
                $key_hash = md5(uniqid(openssl_random_pseudo_bytes(32)));

                $appointment_page = '';
                if (function_exists('fw_get_db_settings_option')) {
                    $appointment_page = fw_get_db_settings_option('appointment_form_page', true);
                }

                if (empty($appointment_page[0])) {
                    $json['type'] = 'error';
                    $json['message'] = esc_html__('Please set the page first in directory settings.', 'listingo');
                    echo json_encode($json);
                    die;
                } else {
                    $appt_page_slug = classified_get_slug($appointment_page[0]);
                }

                $time_slot_link = esc_url(add_query_arg(array(
                    'key' => $key_hash
                                ), home_url('/' . esc_attr($appt_page_slug) . '/', $protocol)));

                //Update appointmnet key in user logged in table.
                update_user_meta($params['user_id'], 'appointment_data', $merge_appointment_data);
                update_user_meta($params['user_id'], 'appointment_key', $key_hash);

                $json['type'] = 'success';
                $json['appointment_link'] = $time_slot_link;
            }


            return new WP_REST_Response($json, 200);
        }

        public function get_provider_reviews($request) {

            $params = $request->get_params();
            //$params['provider_id'] 
            global $paged;

            $pg_page = get_query_var('page') ? get_query_var('page') : 1; //rewrite the global var
            $pg_paged = get_query_var('paged') ? get_query_var('paged') : 1; //rewrite the global var
//paged works on single pages, page - works on homepage
            $paged = max($pg_page, $pg_paged);
            $show_posts = 4;

// //Get User Queried Object Data
// $author_profile = $wp_query->get_queried_object();
// //Get The Category Type
// $category_type = $author_profile->category;
// /* Get the total wait time. */
// $total_time = classified_get_reviews_evaluation($category_type, 'total_wait_time');
// /* Get the rating headings */
// $rating_titles = classified_get_reviews_evaluation($category_type, 'leave_rating');

            /**
             * @Prepare Reviews Data
             * @Get Total Reviews
             * @Get Reviews Loop Data
             */
            $meta_query_args = array('relation' => 'AND');
            $meta_query_args[] = array(
                'key' => 'user_to',
                'value' => $params['provider_id'],
                'compare' => '=',
                'type' => 'NUMERIC'
            );

            $query_args = array('posts_per_page' => -1,
                'post_type' => 'sp_reviews',
                'post_status' => 'publish',
                'order' => 'ASC'
            );

            $query_args['meta_query'] = $meta_query_args;

            $query = new WP_Query($query_args);

            $total_reviews = $query->post_count;

            $total_reviews_text = esc_html__('Reviews', 'listingo');
            $review_heading = sprintf("%u %s", $total_reviews, $total_reviews_text);

            $query_args1 = array(
                'posts_per_page' => $show_posts,
                'post_type' => 'sp_reviews',
                'paged' => $paged,
                'order' => 'ASC',
                'post_status' => 'publish',
                'ignore_sticky_posts' => 1);

            $query_args1['meta_query'] = $meta_query_args;
            $review_query = new WP_Query($query_args1);
            ?>


            <?php

            if ($review_query->have_posts()) {

                while ($review_query->have_posts()) {
                    $review_query->the_post();
                    global $post;
                    $post_author = $post->post_author;
                    $reviewer_name = classified_get_username($post_author);
                    $review_wait_time = get_post_meta($post->ID, 'review_wait_time', true);
                    $category_type = get_post_meta($post->ID, 'category_type', true);
                    $total_time = classified_get_reviews_evaluation($category_type, 'total_wait_time');
                    /**
                     * Count user total rating
                     * with individual rating plus.
                     */
                    $count_indivi_rating = 0;
                    if (!empty($rating_titles)) {
                        foreach ($rating_titles as $key => $value) {
                            $indivi_rating = get_post_meta($post->ID, $key, true);
                            $count_indivi_rating += $indivi_rating;
                        }
                    }
                    if (count($rating_titles)) {
                        $total_ratings = ($count_indivi_rating / count($rating_titles)) * intval(20);
                    } else {
                        $total_ratings = $count_indivi_rating;
                    }



                    $review_date = get_the_date('Y-m-d h:i:s');
                    $avatar = apply_filters(
                            'classified_get_media_filter', classified_get_user_avatar(array('width' => 100, 'height' => 100), $post_author), array('width' => 100, 'height' => 100)
                    );

                    $review_time = '';
                    if (!empty($total_time[$review_wait_time])) {
                        $review_time = esc_attr($total_time[$review_wait_time]);
                    }

                    if (( apply_filters('classified_get_user_type', $post_author) === 'business' || apply_filters('classified_get_user_type', $post_author) === 'professional' ) && function_exists('fw_get_db_settings_option')
                    ) {
                        $author_url = get_author_posts_url($post_author);
                    } else {
                        $author_url = 'javascript:;';
                    }
                    echo get_the_title();
                    echo esc_attr($reviewer_name);
                    echo human_time_diff(strtotime($review_date), current_time('timestamp')) . esc_html__(' ago', 'listingo');
                    echo esc_attr($total_ratings);
                    if (!empty($rating_titles)) {
                        if (!empty($rating_titles)) {
                            echo force_balance_tags($review_time);

                            foreach ($rating_titles as $key => $rating) {
                                $individual_rating = get_post_meta($post->ID, $key, true);
                                $indivi_rating_total = ($individual_rating / intval(5)) * intval(100);
                                echo esc_attr($indivi_rating_total);
                                echo esc_attr($rating);
                            }
                        }
                    } 

                    the_content();
                } wp_reset_postdata();
            } else {
                echo 'No Reviews Found';
            }
           die();
        }

        public function save_rating($request) {

            $params = $request->get_params();



            // if( function_exists('classified_is_demo_site') ) { 
            //     classified_is_demo_site() ;
            // }; 


            if (empty($params['review_title']) || empty($params['review_description']) || empty($params['provider_id'])) {
                $json['type'] = 'error';
                $json['message'] = esc_html__('Please fill all the fields..', 'listingo');
                //echo json_encode($json);
                return new WP_REST_Response($json, 200);
            }

            $user_to = !empty($params['provider_id']) ? intval($params['provider_id']) : '';


            $cat_review_status = 'pending';

            if (function_exists('fw_get_db_settings_option')) {
                $cat_review_status = fw_get_db_settings_option('dir_review_status', $default_value = null);
            }

            $user_reviews = array(
                'posts_per_page' => "-1",
                'post_type' => 'sp_reviews',
                'post_status' => 'any',
                'author' => $params['user_id'],
                'meta_key' => 'user_to',
                'meta_value' => $params['provider_id'],
                'meta_compare' => "=",
                'orderby' => 'meta_value',
                'order' => 'ASC',
            );

            $reviews_query = new WP_Query($user_reviews);
            $reviews_count = $reviews_query->post_count;

            if (isset($reviews_count) && $reviews_count > 0) {
                $json['type'] = 'error';
                $json['message'] = esc_html__('You have already submit a review.', 'listingo');
                return new WP_REST_Response($json, 200);
                die();
            }

            $db_category_type = get_user_meta($user_to, 'category', true);

            /* Get the rating headings */
            $rating_titles = classified_get_reviews_evaluation($db_category_type, 'leave_rating');

            //Office Evaluation     
            if (empty($params['review_wait_time'])) {
                $json['type'] = 'error';
                $json['message'] = esc_html__('Total wait time required.', 'listingo');
                return new WP_REST_Response($json, 200);
                die();
            }

            //Provider Evaluation       
            if (!empty($rating_titles)) {
                foreach ($rating_titles as $slug => $label) {
                    if (empty($params[$slug])) {
                        $json['type'] = 'error';
                        $json['message'] = esc_html__('Rating is required.', 'listingo');
                        return new WP_REST_Response($json, 200);
                        die();
                    }
                }
            }


            if (!empty($params['review_title']) || !empty($params['review_description']) || !empty($params['provider_id'])
            ) {

                $review_heading = sanitize_text_field($params['review_title']);
                $review_description = sanitize_text_field($params['review_description']);
                $recommended = sanitize_text_field($params['recommended']);
                $review_wait_time = sanitize_text_field($params['review_wait_time']);
                $user_from = intval($current_user->ID);
                $user_to = intval($params['provider_id']);
                $category_type = intval($db_category_type);

                $review_post = array(
                    'post_title' => $review_heading,
                    'post_status' => $cat_review_status,
                    'post_content' => $review_description,
                    'post_author' => $user_from,
                    'post_type' => 'sp_reviews',
                    'post_date' => current_time('Y-m-d H:i:s')
                );

                $post_id = wp_insert_post($review_post);

                //Rating
                $rating = 0;

                /* Get the rating headings */
                $rating_evaluation = classified_get_reviews_evaluation($db_category_type, 'leave_rating');
                $rating_evaluation_count = !empty($rating_evaluation) ? count($rating_evaluation) : 0;

                $review_extra_meta = array();

                //Office Evaluation     
                if (!empty($rating_evaluation)) {
                    foreach ($rating_evaluation as $slug => $label) {
                        if (isset($params[$slug])) {
                            $review_extra_meta[$slug] = esc_attr($params[$slug]);
                            update_post_meta($post_id, $slug, esc_attr($params[$slug]));
                            $rating += (int) $params[$slug];
                        }
                    }
                }
                if ($rating_evaluation_count) {
                    $user_rating = $rating / $rating_evaluation_count;
                } else {
                    $user_rating = $rating;
                }

                $user_rating = number_format((float) $user_rating, 2, '.', '');

                $review_meta = array(
                    'user_rating' => $user_rating,
                    'user_from' => $user_from,
                    'user_to' => $user_to,
                    'recommended' => $recommended,
                    'category_type' => $category_type,
                    'review_wait_time' => $review_wait_time,
                    'review_date' => current_time('Y-m-d H:i:s'),
                );

                $review_meta = array_merge($review_meta, $review_extra_meta);

                //Update post meta
                foreach ($review_meta as $key => $value) {
                    update_post_meta($post_id, $key, $value);
                }

                $review_meta['user_from'] = array($user_from);
                $review_meta['user_to'] = array($user_to);

                $new_values = $review_meta;
                if (isset($post_id) && !empty($post_id)) {
                    fw_set_db_post_option($post_id, null, $new_values);
                }

                /* Update recommendation in user table */
                $total_recommendations = 0;
                $user_review_meta = array();
                if (isset($cat_review_status) && $cat_review_status == 'publish') {
                    if (isset($recommended) && $recommended === 'yes') {
                        $total_recommendations = classified_get_review_data($user_to, 'sp_total_recommendation', 'value');
                        $total_recommendations++;
                    } else {
                        $total_recommendations = classified_get_review_data($user_to, 'sp_total_recommendation', 'value');
                    }
                }

                //$user_review_meta['sp_total_recommendation'] = $total_recommendations;
                //update_user_meta($user_to, 'review_data', $user_review_meta);

                /* Update avarage rating in user table */
                $average_rating = classified_get_everage_rating($user_to);

                foreach ($average_rating as $key => $rating) {
                    $user_review_meta[$key] = $rating;
                }

                update_user_meta($user_to, 'review_data', $user_review_meta);

                $json['type'] = 'success';
                if (isset($cat_review_status) && $cat_review_status == 'publish') {
                    $json['message'] = esc_html__('Your review published successfully.', 'listingo');
                    $json['html'] = 'refresh';
                } else {
                    $json['message'] = esc_html__('Your review submitted successfully, it will be publised after approval.', 'listingo');
                    $json['html'] = '';
                }

                /* Mail Code Here */
                if (class_exists('ClassifiedProcessEmail')) {
                    $user_from_data = get_userdata($user_from);
                    $user_to_data = get_userdata($user_to);
                    $email_helper = new ClassifiedProcessEmail();

                    $emailData = array();

                    //User to data
                    $emailData['email_to'] = $user_to_data->user_email;
                    $emailData['link_to'] = get_author_posts_url($user_to_data->ID);
                    $emailData['username_to'] = classified_get_username($user_to);
                    $emailData['username_from'] = classified_get_username($user_from);
                    $emailData['link_from'] = get_author_posts_url($user_from_data->ID);

                    //General
                    $emailData['rating'] = $user_rating;
                    $emailData['reason'] = $review_heading;

                    $email_helper->process_rating_email($emailData);
                }

                //echo json_encode($json);
                return new WP_REST_Response($json, 200);

                die;
            } else {
                $json['type'] = 'error';
                $json['message'] = esc_html__('Please fill all the fields.', 'listingo');
                //echo json_encode($json);
                return new WP_REST_Response($json, 200);
                die;
            }
        }

        public function create_provider($request) {

            $params = $request->get_params();

            $_POST['register'] = $params['register'];
            if (isset($_POST['register']['account']) && sanitize_text_field($_POST['register']['account']) === 'seeker') {


                $data_array = array(
                    'username' => esc_html__('Username is required.', 'classified_core'),
                    'first_name' => esc_html__('First name is required.', 'fclassified_core'),
                    'last_name' => esc_html__('Last name is required.', 'classified_core'),
                    'gender' => esc_html__('Gender is required.', 'classified_core'),
                    'phone' => esc_html__('Phone number is required.', 'classified_core'),
                    'email' => esc_html__('Email address is required.', 'classified_core'),
                    'password' => esc_html__('Password is required.', 'classified_core'),
                    'confirm_password' => esc_html__('Please re-type your password.', 'classified_core'),
                );

                $db_user_role = 'customer';
            } else {

                if (isset($_POST['register']['type']) && sanitize_text_field($_POST['register']['type']) === 'business') {
                    $required_fields = array(
                        'company_name' => esc_html__('Company name is required.', 'fclassified_core'),
                    );
                    $db_user_role = 'business';
                } else {

                    $required_fields = array(
                        'first_name' => esc_html__('First name is required.', 'fclassified_core'),
                        'last_name' => esc_html__('Last name is required.', 'classified_core'),
                        'gender' => esc_html__('Gender is required.', 'classified_core'),
                    );
                    $db_user_role = 'professional';
                }

                $data_array = array(
                    'category' => esc_html__('Please select category.', 'classified_core'),
                    'sub_category' => esc_html__('Sub category is required.', 'classified_core'),
                    'phone' => esc_html__('Phone number is required.', 'classified_core'),
                    'email' => esc_html__('Email address is required.', 'classified_core'),
                    'password' => esc_html__('Password is required.', 'classified_core'),
                    'confirm_password' => esc_html__('Please re-type your password.', 'classified_core'),
                );

                $data_array = array_merge($data_array, $required_fields);
            }

            $emailData = array();
            foreach ($data_array as $key => $value) {
                if (empty($_POST['register'][$key])) {
                    $json['type'] = 'error';
                    $json['message'] = $value;
                    echo json_encode($json);
                    die;
                }

                if ($key === 'email') {
                    if (!is_email($_POST['register'][$key])) {
                        $json['type'] = 'error';
                        $json['message'] = esc_html__('Please add a valid email address.', 'classified_core');
                        echo json_encode($json);
                        die;
                    }
                }

                if ($key === 'confirm_password') {
                    if ($_POST['register']['password'] != $_POST['register']['confirm_password']) {
                        $json['type'] = 'error';
                        $json['message'] = esc_html__('Password does not match.', 'classified_core');
                        echo json_encode($json);
                        die;
                    }
                }
            }


            if ($_POST['terms'] === '0') {
                $json['type'] = 'error';
                $json['message'] = esc_html__('Please select term and conditions', 'classified_core');
                echo json_encode($json);
                die;
            }

            if (function_exists('fw_get_db_settings_option')) {
                $captcha_settings = fw_get_db_settings_option('captcha_settings', $default_value = null);
            }

            //recaptcha check
            if (isset($captcha_settings) && $captcha_settings === 'enable'
            ) {
                if (isset($_POST['g-recaptcha-response']) && !empty($_POST['g-recaptcha-response'])) {
                    $docReResult = classified_get_recaptcha_response($_POST['g-recaptcha-response']);

                    if ($docReResult == 1) {
                        $workdone = 1;
                    } else if ($docReResult == 2) {
                        echo json_encode(array('type' => 'error', 'loggedin' => false, 'message' => esc_html__('Some error occur, please try again later.', 'classified_core')));
                        die;
                    } else {
                        echo json_encode(array('type' => 'error', 'loggedin' => false, 'message' => esc_html__('Wrong reCaptcha. Please verify first.', 'classified_core')));
                        die;
                    }
                } else {
                    echo json_encode(array('type' => 'error', 'loggedin' => false, 'message' => esc_html__('Please enter reCaptcha!', 'classified_core')));
                    die;
                }
            }

            extract($_POST['register']);
            $json = array();

            $random_password = $password;

            $user_identity = wp_create_user($username, $random_password, $email);
            if (is_wp_error($user_identity)) {
                $json['type'] = "error";
                $json['message'] = esc_html__("User already exists. Please try another one.", 'classified_core');
                echo json_encode($json);
                die;
            } else {
                global $wpdb;
                wp_update_user(array('ID' => esc_sql($user_identity), 'role' => esc_sql($db_user_role), 'user_status' => 1));

                $wpdb->update(
                        $wpdb->prefix . 'users', array('user_status' => 1), array('ID' => esc_sql($user_identity))
                );

                $full_name = classified_get_username($user_identity);

                if (isset($_POST['register']['account']) && sanitize_text_field($_POST['register']['account']) === 'seeker') {
                    update_user_meta($user_identity, 'first_name', $first_name);
                    update_user_meta($user_identity, 'last_name', $last_name);
                } else {
                    if (isset($_POST['register']['type']) && sanitize_text_field($_POST['register']['type']) === 'business') {
                        update_user_meta($user_identity, 'company_name', $company_name);
                    } else {
                        update_user_meta($user_identity, 'first_name', $first_name);
                        update_user_meta($user_identity, 'last_name', $last_name);
                    }
                }

                if (function_exists('fw_get_db_settings_option')) {
                    $dir_longitude = fw_get_db_settings_option('dir_longitude');
                    $dir_latitude = fw_get_db_settings_option('dir_latitude');
                    $dir_longitude = !empty($dir_longitude) ? $dir_longitude : '-0.1262362';
                    $dir_latitude = !empty($dir_latitude) ? $dir_latitude : '51.5001524';
                } else {
                    $dir_longitude = '-0.1262362';
                    $dir_latitude = '51.5001524';
                }


                update_user_meta($user_identity, 'show_admin_bar_front', false);
                update_user_meta($user_identity, 'phone_number', sanitize_text_field($phone_number));
                update_user_meta($user_identity, 'verify_user', $verify_user);
                update_user_meta($user_identity, 'full_name', sanitize_text_field($full_name));
                update_user_meta($user_identity, 'phone', sanitize_text_field($phone));
                update_user_meta($user_identity, 'email', sanitize_text_field($email));
                update_user_meta($user_identity, 'category', sanitize_text_field($category));
                update_user_meta($user_identity, 'sub_category', sanitize_text_field($sub_category));
                update_user_meta($user_identity, 'activation_status', 'active');

                update_user_meta($user_identity, 'latitude', $dir_latitude);
                update_user_meta($user_identity, 'longitude', $dir_longitude);

                $key_hash = md5(uniqid(openssl_random_pseudo_bytes(32)));
                update_user_meta($user_identity, 'confirmation_key', $key_hash);

                $protocol = is_ssl() ? 'https' : 'http';

                $verify_link = esc_url(add_query_arg(array(
                    'key' => $key_hash . '&verifyemail=' . $email
                                ), home_url('/', $protocol)));


                //Send email to users and admin
                if (class_exists('ClassifiedProcessEmail')) {
                    $email_helper = new ClassifiedProcessEmail();

                    $emailData = array();
                    $emailData['user_identity'] = $user_identity;
                    $emailData['first_name'] = esc_attr($first_name);
                    $emailData['last_name'] = esc_attr($last_name);
                    $emailData['password'] = $random_password;
                    $emailData['username'] = $username;
                    $emailData['email'] = $email;

                    $email_helper->process_registeration_email($emailData);
                    $email_helper->process_registeration_admin_email($emailData);

                    $emailData['verify_link'] = $verify_link;
                    $email_helper->process_email_verification($emailData);
                }


                $json['type'] = "success";
                $json['message'] = esc_html__("An email has sent to your email address, please verify your account before using our services or contact to administrator to verify your account.", "classified_core");
                return new WP_REST_Response($json, 200);
            }
        }

    }

}

add_action('rest_api_init', function () {
    $controller = new ClassifiedApp_Providers_Route;
    $controller->register_routes();
});
