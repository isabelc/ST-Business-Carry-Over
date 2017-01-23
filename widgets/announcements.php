<?php
/**
 * Adds Announcements widget
 *
 * @author Smartest Themes
 * @package ST Business Carry Over
 * @extends WP_Widget
 */

 if ( ! class_exists( 'SmartestAnnouncements_Legacy' ) ) {
 
	class SmartestAnnouncements_Legacy extends WP_Widget {
		/**
		 * Register widget
		 */
		public function __construct() {
			parent::__construct(
				'smartest_announcements',
				__( 'Smartest Announcements', 'st-business-carry-over' ),
				array( 'description' => __( 'Display the latest Announcements.', 'st-business-carry-over' ), )
			);
		}
		/**
		 * Front-end display of widget.
		 * @param array $args     Widget arguments.
		 * @param array $instance Saved values from database.
		 */
		public function widget( $args, $instance ) {
			$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Latest News', 'st-business-carry-over' ) : $instance['title'], $instance, $this->id_base );
			$number = isset( $instance['number'] ) ? $instance['number'] : 3;
			$see_all_label = ( ! empty( $instance['see_all_label'] ) ) ? $instance['see_all_label'] : __('All Announcements', 'st-business-carry-over');

			echo $args['before_widget'];
			echo '<h3 class="widget-title">'. $title . '</h3>';
			
			/** 
			* loop through announcements 
			*/
			$query_args = array( 
				'posts_per_page' => $number, 
				'post_type' => 'smartest_news',
				'order' => 'DESC' );
			$sbfnews = new WP_Query( $query_args );
			if ( $sbfnews->have_posts() ) { ?>
				<ul>
				<?php while ( $sbfnews->have_posts() ) {
					$sbfnews->the_post(); ?>
					<li><a href="<?php echo get_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php echo get_the_title(); ?></a><br />
					<?php $datetime = get_the_date('Y-m-d');
					printf ( '<time datetime="%s">%s</time>', $datetime, get_the_date() ); ?>
					</li>
			 
				<?php } ?>
				</ul>
				<?php $li = '<a href="'.get_post_type_archive_link( 'smartest_news' ).'">'. $see_all_label . '</a>';
				?> <p><?php printf(__( '%s', 'st-business-carry-over'), $li); ?></p>

		<?php } else {
					?>
					<p><?php _e('Coming soon.', 'st-business-carry-over'); ?></p>		
		<?php }
			wp_reset_postdata();
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
			$instance['see_all_label'] = strip_tags($new_instance['see_all_label']);
			return $instance;
		}

		/**
		 * Back-end widget form.
		 * @param array $instance Previously saved values from database.
		 */
		public function form( $instance ) {
			$title = isset( $instance[ 'title' ] ) ? $title = $instance[ 'title' ] : __( 'Latest News', 'st-business-carry-over' );
			$number = isset( $instance[ 'number' ] ) ? $instance[ 'number' ] : 3;
			$see_all_label = isset( $instance['see_all_label'] ) ? esc_attr( $instance['see_all_label'] ) : '';

	/* Default Widget Settings */
			?>
			<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'st-business-carry-over' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>

			<p>
			<label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'How many recent announcements to show:', 'st-business-carry-over' ); ?></label> 
			<input type="text" class="widefat" id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" value="<?php echo esc_attr( $number ); ?>" />
		</p>
			<p><label for="<?php echo $this->get_field_id( 'see_all_label' ); ?>"><?php _e( 'Replace the link label, "All Announcements", with your own text:', 'st-business-carry-over' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'see_all_label' ); ?>" name="<?php echo $this->get_field_name( 'see_all_label' ); ?>" type="text" value="<?php echo $see_all_label; ?>" /></p>

			<?php 
		}

	}
}
?>