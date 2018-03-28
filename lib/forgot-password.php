<?php

if (!class_exists('ClassifiedAppForgotPasswordRoutes')) {

    class ClassifiedAppForgotPasswordRoutes extends WP_REST_Controller {

        /**
         * Register the routes for the objects of the controller.
         */
        public function register_routes() {
            $version = '1';
            $namespace = 'api/v' . $version;
            $base = 'forgot_password';
            register_rest_route($namespace, '/' . $base, array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_forgot_password'),
                    'args' => array(
                    ),
                ),
            ));
        }

        /**
         * Set Forgot Password
         *
         * @param WP_REST_Request $request Full data about the request.
         * @return WP_Error|WP_REST_Response
         */
        public function get_forgot_password($request) {
            global $wpdb;
            $json = array();
            $params = $request->get_params();

            if (isset($params['email'])) {
                $user_login = $params['email'];
                $status = true;
                $response_message = '';
                $json['message'] = 'Some error occured';

                $user_login = sanitize_text_field($user_login);

                if (empty($user_login)) {
                    $status = false;
                    $response_message = 'Please enter email address';
                } else if (!is_email($user_login)) {
                    $response_message = 'Please add a valid email address';
                } else if (strpos($user_login, '@')) {
                    $user_data = get_user_by_email(trim($user_login));
                    if (empty($user_data) || $user_data->caps['administrator'] == 1) {
                        $status = false;
                        $response_message = 'Email address does not exists.';
                    }
                } else {
                    $login = trim($user_login);
                    $user_data = get_user_by('login', $login);
                }

                if ($user_data) {
                    // redefining user_login ensures we return the right case in the email
                    $user_login = $user_data->user_login;
                    $user_email = $user_data->user_email;

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

                    echo $message;
                    die;

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
    $controller = new ClassifiedAppForgotPasswordRoutes;
    $controller->register_routes();
});
