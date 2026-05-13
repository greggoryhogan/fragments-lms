<?php
/**
 * Fragment LMS Ajax
 *
 * @package FLMS\Classes
 * @version 1.0.0
 */

 if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require dirname( FLMS_PLUGIN_FILE ) . '/assets/library/plugin-update-checker-5.5/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/greggoryhogan/fragments-lms/', // GitHub URL of your repo
	FLMS_PLUGIN_FILE, // Path to your plugin file
	'flms' // The plugin slug (unique identifier for your plugin)
);

$myUpdateChecker->setBranch('master');

$myUpdateChecker->getVcsApi()->enableReleaseAssets(); // trigger using release