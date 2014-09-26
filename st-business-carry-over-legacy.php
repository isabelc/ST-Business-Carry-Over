<?php
/*
Plugin Name: ST Business Carry Over Legacy
Plugin URI: @todo
Description: Carry over your staff, announcements, services, and reviews from your Legacy Smartest Themes to any WordPress theme.
Version: 1.0-alpha-2
Author: Smartest Themes
Author URI: http://smartestthemes.com
License: GPL2
Text Domain: st-business-carry-over-legacy
Domain Path: languages
Copyright 2014 Smartest Themes(email : isa@smartestthemes.com)

ST Business Carry Over Legacy is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

ST Business Carry Over Legacy is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with ST Business Carry Over Legacy; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*

@todo final checklist:
@todo update all textdomain of 'st-business-carry-over' or 'crucible' or 'smartestb' or 'quick-business-website'... to 'st-business-carry-over-legacy'

*/

class ST_Business_Carry_Over_Legacy{
	private static $instance = null;

	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	private function __construct() {
		
		if( ! defined('STBUSCARRYOVERLEGACY_PATH')) {
			define( 'STBUSCARRYOVERLEGACY_PATH', plugin_dir_path(__FILE__) );
		}

		add_action( 'admin_init', array( $this, 'admin_init' ) );
		
		// Registers custom taxonomy for services
		add_action( 'init', array( $this, 'taxonomies' ), 0 );	
		
		add_action( 'init', array( $this, 'create_smartest_business_cpts' ) );
		
		add_action( 'init', array( $this, 'init_meta_boxes' ), 9999 );
		
		add_action( 'plugins_loaded', array( $this, 'load' ) );
		
		add_action( 'after_setup_theme', array( $this, 'after_setup' ) );
		
		add_action( 'admin_menu', array( $this, 'add_admin' ) );
		
		add_action( 'wp_ajax_smartestthemes_ajax_post_action', array( $this, 'ajax_callback' ) );
		
		add_filter( 'stbcol_meta_boxes', array( $this, 'metaboxes' ) );
		
		add_filter( 'enter_title_here', array( $this, 'change_staff_enter_title' ) );
		
		add_action( 'widgets_init', array( $this, 'register_widgets' ) );
		
		// Add job title column to staff back end
		add_filter( 'manage_edit-smartest_staff_columns', array( $this, 'smar_manage_edit_staff_columns' ) );
		add_action( 'manage_smartest_staff_posts_custom_column', array( $this, 'smar_manage_staff_columns' ), 10, 2 );

		//  Add featured service column to services back end
		add_filter( 'manage_edit-smartest_services_columns', array( $this, 'smar_manage_edit_services_columns' ) );
		add_action( 'manage_smartest_services_posts_custom_column', array( $this, 'smar_manage_services_columns' ), 10, 2 );

		//  Add featured news column to news back end
		add_filter( 'manage_edit-smartest_news_columns', array( $this, 'smar_manage_edit_news_columns' ) );
		add_action( 'manage_smartest_news_posts_custom_column', array( $this, 'smar_manage_news_columns' ), 10, 2 );	
			
		// custom sort order
		add_filter( 'parse_query', array( $this, 'sort_staff' ) );
		add_filter( 'parse_query', array( $this, 'sort_services' ) );
		
		// add widget styles inline in head, if needed
		add_action( 'wp_head', 'stbco_wp_head', 9999 );
		
    } // end __contruct

	/**
	* Only upon plugin activation, setup options and flush rewrite rules for custom post types.
	*/
	public static function activate() { 
	
		add_action( 'admin_head', array( __CLASS__, 'option_setup' ) );
		flush_rewrite_rules();
		
	}
	
	/**
	* Include plugin options and load textdomain
	*/
	
