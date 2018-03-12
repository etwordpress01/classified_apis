<?php

if (!class_exists('ClassifiedAppTrendingCategoriesRoutes')) {

    class ClassifiedAppTrendingCategoriesRoutes extends WP_REST_Controller
    {

        /**
         * Register the routes for the objects of the controller.
         */
        public function register_routes() {
            $version 	= '1';
            $namespace  = 'api/v' . $version;
            $base 		= 'trending_categories';
            register_rest_route($namespace, '/' . $base,
                    array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_trending_categories'),
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
        public function get_trending_categories($request) {
			global $wpdb;
			
			$taxonomy	= 'ca_category';
			$query = "SELECT a.name, a.slug, b.term_id, b.count FROM $wpdb->term_taxonomy b
						LEFT JOIN $wpdb->terms a
						ON b.term_id = a.term_id
						WHERE b.taxonomy = 'ca_category'
						ORDER BY b.count DESC
						LIMIT 10";

			$cust_query = $wpdb->get_results($query);
			$items	= array();
            $options = '';

            if (!empty($cust_query)) {
                $counter = 0;
				//usort($cust_query,array(&$this, 'classified_get_cat_sort'));
                foreach ($cust_query as $key => $term) {
                    $meta = get_post_meta($term->term_id);
                    $item = array();
					
                    $item['id'] 	= $term->term_id;
					$item['slug'] 	= $term->slug;
					$item['parent'] = $term->parent;
                    $item['title'] 	= $term->name;
					$item['count'] 	= $term->count;
                    $item  			+= fw_get_db_term_option($term->term_id, $taxonomy);
					
					$items[] 		= $item;
                }
            }

            return new WP_REST_Response($items, 200);
        }
		
		/**
		 * @sort categories by post count
		 * @return html
		 */
		public function classified_get_cat_sort($a, $b){  //The function to order our authors
		  if ($a->count == $b->count) {  //This is where the name of our custom meta key is entered, I named mine "order"
			return 0;  
		  }  
		  return ( $b->count < $a->count ) ? -1 : 1;  //The actual sorting is done here. Change ">" to "<" to reverse order
		} 

    }

}

add_action('rest_api_init', function () {
    $controller = new ClassifiedAppTrendingCategoriesRoutes;
    $controller->register_routes();
});
