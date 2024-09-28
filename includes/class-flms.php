<?php
/**
 * FLMS setup
 *
 * @package FLMS
 * @since   1.0.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main WooCommerce Class.
 *
 * @class WooCommerce
 */
final class FLMS {

	/**
	 * FLMS version.
	 *
	 * @var string
	 */
	public $version = '';

	/**
	 * WooCommerce Constructor.
	 */
	public function __construct() {
		if( !function_exists('get_plugin_data') ){
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}
		$plugin_data = get_plugin_data( FLMS_PLUGIN_FILE );
		$this->version = $plugin_data['Version'];
		$this->definitions();
		$this->includes();
		$this->enqueue_scripts();
		$this->modules();
		$this->init_db_tables();
	}

    private function definitions() {
        $this->define( 'FLMS_ABSPATH', dirname( FLMS_PLUGIN_FILE ) . '/' );
		$this->define( 'FLMS_PLUGIN_URL', plugin_dir_url( FLMS_PLUGIN_FILE ));
		$this->define( 'FLMS_PLUGIN_SLUG', 'fragment-lms' );
    }

    private function includes() {
		
        //Class autoloader
        include_once FLMS_ABSPATH . 'includes/class-flms-autoloader.php';

		//Defaults
		include_once FLMS_ABSPATH . 'includes/global.php';
		
        //Setup
		include_once FLMS_ABSPATH . 'includes/class-flms-settings.php';
		include_once FLMS_ABSPATH . 'includes/class-flms-setup.php';
		include_once FLMS_ABSPATH . 'includes/class-flms-reports.php';
		include_once FLMS_ABSPATH . 'includes/class-flms-importer.php';
		include_once FLMS_ABSPATH . 'includes/class-flms-exporter.php';
		include_once FLMS_ABSPATH . 'includes/class-flms-questions.php';
		include_once FLMS_ABSPATH . 'includes/class-flms-ajax.php';
		include_once FLMS_ABSPATH . 'includes/class-flms-permalinks.php';
		include_once FLMS_ABSPATH . 'includes/class-flms-course-manager.php';
		include_once FLMS_ABSPATH . 'includes/class-flms-editor-content.php';
		include_once FLMS_ABSPATH . 'includes/class-flms-template.php';
		if(flms_is_module_active('woocommerce') && is_plugin_active( 'woocommerce/woocommerce.php' )) {
			include_once FLMS_ABSPATH . 'includes/class-flms-module-woocommerce.php';
		}
		if(flms_is_module_active('course_credits')) {
			include_once FLMS_ABSPATH . 'includes/class-flms-module-course-credits.php';
		}
		if(flms_is_module_active('course_taxonomies')) {
			include_once FLMS_ABSPATH . 'includes/class-flms-module-course-taxonomies.php';
		}
		if(flms_is_module_active('acf')) {
			include_once FLMS_ABSPATH . 'includes/class-flms-module-acf.php';
		}
		include_once FLMS_ABSPATH . 'includes/class-flms-shortcodes.php';
		
    }

	public function enqueue_scripts() {
		add_action('wp', array($this, 'register_frontend_scripts'));
		add_action('admin_enqueue_scripts', array($this, 'register_admin_scripts'));
	}

