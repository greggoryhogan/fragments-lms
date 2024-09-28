<?php
/**
 * Fragment LMS Setup.
 *
 * @package FLMS\Classes
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Autoloader class.
 */
class FLMS_Filters {

	static $admin_color_scheme = [];

	/**
	 * The Constructor.
	 */
	public function __construct() {
		//add_filter( 'flms_page_title', array($this, 'flms_page_title_filter'), 1, 1 );
	}

	public function flms_page_title_filter($title) {

	}
}
new FLMS_Filters();
