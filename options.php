<?php
/**
* The plugin options for ST Business Carry Over
*/
function stbco_options(){

	$shortname = 'st';

	// globalize the options
	global $smartestthemes_options;
	$smartestthemes_options = get_option('smartestthemes_options');

	$options = array();

	/* Preferences */
	$options[] = array(
		'name' => __('Preferences','st-business-carry-over-legacy'),
		'class' => 'preferences',
		'type' => 'heading');
	$options[] = array(
		'name' => __('Enable Staff?','st-business-carry-over-legacy'),
		'desc' => __('Check this to show your staff members.','st-business-carry-over-legacy'),
		'id' => $shortname.'_show_staff',
		'std' => 'true',
		'type' => 'checkbox');

	$options[] = array(
		'name' => __('Enable Announcements?','st-business-carry-over-legacy'),
		'desc' => __('Check this to show your Announcements (News).','st-business-carry-over-legacy'),
		'id' => $shortname.'_show_news',
		'std' => 'true',
		'type' => 'checkbox');

		
	$options[] = array(
		'name' => __('Enable Services?','st-business-carry-over-legacy'),
		'desc' => __('Check this to show your services.','st-business-carry-over-legacy'),
		'id' => $shortname.'_show_services',
		'std' => 'true',
		'type' => 'checkbox');
						

		
	/* Reviews */		
	$options[] = array(
		'name' => __( 'Reviews','st-business-carry-over-legacy' ),
		'class' => 'reviews',
		'type' => 'heading');
		
	$options[] = array(
		'name' => __('Enable Reviews?','st-business-carry-over-legacy'),
		'desc' => __('Check this to add a page to let visitors submit reviews for your approval. Reviews are not public unless you approve them.','st-business-carry-over-legacy'),
		'id' => $shortname.'_add_reviews',
		'std' => 'true',
		'type' => 'checkbox');

	$options[] = array(
		'name' => __('Reviews shown per page:', 'st-business-carry-over-legacy'),
		'desc' => __('Enter a number. If blank, the default is 10.','st-business-carry-over-legacy'),
		'id' => $shortname.'_reviews_per_page',
		'std' => '10',
		'type' => 'text');
	$options[] = array(
		'name'	=> __('Location of Review Form','st-business-carry-over-legacy'),
		'desc'	=> '',
		'id'	=> $shortname.'_reviews_form_location',
		'type'	=> 'select2',
		'std'	=> 'above',
		'options' => array(
				'above' => __('Above Reviews', 'st-business-carry-over-legacy'),
				'below' => __('Below Reviews', 'st-business-carry-over-legacy')));
	$options[] = array(
		'name'	=> __('Fields to ask for on Review Form','st-business-carry-over-legacy'),
		'desc'	=> '',
		'id'	=> $shortname.'_reviews_ask_fields',
		'type'	=> 'multicheck',
		'std'	=> 'ask_femail',
		'options' => array(
				'ask_fname'		=> __('Name', 'st-business-carry-over-legacy'),
				'ask_femail'	=> __('Email', 'st-business-carry-over-legacy'),
				'ask_fwebsite'	=> __('Website', 'st-business-carry-over-legacy'),
				'ask_ftitle' 	=> __('Review Title', 'st-business-carry-over-legacy'))
				);
	$options[] = array(
		'name' => __('Fields to require on Review Form','st-business-carry-over-legacy'),
		'desc' => '',
		'id' => $shortname.'_reviews_require_fields',
		'type' => 'multicheck',
		'std' => 'require_femail',
		'options' => array(
				'require_fname'		=> __('Name', 'st-business-carry-over-legacy'),
				'require_femail'	=> __('Email', 'st-business-carry-over-legacy'),
				'require_fwebsite'	=> __('Website', 'st-business-carry-over-legacy'),
				'require_ftitle' 	=> __('Review Title', 'st-business-carry-over-legacy'))
				);
	$options[] = array(
		'name' => __('Fields to show on each approved review','st-business-carry-over-legacy'),
		'desc' => __('It is usually NOT a good idea to show email addresses publicly.', 'st-business-carry-over-legacy'),
		'id' => $shortname.'_reviews_show_fields',
		'type' => 'multicheck',
		'std' => 'show_fname',
		'options' => array(
				'show_fname'	=> __('Name', 'st-business-carry-over-legacy'),
				'show_femail'	=> __('Email', 'st-business-carry-over-legacy'),
				'show_fwebsite'	=> __('Website', 'st-business-carry-over-legacy'),
				'show_ftitle' 	=> __('Review Title', 'st-business-carry-over-legacy'))
				);
	$options[] = array( 
		'type' => 'info',
		'std' => __('Custom Fields on Review Form','st-business-carry-over-legacy'),
		'class'	=> 'plain-title',
		);
	$options[] = array( 
		'type' => 'info',
		'std' => __('Enter the names of any additional fields you would like.','st-business-carry-over-legacy'),
		'class'	=> 'intro',
		);
	/* 6 custom fields */
	for ($i = 0; $i < 6; $i++) {
		$options[] = array(
			'desc'	=> '',
			'id'	=> $shortname.'_reviews_custom_field_' . $i,
			'std'	=> '',
			'class'	=> 'half-multi',
			'type'	=> 'text');
					
		$options[] = array(
			'desc'	=> '',
			'id'	=> $shortname.'_reviews_custom' . $i,
			'type'	=> 'multicheck',
			'std'	=> '',
			'class'	=> 'multi',
			'options'	=> array(
					'ask'		=> __('Ask', 'st-business-carry-over-legacy'),
					'require'	=> __('Require', 'st-business-carry-over-legacy'),
					'show'		=> __('Show', 'st-business-carry-over-legacy')));

	}			
	$options[] = array(
		'name' => __('Heading tag to use for Review Titles','st-business-carry-over-legacy'),
		'desc' => __('Select an HTML heading tag for the individual review titles.','st-business-carry-over-legacy'),
		'id' => $shortname.'_reviews_title_tag',
		'std' => 'h2',
		'type' => 'select',
		'options' => array('h2','h3','h4','h5','h6'));			
	$options[] = array(
			'name' => __('Button text for showing the Review form','st-business-carry-over-legacy'),
			'desc'	=> __('Clicking this button will show the review submission form. What do you want this button to say?','st-business-carry-over-legacy'),
			'id'	=> $shortname.'_reviews_show_form_button',
			'std'	=> __('Click here to submit your review','st-business-carry-over-legacy'),
			'type'	=> 'text');
	$options[] = array(
			'name' => __('Heading to be displayed above the Review form','st-business-carry-over-legacy'),
			'desc'	=> __('This will be shown as a heading immediately above the review form.','st-business-carry-over-legacy'),
			'id'	=> $shortname.'_review_form_heading',
			'std'	=> __('Submit Your Review','st-business-carry-over-legacy'),
			'type'	=> 'text');
	$options[] = array(
			'name' => __('Text to use for Review Form Submit Button','st-business-carry-over-legacy'),
			'desc'	=> __('This is the Submit button to submit a review. What do you want this button to say?','st-business-carry-over-legacy'),
			'id'	=> $shortname.'_review_submit_button_text',
			'std'	=> __('Submit Your Review','st-business-carry-over-legacy'),
			'type'	=> 'text');

	update_option('stbco_template',$options);
}
?>