	public function register_frontend_scripts() {
		global $post, $wp, $flms_settings;
		if(!isset($post)) {
			return;
		}
		wp_register_style( 'flms-core', FLMS_PLUGIN_URL . 'assets/css/frontend-core.css', false, $this->version );
		wp_enqueue_style('flms-core');
		wp_register_style( 'flms-global', FLMS_PLUGIN_URL . 'assets/css/global.css', false, $this->version );
		wp_enqueue_style('flms-global');

		wp_register_style( 'select2', FLMS_PLUGIN_URL . 'assets/library/select2/select2.min.css', false, '4.1.0' );
		wp_register_script( 'select2', FLMS_PLUGIN_URL . 'assets/library/select2/select2.min.js', array('jquery'), '4.1.0', true );	
		if(!wp_script_is( 'select2', 'enqueued' )) {
			wp_enqueue_script('select2');
		}
		
		wp_register_style( 'flms-all-courses', FLMS_PLUGIN_URL . 'assets/css/all-courses.css', false, $this->version );
		//wp_enqueue_script( 'flms-js-range-touch', , array(), '3.0.5' );
		wp_register_script( 'flms-all-courses', FLMS_PLUGIN_URL . 'assets/js/frontend/all-courses.js', array('jquery','select2'), $this->version, true );

		wp_enqueue_script( 'flms-js-cookie', FLMS_PLUGIN_URL . 'assets/js/lib/js.cookie.min.js', array(), '3.0.5' );
		wp_register_script( 'flms-frontend', FLMS_PLUGIN_URL . 'assets/js/frontend/frontend.js', array('jquery','flms-js-cookie','select2'), $this->version, array('strategy' => 'defer') );
		
		wp_enqueue_script('flms-frontend');
		$current_user_id = get_current_user_id();
		$course_id = flms_get_course_id($post->ID);
		$course = new FLMS_Course($course_id);
		global $flms_active_version;
		$current_post = 0;
		if(isset($post)) {
			$current_post = $post->ID;
		}
		$frontend_data = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'current_user_id' => $current_user_id,
			'course_id' => $course_id,
			'version_index' => $flms_active_version,
			'current_post' => $current_post
		);
		wp_localize_script( 'flms-frontend', 'flms_frontend', $frontend_data);

		//lesson videos	
		wp_register_script( 'flms-video', FLMS_PLUGIN_URL . 'assets/js/frontend/video.js', array('jquery'), $this->version, array('strategy' => 'defer') );
		
		$flms_content = get_post_meta($current_post,'flms_version_content',true);	
		global $flms_active_version;
		$video_settings = flms_get_video_settings_default_fields();
		if(isset($flms_content["$flms_active_version"]['video_settings'])) {
			$video_settings = $flms_content["$flms_active_version"]['video_settings'];
		}
		global $flms_user_progress;
		if(is_array($flms_user_progress)) {
			if(in_array($post->ID, $flms_user_progress)) {
				$video_settings['force_full_video'] = 0;
			}
		}
		$video_data = array(
			'settings' => $video_settings
		);
		wp_localize_script( 'flms-video', 'flms_video', $video_data);

		wp_register_style( 'flms-questions', FLMS_PLUGIN_URL . 'assets/css/questions.css', false, $this->version );
		wp_register_script( 'flms-exams', FLMS_PLUGIN_URL . 'assets/js/exams.js', array('jquery','wp-editor'), $this->version, array('strategy' => 'defer') );
		
		if(isset($post)) {
			/*if(flms_is_flms_post_type($post)) {
				wp_enqueue_script( 'flms-course', FLMS_PLUGIN_URL . 'assets/js/frontend/course.js', array('jquery'), $this->version, true );
				$exam_data = array(
					'current_page' => $page,
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'exam_id' => get_the_ID(),
					'version_index' => $flms_active_version
				);
				wp_localize_script( 'flms-course', 'flms_course', $course_data);
			}*/
			
			if($post->post_type == 'flms-exams') {
				wp_enqueue_editor();
				wp_enqueue_style('flms-questions');
				wp_enqueue_script('flms-exams');
				//wp_enqueue_script('flms-exam-navigation');
				
				if(isset($wp->query_vars['flms-exam-page'])) {
					$page = $wp->query_vars['flms-exam-page'];
				} else {
					$page = 1;
				}
				
				$exam_data = array(
					'current_page' => $page,
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'exam_id' => get_the_ID(),
					'version_index' => $flms_active_version,
					'current_user_id' => $current_user_id,
				);
				wp_localize_script( 'flms-exams', 'flms_exams', $exam_data);
			}
		}

