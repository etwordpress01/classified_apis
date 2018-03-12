<?php

if (!class_exists('ClassifiedAppFeaturedRoutes')) {

    class ClassifiedAppFeaturedRoutes extends WP_REST_Controller
    {

        /**
         * Register the routes for the objects of the controller.
         */
        public function register_routes() {
            $version 	= '1';
            $namespace  = 'api/v' . $version;
            $base 		= 'featured_ads';
            register_rest_route($namespace, '/' . $base,
                    array(
                array(
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => array($this, 'get_featured_ads'),
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
        public function get_featured_ads($request) {
			$today = time();
			$query_args = array(
				'posts_per_page' => 10,
				'post_type' => 'ca_classified_ads',
				'order' => 'DESC',
				'orderby' => 'ID',
				'post_status' => 'publish',
				'ignore_sticky_posts' => 1,
				'meta_query' => array(
					array(
						'key' => 'featured',
						'value' => $today,
						'type' => 'numeric',
						'compare' => '>'
					),
				),
			);
			
			$query = new WP_Query($query_args);
			
			$items	= array();
            $options = '';
			
			if ($query->have_posts()) {
				while ($query->have_posts()) {
					$query->the_post();
					global $post;
					$price = '';
					$gallery = '';
					$price_on_call = '';
					$phone = '';
					$address = '';
					$today = time();
					
					if (function_exists('fw_get_db_post_option')) {
						$price = fw_get_db_post_option($post->ID, 'price', true);
						$gallery = fw_get_db_post_option($post->ID, 'gallery', true);
						$price_on_call = fw_get_db_post_option($post->ID, 'price_on_call', true);
						$phone = fw_get_db_post_option($post->ID, 'phone', true);
						$address = fw_get_db_post_option($post->ID, 'profile_address', true);
					}                    

					$height = 220;
					$width = 220;
					$thumbnail = classified_pro_prepare_thumbnail($post->ID, $width, $height);

					if (empty($thumbnail)) {
						$thumbnail = get_template_directory_uri() . '/images/blog-list.jpg';
					}
					
					$item = array();
					
					$item['id'] 	= $post->ID;
					$item['slug'] 	= $post->post_name;
					$item['thumbnail'] 	= $thumbnail;
					$item['price'] 		= $price;
					if( $price_on_call === true || $price_on_call === 1 ){
						$item['price'] 		= esc_html__('Price on call', 'classified-pro');
					}
					
					$item['is_featured'] 	= 'yes';
                    $item['title'] 			= get_the_title($post->ID);

					$items[] 		= $item;

				}
			}


            return new WP_REST_Response($items, 200);
        }

    }

}

add_action('rest_api_init', function () {
    $controller = new ClassifiedAppFeaturedRoutes;
    $controller->register_routes();
});
