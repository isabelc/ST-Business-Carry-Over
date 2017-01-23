<?php
/**
* The plugin options for ST Business Carry Over
*/
function stbcol_options(){

	$shortname = 'smartestb';

	// globalize the options
	global $smartestb_options;
	$smartestb_options = get_option('smartestb_options');

	$options = array();

	/* Preferences */
	$options[] = array(
		'name' => __('Preferences','st-business-carry-over'),
		'class' => 'preferences',
		'type' => 'heading');
	$options[] = array(
		'name' => __('Enable Staff?','st-business-carry-over'),
		'desc' => __('Check this to show your staff members.','st-business-carry-over'),
		'id' => $shortname.'_show_staff',
		'std' => 'true',
		'type' => 'checkbox');

	$options[] = array(
		'name' => __('Enable Announcements?','st-business-carry-over'),
		'desc' => __('Check this to show your Announcements (News).','st-business-carry-over'),
		'id' => $shortname.'_show_news',
		'std' => 'true',
		'type' => 'checkbox');

		
	$options[] = array(
		'name' => __('Enable Services?','st-business-carry-over'),
		'desc' => __('Check this to show your services.','st-business-carry-over'),
		'id' => $shortname.'_show_services',
		'std' => 'true',
		'type' => 'checkbox');
		
$options[] = array(
	'desc' => sprintf( __('%s Set Custom Sort-Order? %s Check this to set a custom sort-order for services. Default sort-order is descending order by date of post.','st-business-carry-over'), '<strong>', '</strong>' ),
	'id' => $shortname.'_enable_service_sort',
	'std' => 'false',
	'type' => 'checkbox');		
						
	$options[] = array(
		'name' => __('Enable Reviews?','st-business-carry-over'),
		'desc' => __('Check this to add a page to let visitors submit reviews for your approval. Reviews are not public unless you approve them.','st-business-carry-over'),
		'id' => $shortname.'_add_reviews',
		'std' => 'true',
		'type' => 'checkbox');

	update_option('stbcol_template',$options);
}
?>