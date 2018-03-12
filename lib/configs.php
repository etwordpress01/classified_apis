<?php

if (!class_exists('ClassifiedApp_Config_Route')) {

    class ClassifiedApp_Config_Route extends WP_REST_Controller
    {

        /**
         * Register the routes for the objects of the controller.
         */
        public function register_routes() {
            $version = '1';
            $namespace = 'api/v' . $version;
            $base = 'configs';

            register_rest_route($namespace, '/' . $base . '/countries',
                    array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_countries'),
                    // 'permission_callback' => array( $this, 'get_items_permissions_check' ),
                    'args' => array(
                    ),
                ),
            ));
        }

        /**
         * Get countries
         *
         * @param WP_REST_Request $request Full data about the request.
         * @return WP_Error|WP_REST_Response
         */
        public function get_countries($request) {
            // Get the categories for post and product post types
            $parent_terms = get_terms('countries',
                    array('parent' => 0,
                'orderby' => 'slug',
                'hide_empty' => false,
                'fields' => 'names'));

            $countries = array();
            if (isset($parent_terms) && !empty($parent_terms)) {
                foreach ($parent_terms as $key => $pterm) {

                    $args = array(
                        'hide_empty' => false,
                        'meta_key' => 'country',
                        'meta_value' => $pterm->slug,
                        'fields' => 'names'
                    );
                    $cities = get_terms('cities', $args);
                    $a = (array) $pterm;
                    $countries[$key]['name'] = $a[0];
                    $countries[$key]['cities'] = (array) $cities;
                }
            }


            return new WP_REST_Response($countries, 200);
        }

    }

}
add_action('rest_api_init', function () {
    $controller = new ClassifiedApp_Config_Route;
    $controller->register_routes();
});
