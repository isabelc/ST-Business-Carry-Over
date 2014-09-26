<?php
/**
 * @package ST Business Carry Over
 * @subpackage Reviews
 * Description: Get reviews from visitors, and aggregate ratings and stars for your business in search results. Adds Microdata markup (Schema.org) for rich snippets. Includes Testimonial widget. Optional: pulls aggregaterating to home page. Option to not pull it to home page, and just have a reviews page. Requires Smartest Themes for full functionality.
 */
 
 if ( ! class_exists( 'Smartest_Themes_Reviews' ) ) {
 
	class Smartest_Themes_Reviews {

		private static $instance = null;
		public static function get_instance() {
			if ( null == self::$instance ) {
				self::$instance = new self;
			}
			return self::$instance;
		}
		
		var $got_aggregate = false;
		var $p = '';
		var $status_msg = '';
		
		private function __construct() {
			global $wpdb;
			$this->dbtable = $wpdb->prefix . 'st_reviews';
			add_action('init', array($this, 'init'));

			add_action( 'widgets_init', array($this, 'smartest_reviews_register_widgets'));
			add_action('template_redirect',array($this, 'template_redirect'));
			add_action('admin_menu', array($this, 'addmenu'));
			add_action('wp_ajax_update_field', array($this, 'admin_view_reviews'));
			add_action('save_post', array($this, 'admin_save_post'), 10, 2);
			add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
			add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
		}

		function addmenu() {
			if(get_option('st_add_reviews') == 'true') {       
				add_menu_page(__('Reviews', 'st-business-carry-over-legacy'), __('Reviews', 'st-business-carry-over-legacy'), 'edit_others_posts', 'smar_view_reviews', array($this, 'admin_view_reviews'), 'dashicons-star-filled', 62);
			}
		}
		/**
		* Generates an html link for a review with its page and ID as URL parameters
		*/
		function get_jumplink_for_review($review,$page) {
		   $link = get_permalink( get_option('smartestthemes_reviews_page_id') );
			if (strpos($link,'?') === false) {
				$link = trailingslashit($link) . "?smarp=$page#review-$review->id";
			} else {
				$link = $link . "&smarp=$page#review-$review->id";
			}
			return $link;
		}

		function make_p_obj() {
			$this->p = new stdClass();
			foreach ($_GET as $c => $val) {
				if (is_array($val)) {
					$this->p->$c = $val;
				} else {
					$this->p->$c = trim(stripslashes($val));
				}
			}

			foreach ($_POST as $c => $val) {
				if (is_array($val)) {
					$this->p->$c = $val;
				} else {
					$this->p->$c = trim(stripslashes($val));
				}
			}
		}
		
		function template_redirect() {
			global $post, $smartestthemes_options;
			
			// prevent errors
			if (!isset($post) || !isset($post->ID)) {
				$post = new stdClass();
				$post->ID = 0;
			}
			
			if ($post->ID > 0) {
			
				if (isset($_COOKIE['smar_status_msg'])) {
					$this->status_msg = $_COOKIE['smar_status_msg'];
					if ( !headers_sent() ) {
						setcookie('smar_status_msg', '', time() - 3600); /* delete the cookie */
						unset($_COOKIE['smar_status_msg']);
					}
				}

				// only if our Reviews form was submitted...
				if ( isset($_POST["submitsmar_$post->ID"]) ) {
					$get = $_POST["submitsmar_$post->ID"];
					if ( $get == $smartestthemes_options['st_review_submit_button_text'] ) {
						$msg = $this->add_review($post->ID);
						$has_error = $msg[0];
						$status_msg = $msg[1];
						$cookie = array('smar_status_msg' => $status_msg);
						$this->smar_redirect( '?page_id=' . get_option('smartestthemes_reviews_page_id') . '#smar_status_msg', $cookie);
					}
				}
			}
		}
		/**
		* Generate a random string
		*/
		function rand_string($length) {
			$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
			$str = '';
			$size = strlen($chars);
			for ($i = 0; $i < $length; $i++) {
				$str .= $chars[rand(0, $size - 1)];
			}
			return $str;
		}
		
		/**
		* fills the value for got_aggregate()
		*/
		function get_aggregate_reviews() {
			if ($this->got_aggregate !== false) {
				return $this->got_aggregate;
			}
			global $wpdb;
			$pageID = get_option('smartestthemes_reviews_page_id');
			$row = $wpdb->get_results("SELECT COUNT(*) AS `total`,AVG(review_rating) AS `aggregate_rating`,MAX(review_rating) AS `max_rating` FROM `$this->dbtable` WHERE `status`=1");
			/* make sure we have at least one review before continuing below */
			if ($wpdb->num_rows == 0 || $row[0]->total == 0) {
				$this->got_aggregate = array("aggregate" => 0, "max" => 0, "total" => 0, "text" => __('Reviews for my site', 'st-business-carry-over-legacy'));
				return false;
			}
			$aggregate_rating = $row[0]->aggregate_rating;
			$max_rating = $row[0]->max_rating;
			$total_reviews = $row[0]->total;
			$row = $wpdb->get_results("SELECT `review_text` FROM `$this->dbtable` WHERE `page_id`=$pageID AND `status`=1 ORDER BY `date_time` DESC");
			$sample_text = ! empty( $row[0]->review_text ) ? substr($row[0]->review_text, 0, 180) : '';
			$this->got_aggregate = array("aggregate" => $aggregate_rating, "max" => $max_rating, "total" => $total_reviews, "text" => $sample_text);
			return true;
		}
		
		function get_reviews($startpage, $perpage, $status) {
			global $wpdb;
			$startpage = $startpage - 1; // mysql starts at 0 instead of 1, so reduce them all by 1
			if ($startpage < 0) { $startpage = 0; }
			$limit = 'LIMIT ' . $startpage * $perpage . ',' . $perpage;
			
			if ($status == -1) {
				$qry_status = '1=1';
			} else {
				$qry_status = "`status`=$status";
			}

			$reviews = $wpdb->get_results("SELECT 
				`id`,
				`date_time`,
				`reviewer_name`,
				`reviewer_email`,
				`review_title`,
				`review_text`,
				`review_response`,
				`review_rating`,
				`reviewer_url`,
				`reviewer_ip`,
				`status`,
				`page_id`,
				`custom_fields`
				FROM `$this->dbtable` WHERE $qry_status ORDER BY `date_time` DESC $limit
				");
			$total_reviews = $wpdb->get_results("SELECT COUNT(*) AS `total` FROM `$this->dbtable` WHERE $qry_status");
			$total_reviews = $total_reviews[0]->total;
			
			return array($reviews, $total_reviews);
		}
		
		/**
		* Returns the HTML string for the business schema, address, and phone with microdata for the aggregate rating.
		* @param string $location, where it is called from. Accepts reviews or shortcode.
		*/
		public function get_business_schema( $location ) {

			global $smartestthemes_options;

			$schema = empty($smartestthemes_options['st_business_itemtype']) ? 'LocalBusiness' : $smartestthemes_options['st_business_itemtype'];
			
			$bn = empty($smartestthemes_options['st_business_name']) ? get_bloginfo('name') : stripslashes_deep(esc_attr($smartestthemes_options['st_business_name']));
			
			$phone = empty($smartestthemes_options['st_phone_number']) ? '' : $smartestthemes_options['st_phone_number'];
			
			$street = empty($smartestthemes_options['st_address_street']) ? '' : $smartestthemes_options['st_address_street'];
			$city = empty($smartestthemes_options['st_address_city']) ? '' : $smartestthemes_options['st_address_city'];
			$state = empty($smartestthemes_options['st_address_state']) ? '' : $smartestthemes_options['st_address_state'];
			$zip = empty($smartestthemes_options['st_address_zip']) ? '' : $smartestthemes_options['st_address_zip'];
			$country = empty($smartestthemes_options['st_address_country']) ? '' : $smartestthemes_options['st_address_country'];
			$suite = empty($smartestthemes_options['st_address_suite']) ? '' : $smartestthemes_options['st_address_suite'];	
			
			$wrapper_class = ('reviews' == $location) ? 'st-reviews-business-schema' : 'st-agg-rating-business-schema';
			$bn_class = ('reviews' == $location) ? 'st-reviews-business-name' : 'st-agg-rating-bn';
			$phone_class = ('reviews' == $location) ? 'st-reviews-business-phone' : 'st-agg-rating-phone';
			$address_class = ('reviews' == $location) ? 'st-reviews-business-address' : 'st-agg-rating-address';
			$closer = ('reviews' == $location) ? '</span><hr />' : '</span>';
			
			$out = '';
			
			if ('reviews' == $location) {
			
				$out .= '<div class="reviews-list" itemprop="itemReviewed" itemscope itemtype="http://schema.org/'. apply_filters( 'stbco_agg_rating_schema', $schema ) . '"><span class="' . $wrapper_class . '">';
			
			} else {
			
				$out .= '<br /><span class="' . $wrapper_class . '" itemprop="itemReviewed" itemscope itemtype="http://schema.org/'. apply_filters( 'stbco_agg_rating_schema', $schema ) . '">';

			}
			
			$out .= '<a href="' . site_url('/') . '" class="' . $bn_class . '"><span itemprop="name">' . apply_filters( 'stbco_agg_rating_bn', $bn ) . '</span></a><br />';
			
			if ( $phone) {
				$out .= '<span itemprop="telephone" class="' . $phone_class . '">' . apply_filters( 'stbco_agg_rating_phone', $phone ) . '</span><br />';
			}
			
			if ( $street || $city || $state || $zip || $country || $suite ) {
			
				$out .= '<span itemprop="address" itemscope itemtype="http://schema.org/PostalAddress" class="' . $address_class . '"><span itemprop="streetAddress">' . apply_filters( 'stbco_agg_rating_street', $street ) . '</span>&nbsp; ' . apply_filters( 'stbco_agg_rating_suite', $suite ) . '<br /><span itemprop="addressLocality"> ' . apply_filters( 'stbco_agg_rating_city', $city ) . '</span>';
				
				if ( $city && $state ) {
					$out .= ', ';
				}
				
				$out .= '<span itemprop="addressRegion">' . apply_filters( 'stbco_agg_rating_state', $state ) . '</span>&nbsp;<span itemprop="postalCode">' . apply_filters( 'stbco_agg_rating_zip', $zip ) . '</span>&nbsp; ' . apply_filters( 'stbco_agg_rating_country', $country ) . '</span> &nbsp;<br />';
			}

			$out .= $closer;

			return $out;

		}		

		
		/**
		* Gathers the aggregate data.
		* @param string, the location it is called from, accepts 'reviews-footer', or 'shortcode'
		* @uses get_business_schema()
		* @return string, the HTML for the aggregate rating with schema.org microdata
		*/
		function get_the_aggregate_rating( $location ) {
			
			$out = '<div class="st-reviews-aggregate">';
			
			$this->get_aggregate_reviews();// fills the values for got_aggregate
			$average_score = number_format($this->got_aggregate["aggregate"], 1);
			
			$out .= '<br /><span itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating" class="smar-aggregate-rating">' . __('Average rating:', 'st-business-carry-over-legacy'). ' <span itemprop="ratingValue">' . $average_score . '</span> ' . __('out of', 'st-business-carry-over-legacy'). ' <span itemprop="bestRating">5 </span> '. __('based on', 'st-business-carry-over-legacy').' <span itemprop="ratingCount">' . $this->got_aggregate['total'] . ' </span>' . _n( 'review', 'reviews.', $this->got_aggregate['total'], 'st-business-carry-over-legacy' ) . '</span>';
			
			// get the business schema, address, and phone with microdata for shortcode
			if ( 'reviews-footer' != $location ) {
					$out .= $this->get_business_schema('shortcode');
			}
			
			$out .= '</div><!-- .st-reviews-aggregate -->';
			
			return $out;
		}

		/*
		* Shortcode for the Aggregate Rating
		*/
		public function aggregate_footer_func() {
			return $this->get_the_aggregate_rating('shortcode');
		}

		function iso8601($time=false) {
			if ($time === false)
				$time = time();
			$date = date('Y-m-d\TH:i:sO', $time);
			return (substr($date, 0, strlen($date) - 2) . ':' . substr($date, -2));
		}

		function pagination($total_results) {
			global $post;

			$per_page = get_option('st_reviews_per_page');
			if ( empty($per_page) || ( $per_page < 1 ) || ! is_numeric($per_page) ) {
				$per_page = 10;
			}
		
			$out = '';
			$uri = false;
			$pretty = false;

			$range = 2;
			$showitems = ($range * 2) + 1;

			$paged = $this->page;
			if ($paged == 0) { $paged = 1; }
			
			if (!isset($this->p->review_status)) {
				$this->p->review_status = 0;
			}
			$pages = ceil($total_results / $per_page);

			if ($pages > 1) {
				if (is_admin()) {
					$url = '?page=smar_view_reviews&amp;review_status=' . $this->p->review_status . '&amp;';
				} else {
					$uri = trailingslashit(get_permalink($post->ID));
					if (strpos($uri, '?') === false) {
						$url = $uri . '?';
						$pretty = true;
					} /* page is using pretty permalinks */ else {
						$url = $uri . '&amp;';
						$pretty = false;
					} /* page is using get variables for pageid */
				}

				$out .= '<div class="smar_pagination"><div class="smar_pagination_page">'. __('Page: ', 'st-business-carry-over-legacy'). '</div>';

				if ($paged > 2 && $paged > $range + 1 && $showitems < $pages) {
					if ($uri && $pretty) {
						$url2 = $uri;
					} /* not in admin AND using pretty permalinks */ else {
						$url2 = $url;
					}
					$out .= '<a href="' . $url2 . '">&laquo;</a>';
				}

				if ($paged > 1 && $showitems < $pages) {
					$out .= '<a href="' . $url . 'smarp=' . ($paged - 1) . '">&lsaquo;</a>';
				}

				for ($i = 1; $i <= $pages; $i++) {
					if ($i == $paged) {
						$out .= '<span class="smar_current">' . $paged . '</span>';
					} else if (!($i >= $paged + $range + 1 || $i <= $paged - $range - 1) || $pages <= $showitems) {
						if ($i == 1) {
							if ($uri && $pretty) {
								$url2 = $uri;
							} /* not in admin AND using pretty permalinks */ else {
								$url2 = $url;
							}
							$out .= '<a href="' . $url2 . '" class="smar_inactive">' . $i . '</a>';
						} else {
							$out .= '<a href="' . $url . 'smarp=' . $i . '" class="smar_inactive">' . $i . '</a>';
						}
					}
				}

				if ($paged < $pages && $showitems < $pages) {
					$out .= '<a href="' . $url . 'smarp=' . ($paged + 1) . '">&rsaquo;</a>';
				}
				if ($paged < $pages - 1 && $paged + $range - 1 < $pages && $showitems < $pages) {
					$out .= '<a href="' . $url . 'smarp=' . $pages . '">&raquo;</a>';
				}
				$out .= '</div>';
				$out .= '<div class="smar_clear smar_pb5"></div>';

				return $out;
			}
		}
		
		/**
		* Display the Reviews list
		*/
		function reviews_list() {
		
			global $smartestthemes_options;
			$add_reviews = empty($smartestthemes_options['st_add_reviews']) ? '' : $smartestthemes_options['st_add_reviews'];
		
			$per_page = empty($smartestthemes_options['st_reviews_per_page']) ? '10' : $smartestthemes_options['st_reviews_per_page'];
			if ( ( $per_page < 1 ) || ! is_numeric($per_page) ) {
				$per_page = 10;
			}
			
			$arr_Reviews = $this->get_reviews($this->page, $per_page, 1);
			$reviews = $arr_Reviews[0];
			$total_reviews = intval($arr_Reviews[1]);
			$reviews_content = '';
			$showtitle = '';
			$title_tag = empty($smartestthemes_options['st_reviews_title_tag']) ? 'h2' : $smartestthemes_options['st_reviews_title_tag'];
			
			if (count($reviews) == 0) {
				$reviews_content .= '<p>' .  __('There are no reviews yet. Be the first to leave yours!', 'st-business-carry-over-legacy') . '</p>';
			} elseif ($add_reviews != 'true') {
				$reviews_content .= '<p>' . __('Reviews are not available.', 'st-business-carry-over-legacy') . '</p>';
			} else {

				$reviews_content .= $this->get_business_schema( 'reviews' );
				
				foreach ($reviews as $review) {
					
					$hide_name = '';
					
					if (get_option('st_reviews_show_fields_show_fname') == 'false') {
						$review->reviewer_name = __('Anonymous', 'st-business-carry-over-legacy');
						$hide_name = 'smar_hide';
					}
					if ($review->reviewer_name == '') {
						$review->reviewer_name = __('Anonymous', 'st-business-carry-over-legacy');
					}

					if (get_option('st_reviews_show_fields_show_fwebsite') == 'true' && $review->reviewer_url != '') {
						$review->review_text .= '<br /><small><a href="' . $review->reviewer_url . '">' . $review->reviewer_url . '</a></small>';
					}
					if (get_option('st_reviews_show_fields_show_femail') == 'true' && $review->reviewer_email != '') {
						$review->review_text .= '<br /><small>' . $review->reviewer_email . '</small>';
					}
					
					if (get_option('st_reviews_show_fields_show_ftitle') == 'true' && $review->review_title != '') {
						$showtitle = true;
					}

					$review->review_text = nl2br($review->review_text);
					$review_response = '';
					
					if (strlen($review->review_response) > 0) {
					
						$prep_response = nl2br($review->review_response);
						
						$review_response = '<p class="response"><strong>'.__('Response:', 'st-business-carry-over-legacy').'</strong> ' . stripslashes_deep(esc_attr($prep_response)) . '</p>';
					}

					$custom_shown = '';
					
					$custom_fields_unserialized = @unserialize($review->custom_fields);
					if (!is_array($custom_fields_unserialized)) {
						$custom_fields_unserialized = array();
					}
			
					for ($i = 0; $i < 6; $i++) {
						if ( isset($custom_fields_unserialized[$i]) ) {
							$is_label_entered = empty($smartestthemes_options['st_reviews_custom_field_' . $i]) ? '' : stripslashes_deep(esc_attr($smartestthemes_options['st_reviews_custom_field_' . $i]));
						
							// if label is entered and show is checked
							if ( $is_label_entered && get_option('st_reviews_custom' . $i . '_show') == 'true' && $custom_fields_unserialized[$i] != '') {
							
								$custom_shown .= '<div class="st-reviews-custom-field-' . $i . '"><span class="reviews-custom-label">' . $is_label_entered . ': </span> <span class="reviews-custom-vlue"> ' . stripslashes_deep(esc_attr($custom_fields_unserialized[$i])) . ' </span></div>';
							}
						}
					
					}
					$name_block = '' .'<div class="smar_fl smar_rname clear">' .'<abbr title="' . $this->iso8601(strtotime($review->date_time)) . '" itemprop="dateCreated">' . date("M d, Y", strtotime($review->date_time)) . '</abbr>&nbsp;' .'<span class="' . $hide_name . '">'. __('by', 'st-business-carry-over-legacy').'</span>&nbsp;' . '<span class="isa_vcard" id="review-smar-reviewer-' . $review->id . '">' . '<span class="' . $hide_name . '" itemprop="author">' . stripslashes_deep(esc_attr($review->reviewer_name)) . '</span>' . '</span>' . '<div class="smar_clear"></div>' . $custom_shown . '</div>';
	 
					$reviews_content .= '<div itemprop="review" itemscope itemtype="http://schema.org/Review" id="review-' . $review->id . '">';
				
					if ( $showtitle ) {
						$reviews_content .= '<' . $title_tag . ' itemprop="description" class="summary">' . stripslashes_deep(esc_attr($review->review_title)) . '</' . $title_tag . '>';
					}
				
					$reviews_content .= '<div class="smar_fl smar_sc"><div class="smar_rating">' . $this->output_rating($review->review_rating, false) . '</div></div>' . $name_block . '<div class="smar_clear smar_spacing1"></div><blockquote itemprop="reviewBody" class="description"><p>' . stripslashes_deep(esc_attr($review->review_text)) . ' '.__('Rating:', 'st-business-carry-over-legacy').' <span itemprop="reviewRating" itemscope itemtype="http://schema.org/Rating"><span itemprop="ratingValue">'.$review->review_rating.'</span></span>  '.__('out of 5.', 'st-business-carry-over-legacy').'</p></blockquote>' . $review_response . '</div><hr />';

				}//  foreach ($reviews as $review)
				
				$reviews_content .= $this->get_the_aggregate_rating('reviews-footer') . '</div><!-- .reviews-list -->';
				
			}//if else if (count($reviews
			return array($reviews_content, $total_reviews);
		}
		
		public function output_rating($rating, $enable_hover) {
			$out = '';
			$rating_width = 20 * $rating; /* 20% for each star if having 5 stars */
			$out .= '<div class="sp_rating">';
			if ($enable_hover) {
				$out .= '<div class="status"><div class="score"><a class="score1">1</a><a class="score2">2</a><a class="score3">3</a><a class="score4">4</a><a class="score5">5</a></div></div>';
			}

			$out .= '<div class="base"><div class="average" style="width:' . $rating_width . '%"></div></div>';
			$out .= '</div>';

			return $out;
		}

		function show_reviews_form() {
			global $post, $smartestthemes_options;
			$fields = '';
			$out = '';
			$req_js = '';

			if ( isset($_COOKIE['smar_status_msg']) ) {
				$this->status_msg = $_COOKIE['smar_status_msg'];
			}
			
			if ($this->status_msg != '') {
				$req_js .= "<script type='text/javascript'>smar_del_cookie('smar_status_msg');</script>";
			}

			/* a silly and crazy but effective antispam measure.. bots wont have a clue */
			$rand_prefixes = array();
			for ($i = 0; $i < 15; $i++) {
				$rand_prefixes[] = $this->rand_string(mt_rand(1, 8));
			}
			
			if (!isset($this->p->fname)) { $this->p->fname = ''; }
			if (!isset($this->p->femail)) { $this->p->femail = ''; }
			if (!isset($this->p->fwebsite)) { $this->p->fwebsite = ''; }
			if (!isset($this->p->ftitle)) { $this->p->ftitle = ''; }
			if (!isset($this->p->ftext)) { $this->p->ftext = ''; }

			if (get_option('st_reviews_ask_fields_ask_fname') == 'true') {
				if (get_option('st_reviews_require_fields_require_fname') == 'true') {
					$req = '*';
				} else {
					$req = '';
				}
				$fields .= '<tr><td><label for="' . $rand_prefixes[0] . '-fname" class="comment-field">'. __('Name:', 'st-business-carry-over-legacy').' ' . $req . '</label></td><td><input class="text-input" type="text" id="' . $rand_prefixes[0] . '-fname" name="' . $rand_prefixes[0] . '-fname" value="' . $this->p->fname . '" /></td></tr>';
			}
			if (get_option('st_reviews_ask_fields_ask_femail') == 'true') {
				if (get_option('st_reviews_require_fields_require_femail') == 'true') {
					$req = '*';
				} else {
					$req = '';
				}
				$fields .= '<tr><td><label for="' . $rand_prefixes[1] . '-femail" class="comment-field">'. __('Email:', 'st-business-carry-over-legacy').' ' . $req . '</label></td><td><input class="text-input" type="text" id="' . $rand_prefixes[1] . '-femail" name="' . $rand_prefixes[1] . '-femail" value="' . $this->p->femail . '" /></td></tr>';
			}
			if (get_option('st_reviews_ask_fields_ask_fwebsite') == 'true') {
				if (get_option('st_reviews_require_fields_require_fwebsite') == 'true') {
					$req = '*';
				} else {
					$req = '';
				}
				$fields .= '<tr><td><label for="' . $rand_prefixes[2] . '-fwebsite" class="comment-field">'. __('Website:', 'st-business-carry-over-legacy').' ' . $req . '</label></td><td><input class="text-input" type="text" id="' . $rand_prefixes[2] . '-fwebsite" name="' . $rand_prefixes[2] . '-fwebsite" value="' . $this->p->fwebsite . '" /></td></tr>';
			}
			if (get_option('st_reviews_ask_fields_ask_ftitle') == 'true') {
				if (get_option('st_reviews_require_fields_require_ftitle') == 'true') {
					$req = '*';
				} else {
					$req = '';
				}
				$fields .= '<tr><td><label for="' . $rand_prefixes[3] . '-ftitle" class="comment-field">'. __('Review Title:', 'st-business-carry-over-legacy').' ' . $req . '</label></td><td><input class="text-input" type="text" id="' . $rand_prefixes[3] . '-ftitle" name="' . $rand_prefixes[3] . '-ftitle" maxlength="150" value="' . $this->p->ftitle . '" /></td></tr>';
			}

			
			for ($i = 0; $i < 6; $i++) {
			
				// get the field label
				$field_label = empty($smartestthemes_options['st_reviews_custom_field_' . $i]) ? '' : $smartestthemes_options['st_reviews_custom_field_' . $i];
				
				// for each of the 6, if label is entered and if ask is checked
				if ( $field_label && (get_option('st_reviews_custom' . $i . '_ask') == 'true')) {
				
					$custom_i = "custom_$i";
					if (!isset($this->p->$custom_i)) {
						$this->p->$custom_i = '';
					}
				
					if (get_option('st_reviews_custom' . $i . '_require') == 'true') {
						$req = '*';
					} else {
						$req = '';
					}
						
					$fields .= '<tr><td><label for="custom_' . $i . '" class="comment-field">' . stripslashes_deep(esc_attr($field_label)) . ': ' . $req . '</label></td><td><input class="text-input" type="text" id="custom_' . $i . '" name="custom_' . $i . '" maxlength="150" value="' . $this->p->$custom_i . '" /></td></tr>';
				
				}
			}

			$button_html = '<div id="smar_status_msg">' . $this->status_msg . '</div>'; /* show errors or thank you message */
			$button_text = empty($smartestthemes_options['st_reviews_show_form_button']) ? __('Click here to submit your review','st-business-carry-over-legacy') : esc_attr($smartestthemes_options['st_reviews_show_form_button']);
			$button_html .= '<p><a id="smar_button_1" href="javascript:void(0);">' . $button_text . '</a></p>';
			$submit_button_text = empty($smartestthemes_options['st_review_submit_button_text']) ? __('Submit Your Review','st-business-carry-over-legacy') : esc_attr($smartestthemes_options['st_review_submit_button_text']);
			$form_heading = empty($smartestthemes_options['st_review_form_heading']) ? __('Submit Your Review','st-business-carry-over-legacy') : esc_attr($smartestthemes_options['st_review_form_heading']);
			
			$out .= $button_html . '<div id="smar_respond_2">';
			
			if ( $req_js ) {
				$out .= $req_js;
			}
			
			$out .= '<form id="st-reviews-form" method="post" action="javascript:void(0);">
						<div id="smar_div_2">
						<input type="hidden" id="frating" name="frating" />
						<table id="smar_table_2">
							<tbody>
								<tr><td colspan="2"><div id="smar_postcomment">' . $form_heading . '</div></td></tr>
								' . $fields;

			$out2 = '   
				<tr>
					<td><label class="comment-field">'. __('Rating:', 'st-business-carry-over-legacy').'</label></td>
					<td><div class="smar_rating">' . $this->output_rating(0, true) . '</div></td>
				</tr>';

			$out3 = '
				<tr><td colspan="2"><label for="' . $rand_prefixes[5] . '-ftext" class="comment-field">'. __('Review:', 'st-business-carry-over-legacy').'</label></td></tr>
				<tr><td colspan="2"><textarea id="' . $rand_prefixes[5] . '-ftext" name="' . $rand_prefixes[5] . '-ftext" rows="8" cols="50">' . $this->p->ftext . '</textarea></td></tr>
				<tr>
					<td colspan="2" id="smar_check_confirm">
						<div class="smar_clear"></div>    
						<input type="checkbox" name="' . $rand_prefixes[6] . '-fconfirm1" id="fconfirm1" value="1" />
						<div class="smar_fl"><input type="checkbox" name="' . $rand_prefixes[7] . '-fconfirm2" id="fconfirm2" value="1" /></div><div class="smar_fl smar_checklabel"><label for="fconfirm2">'. __('Check this box to confirm you are human.', 'st-business-carry-over-legacy').'</label></div>
						<div class="smar_clear"></div>
						<input type="checkbox" name="' . $rand_prefixes[8] . '-fconfirm3" id="fconfirm3" value="1" />
						</td>
					</tr>
					<tr><td colspan="2"><input id="smar_submit_btn" name="submitsmar_' . $post->ID . '" type="submit" value="' . $submit_button_text . '" /></td></tr>
					</tbody>
					</table>
					</div>
					</form>';

			$out4 = '<hr /></div>';
			$out4 .= '<div class="smar_clear smar_pb5"></div>';

			return $out . $out2 . $out3 . $out4;
		}

		/* insert reviews into db and send mail notification to admin
		*/
		function add_review($pageID) {
			global $wpdb,$smartestthemes_options;

			/* begin - some antispam magic */
			$this->newp = new stdClass();

			foreach ($this->p as $col => $val) {
				$pos = strpos($col, '-');
				if ($pos !== false) {
					$col = substr($col, $pos + 1); /* off by one */
				}
				$this->newp->$col = $val;
			}

			$this->p = $this->newp;
			unset($this->newp);
			/* end - some antispam magic */

			/* some sanitation */
			$date_time = date('Y-m-d H:i:s');
			$ip = $_SERVER['REMOTE_ADDR'];
			
			if (!isset($this->p->fname)) { $this->p->fname = ''; }
			if (!isset($this->p->femail)) { $this->p->femail = ''; }
			if (!isset($this->p->fwebsite)) { $this->p->fwebsite = ''; }
			if (!isset($this->p->ftitle)) { $this->p->ftitle = ''; }
			if (!isset($this->p->ftext)) { $this->p->ftext = ''; }
			if (!isset($this->p->femail)) { $this->p->femail = ''; }
			if (!isset($this->p->fwebsite)) { $this->p->fwebsite = ''; }
			if (!isset($this->p->frating)) { $this->p->frating = 0; } /* default to 0 */
			if (!isset($this->p->fconfirm1)) { $this->p->fconfirm1 = 0; } /* default to 0 */
			if (!isset($this->p->fconfirm2)) { $this->p->fconfirm2 = 0; } /* default to 0 */
			if (!isset($this->p->fconfirm3)) { $this->p->fconfirm3 = 0; } /* default to 0 */
			
			$this->p->fname = trim(strip_tags($this->p->fname));
			$this->p->femail = trim(strip_tags($this->p->femail));
			$this->p->ftitle = trim(strip_tags($this->p->ftitle));
			$this->p->ftext = trim(strip_tags($this->p->ftext));
			$this->p->frating = intval($this->p->frating);

			/* begin - server-side validation */
			$errors = '';

			$rn = get_option('st_reviews_require_fields_require_fname');
			$re = get_option('st_reviews_require_fields_require_femail');
			$rw = get_option('st_reviews_require_fields_require_fwebsite');
			$rt = get_option('st_reviews_require_fields_require_ftitle');
			
			$require_fields = array(
				'st_reviews_require_fields_require_fname'		=> $rn,
				'st_reviews_require_fields_require_femail'		=> $re,
				'st_reviews_require_fields_require_fwebsite'	=> $rw,
				'st_reviews_require_fields_require_ftitle'		=> $rt);
			
		
			foreach ($require_fields as $col => $val) {
				if ($val == 'true') {
				
					// extract final section after the last _
					$extract_f_name = explode('_', $col);
					$f_name = end($extract_f_name);
						
					if (!isset($this->p->$f_name) || $this->p->$f_name == '') {
					
						// remove the 1st char, then capitalize the new first char
						$nice_name = ucfirst(substr($f_name, 1));
						$errors .= __('You must include your', 'st-business-carry-over-legacy').' ' . $nice_name . '.<br />';
					}
				}
			}

			for ($i = 0; $i < 6; $i++) {
			
				if (get_option('st_reviews_custom' . $i . '_require') == 'true') {
					$custom_i = "custom_$i";
					if (!isset($this->p->$custom_i) || $this->p->$custom_i == '') {
						// get field name for error msg
						$nice_name = empty($smartestthemes_options['st_reviews_custom_field_' . $i]) ? '' : stripslashes($smartestthemes_options['st_reviews_custom_field_' . $i]);
						$errors .= __('You must complete "', 'st-business-carry-over-legacy'). ' ' . $nice_name . '".<br />';
					}
				}
				
			}

			/* only do regex matching if not blank */
			if ($this->p->femail != '' && get_option('st_reviews_ask_fields_ask_femail') == 'true') {
				if (!preg_match('/^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,4})$/', $this->p->femail)) {
					$errors .= __('The email address provided is not valid.', 'st-business-carry-over-legacy').'<br />';
				}
			}


			if (intval($this->p->fconfirm1) == 1 || intval($this->p->fconfirm3) == 1) {
				$errors .= __('You have triggered our anti-spam system. Please try again. Code 001.', 'st-business-carry-over-legacy').'<br />';
			}

			if (intval($this->p->fconfirm2) != 1) {
				$errors .= __('You have triggered our anti-spam system. Please try again. Code 002', 'st-business-carry-over-legacy').'<br />';
			}

			if ($this->p->frating < 1 || $this->p->frating > 5) {
				$errors .= __('You have triggered our anti-spam system. Please try again. Code 003', 'st-business-carry-over-legacy').'<br />';
			}

		   if (strlen(trim($this->p->ftext)) < 5) {
				$errors .= __('You must include a review. Please make reviews at least 5 letters.', 'st-business-carry-over-legacy').'<br />';
			}

			/* returns true for errors */
			if ($errors) {
				return array(true, "<div>$errors</div>");
			}
			/* end - server-side validation */

			
			$custom_insert = array();
			for ($i = 0; $i < 6; $i++) {
			
				// for each of the 6, if ask is checked
				
				if (get_option('st_reviews_custom' . $i . '_ask') == 'true') {

					$custom_i = "custom_$i";				
					if ( isset($this->p->$custom_i) ) {
						$custom_insert[$i] = $this->p->$custom_i;
					}
				
				}
			}
			$custom_insert = serialize($custom_insert);
			$query = $wpdb->prepare("INSERT INTO `$this->dbtable` 
					(`date_time`, `reviewer_name`, `reviewer_email`, `reviewer_ip`, `review_title`, `review_text`, `status`, `review_rating`, `reviewer_url`, `custom_fields`, `page_id`) 
					VALUES (%s, %s, %s, %s, %s, %s, %d, %d, %s, %s, %d)", $date_time, $this->p->fname, $this->p->femail, $ip, $this->p->ftitle, $this->p->ftext, 0, $this->p->frating, $this->p->fwebsite, $custom_insert, $pageID);

			$wpdb->query($query);
			$bn = empty($smartestthemes_options['st_business_name']) ? get_bloginfo('name') : stripslashes_deep($smartestthemes_options['st_business_name']);
			$admin_linkpre = get_admin_url().'admin.php?page=smar_view_reviews';
			$admin_link = sprintf(__('Link to admin approval page: %s', 'st-business-carry-over-legacy'), $admin_linkpre);
			$ac = sprintf(__('A new review has been posted on %1$s\'s website.','st-business-carry-over-legacy'),$bn) . "\n\n" .
		__('You will need to login to the admin area and approve this review before it will appear on your site.','st-business-carry-over-legacy') . "\n\n" .$admin_link;

			@wp_mail(get_bloginfo('admin_email'), $bn.': '. sprintf(__('New Review Posted on %1$s', 'st-business-carry-over-legacy'), 
									date('m/d/Y h:i e') ), $ac );

			/* returns false for no error */
			return array(false, '<div>'.__('Thank you for your comments. All submissions are moderated and if approved, yours will appear soon.', 'st-business-carry-over-legacy').'</div>');
		}
		/**
		* Refresh the page when a Review is submitted
		*/
		function smar_redirect($url, $cookie = array()) {
			$headers_sent = headers_sent();
			if ($headers_sent == true) {
				/* use JS redirect and add cookie before redirect */
				/* we do not html comment script blocks here - to prevent any issues with other plugins adding content to newlines, etc */
				$out = '<html><head><title>'.__('Redirecting', 'st-business-carry-over-legacy').'...</title></head><body><div style="clear:both;text-align:center;padding:10px;">' .
						__('Processing... Please wait...', 'st-business-carry-over-legacy') .
						'<script type="text/javascript">';
				foreach ($cookie as $col => $val) {
					$val = preg_replace("/\r?\n/", "\\n", addslashes($val));
					$out .= "document.cookie=\"$col=$val\";";
				}
				$out .= "window.location='$url';";
				$out .= "</script>";
				$out .= "</div></body></html>";
				echo $out;
			} else {
				foreach ($cookie as $col => $val) {
					setcookie($col, $val); /* add cookie via headers */
				}
				if (ob_get_length()) {
					ob_end_clean();
				}
				wp_redirect($url);
			}
			
			exit();
		}
		
		
		/**
		* Create the Reviews table unless it exists
		*/
		
		function create_table() {
			global $wpdb;
			
			$charset_collate = '';

			if ( ! empty( $wpdb->charset ) ) {
			  $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
			}

			if ( ! empty( $wpdb->collate ) ) {
			  $charset_collate .= " COLLATE {$wpdb->collate}";
			}
			if($wpdb->get_var("SHOW TABLES LIKE '$this->dbtable'") != $this->dbtable) {
				$sql = "CREATE TABLE $this->dbtable (
						  id int(11) NOT NULL AUTO_INCREMENT,
						  date_time datetime NOT NULL,
						  reviewer_name varchar(150) DEFAULT NULL,
						  reviewer_email varchar(150) DEFAULT NULL,
						  reviewer_ip varchar(15) DEFAULT NULL,
						  review_title varchar(150) DEFAULT NULL,
						  review_text text,
						  review_response text,
						  status tinyint(1) DEFAULT '0',
						  review_rating tinyint(2) DEFAULT '0',
						  reviewer_url varchar(255) NOT NULL,
						  page_id int(11) NOT NULL DEFAULT '0',
						  custom_fields text,
						  PRIMARY KEY  (id),
						  KEY status (status),
						  KEY page_id (page_id)
						  ) $charset_collate;";

				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				dbDelta( $sql );
			}
			return true;
		}
		
		/**
		* Initiate Reviews
		*/
		public function init() {
			$this->make_p_obj(); /* make P variables object */
			$this->create_table();
			if ( !isset($this->p->smarp) ) {
				$this->p->smarp = 1;
			}
			$this->page = intval($this->p->smarp);
			if ($this->page < 1) {
				$this->page = 1;
			}
		}
		
		/**
		* The reviews page shortcode
		*/
		public function reviews_shortcode( $atts ) {
		
			$reviews_content = '<div id="smar_respond_1">';
		  
			global $smartestthemes_options;
			if ( $smartestthemes_options['st_reviews_form_location'] == 'above' ) {
				$reviews_content .= $this->show_reviews_form();
			}

			$ret_Arr = $this->reviews_list();
			$reviews_content .= $ret_Arr[0];
			$total_reviews = $ret_Arr[1];
			$reviews_content .= $this->pagination($total_reviews);

			if ( $smartestthemes_options['st_reviews_form_location'] == 'below' ) {
				$reviews_content .= $this->show_reviews_form();
			}
			$reviews_content .= '</div>';
			return $reviews_content;
		}
		
		/**
		 * Load css and js on Reviews page
		 */
		public function enqueue_scripts() {
			if( get_option( 'st_add_reviews' ) == 'true'  ) {
				wp_register_style( 'smartest-reviews', plugins_url( 'reviews.css', __FILE__) );
				wp_register_script( 'smartest-reviews', plugins_url( 'reviews.js',  __FILE__ ), array( 'jquery' ) );
				if( is_page( get_option( 'smartestthemes_reviews_page_id' ) ) ) {
					wp_enqueue_style( 'smartest-reviews' );
					wp_enqueue_script( 'smartest-reviews' );
					global $smartestthemes_options;
					
					$custom_field = array();
					for ($i = 0; $i < 6; $i++) {
						$custom_field[] = empty($smartestthemes_options['st_reviews_custom_field_' . $i]) ? '' : stripslashes_deep(esc_attr($smartestthemes_options['st_reviews_custom_field_' . $i]));
					}
					
					$loc = array(
						'hidebutton'		=> __('Click here to hide form', 'st-business-carry-over-legacy'),
						'email'				=> __('The email address provided is not valid.', 'st-business-carry-over-legacy'),
						'email_empty'		=> __('You must include your email. Your email will not be published.', 'st-business-carry-over-legacy'),
						'name'				=> __('You must include your name.', 'st-business-carry-over-legacy'),
						'review'			=> __('You must include a review. Please make reviews at least 4 letters.', 'st-business-carry-over-legacy'),
						'human'				=> __('You must confirm that you are human.', 'st-business-carry-over-legacy'),
						'code2'				=> __('Code 2.', 'st-business-carry-over-legacy'),
						'code3'				=> __('Code 3.', 'st-business-carry-over-legacy'),
						'rating'			=> __('Please select a star rating from 1 to 5.', 'st-business-carry-over-legacy'),
						'website'			=> __('You must include a website.', 'st-business-carry-over-legacy'),
						'title'				=> __('You must include a Review Title.', 'st-business-carry-over-legacy'),
						'req_name'			=> get_option('st_reviews_require_fields_require_fname'),
						'req_email'			=> get_option('st_reviews_require_fields_require_femail'),
						'req_website'		=> get_option('st_reviews_require_fields_require_fwebsite'),
						'req_title'			=> get_option('st_reviews_require_fields_require_ftitle'),
						'req_custom0'		=> get_option('st_reviews_custom0_require'),
						'req_custom0_error'	=> sprintf(__('You must complete "%1$s".', 'st-business-carry-over-legacy'), $custom_field[0]),
						'req_custom1'		=> get_option('st_reviews_custom1_require'),
						'req_custom1_error'	=> sprintf(__('You must complete "%1$s".', 'st-business-carry-over-legacy'), $custom_field[1]),
						'req_custom2'		=> get_option('st_reviews_custom2_require'),
						'req_custom2_error'	=> sprintf(__('You must complete "%1$s".', 'st-business-carry-over-legacy'), $custom_field[2]),
						'req_custom3'		=> get_option('st_reviews_custom3_require'),
						'req_custom3_error'	=> sprintf(__('You must complete "%1$s".', 'st-business-carry-over-legacy'), $custom_field[3]),
						'req_custom4'		=> get_option('st_reviews_custom4_require'),
						'req_custom4_error'	=> sprintf(__('You must complete "%1$s".', 'st-business-carry-over-legacy'), $custom_field[4]),
						'req_custom5'		=> get_option('st_reviews_custom5_require'),
						'req_custom5_error'	=> sprintf(__('You must complete "%1$s".', 'st-business-carry-over-legacy'), $custom_field[5])
						);
					wp_localize_script( 'smartest-reviews', 'smartlocal', $loc);
				}
			}
		}
		/**
		 * widget
		 */
		public function smartest_reviews_register_widgets() {
			if( get_option('st_add_reviews') == 'true'  ) {
				register_widget('SmartestReviewsTestimonial');
			}
		}

		public function admin_save_post($post_id, $post) {
			global $meta_box,$wpdb;
			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
				return $post_id;
			}

			// check permissions
			if ( isset($this->p->post_type) && $this->p->post_type == 'page' ) {
				if (!current_user_can('edit_page', $post_id)) {
					return $post_id;
				}
			} elseif (!current_user_can('edit_post', $post_id)) {
				return $post_id;
			}

			if ( isset($meta_box) && isset($meta_box['fields']) && is_array($meta_box['fields']) ) {
				foreach ($meta_box['fields'] as $field) {
						
					if ( isset($this->p->post_title) ) {
						$old = get_post_meta($post_id, $field['id'], true);
							
						if (isset($this->p->$field['id'])) {
							$new = $this->p->$field['id'];
							if ($new && $new != $old) {
								update_post_meta($post_id, $field['id'], $new);
							} elseif ($new == '' && $old) {
								delete_post_meta($post_id, $field['id'], $old);
							}
						} else {
							delete_post_meta($post_id, $field['id'], $old);
						}
					}
				}
			}
			return $post_id;
		}
		/**
		 * Load admin css and js on admin View Reviews page
		 */	
		public function enqueue_admin_scripts() {
			if (isset($this->p->page) && ( $this->p->page == 'smar_view_reviews' ) ) {
				wp_enqueue_script( 'st-reviews-admin', plugins_url( 'reviews-admin.js',  __FILE__ ), array('jquery') );
				wp_enqueue_style( 'st-reviews-admin',  plugins_url( 'reviews-admin.css' , __FILE__ ) );
			}
		}	
		function admin_view_reviews() {
			global $wpdb, $smartestthemes_options;
			
			$per_page = empty($smartestthemes_options['st_reviews_per_page']) ? 10 : $smartestthemes_options['st_reviews_per_page'];
			if ( ( $per_page < 1 ) || ! is_numeric($per_page) ) {
				$per_page = 10;
			}
			
			if (!isset($this->p->s)) { $this->p->s = ''; }
			$this->p->s_orig = $this->p->s;
			
			if (!isset($this->p->review_status)) { $this->p->review_status = 0; }
			$this->p->review_status = intval($this->p->review_status);
			
			/* begin - actions */
			if (isset($this->p->action)) {
			
				if (isset($this->p->r)) {
					$this->p->r = intval($this->p->r);

					switch ($this->p->action) {
						case 'deletereview':
							$wpdb->query("DELETE FROM `$this->dbtable` WHERE `id`={$this->p->r} LIMIT 1");
							break;
						case 'trashreview':
							$wpdb->query("UPDATE `$this->dbtable` SET `status`=2 WHERE `id`={$this->p->r} LIMIT 1");
							break;
						case 'approvereview':
							$wpdb->query("UPDATE `$this->dbtable` SET `status`=1 WHERE `id`={$this->p->r} LIMIT 1");
							break;
						case 'unapprovereview':
							$wpdb->query("UPDATE `$this->dbtable` SET `status`=0 WHERE `id`={$this->p->r} LIMIT 1");
							break;
						case 'update_field':
							
							if (ob_get_length())
								ob_end_clean();
							
							if (!is_array($this->p->json)) {
								header('HTTP/1.1 403 Forbidden');
								echo json_encode(array("errors" => __('Bad Request', 'st-business-carry-over-legacy')));
								exit(); 
							}
							
							$show_val = '';
							$update_col = false;
							$update_val = false;
							
							foreach ($this->p->json as $col => $val) {
								
								switch ($col) {
									case 'date_time':
										$d = date("m/d/Y g:i a",strtotime($val));
										if (!$d || $d == '01/01/1970 12:00 am') {
											header('HTTP/1.1 403 Forbidden');
											echo json_encode(array("errors" => __('Bad Date Format', 'st-business-carry-over-legacy')));
											exit(); 
										}
										
										$show_val = $d;
										$d2 = date("Y-m-d H:i:s",strtotime($val));
										$update_col = $col;
										$update_val = $d2;
										break;
										
									default:
										if ($val == '') {
											header('HTTP/1.1 403 Forbidden');
											echo json_encode(array("errors" => __('Bad Value', 'st-business-carry-over-legacy')));
											exit(); 
										}
										
										/* for storing in DB - fix with IE 8 workaround */
										$val = str_replace( array("<br />","<br/>","<br>") , "\n" , $val );	
	 
										if (substr($col,0,7) == 'custom_') /* updating custom fields */
										{
											$custom_num = substr($col,7); // gets the number after the _
											
											// get the old custom value 
											$old_value = $wpdb->get_results("SELECT `custom_fields` FROM `$this->dbtable` WHERE `id`={$this->p->r} LIMIT 1");	
											
											if ($old_value && $wpdb->num_rows)
											{
												$old_value = @unserialize($old_value[0]->custom_fields);
												
												if (!is_array($old_value)) {
													$old_value = array();
												}

												$old_value[$custom_num] = $val;// assign the new value
												$update_col = 'custom_fields';
												$update_val = serialize($old_value);

											}
											
										}
										else /* updating regular fields */
										{									
											$update_col = $col;
											$update_val = $val;
										}

										$show_val = $val;
										
										break;
								}
								
							}
							
							if ($update_col !== false && $update_val !== false) {
							
								$query = $wpdb->prepare("UPDATE `$this->dbtable` SET `$update_col` = %s WHERE `id` ={$this->p->r} LIMIT 1", $update_val);
								
								$update_db = $wpdb->query($query);
								
								echo $show_val;
							
							}
							
							exit();
							break;
					}
				}
				
				if ( isset($this->p->delete_reviews) && is_array($this->p->delete_reviews) && count($this->p->delete_reviews) ) {
					
					foreach ($this->p->delete_reviews as $i => $rid) {
						$this->p->delete_reviews[$i] = intval($rid);
					}
					
					if (isset($this->p->act2)) { $this->p->action = $this->p->action2; }
					
					switch ($this->p->action) {
						case 'bapprove':
							$wpdb->query("UPDATE `$this->dbtable` SET `status`=1 WHERE `id` IN(".implode(',',$this->p->delete_reviews).")");
							break;
						case 'bunapprove':
							$wpdb->query("UPDATE `$this->dbtable` SET `status`=0 WHERE `id` IN(".implode(',',$this->p->delete_reviews).")");
							break;
						case 'btrash':
							$wpdb->query("UPDATE `$this->dbtable` SET `status`=2 WHERE `id` IN(".implode(',',$this->p->delete_reviews).")");
							break;
						case 'bdelete':
							$wpdb->query("DELETE FROM `$this->dbtable` WHERE `id` IN(".implode(',',$this->p->delete_reviews).")");
							break;
					}
				}
				$this->smar_redirect("?page=smar_view_reviews&review_status={$this->p->review_status}");
			}
			/* end - actions */
			
			/* begin - searching */
			if ($this->p->review_status == -1) {
				$sql_where = '-1=-1';
			} else {
				$sql_where = 'status='.$this->p->review_status;
			}
			
			$and_clause = '';
			if ($this->p->s != '') { /* searching */
				$this->p->s = '%'.$this->p->s.'%';
				$sql_where = '-1=-1';
				$this->p->review_status = -1;
				$and_clause = "AND (`reviewer_name` LIKE %s OR `reviewer_email` LIKE %s OR `reviewer_ip` LIKE %s OR `review_text` LIKE %s OR `review_response` LIKE %s OR `reviewer_url` LIKE %s)";
				$and_clause = $wpdb->prepare($and_clause,$this->p->s,$this->p->s,$this->p->s,$this->p->s,$this->p->s,$this->p->s);
				
				$query = "SELECT 
					`id`,
					`date_time`,
					`reviewer_name`,
					`reviewer_email`,
					`reviewer_ip`,
					`review_title`,
					`review_text`,
					`review_response`,
					`review_rating`,
					`reviewer_url`,
					`status`,
					`page_id`,
					`custom_fields`
					FROM `$this->dbtable` WHERE $sql_where $and_clause ORDER BY `id` DESC"; 
				
				$reviews = $wpdb->get_results($query);
				$total_reviews = 0; /* no pagination for searches */
			}
			/* end - searching */
			else {
				$arr_Reviews = $this->get_reviews($this->page,$per_page,$this->p->review_status);
				$reviews = $arr_Reviews[0];
				$total_reviews = $arr_Reviews[1];
			}
			$status_text = "";
			switch ($this->p->review_status)
			{
				case -1:
					$status_text = __('Submitted', 'st-business-carry-over-legacy');
					break;
				case 0:
					$status_text = __('Pending', 'st-business-carry-over-legacy');
					break;
				case 1:
					$status_text = __('Approved', 'st-business-carry-over-legacy');
					break;
				case 2:
					$status_text = __('Trashed', 'st-business-carry-over-legacy');
					break;
			}
			
			$pending_count = $wpdb->get_results("SELECT COUNT(*) AS `count_pending` FROM `$this->dbtable` WHERE `status`=0");
			$pending_count = $pending_count[0]->count_pending;
			
			$approved_count = $wpdb->get_results("SELECT COUNT(*) AS `count_approved` FROM `$this->dbtable` WHERE `status`=1");
			$approved_count = $approved_count[0]->count_approved;

			$trash_count = $wpdb->get_results("SELECT COUNT(*) AS `count_trash` FROM `$this->dbtable` WHERE `status`=2");
			$trash_count = $trash_count[0]->count_trash;
			?>
			<div id="smar_respond_1" class="wrap">
				<div class="icon32" id="icon-edit-comments"><br /></div>
				<h2><?php _e('Reviews', 'st-business-carry-over-legacy'); ?> - <?php echo sprintf(__('%s Reviews', 'st-business-carry-over-legacy'), $status_text); ?></h2>
				  <ul class="subsubsub">
					<li class="all"><a <?php if ($this->p->review_status == -1) { echo 'class="current"'; } ?> href="?page=smar_view_reviews&amp;review_status=-1"><?php _e('All', 'st-business-carry-over-legacy'); ?></a> |</li>
					<li class="moderated"><a <?php if ($this->p->review_status == 0) { echo 'class="current"'; } ?> href="?page=smar_view_reviews&amp;review_status=0"><?php _e('Pending ', 'st-business-carry-over-legacy'); ?>
						<span class="count">(<span class="pending-count"><?php echo $pending_count;?></span>)</span></a> |
					</li>
					<li class="approved"><a <?php if ($this->p->review_status == 1) { echo 'class="current"'; } ?> href="?page=smar_view_reviews&amp;review_status=1"><?php _e('Approved', 'st-business-carry-over-legacy'); ?>
						<span class="count">(<span class="pending-count"><?php echo $approved_count;?></span>)</span></a> |
					</li>
					<li class="trash"><a <?php if ($this->p->review_status == 2) { echo 'class="current"'; } ?> href="?page=smar_view_reviews&amp;review_status=2"><?php _e('Trash', 'st-business-carry-over-legacy'); ?>
						<span class="count">(<span class="pending-count"><?php echo $trash_count;?></span>)</span></a>
					</li>
				  </ul>

				  <form method="GET" action="" id="search-form" name="search-form">
					  <p class="search-box">
						  <?php if ($this->p->s_orig): ?><span style='color:#c00;font-weight:bold;'><?php _e('RESULTS FOR: ', 'st-business-carry-over-legacy'); ?></span><br /><?php endif; ?>
						  <label for="comment-search-input" class="screen-reader-text"><?php _e('Search Reviews:', 'st-business-carry-over-legacy'); ?></label> 
						  <input type="text" value="<?php echo $this->p->s_orig; ?>" name="s" id="comment-search-input" />
						  <input type="hidden" name="page" value="smar_view_reviews" />
						  <input type="submit" class="button" value="<?php _e('Search Reviews', 'st-business-carry-over-legacy'); ?>" />
					  </p>
				  </form>

				  <form method="POST" action="?page=smar_view_reviews" id="comments-form" name="comments-form">
				  <input type="hidden" name="review_status" value="<?php echo $this->p->review_status; ?>" />
				  <div class="tablenav">
					<div class="alignleft actions">
						  <select name="action">
								<option selected="selected" value="-1"><?php _e('Bulk Actions', 'st-business-carry-over-legacy'); ?></option>
								<option value="bunapprove"><?php _e('Unapprove', 'st-business-carry-over-legacy'); ?></option>
								<option value="bapprove"><?php _e('Approve', 'st-business-carry-over-legacy'); ?></option>
								<option value="btrash"><?php _e('Move to Trash', 'st-business-carry-over-legacy'); ?></option>
								<option value="bdelete"><?php _e('Delete Forever', 'st-business-carry-over-legacy'); ?></option>
						  </select>&nbsp;
						  <input type="submit" class="button-secondary apply" name="act" value="<?php _e('Apply', 'st-business-carry-over-legacy'); ?>" id="doaction" /></div>
						  <div class="alignright actions reviews-admin"><?php echo $this->pagination($total_reviews); ?></div>
						  <br class="clear" /></div> <div class="clear"></div><table cellspacing="0" class="widefat comments fixed"><thead><tr><th style="" class="manage-column column-cb check-column" id="cb" scope="col"><input type="checkbox" /></th><th style="" class="manage-column column-author" id="author" scope="col"><?php _e('Author', 'st-business-carry-over-legacy'); ?></th><th style="" class="manage-column column-comment" id="comment" scope="col"><?php _e('Review', 'st-business-carry-over-legacy'); ?></th></tr>
					</thead>
					<tfoot>
					  <tr>
						<th style="" class="manage-column column-cb check-column" scope="col"><input type="checkbox" /></th>
						<th style="" class="manage-column column-author" scope="col"><?php _e('Author', 'st-business-carry-over-legacy'); ?></th>
						<th style="" class="manage-column column-comment" scope="col"><?php _e('Review', 'st-business-carry-over-legacy'); ?></th>
					  </tr>
					</tfoot>
					<tbody class="list:comment" id="the-comment-list">
					  <?php
					  if (count($reviews) == 0) { ?>
							<tr><td colspan="3" align="center"><br />
	<?php echo sprintf(__('There are no %s reviews yet.', 'st-business-carry-over-legacy'), $status_text); ?> <br /><br /></td></tr>
						  <?php
					  }
					  foreach ($reviews as $review) {
						  $rid = $review->id;
						  $update_path = get_admin_url()."admin-ajax.php?page=smar_view_reviews&r=$rid&action=update_field";
						  $hash = md5( strtolower( trim( $review->reviewer_email ) ) );
						  $review->review_title = stripslashes($review->review_title);
						  $review->review_text = stripslashes($review->review_text);
						  $review->review_response = stripslashes($review->review_response);
						  $review->reviewer_name = stripslashes($review->reviewer_name);
						  if ($review->reviewer_name == '') { $review->reviewer_name = __('Anonymous', 'st-business-carry-over-legacy'); }
						  $review_text = nl2br($review->review_text);
						  $review_text = str_replace( array("\r\n","\r","\n") , "" , $review_text );
						  $review_response = nl2br($review->review_response);
						  $review_response = str_replace( array("\r\n","\r","\n") , "" , $review_response );
						?>
						  <tr class="approved" id="review-<?php echo $rid;?>">
							<th class="check-column" scope="row"><input type="checkbox" value="<?php echo $rid;?>" name="delete_reviews[]" /></th>
							<td class="author column-author">
								<img width="32" height="32" class="avatar avatar-32 photo" src=
								"http://1.gravatar.com/avatar/<?php echo $hash; ?>?s=32&amp;d=http%3A%2F%2F1.gravatar.com%2Favatar%2Fad516503a11cd5ca435acc9bb6523536%3Fs%3D32&amp;r=G"
								alt="" />&nbsp;<span style="font-weight:bold;" class="best_in_place" data-url='<?php echo $update_path; ?>' data-object='json' data-attribute='reviewer_name'><?php echo $review->reviewer_name; ?></span>
								<br />
								<a href="<?php echo $review->reviewer_url; ?>"><?php echo $review->reviewer_url; ?></a><br />
								<a href="mailto:<?php echo $review->reviewer_email; ?>"><?php echo $review->reviewer_email; ?></a><br />
								<a href="?page=smar_view_reviews&amp;s=<?php echo $review->reviewer_ip; ?>"><?php echo $review->reviewer_ip; ?></a><br />
								<div style="margin-left:-4px;">
									<div style="height:22px;" class="best_in_place" 
										 data-collection='[[1,"Rated 1 Star"],[2,"Rated 2 Stars"],[3,"Rated 3 Stars"],[4,"Rated 4 Stars"],[5,"Rated 5 Stars"]]' 
										 data-url='<?php echo $update_path; ?>' 
										 data-object='json'
										 data-attribute='review_rating' 
										 data-callback='make_stars_from_rating'
										 data-type='select'><?php 
										 echo $this->output_rating($review->review_rating,false);
									?></div>
								</div>
							</td>
							<td class="comment column-comment">
							  <div class="smar-submitted-on">
								<span class="best_in_place" data-url='<?php echo $update_path; ?>' data-object='json' data-attribute='date_time'>
								<?php echo date_i18n( get_option( 'date_format' ), strtotime( $review->date_time ) ) . ' at ' . date_i18n( get_option( 'time_format' ), strtotime( $review->date_time ) );
								?>
								</span>
								<?php if ($review->status == 1) : 

									// hide jumplink if on a search page...
									if ($this->p->s == '') {
										/* not searching */
									
										?>[<a target="_blank" href="<?php 
								
										echo $this->get_jumplink_for_review($review,$this->page); ?>"><?php _e('View on Reviews Page', 'st-business-carry-over-legacy'); ?></a>]<?php
									}
								endif; ?>
							  </div>
							  <p>
								  <span style="font-size:13px;font-weight:bold;"><?php _e('Title:', 'st-business-carry-over-legacy'); ?>&nbsp;</span>
								  <span style="font-size:14px; font-weight:bold;" 
										class="best_in_place" 
										data-url='<?php echo $update_path; ?>' 
										data-object='json'
										data-attribute='review_title'><?php echo $review->review_title; ?></span>
								  <br /><br />
								<?php
								$custom_unserialized = @unserialize($review->custom_fields);
								if (!is_array($custom_unserialized)) {
									$custom_unserialized = array();
								}
								for ($i = 0; $i < 6; $i++) {
								
									$label = empty($smartestthemes_options['st_reviews_custom_field_' . $i]) ? '' : stripslashes_deep(esc_attr($smartestthemes_options['st_reviews_custom_field_' . $i]));
								
									if ( isset($custom_unserialized[$i]) && $label ) {
											
										$value = empty($custom_unserialized[$i]) ? '' : esc_attr($custom_unserialized[$i]);
											
										echo '<strong>' . $label . '</strong>: <span class="best_in_place" data-url="' . $update_path . '" data-object="json" data-attribute="custom_' . $i . '">' . $value . ' </span><br />';
											
									}
								}
								?>
								  
								  <div class="best_in_place" 
										data-url='<?php echo $update_path; ?>' 
										data-object='json'
										data-attribute='review_text' 
										data-callback='callback_review_text'
										data-type='textarea'><?php echo $review_text; ?></div>
								 <div style="font-size:13px;font-weight:bold;">
									 <br />
									 <?php _e('Official Response:', 'st-business-carry-over-legacy'); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
									 <span style="font-size:11px;font-style:italic;"><?php _e('Leave this blank if you do not want it to be public', 'st-business-carry-over-legacy'); ?></span>
								 </div>
								 <div class="best_in_place" 
										data-url='<?php echo $update_path; ?>'
										data-object='json'
										data-attribute='review_response' 
										data-callback='callback_review_text'
										data-type='textarea'><?php echo $review_response; ?></div>
							  </p>
							  <div class="row-actions">
								<span class="approve <?php if ($review->status == 0 || $review->status == 2) { echo 'smar_show'; } else { echo 'smar_hide'; }?>"><a title="<?php _e( 'Mark as Approved', 'st-business-carry-over-legacy' ); ?>"
								href="?page=smar_view_reviews&amp;action=approvereview&amp;r=<?php echo $rid;?>&amp;review_status=<?php echo $this->p->review_status;?>">
								<?php _e('Mark as Approved', 'st-business-carry-over-legacy'); ?></a>&nbsp;|&nbsp;</span>
								<span class="unapprove <?php if ($review->status == 1 || $review->status == 2) { echo 'smar_show'; } else { echo 'smar_hide'; }?>"><a title="<?php _e( 'Mark as Unapproved', 'st-business-carry-over-legacy' ); ?>"
								href="?page=smar_view_reviews&amp;action=unapprovereview&amp;r=<?php echo $rid;?>&amp;review_status=<?php echo $this->p->review_status;?>">
								<?php _e('Mark as Unapproved', 'st-business-carry-over-legacy'); ?></a><?php if ($review->status != 2): ?>&nbsp;|&nbsp;<?php endif; ?></span>
								<span class="trash <?php if ($review->status == 2) { echo 'smar_hide'; } else { echo 'smar_show'; }?>"><a title="<?php _e( 'Move to Trash', 'st-business-carry-over-legacy' ); ?>" 
								href= "?page=smar_view_reviews&amp;action=trashreview&amp;r=<?php echo $rid;?>&amp;review_status=<?php echo $this->p->review_status;?>">
								<?php _e('Move to Trash', 'st-business-carry-over-legacy'); ?></a><?php if ($review->status != 2): ?>&nbsp;|&nbsp;<?php endif; ?></span>
								<span class="trash <?php if ($review->status == 2) { echo 'smar_hide'; } else { echo 'smar_show'; }?>"><a title="<?php _e( 'Delete Forever', 'st-business-carry-over-legacy' ); ?>" 
								href= "?page=smar_view_reviews&amp;action=deletereview&amp;r=<?php echo $rid;?>&amp;review_status=<?php echo $this->p->review_status;?>">
								<?php _e('Delete Forever', 'st-business-carry-over-legacy'); ?></a></span>
							  </div>
							</td>
						  </tr>
					  <?php
					  }
					  ?>
					</tbody>
				  </table>

				  <div class="tablenav">
					<div class="alignleft actions" style="float:left;">
						  <select name="action2">
								<option selected="selected" value="-1"><?php _e('Bulk Actions', 'st-business-carry-over-legacy'); ?></option>
								<option value="bunapprove"><?php _e('Unapprove', 'st-business-carry-over-legacy'); ?></option>
								<option value="bapprove"><?php _e('Approve', 'st-business-carry-over-legacy'); ?></option>
								<option value="btrash"><?php _e('Move to Trash', 'st-business-carry-over-legacy'); ?></option>
								<option value="bdelete"><?php _e('Delete Forever', 'st-business-carry-over-legacy'); ?></option>
						  </select>&nbsp;
						  <input type="submit" class="button-secondary apply" name="act2" value="<?php _e('Apply', 'st-business-carry-over-legacy'); ?>" id="doaction2" />
					</div>
					<div class="alignright actions reviews-admin"><?php echo $this->pagination($total_reviews); ?></div>  
					<br class="clear" />
				  </div>
				</form>
				<div id="ajax-response"></div>
			  </div>
			<?php
		} // end admin_view_reviews	
		
	} // end class

	$Smartest_Themes_Reviews = Smartest_Themes_Reviews::get_instance();

	if ( ! shortcode_exists( 'smartest_reviews' ) ) {
		add_shortcode( 'smartest_reviews', array( $Smartest_Themes_Reviews, 'reviews_shortcode' ) );
	}
	if ( ! shortcode_exists( 'aggregate_rating' ) ) {
		add_shortcode( 'aggregate_rating', array( $Smartest_Themes_Reviews, 'aggregate_footer_func' ) );
	}

}

/* get widget */
include_once 'widget-testimonial.php';

?>