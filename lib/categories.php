<?php

if (!class_exists('ClassifiedAppCategoryRoutes')) {

    class ClassifiedAppCategoryRoutes extends WP_REST_Controller
    {

        /**
         * Register the routes for the objects of the controller.
         */
        public function register_routes() {
            $version 	= '1';
            $namespace  = 'api/v' . $version;
            $base 		= 'ad_categories';
            register_rest_route($namespace, '/' . $base,
                    array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_categories'),
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
        public function get_categories($request) {
			$taxonomy	= 'ca_category';
			$cust_query = get_terms(array(
                'taxonomy' 		=> $taxonomy,
                'hide_empty' 	=> false,
				'parent' 		=> 0,
            ));
			
			$items	= array();
            $options = '';

            if (!empty($cust_query)) {
                $counter = 0;
                foreach ($cust_query as $key => $term) {
                    $meta = get_post_meta($term->term_id);
                    $item = array();
					
                    $item['id'] 	= $term->term_id;
					$item['slug'] 	= $term->slug;
					$item['parent'] = $term->parent;
                    $item['title'] 	= $term->name;
					$item['count'] 	= $term->count;
                    $item  			+= fw_get_db_term_option($term->term_id, $taxonomy);

					$term_children = get_term_children( $term->term_id, $taxonomy );
					$childs	= array();
					$all_child	= array();
					if( !empty( $term_children ) ){
						foreach ( $term_children as $child ) {
							$child_term = get_term_by( 'id', $child, $taxonomy );

							$childs['id'] 		= $child_term->term_id;
							$childs['parent'] 	= $child_term->parent;
							$childs['slug'] 	= $child_term->slug;
							$childs['title'] 	= $child_term->name;
							$childs['count'] 	= $child_term->count;
							$childs  			+= fw_get_db_term_option($child_term->term_id, $taxonomy);
							
							$all_child[]	= $childs;
						}
					}

					$item['sub_categories'] = $all_child;
					$items[] 		= $item;
                }
            }


            return new WP_REST_Response($items, 200);
        }

    }

}

add_action('rest_api_init', function () {
    $controller = new ClassifiedAppCategoryRoutes;
    $controller->register_routes();
});
