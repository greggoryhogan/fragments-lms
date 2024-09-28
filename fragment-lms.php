<?php
/*
Plugin Name:  Fragments LMS
Plugin URI:	  https://fragmentlms.com
Description:  Learning management software built for developers, by developers
Version:	  1.2.98
Author:		  Fragment
Author URI:   https://fragmentwebworks.com
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  flms
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'FLMS_PLUGIN_FILE' ) ) {
	define( 'FLMS_PLUGIN_FILE', __FILE__ );
}

// Include the main WooCommerce class.
if ( ! class_exists( 'FLMS', false ) ) {
	include_once dirname( FLMS_PLUGIN_FILE ) . '/includes/class-flms.php';
}

register_activation_hook(__FILE__, 'flms_activation');
register_deactivation_hook(__FILE__, 'flms_deactivation');

add_action( 'activated_plugin', 'cyb_activation_redirect' );
function cyb_activation_redirect( $plugin ) {
    if( $plugin == plugin_basename( __FILE__ ) ) {
        wp_redirect(admin_url('admin.php?page=flms-setup'));
		exit;
    }
}
/**
 * Add option to flush permalinks on activation
 */
function flms_activation() {
	add_option('flms_permlaink_flush', 'true');
	add_option('flms_welcome', 'no');
}

/** 
 * Delete flush option and any applicable plugin data on deactivation
 */
function flms_deactivation() {
	global $wpdb;
	delete_option('flms_permlaink_flush');
	global $flms_settings;
	if(isset($flms_settings['advanced'])) {
		if(isset($flms_settings['advanced']['delete_plugin_data_on_deactivate'])) {
			if($flms_settings['advanced']['delete_plugin_data_on_deactivate'] == 'on') {

				//delete exported files
				$exporter = new FLMS_Exporter();
				$export_dir = $exporter->get_export_dir();
				$export_files = array_diff(scandir($export_dir), array('..', '.', '.DS_Store'));
				if(!empty($export_files)) {
					foreach($export_files as $file) {
						$file_path = $export_dir . '/'. $file;
						$deleted = unlink($file_path);
					}
				}

				//delete woo products
				$args = array(
					'posts_per_page' => -1,
					'fields' => 'ids',
					'post_type' => array('product','product_variation'),
					'meta_query' => array(
						array(
							'key'     => 'flms_woocommerce_course_id',
							'compare' => 'EXISTS'
						),
					),
					
				);
				//flms_woocommerce_product_id
				$products = get_posts($args);
				foreach($products as $product) {
					wp_delete_post( $product, true);
				}

				//delete options
				$results = $wpdb->get_results("SELECT DISTINCT option_name FROM `wp_options` WHERE option_name like 'flms_%'",ARRAY_A );
				foreach ($results as $row){    
					delete_option($row['option_name']);
				}
				
				//delete post meta
				$results = $wpdb->get_results("SELECT DISTINCT meta_key FROM `wp_postmeta` WHERE meta_key like 'flms_%'",ARRAY_A );
				foreach ($results as $row){    
					delete_post_meta_by_key($row['meta_key']);
				}
				//delete user meta
				$results = $wpdb->get_results("SELECT DISTINCT meta_key FROM `wp_usermeta` WHERE meta_key like 'flms_%'",ARRAY_A );
				foreach ($results as $row){    
					delete_metadata('user', 0, $row['meta_key'], '', true);
				}
				
				//delete post types
				$flms_post_types = flms_get_plugin_post_types();
				$post_types = array();
				foreach($flms_post_types as $flms_pt) {
					$post_types[] = $flms_pt['internal_permalink'];
				}
				$post_types[] = 'flms-questions';

				foreach($post_types as $post_type) {
					$wpdb->query("DELETE FROM {$wpdb->prefix}posts WHERE post_type='$post_type'");
					$wpdb->query("DELETE FROM {$wpdb->prefix}postmeta WHERE post_id NOT IN (SELECT id FROM {$wpdb->prefix}posts)");
					$wpdb->query("DELETE FROM {$wpdb->prefix}term_relationships WHERE object_id NOT IN (SELECT id FROM {$wpdb->prefix}posts)");
				}

				//delete question categories
				$results = $wpdb->get_results("select * from {$wpdb->prefix}term_taxonomy where `taxonomy` LIKE  'flms-question-categories'",ARRAY_A );
				foreach ($results as $row){     
					$wpdb->query("DELETE from {$wpdb->prefix}terms WHERE `term_id` =  '".$row['term_id']."'") ;
					$wpdb->query("DELETE from {$wpdb->prefix}term_relationships WHERE `term_taxonomy_id` =  '".$row['term_taxonomy_id']."'");    
				}
				$wpdb->query("DELETE from {$wpdb->prefix}term_taxonomy WHERE `taxonomy` LIKE  'flms-question-categories'");

				//delete settings themselves
				delete_option('flms_settings');
				delete_option('flms_welcome');

				//delete our tables
				$tables = array(
					FLMS_ACTIVITY_TABLE,
					FLMS_COURSE_QUERY_TABLE,
					FLMS_REPORTING_TABLE
				);
				foreach($tables as $table_name) {
					// drop the table from the database.
					$wpdb->query( "DROP TABLE IF EXISTS $table_name" );
				}

				//Peace out!
			}
		}
	} 

}