	public function load() {
		
		load_plugin_textdomain( 'st-business-carry-over-legacy', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		
		include STBUSCARRYOVERLEGACY_PATH . 'options.php';
		add_action( 'init', 'stbcol_options' );
	}
	
	
	
	/**
	* Store plugin version as option
	*
	* @return void
	*/
	public function admin_init(){
		$plugin_data = get_plugin_data( __FILE__, false );
		update_option( 'stbcol_smartestb_plugin_version', $plugin_data['Version'] );
	}	
	
	
	/**  
	* Setup options panel
	*/
	public function option_setup(){
		//Update EMPTY options

		// @todo sync with qbw to get old legacy names
		
		// get the old options first so as not to remove the original options such as business address
		$smartestthemes_array = get_option('smartestb_options');
		if ( empty( $smartestthemes_array ) ) {
			$smartestthemes_array = array();
		}

		update_option('smartestb_options', $smartestthemes_array);// add the old options

		$template = get_option('stbcol_template');

		foreach($template as $option) {
			if($option['type'] != 'heading'){
				$id = isset($option['id']) ? $option['id'] : '';
				$std = isset($option['std']) ? $option['std'] : '';
				$db_option = get_option($id);
				if(empty($db_option)){
					if(is_array($option['type'])) {
						foreach($option['type'] as $child){
							$c_id = $child['id'];
							$c_std = $child['std'];
							update_option($c_id,$c_std);
							$smartestthemes_array[$c_id] = $c_std;

						}
					} else {
						update_option($id,$std);
						$smartestthemes_array[$id] = $std;
					}
				}
				else { //So just store the old values over again.
					$smartestthemes_array[$id] = $db_option;
				}
			}
		}
		
		update_option('smartestb_options',$smartestthemes_array);	
	
	}// end option_setup	

	/** 
	*  add admin options page
	*/
	public function add_admin() {
		global $query_string;

		$pagename = 'st-carryover-legacy-settings';
		
		if ( isset($_REQUEST['page']) && $pagename == $_REQUEST['page'] ) {
			if (isset($_REQUEST['smartestthemes_save']) && 'reset' == $_REQUEST['smartestthemes_save']) {
				$options =  get_option('stbcol_template');
				
				$this->reset_options($options,$pagename);
				
				header("Location: admin.php?page=$pagename&reset=true");
				die;
			}
		}
		
		$icon = plugins_url( 'images/smartestthemes-icon.png' , __FILE__ );
		
		$sto=add_menu_page( __( 'ST Business Carry Over Legacy Options', 'st-business-carry-over-legacy' ), __('Business Carry Over Options', 'st-business-carry-over-legacy' ), 'activate_plugins', $pagename, array($this, 'options_page'), $icon, 68.9);
		
		add_action( 'admin_head-'. $sto, array( $this, 'frame_load' ));
		
	} // end add_admin
	
	/**
	* Reset options
	*/
	function reset_options($options, $page = ''){
		global $wpdb;
		$query_inner = '';
		$count = 0;
		$excludes = array( 'blogname' , 'blogdescription' );
		foreach($options as $option){
			if(isset($option['id'])){ 
				$count++;
				$option_id = $option['id'];
				$option_type = $option['type'];
				
				//Skip assigned id's
				if(in_array($option_id,$excludes)) { continue; }
				
				if($count > 1){ $query_inner .= ' OR '; }
				if($option_type == 'multicheck'){
					$multicount = 0;
					foreach($option['options'] as $option_key => $option_option){
						$multicount++;
						if($multicount > 1){ $query_inner .= ' OR '; }
						$query_inner .= "option_name = '" . $option_id . "_" . $option_key . "'";
						
					}
					
				} else if(is_array($option_type)) {
					$type_array_count = 0;
					foreach($option_type as $inner_option){
						$type_array_count++;
						$option_id = $inner_option['id'];
						if($type_array_count > 1){ $query_inner .= ' OR '; }
						$query_inner .= "option_name = '$option_id'";
					}
					
				} else {
					$query_inner .= "option_name = '$option_id'";
				}
			}
		}
		
		//When Theme Options page is reset - Add the smartestb_options option

		if ( $page == 'st-carryover-legacy-settings' ) {
			$query_inner .= " OR option_name = 'smartestb_options'";
		}
		$query = "DELETE FROM $wpdb->options WHERE $query_inner";
		$wpdb->query($query);
	}	
	
	/** 
	 * add CPTs conditionally, if enabled
	 * adds smartest_staff, smartest_news, smartest_services 
	 */
	function create_smartest_business_cpts() {
		
		$options = get_option('smartestb_options');
		
		$staff = empty($options['smartestb_show_staff']) ? '' : $options['smartestb_show_staff'];
		$news = empty($options['smartestb_show_news']) ? '' : $options['smartestb_show_news'];
		$services = empty($options['smartestb_show_services']) ? '' : $options['smartestb_show_services'];
		
		if( $staff == 'true'  ) {
			$args = array(
				'label'						=> __('Staff','st-business-carry-over-legacy'),
				'singular_label'			=> __('Staff','st-business-carry-over-legacy'),
				'public'						=> true,
				'show_ui'					=> true,
				'capability_type'		=> 'post',
				'hierarchical'				=> false,
				'rewrite'					=> array(
												'slug'				=> __('staff', 'st-business-carry-over-legacy'),
												'with_front'	=> false),
				'exclude_from_search'	=> false,
				'labels'							=> array(
					'name'					=> __( 'Staff','st-business-carry-over-legacy' ),
					'singular_name'		=> __( 'Staff','st-business-carry-over-legacy' ),
					'add_new'				=> __( 'Add New','st-business-carry-over-legacy' ),
					'add_new_item'	=> __( 'Add New Staff','st-business-carry-over-legacy' ),
					'all_items'				=> __( 'All Staff','st-business-carry-over-legacy' ),
					'edit'						=> __( 'Edit','st-business-carry-over-legacy' ),
					'edit_item'			=> __( 'Edit Staff','st-business-carry-over-legacy' ),
					'new_item'			=> __( 'New Staff','st-business-carry-over-legacy' ),
					'view'					=> __( 'View Staff','st-business-carry-over-legacy' ),
					'view_item'			=> __( 'View Staff','st-business-carry-over-legacy' ),
					'search_items'		=> __( 'Search Staff','st-business-carry-over-legacy' ),
					'not_found'			=> __( 'No staff found','st-business-carry-over-legacy' ),
					'not_found_in_trash' => __( 'No staff found in Trash','st-business-carry-over-legacy' ),
					'parent'					=> __( 'Parent Staff','st-business-carry-over-legacy' ),
				),
				'supports'				=> array('title','editor','thumbnail'),
				'has_archive'			=> true,
				'menu_icon'			=> 'dashicons-groups',
			);
			register_post_type( 'smartest_staff' , $args );
		}// end if show staff enabled
		
		if($news == 'true') {
			$args = array(
				'label' 					=> __('Announcements','st-business-carry-over-legacy'),
				'singular_label'		=> __('Announcement','st-business-carry-over-legacy'),
				'public'					=> true,
				'show_ui'				=> true,
				'capability_type'	=> 'post',
				'hierarchical'			=> false,
				'rewrite'				=> array(
						'slug'				=> __('news','st-business-carry-over-legacy'),
						'with_front'	=> false,
				),
				'exclude_from_search' => false,
				'labels' => array(
					'name'						=> __( 'Announcements','st-business-carry-over-legacy' ),
					'singular_name'			=> __( 'Announcement','st-business-carry-over-legacy' ),
					'add_new'					=> __( 'Add New','st-business-carry-over-legacy' ),
					'add_new_item'		=> __( 'Add New Announcement','st-business-carry-over-legacy' ),
					'all_items'					=> __( 'All Announcements','st-business-carry-over-legacy' ),
					'edit'							=> __( 'Edit','st-business-carry-over-legacy' ),
					'edit_item'				=> __( 'Edit Announcement','st-business-carry-over-legacy' ),
					'new_item'				=> __( 'New Announcement','st-business-carry-over-legacy' ),
					'view'						=> __( 'View Announcement','st-business-carry-over-legacy' ),
					'view_item'				=> __( 'View Announcement','st-business-carry-over-legacy' ),
					'search_items'			=> __( 'Search Announcements','st-business-carry-over-legacy' ),
					'not_found'				=> __( 'No announcement found','st-business-carry-over-legacy' ),
					'not_found_in_trash'	=> __( 'No announcements found in Trash','st-business-carry-over-legacy' ),
					'parent'						=> __( 'Parent Announcement','st-business-carry-over-legacy' ),
				),
				'supports'			=> array('title','editor','thumbnail'),
				'has_archive'		=> true,
				'menu_icon'		=> 'dashicons-exerpt-view'
			);
			register_post_type( 'smartest_news' , $args );
		}// end if show news enabled
		
		if($services == 'true') {
			$args = array(
				'label'				=> __('Services','st-business-carry-over-legacy'),
				'singular_label'	=> __('Service','st-business-carry-over-legacy'),
				'public'				=> true,
				'show_ui'			=> true,
				'capability_type' => 'post',
				'hierarchical'		=> false,
				'rewrite'			=> array(
							'slug'				=> __('services','st-business-carry-over-legacy'),
							'with_front'	=> false),
				'exclude_from_search' => false,
				'labels' => array(
					'name'					=> __( 'Services','st-business-carry-over-legacy' ),
					'singular_name'		=> __( 'Service','st-business-carry-over-legacy' ),
					'add_new'				=> __( 'Add New','st-business-carry-over-legacy' ),
					'all_items'				=> __( 'All Services','st-business-carry-over-legacy' ),
					'add_new_item'	=> __( 'Add New Service','st-business-carry-over-legacy' ),
					'edit'						=> __( 'Edit','st-business-carry-over-legacy' ),
					'edit_item'			=> __( 'Edit Service','st-business-carry-over-legacy' ),
					'new_item'			=> __( 'New Service','st-business-carry-over-legacy' ),
					'view'					=> __( 'View Services','st-business-carry-over-legacy' ),
					'view_item'			=> __( 'View Service','st-business-carry-over-legacy' ),
					'search_items'		=> __( 'Search Services','st-business-carry-over-legacy' ),
					'not_found'			=> __( 'No services found','st-business-carry-over-legacy' ),
					'not_found_in_trash' => __( 'No services found in Trash','st-business-carry-over-legacy' ),
					'parent'					=> __( 'Parent Service','st-business-carry-over-legacy' ),
					),
				'supports'					=> array('title','editor','thumbnail'),
				'has_archive'				=> true,
				'menu_icon'				=> 'dashicons-portfolio'
			);
			register_post_type( 'smartest_services' , $args );
		}// end if show services enabled

	}
	
	/**
	 * Registers custom taxonomy for services.
	 */
	function taxonomies() {
		$category_labels = array(
			'name'						=> __( 'Service Categories', 'st-business-carry-over-legacy' ),
			'singular_name'			=>__( 'Service Category', 'st-business-carry-over-legacy' ),
			'search_items'			=> __( 'Search Service Categories', 'st-business-carry-over-legacy' ),
			'all_items'					=> __( 'All Service Categories', 'st-business-carry-over-legacy' ),
			'parent_item'			=> __( 'Service Parent Category', 'st-business-carry-over-legacy' ),
			'parent_item_colon'	=> __( 'Service Parent Category:', 'st-business-carry-over-legacy' ),
			'edit_item'				=> __( 'Edit Service Category', 'st-business-carry-over-legacy' ),
			'update_item'			=> __( 'Update Service Category', 'st-business-carry-over-legacy' ),
			'add_new_item'		=> __( 'Add New Service Category', 'st-business-carry-over-legacy' ),
			'new_item_name'		=> __( 'New Service Category Name', 'st-business-carry-over-legacy' ),
			'menu_name'			=> __( 'Service Categories', 'st-business-carry-over-legacy' ),
		);
		$category_args = apply_filters( 'smartestthemes_service_category_args', array(
			'hierarchical'					=> true,
			'labels'							=> apply_filters('smartestthemes_service_category_labels', $category_labels),
			'show_ui'						=> true,
			'show_admin_column'	=> true,
			'query_var'					=> true,
			'rewrite'						=> array(
								'slug'				=> 'services/category',
								'with_front'	=> false,
								'hierarchical'	=> true ),
		)
		);
		register_taxonomy( 'smartest_service_category', array('smartest_services'), $category_args );
		register_taxonomy_for_object_type( 'smartest_service_category', 'smartest_services' );
	}
	
	/** 
	 * Activate Reviews, create Reviews page if needed, and update the About page content.
	 */
	public function after_setup() {

		$options = get_option('smartestb_options');
		$reviews = empty($options['smartestb_add_reviews']) ? '' : $options['smartestb_add_reviews'];
	
		if (!class_exists('SMARTESTReviewsLegacy') && ($reviews == 'true')) {
			include_once STBUSCARRYOVERLEGACY_PATH . 'reviews/reviews.php';
		}
		
		if ( $reviews == 'true' ) {
			// Create the reviews page if necessary.
			global $wpdb;
			// If page already been created, do not create
			$page_exists = get_option( 'smartest_reviews_page_id' );
			if ( $page_exists > 0 && get_post( $page_exists ) ) {
				return;
			}
			$page_data = array(
				'post_status' => 'publish',
				'post_type' => 'page',
				'post_author' => 1,
				'post_name' => esc_sql( _x('reviews', 'page_slug', 'st-business-carry-over-legacy') ),
				'post_title' => __('Reviews', 'st-business-carry-over-legacy'),
				'post_content' => '[smartest_reviews]',
				'post_parent' => 0,
				'comment_status' => 'closed'
			);
			$page_id = wp_insert_post( $page_data );
			update_option( 'smartest_reviews_page_id', $page_id );			
		}
		
		// Add the custom About page content from Theme Options panel to the regular About page content.
		// Run this update only once
		if ( get_option( 'stbcol_update_about_page' ) != 'completed' ) {

			$about_page_id = get_option( 'smartest_about_page_id' );
			
			// get any regular about page content
			$about_page = get_post( $about_page_id );
			
			if ( $about_page ) {
			
				$about_page_regular_content = empty( $about_page->post_content ) ? '' : $about_page->post_content;
			
				
				// custom content
				$about_page_custom_content = empty($options['smartestb_about_page']) ? '' : $options['smartestb_about_page'];

				if ( $about_page_custom_content ) {
				
					$custom_out = stripslashes_deep( $about_page_custom_content );
					$custom_out = wpautop( $custom_out );
				}

		
				// custom About page image
				$about_pic = empty($options['smartestb_about_picture']) ? '' : $options['smartestb_about_picture'];

				if ( $about_pic ) {
				
					$about_pic_out = '<figure><img src="' . $about_pic . '" alt="' . __( 'About us', 'st-business-carry-over-legacy' ) . '" /></figure><br />';
					
				}
				
				// Update the about page with the custom About page content.
				$new_about_page_post = array(
					'ID'					=> $about_page_id,
					'post_content'	=> $about_pic_out . $custom_out . $about_page_regular_content
				);

				// Update the post into the database
				wp_update_post( $new_about_page_post );
  
			}
			
			update_option( 'stbcol_update_about_page', 'completed' );
			
		}

		
		
		
	}
	
	/**
	 * Custom metaboxes and fields.
	 * For staff: occupational title & social links.
	 * For services: featured option and sort order.
	 * For news: featured option.
	 */

	/**
	 * Define the metabox and field configurations.
	 * @param  array $meta_boxes
	 * @return array
	 */
	function metaboxes( array $meta_boxes ) {
		$prefix = '_smab_';

		// @test remove global $smartestb_options;
		
		$meta_boxes[] = array(
			'id'         => 'staff_details',
			'title'      => __('Details', 'st-business-carry-over-legacy'),
			'pages'      => array( 'smartest_staff', ), // Post type
			'context'    => 'normal',
			'priority'   => 'high',
			'show_names' => true,
			'fields'     => array(
				array(
					'name' => __('Job Title', 'st-business-carry-over-legacy'),
					'desc' => __('The staff member\'s job title. Optional', 'st-business-carry-over-legacy'),
					'id'   => $prefix . 'staff_job_title',
					'type' => 'text_medium',
				),
				array(
					'name' => __( 'Sort Order Number', 'st-business-carry-over-legacy' ),
					'desc' => __( 'Give this person a number to order them on the list on the staff page and in the staff widget. Numbers do not have to be consecutive; for example, you could number them like, 10, 20, 35, 45, etc. This would leave room to insert new staff members later without having to change everyone\'s current number.', 'st-business-carry-over-legacy' ),
					'id'   => $prefix . 'staff-order-number',
					'type' => 'text',
					'std' => 9999
				),
				array(
					'name' => __('Facebook Profile ID', 'st-business-carry-over-legacy'),
					'desc' => __('The staff member\'s Facebook profile ID. Optional', 'st-business-carry-over-legacy'),
					'id'   => $prefix . 'staff_facebook',
					'type' => 'text_medium',
				),
				array(
					'name' => __('Twitter Username', 'st-business-carry-over-legacy'),
					'desc' => __('The staff member\'s Twitter username. Optional', 'st-business-carry-over-legacy'),
					'id'   => $prefix . 'staff_twitter',
					'type' => 'text_medium',
				),
				array(
					'name' => __('Google Plus Profile ID', 'st-business-carry-over-legacy'),
					'desc' => __('The staff member\'s Google Plus profile ID. Optional', 'st-business-carry-over-legacy'),
					'id'   => $prefix . 'staff_gplus',
					'type' => 'text_medium',
				),
				 array(
					'name' => __('Linkedin Profile', 'st-business-carry-over-legacy'),
					'desc' => __('The part of the profile address after "www.linkedin.com/". Optional', 'st-business-carry-over-legacy'),
					'id' => $prefix . 'staff_linkedin',
					'type' => 'text_medium',
				)
			)
		);

		// services 'featured' meta box
		$meta_boxes[] = array(
			'id'         => 'featured_svcs',
			'title'      => __('Featured Services', 'st-business-carry-over-legacy'),
			'pages'      => array( 'smartest_services', ),
			'context'    => 'side',
			'priority'   => 'default',//high, core, default, low
			'show_names' => true,
			'fields'     => array(
				array(
					'name' => __('Feature this?', 'st-business-carry-over-legacy'),
					'desc' => __('Check this box to feature this service in the list of featured services on the home page and in the Featured Services widget.', 'st-business-carry-over-legacy'),
					'id'   => $prefix . 'services_featured',
					'type' => 'checkbox',
				),
			)
		);

		if( get_option('smartestb_enable_service_sort') == 'true' ) { 			
			$meta_boxes[] = array(
				'id'         => 'services-sort-order',
				'title'      => __( 'Set a Sort-Order', 'st-business-carry-over-legacy' ),
				'pages'      => array( 'smartest_services' ),
				'context'    => 'normal',
				'priority'   => 'high',//high, core, default, low
				'show_names' => true,
				'fields'     => array(
					array(
						'name' => __( 'Sort Order Number', 'st-business-carry-over-legacy' ),
						'desc' => __( 'Give this service a number to order it on the list on the service page and in the services widget. Numbers do not have to be consecutive; for example, you could number them like, 10, 20, 35, 45, etc. This would leave room to insert new services later without having to change all current numbers.', 'st-business-carry-over-legacy' ),
						'id'   => $prefix . 'service-order-number',
						'type' => 'text',
						'std' => 9999
					),
				)
			);
		}
		
		$meta_boxes[] = array(
			'id'         => 'featured_news',
			'title'      => __('Featured News', 'st-business-carry-over-legacy'),
			'pages'      => array( 'smartest_news', ),
			'context'    => 'side',
			'priority'   => 'default',
			'show_names' => true, // Show field names on the left
			'fields'     => array(
				array(
					'name' => __('Feature this?', 'st-business-carry-over-legacy'),
					'desc' => __('Check this box to feature this announcement in the Featured Announcements widget.', 'st-business-carry-over-legacy'),
					'id'   => $prefix . 'news_featured',
					'type' => 'checkbox',
				),
			)
		);

		return apply_filters( 'smartestthemes_cmb', $meta_boxes );
	}
	
	/**
	 * Initialize the metabox class.
	 */
	function init_meta_boxes() {
		if ( ! class_exists( 'stbcol_Meta_Box' ) )
			require_once STBUSCARRYOVERLEGACY_PATH . 'metabox/init.php';
	}
	
	/**
	 * 'Enter Staff member's name here' instead of 'Enter title here'
	 * for smartest_staff cpt
	 */
	public function change_staff_enter_title( $title ){
		$screen = get_current_screen();
		if  ( 'smartest_staff' == $screen->post_type ) {
			$title = __('Enter staff member\'s name here', 'st-business-carry-over-legacy');
		}
		return $title;
	}

		
	/**
	 * register widgets
	 */
	public function register_widgets() {
	
		$options = get_option('smartestb_options');

		$svcs = empty($options['smartestb_show_services']) ? '' : $options['smartestb_show_services'];
		$staff = empty($options['smartestb_show_staff']) ? '' : $options['smartestb_show_staff'];
		$news = empty($options['smartestb_show_news']) ? '' : $options['smartestb_show_news'];
		
		if( $news == 'true'  ) { 
		
			include STBUSCARRYOVERLEGACY_PATH . 'widgets/announcements.php';
			include STBUSCARRYOVERLEGACY_PATH . 'widgets/featured-announcements.php';
		
			register_widget('SmartestAnnouncements_Legacy');
			register_widget('SmartestFeaturedAnnounceLegacy');
		
		}
		if( $svcs == 'true'  ) { 
		
			include STBUSCARRYOVERLEGACY_PATH . 'widgets/all-services.php';
			include STBUSCARRYOVERLEGACY_PATH . 'widgets/featured-services.php';		
		
			register_widget('SmartestServices_Legacy'); 
			register_widget('SmartestFeaturedServicesLegacy');
		
		}
		if( $staff == 'true' ) {
		
			include STBUSCARRYOVERLEGACY_PATH . 'widgets/staff.php';
			register_widget('SmartestStaff_Legacy');
		
		}
	}
	
/** 
 * Add job title column to staff admin
 */

function smar_manage_edit_staff_columns( $columns ) {
	$columns = array(
		'cb' => '<input type="checkbox" />',
		'title' => __('Name', 'st-business-carry-over-legacy'),
		'jobtitle' => __('Job Title', 'st-business-carry-over-legacy'),
		'date' => __('Date', 'st-business-carry-over-legacy')
	);

	return $columns;
}
/**
 * Add data to job title column in staff admin
 */


function smar_manage_staff_columns( $column, $post_id ) {
	global $post;
	switch( $column ) {
		case 'jobtitle' :
			$jobtitle = get_post_meta( $post_id, '_smab_staff_job_title', true );
			 echo $jobtitle;
			break;
		default :
			break;
	}
}

	/**
	 * Add featured service column to services admin
	 */

	function smar_manage_edit_services_columns( $columns ) {
		$columns = array(
			'cb' => '<input type="checkbox" />',
			'title' => __('Title', 'st-business-carry-over-legacy'),
			'taxonomy-smartest_service_category' => __('Categories', 'st-business-carry-over-legacy'),
			'featureds' => __('Featured', 'st-business-carry-over-legacy'),
			'date' => __('Date', 'st-business-carry-over-legacy')
		);
		return $columns;
	}

	/**
	 * Add data to featured services column in services admin
	 */
	
	function smar_manage_services_columns( $column, $post_id ) {
		global $post;
		switch( $column ) {
			case 'featureds' :
				$sf = get_post_meta( $post_id, '_smab_services_featured', true );
				if ( $sf )
					_e('Featured', 'st-business-carry-over-legacy');
				break;
			default :
				break;
		}
	}

	/**
	 * Add featured news column to news admin
	 */

	function smar_manage_edit_news_columns( $columns ) {
		$columns = array(
			'cb' => '<input type="checkbox" />',
			'title' => __('Title', 'st-business-carry-over-legacy'),
			'featuredn' => __('Featured', 'st-business-carry-over-legacy'),
			'date' => __('Date', 'st-business-carry-over-legacy')
		);
		return $columns;
	}

	/**
	 * Add data to featured news column in news admin
	 */

	function smar_manage_news_columns( $column, $post_id ) {
		global $post;
		switch( $column ) {
			case 'featuredn' :
				$sf = get_post_meta( $post_id, '_smab_news_featured', true );
				if ( $sf )
					_e('Featured', 'st-business-carry-over-legacy');
				break;
			default :
				break;
		}
	}

	/**
	 * Sort staff archive by staff order number key
	 *
	 * @uses is_admin()
	 * @uses is_post_type_archive()
	 * @uses is_main_query()
	 */
	function sort_staff($query) {
		if( !is_admin() && is_post_type_archive('smartest_staff') && $query->is_main_query() && isset( $query->query_vars['meta_key'] ) ) {
		$query->query_vars['orderby'] = 'meta_value_num';
		$query->query_vars['meta_key'] = '_smab_staff-order-number';
		$query->query_vars['order'] = 'ASC';
		}
		return $query;
	}

	/**
	 * Sort services archive by service order number key
	 *
	 * @uses is_admin()
	 * @uses is_post_type_archive()
	 * @uses is_main_query()
	 */
	function sort_services($query) {
		if( !is_admin()
		&&	(
			( is_post_type_archive('smartest_services') || is_tax( 'smartest_service_category' ) )
			&& $query->is_main_query()
			)
		&& isset( $query->query_vars['meta_key'] ) ) {
			$query->query_vars['orderby'] = 'meta_value_num';
			$query->query_vars['meta_key'] = '_smab_service-order-number';
			$query->query_vars['order'] = 'ASC';
		}
		return $query;
	}
	
	/**
	 *
	 * Plugin Options Panel
	 *
	 */
	function options_page(){
		$options		= get_option('stbcol_template');
		$image_dir	= plugins_url( 'images/' , __FILE__ );
		$plugin_data	= get_plugin_data( __FILE__ );
		$version		= $plugin_data['Version'];

		?>
	<div class="wrap" id="smartestthemes-container">
	<div id="smartestthemes-popup-save" class="smartestthemes-save-popup"><div class="smartestthemes-save-save"><?php _e('Options Updated', 'st-business-carry-over-legacy'); ?></div></div>
	<div id="smartestthemes-popup-reset" class="smartestthemes-save-popup"><div class="smartestthemes-save-reset"><?php _e('Options Reset', 'st-business-carry-over-legacy'); ?></div></div>
		<form action="" enctype="multipart/form-data" id="smartestform">
			<div id="header">
			   <div class="logo">
			<?php  // @test if logo shows up
			echo apply_filters('smartestthemes_backend_branding', '<img alt="Smartest Themes" src="'. $image_dir. 'st_logo_admin.png" />'); ?>
			  </div>
				 <div class="theme-info">
					<span class="theme" style="margin-top:10px;">ST Business Carry Over Legacy
							<span class="ver"> <?php printf(__('version %s', 'st-business-carry-over-legacy'), $version); ?>
	</span>						
					</span>
					
				</div>
				<div class="clear"></div>
			</div>
			<?php 
			// Rev up the Options Machine
			$return = $this->machine($options);
			?>
			<div id="support-links">
	<!--[if IE]>
	<div class="ie">
	<![endif]-->
				<ul>
				<li class="right"><img style="display:none" src="<?php echo $image_dir; ?>loading-top.gif" class="ajax-loading-img ajax-loading-img-top" alt="Working..." />
	<input type="submit" value="<?php _e( 'Save All Changes', 'st-business-carry-over-legacy' ); ?>" class="button submit-button" /></li>
				</ul> 
	<!--[if IE]>
	</div>
	<![endif]-->
			</div>
			<div id="main">
				<div id="smartestthemes-nav">
					<ul>
						<?php echo $return[1] ?>
					</ul>		
				</div>
				<div id="content">
				 <?php echo $return[0]; /* Settings */ ?>
				</div><div class="clear"></div>
			</div>
			<!--[if IE]>
			<div class="ie">
			<![endif]-->
			<div class="save_bar_top">
			<img style="display:none" src="<?php echo $image_dir; ?>loading-bottom.gif" class="ajax-loading-img ajax-loading-img-bottom" alt="Working..." />
			
			
			<input type="submit" value="<?php _e( 'Save All Changes', 'st-business-carry-over-legacy' ); ?>" class="button submit-button" />        
			</form>
			<form action="<?php echo esc_html( $_SERVER['REQUEST_URI'] ) ?>" method="post" style="display:inline" id="smartestform-reset">
				<span class="submit-footer-reset">
				<input name="reset" type="submit" value="<?php _e( 'Reset Options', 'st-business-carry-over-legacy' ); ?>" class="button submit-button reset-button" onclick="return confirm(localized_label.reset);" />
				<input type="hidden" name="smartestthemes_save" value="reset" /> 
				</span></form></div><!--[if IE 6]></div><![endif]--><div style="clear:both;"></div>    
	</div><!--wrap-->
	 <?php
	}	// end options_page
	
	
	function frame_load() {
	
		$fr = plugins_url( '/', __FILE__ );

		add_action('admin_head', 'stbcol_admin_head');
		
		function stbcol_admin_head() {
		
			?>
			<link rel="stylesheet" type="text/css" href="<?php echo plugins_url( '/admin-style.css', __FILE__ ); ?>" media="screen" />
			<?php 
			// Localize for js
			$local_str = __('Click OK to reset back to default settings. All custom plugin settings will be lost!', 'st-business-carry-over-legacy');
			?>
			<script>
				var localized_label = {
					reset : "<?php echo $local_str ?>"
				}
			</script>
			<script type="text/javascript">
				jQuery(document).ready(function(){
					jQuery('.group').hide();
					jQuery('.group:first').fadeIn();
					jQuery('.group .collapsed').each(function(){
						jQuery(this).find('input:checked').parent().parent().parent().nextAll().each( 
							function(){
								if (jQuery(this).hasClass('last')) {
									jQuery(this).removeClass('hidden');
									return false;
								}
								jQuery(this).filter('.hidden').removeClass('hidden');
							});
					});
					jQuery('.group .collapsed input:checkbox').click(unhideHidden);
					function unhideHidden(){
						if (jQuery(this).attr('checked')) {
							jQuery(this).parent().parent().parent().nextAll().removeClass('hidden');
						}
						else {
							jQuery(this).parent().parent().parent().nextAll().each( 
								function(){
									if (jQuery(this).filter('.last').length) {
										jQuery(this).addClass('hidden');
										return false;
									}
									jQuery(this).addClass('hidden');
								});
								
						}
					}
					jQuery('#smartestthemes-nav li:first').addClass('current');
					jQuery('#smartestthemes-nav li a').click(function(evt){
							jQuery('#smartestthemes-nav li').removeClass('current');
							jQuery(this).parent().addClass('current');
							var clicked_group = jQuery(this).attr('href');
							jQuery('.group').hide();
								jQuery(clicked_group).fadeIn();
							evt.preventDefault();
						});
					if('<?php if(isset($_REQUEST['reset'])) { echo $_REQUEST['reset'];} else { echo 'false';} ?>' == 'true'){
						var reset_popup = jQuery('#smartestthemes-popup-reset');
						reset_popup.fadeIn();
						window.setTimeout(function(){
							   reset_popup.fadeOut();                        
							}, 2000);
					}
				//Update Message popup
				jQuery.fn.center = function () {
					this.animate({"top":( jQuery(window).height() - this.height() - 200 ) / 2+jQuery(window).scrollTop() + "px"},100);
					this.css("left", 250 );
					return this;
				}
				jQuery('#smartestthemes-popup-save').center();
				jQuery('#smartestthemes-popup-reset').center();
				jQuery(window).scroll(function() { 
					jQuery('#smartestthemes-popup-save').center();
					jQuery('#smartestthemes-popup-reset').center();
				
				});
				
				//Save everything else
				jQuery('#smartestform').submit(function(){
						function newValues() {
						  var serializedValues = jQuery("#smartestform").serialize();
						  return serializedValues;
						}
						jQuery(":checkbox, :radio").click(newValues);
						jQuery("select").change(newValues);
						jQuery('.ajax-loading-img').fadeIn();
						var serializedReturn = newValues();
						var ajax_url = '<?php echo admin_url("admin-ajax.php"); ?>';
						var data = {
							<?php 
							if(isset($_REQUEST['page']) && $_REQUEST['page'] == 'st-carryover-legacy-settings' ){
								?>
								type: 'options',
								<?php
							}
							?>
							action: 'smartestthemes_ajax_post_action',
							data: serializedReturn
						};
						jQuery.post(ajax_url, data, function(response) {
							var success = jQuery('#smartestthemes-popup-save');
							var loading = jQuery('.ajax-loading-img');
							loading.fadeOut();  
							success.fadeIn();
							window.setTimeout(function(){
							   success.fadeOut(); 
							}, 2000);
						});
						return false; 
					});   	 	
				});
			</script>
		<?php }
	}	// end frame_load
	
	
		
	/**
	 *
	 * Ajax Save Action
	 *
	 */
	public function ajax_callback() {
		global $wpdb;
		$save_type = $_POST['type'];
		
		if ( $save_type == 'options' ) {

			// get the old options first so as not to remove the original options such as business address
			$smartestthemes_array = get_option('smartestb_options');
		
			$data = $_POST['data'];
			parse_str($data,$output);

			$options = get_option('stbcol_template');
					
			foreach($options as $option_array){
				$id = isset($option_array['id']) ? $option_array['id'] : '';
				$old_value = get_option($id);
				$new_value = '';
				if(isset($output[$id])){
					$new_value = $output[$option_array['id']];
				}
				if(isset($option_array['id'])) { // Non - Headings...
					
					//Import of prior saved options
					if($id == 'framework_smartestthemes_import_options'){
						//Decode and over write options.
						$new_import = $new_value;
						$new_import = unserialize($new_import);
						if(!empty($new_import)) {
							foreach($new_import as $id2 => $value2){
								if(is_serialized($value2)) {
									update_option($id2,unserialize($value2));
								} else {
									update_option($id2,$value2);
								}
							}
						}
						
					} else {
					
				
						$type = $option_array['type'];
						
						if ( is_array($type)){
							foreach($type as $array){
								if($array['type'] == 'text'){
									$id = $array['id'];
									$new_value = $output[$id];
									update_option( $id, stripslashes($new_value));// isa, may conflict w url inputs that need slashes
								}
							}                 
						}
						elseif($new_value == '' && $type == 'checkbox'){ // Checkbox Save
						
							update_option($id,'false');

						}
						elseif ($new_value == 'true' && $type == 'checkbox'){ // Checkbox Save
						
						
							update_option($id,'true');
							
							
									
									
						}
						elseif($type == 'multicheck'){ // Multi Check Save
							$option_options = $option_array['options'];
							foreach ($option_options as $options_id => $options_value){
								$multicheck_id = $id . "_" . $options_id;
								if(!isset($output[$multicheck_id])){
									update_option($multicheck_id,'false');
								}
								else{
									update_option($multicheck_id,'true'); 
								}
							}
						} 
						elseif($type != 'upload_min'){
							update_option($id,stripslashes($new_value));
						}
					
					}
				}	
			}

			/* Create, Encrypt and Update the Saved Settings */
			global $wpdb;
			$query_inner = '';
			$count = 0;

			print_r($options);
			foreach($options as $option){
				
				if(isset($option['id'])){ 
					$count++;
					$option_id = $option['id'];
					$option_type = $option['type'];
					
					if($count > 1){ $query_inner .= ' OR '; }
					
					if(is_array($option_type)) {
					$type_array_count = 0;
					foreach($option_type as $inner_option){
						$type_array_count++;
						$option_id = $inner_option['id'];
						if($type_array_count > 1){ $query_inner .= ' OR '; }
						$query_inner .= "option_name = '$option_id'";
						}
					}
					else {
					
						$query_inner .= "option_name = '$option_id'";
						
					}
				}
				
			}
			
			$query = "SELECT * FROM $wpdb->options WHERE $query_inner";
					
			$results = $wpdb->get_results($query);
			
			$output = "<ul>";
			
			foreach ($results as $result){
					$name = $result->option_name;
					$value = $result->option_value;
					
					if(is_serialized($value)) {
						
						$value = unserialize($value);
						$smartestthemes_array_option = $value;
						$temp_options = '';
						foreach($value as $v){
							if(isset($v))
								$temp_options .= $v . ',';
							
						}	
						$value = $temp_options;
						$smartestthemes_array[$name] = $smartestthemes_array_option;
					} else {
						$smartestthemes_array[$name] = $value;
					}
					
					$output .= '<li><strong>' . $name . '</strong> - ' . $value . '</li>';
			}
			$output .= "</ul>";
			
			update_option('smartestb_options',$smartestthemes_array);
			flush_rewrite_rules();
		}
		
		die();
	} // end ajax_callback


	/**
	 * Generate Options
	 */
	function machine($options) {
			
		$counter = 0;
		$menu = '';
		$output = '';
		foreach ($options as $value) {
		   
			$counter++;
			$val = '';
			//Start Heading
			 if ( $value['type'] != "heading" )
			 {
				$class = ''; if(isset( $value['class'] )) { $class = $value['class']; }
				$output .= '<div class="section section-'.$value['type'].' '. $class .'">'."\n";
				if ( !empty($value['name']) ) {
					$output .= '<h3 class="heading">'. $value['name'] .'</h3>'."\n";
				}
				$output .= '<div class="option">'."\n" . '<div class="controls">'."\n";
			 } 
			 //End Heading
			$select_value = '';                                   
			switch ( $value['type'] ) {

			case "checkbox": 
		if( !empty($value['std']) ) {
				$std = $value['std'];
		}
			   $saved_std = get_option($value['id']);
			   $checked = '';
				if(!empty($saved_std)) {
					if($saved_std == 'true') {
					$checked = 'checked="checked"';
					}
					else{
					   $checked = '';
					}
				}
				elseif( $std == 'true') {
				   $checked = 'checked="checked"';
				}
				else {
					$checked = '';
				}
				$output .= '<input type="checkbox" class="checkbox smartestthemes-input" name="'.  $value['id'] .'" id="'. $value['id'] .'" value="true" '. $checked .' />';

			break;
			case "info":
				$default = $value['std'];
				$output .= $default;
			break;
			case "heading":
				if($counter >= 2){
				   $output .= '</div>'."\n";
				}
				$jquery_click_hook = preg_replace('#[^A-Za-z0-9]#', '', strtolower($value['name']) );
				$jquery_click_hook = "smartestthemes-option-" . $jquery_click_hook;
						$menu .= '<li><a ';
						if ( !empty( $value['class'] ) ) {
							$menu .= 'class="'.  $value['class'] .'" ';
						}
						$menu .= 'title="'.  $value['name'] .'" href="#'.  $jquery_click_hook  .'"><span class="smartestthemes-nav-icon"></span>'.  $value['name'] .'</a></li>';
				$output .= '<div class="group" id="'. $jquery_click_hook  .'"><h2>'.$value['name'].'</h2>'."\n";
			break;                                  
			} 
			// if TYPE is an array, formatted into smaller inputs... ie smaller values
			if ( is_array($value['type'])) {
				foreach($value['type'] as $array){
				
					$id =   $array['id']; 
					$std =   $array['std'];
					$saved_std = get_option($id);
					if($saved_std != $std && !empty($saved_std) ){$std = $saved_std;} 
					$meta =   $array['meta'];
						
					if($array['type'] == 'text') { // Only text at this point
							 
							 $output .= '<input class="input-text-small smartestthemes-input" name="'. $id .'" id="'. $id .'" type="text" value="'. $std .'" />';  
							 $output .= '<span class="meta-two">'.$meta.'</span>';
						}
					}
			}
			if ( $value['type'] != "heading" ) { 
				if ( $value['type'] != "checkbox" ) 
					{ 
					$output .= '<br/>';
					}
				if(!isset($value['desc'])){ $explain_value = ''; } else{ $explain_value = $value['desc']; } 
				$output .= '</div><div class="explain">'. $explain_value .'</div>'."\n";
				$output .= '<div class="clear"> </div></div></div>'."\n";
				}
		   
		}
		$output .= '</div>';
		return array($output,$menu);

	}// end machine	
	
	/**
	* add widget styles inline in head, if needed
	*/
	public function stbco_wp_head() {

		$css = get_option( 'smartestthemes_widget_styles' );
		if ( $css ) {
			?><style><?php echo $css; ?></style><?php
		}
	}
	
} // end class

register_activation_hook(__FILE__, array('ST_Business_Carry_Over_Legacy', 'activate'));
$ST_Business_Carry_Over_Legacy = ST_Business_Carry_Over_Legacy::get_instance();