		//if(flms_is_module_active('woocommerce')) {
			wp_register_script( 'flms-woocommerce-variations-toggle', FLMS_PLUGIN_URL . 'assets/js/woocommerce_variations_toggle.js', array('jquery'), $this->version);
		//}
		if(flms_is_module_active('groups')) {
			wp_register_script('tagify','https://cdn.jsdelivr.net/npm/@yaireo/tagify',array(),'4.27.0',true);
			wp_register_style('tagify','https://cdn.jsdelivr.net/npm/@yaireo/tagify/dist/tagify.css', false, '4.27.0');

			wp_register_script( 'flms-groups', FLMS_PLUGIN_URL . 'assets/js/frontend/groups.js', array('jquery','tagify'), $this->version);
			$min_seats = isset( $flms_settings['woocommerce']['groups_discount_minimum_seats'] ) ? absint( $flms_settings['woocommerce']['groups_discount_minimum_seats'] ) : 1;
			$discount_amount = isset( $flms_settings['woocommerce']['groups_discount_default_amount'] ) ? absint( $flms_settings['woocommerce']['groups_discount_default_amount'] ) : 0;
			$discount_type = isset( $flms_settings['woocommerce']['groups_bulk_purchase_discount_type'] ) ? sanitize_text_field( $flms_settings['woocommerce']['groups_bulk_purchase_discount_type'] ) : 'percent';
			$reporting_label = flms_get_label('reporting_fee');
			$woo_prefix = '$';
			if(function_exists('get_woocommerce_currency_symbol')) {
				$woo_prefix = get_woocommerce_currency_symbol();
			}
			if(is_singular('flms-groups')) {
				$post_id = get_the_ID();
			} else {
				$post_id = 0;
			}
			$current_user_id = get_current_user_id();
			$invalid_code = apply_filters('flms_invalid_group_code_text', 'Please enter a valid code');
			$code_success = apply_filters('flms_valid_group_code_text', 'Success! Redirecting to the group.');
			$groups_data = array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'post_id' => $post_id,
				'user_id' => $current_user_id,
				'min_seats' => $min_seats,
				'discount_type' => $discount_type,
				'discount_amount' => $discount_amount,
				'reporting_label' => $reporting_label,
				'price_prefix' => $woo_prefix,
				'invalid_code' => $invalid_code,
				'valid_code' => $code_success,
			);
			wp_localize_script( 'flms-groups', 'flms_groups', $groups_data);
		}
	}
	
	public function register_admin_scripts() {
		$screen = get_current_screen(); 
		
		wp_register_style( 'flms-admin', FLMS_PLUGIN_URL . 'assets/css/admin.css', false, $this->version );
		wp_enqueue_style('flms-admin');
		wp_register_style( 'flms-global', FLMS_PLUGIN_URL . 'assets/css/global.css', false, $this->version );
		wp_enqueue_style('flms-global');
		wp_register_script( 'flms-admin', FLMS_PLUGIN_URL . 'assets/js/admin-global.js', array('jquery'), $this->version);
		wp_enqueue_script('flms-admin');
		wp_register_style( 'flms-questions', FLMS_PLUGIN_URL . 'assets/css/questions.css', false, $this->version );
		wp_register_script( 'flms-exams', FLMS_PLUGIN_URL . 'assets/js/exams.js', array('jquery','wp-editor'), $this->version, array('strategy' => 'defer') );
		wp_register_script( 'flms-admin-profile', FLMS_PLUGIN_URL . 'assets/js/admin-profile.js', array('jquery','jquery-ui-autocomplete'), $this->version, true );
		wp_register_script( 'flms-admin-group', FLMS_PLUGIN_URL . 'assets/js/admin-group.js', array('jquery','jquery-ui-autocomplete'), $this->version, true );
		wp_register_script( 'flms-js-cookie', FLMS_PLUGIN_URL . 'assets/js/lib/js.cookie.min.js', array(), '3.0.5' );
		if($screen->post_type == 'flms-questions') {
			wp_enqueue_style('flms-questions');
			wp_enqueue_script('flms-exams');
		}
		
		wp_register_script( 'flms-media-uploader', FLMS_PLUGIN_URL . 'assets/js/media-uploader.js', array('jquery'), $this->version, array('strategy' => 'defer') );

		if(flms_is_module_active('course_certificates')) {
			wp_register_style( 'select2', FLMS_PLUGIN_URL . 'assets/library/select2/select2.min.css', false, '4.1.0' );
			wp_register_script( 'select2', FLMS_PLUGIN_URL . 'assets/library/select2/select2.min.js', array('jquery'), '4.1.0', true );
			wp_register_script( 'flms-certificates', FLMS_PLUGIN_URL . 'assets/js/certificates.js', array('jquery','select2'), $this->version, true );
		}

		if(flms_is_module_active('course_materials')) {
			wp_register_script( 'flms-course-materials', FLMS_PLUGIN_URL . 'assets/js/course-materials.js', array('jquery','jquery-ui-sortable'), $this->version);
			$materials_data = array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			);
			wp_localize_script( 'flms-course-materials', 'flms_course_materials', $materials_data);
		}

		/*if(flms_is_module_active('course_taxonomies')) {
			wp_register_script( 'flms-course-taxonomies', FLMS_PLUGIN_URL . 'assets/js/course-taxonomies.js', array('jquery',), $this->version);
			$materials_data = array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			);
			wp_localize_script( 'flms-course-materials', 'flms_course_materials', $materials_data);
		}*/

		//disable autosave on our cpt
		if ( in_array(get_post_type(), flms_get_plugin_post_type_internal_permalinks() ) ) {
        	wp_dequeue_script( 'autosave' );
		}

	}

    private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	private function modules() {
		//Set label for menu item
		$label = 'Fragments LMS';
		if(flms_is_module_active('white_label')) {
			$white_label = new FLMS_Module_White_Label();
			$label_override = $white_label->get_white_label_field('menu_title');
			if($label_override != '') {
				$label = $label_override;
			}
			$icon = $white_label->get_white_label_field('menu_icon');
			if($icon != '') {
				$this->define( 'FLMS_PLUGIN_DASHICON', $icon );	
			}
		}
		$this->define( 'FLMS_PLUGIN_NAME', $label );
		$this->define('FLMS_SETTINGS_URL', admin_url( 'admin.php?page=flms-setup'));
		//table names
		global $wpdb;
		$this->define('FLMS_ACTIVITY_TABLE', $wpdb->base_prefix.'flms_course_activity');
		$this->define('FLMS_COURSE_QUERY_TABLE', $wpdb->base_prefix.'flms_course_metadata'); //for querying active course products
		$this->define('FLMS_REPORTING_TABLE', $wpdb->base_prefix.'flms_credit_reporting');
	}

	private function init_db_tables() {
		add_action('admin_init', array($this, 'flms_init_db_tables'));
	}
	public function flms_init_db_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		
		//Course Activity
		$tablename = FLMS_ACTIVITY_TABLE;
		$sql = "CREATE TABLE $tablename (
			id bigint(20)  unsigned NOT NULL auto_increment,
			customer_id bigint(20) ,
			course_id varchar(250),
			course_version varchar(250),
			customer_status varchar(250),
			steps_completed varchar(255) DEFAULT NULL, 
			enroll_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			completion_date varchar(250) DEFAULT NULL,
			last_active TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY id (id),
			KEY customer_id (customer_id),
			KEY enroll_date (enroll_date),
			KEY completion_date (completion_date),
			KEY customer_status (customer_status)
			) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		maybe_create_table( $tablename,  $sql );

		//Course Query Data
		$tablename = FLMS_COURSE_QUERY_TABLE;
		$sql = "CREATE TABLE $tablename (
			id bigint(20)  unsigned NOT NULL auto_increment,
			course_id bigint(20),
			meta_key varchar(250),
			meta_value varchar(250),
			PRIMARY KEY id (id),
			KEY course_id (course_id),
			KEY meta_key (meta_key)
			) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		maybe_create_table( $tablename,  $sql );

		//Credit Reporting
		if(flms_is_module_active('course_credits')) {
			$tablename = FLMS_REPORTING_TABLE;
			$sql = "CREATE TABLE $tablename (
				meta_id bigint(20)  unsigned NOT NULL auto_increment,
				entry_id varchar(250),
				credit_type varchar(250),
				accepts_reporting_fee int DEFAULT 0,
				PRIMARY KEY meta_id (meta_id),
				KEY entry_id (entry_id),
				KEY accepts_reporting_fee (accepts_reporting_fee)
				) $charset_collate;";

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			maybe_create_table( $tablename,  $sql );
		}

	}

}
new FLMS();