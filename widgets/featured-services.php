<?php
/**
 * Adds Featured Services widget to show selected services
 *
 * @author 		Smartest Themes
 * @package		ST Business Carry Over
 * @extends 	WP_Widget
 */

 if ( ! class_exists( 'SmartestFeaturedServicesLegacy' ) ) {
 	class SmartestFeaturedServicesLegacy extends WP_Widget {
		/**
		 * Register widget with WordPress.
		 */
		public function __construct() {
			parent::__construct(
				'smartest_featured_services',
				__('Smartest Featured Services', 'st-business-carry-over-legacy'),
				array( 'description' => __( 'Display selected featured services.', 'st-business-carry-over-legacy' ), )
			);
			add_action( 'init', array($this, 'add_css'), 10);
		}
		
		/**
		* Add CSS to custom-style.php
		*/
		public function add_css( $css ) {
			$add_css = '';
			if ( get_option( 'smartestb_show_services' ) == 'true' ) {
				$add_css .= '.sfswrap{width:100%;overflow:hidden;position:relative;padding-bottom:21px;border-bottom:1px #e5e5e5 solid;margin-bottom:20px}.sfsfig{float:left;margin:0 20px 0 0}.sfscontent{overflow:hidden;padding-right:15px}.widget_smartest_featured_services .widget-title{margin-left:0}#sidebar .sfsfig{float:none}#first .footer-widget .sfsfig,#third .footer-widget .sfsfig{max-width:30%;} .sfsfig a{width:100%;}@media screen and (max-device-width:568px){.sfsfig{float:none;margin:0 0 1em 0;}}';
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

			$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Featured Services', 'st-business-carry-over-legacy' ) : $instance['title'], $instance, $this->id_base );
			echo $args['before_widget'];

			echo '<h3 class="widget-title">'. $title . '</h3>';
			
			/* loop through announcements */

			$query_args = array( 
				'post_type' => 'smartest_services',
				'meta_query' => array(
							array  (
								'key' => '_smab_services_featured',
								'value'=> 'on' 
							)
						),
				'orderby' => 'meta_value_num',
				'meta_key' => '_smab_service-order-number',
				'order' => 'ASC'
				);
			$sbffs = new WP_Query( $query_args );
			if ( $sbffs->have_posts() ) {
				while ( $sbffs->have_posts() ) {
					$sbffs->the_post(); ?>
					<div class="sfswrap">
					<?php if ( has_post_thumbnail() ) { ?>
						<figure class="sfsfig"><a href="<?php echo get_permalink(); ?>" title="<?php the_title_attribute(); ?>">
						<?php the_post_thumbnail( 'featservices' ); ?>
						</a></figure>
					<?php } ?>
					
					<div class="sfscontent">
						<h4><a href="<?php echo get_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php echo get_the_title(); ?></a></h4>
						<?php echo get_the_excerpt(); ?>
					</div>
					</div>
				<?php } // endwhile
			} else {
					$li = '<a href="'.get_post_type_archive_link( 'smartest_services' ).'">'. __('Services', 'st-business-carry-over-legacy'). '</a>';
					?>
					<p><?php printf(__( 'Coming soon. See all %s.', 'st-business-carry-over-legacy'), $li); ?></p>		
	<?php	} // endif
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
			$title = isset( $instance[ 'title' ] ) ? $instance[ 'title' ] : __( 'Featured Services', 'st-business-carry-over-legacy' );
			?>
			<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'st-business-carry-over-legacy' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>
			<?php 
		}
	}
}
?>