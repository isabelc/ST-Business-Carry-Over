<?php
/**
 * Adds Staff widget to list all staff members
 *
 * @author 		Smartest Themes
 * @package		ST Business Carry Over
 * @extends 	WP_Widget
 */

if ( ! class_exists( 'SmartestStaff_Legacy' ) ) {

	 class SmartestStaff_Legacy extends WP_Widget {
	 
		/**
		 * Register widget
		 */
		public function __construct() {
			parent::__construct(
				'smartest_staff_list',
				__('Smartest Staff List', 'st-business-carry-over'),
				array( 'description' => __( 'Display the full list of Staff members.', 'st-business-carry-over' ), )
			);
			add_action( 'init', array($this, 'add_css'), 5);
		}
		
		/**
		* Add CSS to custom-style.php
		*/
		public function add_css( $css ) {
			$add_css = '';
			if ( get_option('smartestb_show_staff') == 'true' ) {
				$add_css .= '.widget_smartest_staff_list, .sstwrap {width: 100%;}.sstwrap {overflow: hidden;position: relative;margin-bottom: 1em;display: block;}.ssfig {float: left;margin: 0px 20px 0px 0px;}.sstcontent {padding-top: 20px;	float: left;}';
			}
			$css = get_option('smartestthemes_widget_styles') . $add_css;
			update_option('smartestthemes_widget_styles', $css );
		}
		
		/**
		 * Front-end display of widget.
		 * @param array $args     Widget arguments.
		 * @param array $instance Saved values from database.
		 */
		public function widget( $args, $instance ) {
			$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Staff', 'st-business-carry-over' ) : $instance['title'], $instance, $this->id_base );
			echo $args['before_widget'];

			echo '<h3 class="widget-title">'. $title . '</h3>';
			/** 
			* loop through staff
			*/
			$query_args = array( 
				'posts_per_page' => -1, 
				'post_type' => 'smartest_staff',
				'orderby' => 'meta_value_num',
				'meta_key' => '_smab_staff-order-number',
				'order' => 'ASC' );
			$sbfstaff = new WP_Query( $query_args );
			if ( $sbfstaff->have_posts() ) {
				while ( $sbfstaff->have_posts() ) {
					$sbfstaff->the_post(); ?>
					<div class="sstwrap">
					<?php if ( has_post_thumbnail() ) { ?>
						<figure class="ssfig"><a href="<?php echo get_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_post_thumbnail( 'staffwidget' ); ?></a></figure>
					<?php } ?>
					<div class="sstcontent">
					<h5><a href="<?php echo get_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php echo get_the_title(); ?></a></h5></div></div>
				<?php }
			}
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
			return $instance;
		}

		/**
		 * Back-end widget form.
		 * @param array $instance Previously saved values from database.
		 */
		public function form( $instance ) {
			$title = isset( $instance[ 'title' ] ) ? $instance[ 'title' ] : __( 'Staff', 'st-business-carry-over' );
			?>
			<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'st-business-carry-over' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>
			<?php 
		}
	}
}
?>