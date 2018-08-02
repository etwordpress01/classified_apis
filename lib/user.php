<?php

if (!class_exists('ClassifiedApp_User_Route')) {

    class ClassifiedApp_User_Route extends WP_REST_Controller {

        /**
         * Register the routes for the objects of the controller.
         */
        public function register_routes() {
            $version = '1';
            $namespace = 'api/v' . $version;
            $base = 'user';

            register_rest_route($namespace, '/' . $base . '/do_login', array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_items'),
                    'args' => array(
                    ),
                ),
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'user_login'),
                    'args' => array(),
                ),
                    )
            );

            register_rest_route($namespace, '/' . $base . '/do_register', array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_items'),
                    'args' => array(
                    ),
                ),
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'user_signup'),
                    'args' => array(),
                ),
                    )
            );

            register_rest_route($namespace, '/' . $base . '/token', array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_items'),
                    'args' => array(
                    ),
                ),
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'save_user_device_token'),
                    'args' => array(),
                ),
                    )
            );

            register_rest_route($namespace, '/' . $base . '/remove-token', array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_items'),
                    'args' => array(
                    ),
                ),
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'remove_user_device_token'),
                    'args' => array(),
                ),
                    )
            );


            register_rest_route($namespace, '/' . $base . '/reset-password', array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_items'),
                    // 'permission_callback' => array( $this, 'get_items_permissions_check' ),
                    'args' => array(
                    ),
                ),
                array(
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => array($this, 'forgot_password'),
                    'args' => array(),
                ),
                    )
            );
        }

        /**
         * Sign Up
         *
         * @param WP_REST_Request $request Full data about the request.
         * @return WP_Error|WP_REST_Response
         */
        function user_signup($request) {
            global $wpdb;
            $verify_user = 'off';
            $json = array();
            //$params 			= $request->get_params();
            $params = !empty($request['register']) ? $request['register'] : array();

            $data_array = array(
                'username' => esc_html__('Username is required.', 'classified_pro_core'),
                'first_name' => esc_html__('First name is required.', 'classified_pro_core'),
                'last_name' => esc_html__('Last name is required.', 'classified_pro_core'),
                'email' => esc_html__('Email address is required.', 'classified_pro_core'),
                'password' => esc_html__('Password is required.', 'classified_pro_core'),
                'confirm_password' => esc_html__('Please re-type your password.', 'classified_pro_core'),
            );

            $db_user_role = 'agent';

            $emailData = array();
            foreach ($data_array as $key => $value) {
                if (empty($params[$key])) {
                    $json['type'] = 'error';
                    $json['message'] = $value;
                    echo json_encode($json);
                    die;
                }

                if ($key === 'email') {
                    if (!is_email($params[$key])) {
                        $json['type'] = 'error';
                        $json['message'] = esc_html__('Please add a valid email address.', 'classified_pro_core');
                        echo json_encode($json);
                        die;
                    }
                }

                if ($key === 'confirm_password') {
                    if ($params['password'] != $params['confirm_password']) {
                        $json['type'] = 'error';
                        $json['message'] = esc_html__('Password does not match.', 'classified_pro_core');
                        echo json_encode($json);
                        die;
                    }
                }
            }

            if (!empty($params['terms'])) {
                $json['type'] = 'error';
                $json['message'] = esc_html__('Please select term and conditions', 'classified_pro_core');
                echo json_encode($json);
                die;
            }

            //extract post data
            extract($params);
            $json = array();

            $random_password = $password;

            $user_identity = wp_create_user($username, $random_password, $email);
            if (is_wp_error($user_identity)) {
                $json['type'] = "error";
                $json['message'] = esc_html__("User already exists. Please try another one.", 'classified_pro_core');
                echo json_encode($json);
                die;
            } else {
                global $wpdb;
                wp_update_user(array('ID' => esc_sql($user_identity), 'role' => esc_sql($db_user_role), 'user_status' => 1));

                $wpdb->update(
                        $wpdb->prefix . 'users', array('user_status' => 1), array('ID' => esc_sql($user_identity))
                );

                update_user_meta($user_identity, 'first_name', $first_name);
                update_user_meta($user_identity, 'last_name', $last_name);

                if (function_exists('fw_get_db_settings_option')) {
                    $dir_longitude = fw_get_db_settings_option('dir_longitude');
                    $dir_latitude = fw_get_db_settings_option('dir_latitude');
                    $dir_longitude = !empty($dir_longitude) ? $dir_longitude : '-0.1262362';
                    $dir_latitude = !empty($dir_latitude) ? $dir_latitude : '51.5001524';
                } else {
                    $dir_longitude = '-0.1262362';
                    $dir_latitude = '51.5001524';
                }

                $full_name = classified_pro_get_username($user_identity);

                update_user_meta($user_identity, 'show_admin_bar_front', false);
                update_user_meta($user_identity, 'cp_profile_user_type', 'individual');
                update_user_meta($user_identity, 'verify_user', $verify_user);
                update_user_meta($user_identity, 'full_name', sanitize_text_field($full_name));
                update_user_meta($user_identity, 'email', sanitize_text_field($email));
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
                if (class_exists('ClassifiedProProcessEmail')) {
                    $email_helper = new ClassifiedProProcessEmail();

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

                $user_array = array();
                $user_array['user_login'] = $username;
                $user_array['user_password'] = $random_password;
                $status = wp_signon($user_array, false);

                $json['type'] = "success";
                $json['message'] = esc_html__("An email has sent to your email address, please verify your account before using our services or contact to administrator to verify your account.", "classified_pro_core");
                return new WP_REST_Response($json, 200);
            }
        }

        /**
         * Get a collection of items
         *
         * @param WP_REST_Request $request Full data about the request.
         * @return WP_Error|WP_REST_Response
         */
        public function get_items($request) {
            $items['data'] = array();
            return new WP_REST_Response($items, 200);
        }

        /**
         * Login user for application
         *
         * @param WP_REST_Request $request Full data about the request.
         * @return WP_Error|WP_REST_Request
         */
        public function save_user_device_token($request) {
            $params = $request->get_params();

            $user = get_userdata($params['user_id']);

            if ($user === false) {
                $json['message'] = 'User does not exists!';
            } else {

                $tokens = get_user_meta($params['user_id'], 'device_token');
                if (!in_array($params['device_token'], $tokens)) {
                    add_user_meta($params['user_id'], 'device_token', $params['device_token']);
                    $json['message'] = esc_html__('Token saved!', "classified_core");
                } else {
                    $json['message'] = esc_html__('Token already exists', "classified_core");
                }
            }

            return new WP_REST_Response($json, 200);
        }

        /**
         * Login user for application
         *
         * @param WP_REST_Request $request Full data about the request.
         * @return WP_Error|WP_REST_Request
         */
        public function remove_user_device_token($request) {
            $params = $request->get_params();

            $user = get_userdata($params['user_id']);

            if ($user === false) {
                $json['message'] = esc_html__('User does not exists!', "classified_core");
            } else {

                $tokens = get_user_meta($params['user_id'], 'device_token');
                if (in_array($params['device_token'], $tokens)) {
                    delete_user_meta($params['user_id'], 'device_token', $params['device_token']);
                    $json['message'] = esc_html__('Token removed!', "classified_core");
                } else {
                    $json['message'] = esc_html__('Token does not exists', "classified_core");
                }
            }

            return new WP_REST_Response($json, 200);
        }

        /**
         * Login user for application
         *
         * @param WP_REST_Request $request Full data about the request.
         * @return WP_Error|WP_REST_Request
         */
        public function user_login($request) {
            $params = $request->get_params();

            if (!empty($request['username']) && !empty($request['password'])) {
                $creds = array(
                    'user_login' => $request['username'],
                    'user_password' => $request['password'],
                    'remember' => true
                );

                $user = wp_signon($creds, false);

                if (is_wp_error($user)) {
					$json['type'] 		= "error";
					$json['message'] 	= esc_html__("Wrong username and password.", "classified_core");
					return new WP_REST_Response($json, 200);
                } else {
					unset($user->allcaps);
					unset($user->filter);
					$user->meta = get_user_meta($user->data->ID, '', true);
					$user->avatar = apply_filters(
							'classified_pro_get_media_filter', classified_pro_get_user_avatar(array('width' => 150, 'height' => 150), $user->data->ID), array('width' => 150, 'height' => 150)
					);
					
                    $json['type'] 		= "success";
                    $json['message'] 	= esc_html__("Successfully logged in.", "classified_core");
                    $json['data'] 		= $user;

                    return new WP_REST_Response($json, 200);
                }
            } else {
				$json['type'] 		= "error";
				$json['message'] 	= esc_html__("Please add username and password to login.", "classified_core");
				return new WP_REST_Response($json, 200);
            }
        }

        /**
         * Forgot password for application
         *
         * @param WP_REST_Request $request Full data about the request.
         * @return WP_Error|WP_REST_Request
         */
        public function forgot_password($request) {
            global $wpdb, $wp_hasher;
            $params = $request->get_params();
            $_POST = $request->get_params();
            $json = array();

            if (isset($params['email'])) {
                $user_login = $params['email'];
                $status = true;
                $response_message = '';
                $json['message'] = 'Some error occured';

                $user_login = sanitize_text_field($user_login);

                if (empty($user_login)) {
                    $status = false;
                    $response_message = 'Please enter email address';
                } else if (strpos($user_login, '@')) {
                    $user_data = get_user_by('email', trim($user_login));
                    if (empty($user_data))
                        $status = false;
                    $response_message = 'Email address does not exist';
                } else {
                    $login = trim($user_login);
                    $user_data = get_user_by('login', $login);
                }

                do_action('lostpassword_post');

                if ($user_data) {
                    // redefining user_login ensures we return the right case in the email
                    $user_login = $user_data->user_login;
                    $user_email = $user_data->user_email;

                    do_action('retreive_password', $user_login);  // Misspelled and deprecated
                    do_action('retrieve_password', $user_login);

                    $allow = apply_filters('allow_password_reset', true, $user_data->ID);

                    if (!$allow) {
                        $status = false;
                        $json['message'] = 'Password change not allowed';
                    } else if (is_wp_error($allow))
                        $status = false;

                    $key = wp_generate_password(20, false);
                    do_action('retrieve_password_key', $user_login, $key);

                    if (empty($wp_hasher)) {
                        require_once ABSPATH . 'wp-includes/class-phpass.php';
                        $wp_hasher = new PasswordHash(8, true);
                    }
                    $hashed = $wp_hasher->HashPassword($key);
                    $wpdb->update($wpdb->users, array('user_activation_key' => $hashed), array('user_login' => $user_login));

                    $message = __('Someone requested that the password be reset for the following account:') . "\r\n\r\n";
                    $message .= network_home_url('/') . "\r\n\r\n";
                    $message .= sprintf(__('Username: %s'), $user_login) . "\r\n\r\n";
                    $message .= __('If this was a mistake, just ignore this email and nothing will happen.') . "\r\n\r\n";
                    $message .= __('To reset your password, visit the following address:') . "\r\n\r\n";
                    $message .= '<' . network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login') . ">\r\n";

                    if (is_multisite())
                        $blogname = $GLOBALS['current_site']->site_name;
                    else
                        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

                    $title = sprintf(__('[%s] Password Reset'), $blogname);

                    $title = apply_filters('retrieve_password_title', $title);
                    $message = apply_filters('retrieve_password_message', $message, $key);

                    if ($message && !wp_mail($user_email, $title, $message))
                        $response_message = ( __('The e-mail could not be sent.') . "<br />\n" . __('Possible reason: your host may have disabled the mail() function...') );

                    $response_message = '<p>Link for password reset has been emailed to you. Please check your email.</p>';
                }

                $json['message'] = $response_message;
                if ($status) {
                    $json['type'] = "success";
                    $json['data'] = array();
                    return new WP_REST_Response($json, 200);
                } else {
                    $json['type'] = "error";
                    return new WP_REST_Response($json, 200);
                }
            }
        }

    }

}

add_action('rest_api_init', function () {
    $controller = new ClassifiedApp_User_Route;
    $controller->register_routes();
});
