<?php
/**
 * Adds Featured Announcements widget to show selected announcements
 *
 * @author Smartest Themes
 * @package ST Business Carry Over
 * @extends WP_Widget
 */

if ( ! class_exists( 'SmartestFeaturedAnnounceLegacy' ) ) {
 
	class SmartestFeaturedAnnounceLegacy extends WP_Widget {

		/**
		 * Register widget
		 */
		public function __construct() {
			parent::__construct(
				'smartest_featured_announce',
				__('Smartest Featured Announcements', 'st-business-carry-over-legacy'),
				array( 'description' => __( 'Display selected featured announcements.', 'st-business-carry-over-legacy' ), )
			);
			add_action( 'init', array($this, 'add_css'), 15);
		}
		
		/**
		* Add CSS to custom-style.php
		*/
		public function add_css( $css ) {
			$add_css = '';
			if ( get_option('smartestb_show_news') == 'true' ) {
				$add_css .= '.sfawrap{width:100%;overflow:hidden;position:relative;margin-bottom:3em}.sfafig{float:left;margin:0 20px 0 0}.sfafig img{border:0 none}.sfacontent{overflow:hidden;padding-right:15px}.sfacontent p{margin-bottom:15px;margin-left:0}';
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
			$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Featured News', 'st-business-carry-over-legacy' ) : $instance['title'], $instance, $this->id_base );

			echo $args['before_widget'];
			echo '<h3 class="widget-title">'. $title . '</h3>';
			
			/** 
			* loop through announcements 
			*/
			$query_args = array(
				'post_type' => 'smartest_news',
				'meta_query' => array(
									array  (
										'key' => '_smab_news_featured',
										'value'=> 'on'
										)
								)			
				);
			$sbffa = new WP_Query( $query_args );
			if ( $sbffa->have_posts() ) {
				while ( $sbffa->have_posts() ) {
					$sbffa->the_post(); ?>
					<div class="sfawrap">
					<?php if ( has_post_thumbnail() ) { ?>
						<figure class="sfafig"><a href="<?php echo get_permalink(); ?>" title="<?php the_title_attribute(); ?>">
						<?php the_post_thumbnail( 'newswidget' ); ?>
						</a></figure>
					<?php } else {
						// if not stopped with option @test

						if(get_option('smartestb_stop_theme_icon') != 'true') { ?>

							<a href="<?php echo get_permalink(); ?>" title="<?php the_title_attribute(); ?>" class="sfafig"><div class="newsicon"><i class="fa fa-bullhorn fa-3x"></i></div></a>
						<?php }
					} ?>
						
				<div class="sfacontent">
					<h4><a href="<?php echo get_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php echo get_the_title(); ?></a></h4>
					<p><?php echo get_the_excerpt(); ?></p>
					<a class="button" href="<?php echo get_permalink(); ?>" title="<?php the_title_attribute(); ?>">
					<?php _e( 'Read More', 'st-business-carry-over-legacy' ); ?></a>
				</div></div>
			 
				<?php } // endwhile;
						
				} // end if have posts

				else { 
					$li = '<a href="'.get_post_type_archive_link( 'smartest_news' ).'">'. __('News', 'st-business-carry-over-legacy'). '</a>';
					?>
					<p><?php printf(__( 'Coming soon. See all %s.', 'st-business-carry-over-legacy'), $li); ?></p>		
	<?php 
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
			$title = isset( $instance[ 'title' ] ) ? $instance[ 'title' ] : __( 'Featured News', 'st-business-carry-over-legacy' );		
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