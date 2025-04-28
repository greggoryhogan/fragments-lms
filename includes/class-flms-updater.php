<?php
/**
 * Fragment LMS Ajax
 *
 * @package FLMS\Classes
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

//Plugin updater
//if (is_admin()) {
	
	include_once dirname( FLMS_PLUGIN_FILE ) . '/assets/library/plugin-update-checker-5.5/plugin-update-checker.php';
	
	use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
	$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
		'https://github.com/greggoryhogan/fragments-lms/', // GitHub URL of your repo
		__FILE__, // Path to your plugin file
		'fragments-lms' // The plugin slug (unique identifier for your plugin)
	);
	$myUpdateChecker->setBranch('master');

//}