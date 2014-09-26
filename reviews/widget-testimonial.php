<?php
/**
 * Adds Reviews Testimonial widget
 *
 * @author 		Smartest Themes
 * @package		ST Business Carry Over
 * @subpackage Reviews 
 * @extends 	WP_Widget
 */
if ( ! class_exists( 'SmartestReviewsTestimonialLegacy' ) ) {

	class SmartestReviewsTestimonialLegacy extends WP_Widget {
		/**
		 * Register widget with WordPress.
		 */
		public function __construct() {
			parent::__construct(
				'smartest_reviews_testimonial',
				'Smartest Reviews Testimonial',
				array( 'description' => __( 'Display a random review as a testimonial.', 'st-business-carry-over-legacy' ), )
			);
		}
		/**
		 * Front-end display of widget.
		 * @param array $args     Widget arguments.
		 * @param array $instance Saved values from database.
		 */
		public function widget( $args, $instance ) {
			$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Testimonials', 'st-business-carry-over-legacy' ) : $instance['title'], $instance, $this->id_base );
			$number = isset( $instance['number'] ) ? $instance['number'] : '';
			echo $args['before_widget']; ?>
			<h3 class="widget-title"><?php echo $title; ?></h3>
			<?php
			/** 
			* pull reviews from smartest reviews table 
			*/

			global $wpdb;

			$reviews_pageurl = get_permalink(get_option('smartestthemes_reviews_page_id'));
			$pre = $wpdb->base_prefix;

			if ( is_multisite() ) { 
				global $blog_id;
				$bi = get_current_blog_id();
				
				$pre2 = $pre . $bi . '_st_reviews';
			} else {
				// not Multisite
				$pre2 = $pre . 'st_reviews';
			}

			$number_testimonials = ! empty( $number ) ? $number : 1;
			$getreviews = $wpdb->get_results("SELECT review_text FROM $pre2 WHERE status = 1 LIMIT 0,$number_testimonials");
		
			if ( empty( $getreviews ) ) {
					//no review yet, lure them to leave one
					?>
					<p><?php _e( 'Be the first to', 'st-business-carry-over-legacy' ); echo ' '; ?> <a href="<?php echo $reviews_pageurl; ?>"><?php _e( 'leave a review...', 'st-business-carry-over-legacy' ); ?></a></p>
			<?php } else {
					foreach ( $getreviews as $getreview ) { ?>
						<blockquote><?php echo wp_trim_words( $getreview->review_text, 20); ?></blockquote><br />
					<?php } ?>
					<a href="<?php echo $reviews_pageurl; ?>"><?php _e('More...', 'st-business-carry-over-legacy'); ?></a>
			<?php }
			echo $args['after_widget'];
		}// end widget

		/**
		 * Sanitize widget form values as they are saved.
		 * @param array $new_instance Values just sent to be saved.
		 * @param array $old_instance Previously saved values from database.
		 * @return array Updated safe values to be saved.
		 */
		public function update( $new_instance, $old_instance ) {
			$instance = array();
			$instance['title'] = strip_tags($new_instance['title'] );
			$instance['number'] = strip_tags( $new_instance['number'] );
			return $instance;
		}

		/**
		 * Back-end widget form.
		 * @param array $instance Previously saved values from database.
		 */
		public function form( $instance ) {
			$title = isset( $instance[ 'title' ] ) ? $instance[ 'title' ] : __( 'Testimonials', 'st-business-carry-over-legacy' );
			$number = isset( $instance[ 'number' ] ) ? $instance[ 'number' ] : 1;
			?>
			<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'st-business-carry-over-legacy' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'How many testimonials to show:', 'st-business-carry-over-legacy' ); ?></label> 
			<input type="text" class="widefat" id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" value="<?php echo esc_attr( $number ); ?>" />
		</p>
			<?php 
		}
	}
}
?>