<?php
/**
 * Fragment LMS Setup.
 *
 * @package FLMS\Classes
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;
 
/**
 * Settings class
 */
class FLMS_Settings {

	private $plugin_fields = '';
	private $post_types = array();
	public $settings_name = '';
	
	/**
	 * The Constructor.
	 */
	public function __construct() {
		$this->settings_name = strtolower(static::class);
		$this->post_types = flms_get_plugin_post_types();
		$this->set_plugin_fields();
		add_action( 'admin_init', array($this, 'flms_register_settings') );
		add_action('admin_enqueue_scripts', array($this,'add_settings_js'));
		add_action('wp_head',array($this,'header_scripts'));
		//add_action('admin_head',array($this,'header_scripts'));
		register_setting($this->settings_name, $this->settings_name);

		add_action("pre_update_option_{$this->settings_name}", array($this, 'filter_settings_before_save'), 10, 3);
		add_action("update_option_{$this->settings_name}", array($this, 'update_default_settings'), 10, 3);
	}

	public function header_scripts() {
		global $flms_settings;
		$primary_color = $secondary_color = $background_color = $highlight_color = '';
		if(isset($flms_settings['design']['primary_color'])) {
			$primary_color = $flms_settings['design']['primary_color'];
		}
		if(isset($flms_settings['design']['secondary_color'])) {
			$secondary_color = $flms_settings['design']['secondary_color'];
		}
		if(isset($flms_settings['design']['background_color'])) {
			$background_color = $flms_settings['design']['background_color'];
		}
		if(isset($flms_settings['design']['highlight_color'])) {
			$highlight_color = $flms_settings['design']['highlight_color'];
		}
		if($primary_color != '' || $secondary_color != '' || $background_color != '' || $highlight_color != '') {
			echo '<style>';
				if($primary_color != '') {
					echo '.flms-primary {color: '.$primary_color.';}';
					echo '.flms-primary-bg {background-color: '.$primary_color.';}';
					echo '.flms-primary-bg.flms-tooltip-content:before {border-top-color: '.$primary_color.';}';
					echo '.flms-primary-border {border-color: '.$primary_color.';}';
				}
				if($secondary_color != '') {
					echo '.flms-secondary {color: '.$secondary_color.';}';
					echo '.flms-secondary-bg {background-color: '.$secondary_color.';}';
					echo '.flms-secondary-bg.flms-tooltip-content:before {border-top-color: '.$secondary_color.';}';
					echo '.flms-secondary-border {border-color: '.$secondary_color.';}';
				}
				if($background_color != '') {
					echo '.flms-background {color: '.$background_color.';}';
					echo '.flms-background-bg {background-color: '.$background_color.';}';
					echo '.flms-background-bg.flms-tooltip-content:before {border-top-color: '.$background_color.';}';
					echo '.flms-background-border {border-color: '.$background_color.';}';
				}
				if($highlight_color != '') {
					echo '.flms-highlight {color: '.$highlight_color.';}';
					echo '.flms-highlight-bg {background-color: '.$highlight_color.';}';
					echo '.flms-highlight-border {border-color: '.$highlight_color.';}';
				}
				echo ':root {';
					if($primary_color != '') {
						echo '--flms-primary: '.$primary_color.';';
					}
					if($secondary_color != '') {
						echo '--flms-secondary: '.$secondary_color.';';
					}
					if($background_color != '') {
						echo '--flms-background: '.$background_color.';';
					}
				echo '}';
			echo '</style>';
		}
	}

	public function add_settings_js() {
		global $flms_settings;
		//Load js for saving version
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script('flms-js-cookie');
		wp_enqueue_script(
			'flms-admin-settings',
			FLMS_PLUGIN_URL . 'assets/js/admin-settings.js',
			array('jquery','wp-color-picker','jquery-ui-sortable','flms-js-cookie'),
			flms_get_plugin_version(),
			true
		);
		wp_localize_script( 'flms-admin-settings', 'flms_admin_settings', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'settings' => $flms_settings
		));
	}

	/**
	 * Set default settings fields
	 * @return void
	 * Example: 
	 * 'defaults' => array(
	 *		'label' => 'General settings',
	 *		'description' => 'General plugin settings...',
	 *		'tooltip' => '',
	 *		'fields' => array(
	 *			array(
	 *				'label' => 'Name',
	 *				'key' => 'name',
	 *			),
	 *			array(
	 *				'label' => 'App Key',
	 *				'key' => 'key',
	 *			),
	 *		),
	 *	),
	 */
	public function set_plugin_fields() {
		//Demo content in here for now
		$this->plugin_fields = array(
			'general' => array(
				'label' => 'Welcome',
				'id' => 'general',
				'description' => '',
				'tooltip' => '',
				'layout' => 'standard',
				'fields' => array(
					array(
						'label' => 'Welcome',
						'key' => 'defaults',
						'type' => 'section_heading',
						'description' => 'Welcome! Please review the plugin settings by navigating to each section and adjusting as necessary. Pertinent fields are setting the color scheme under Design and enabling any modules you need to set up your courses.',
						'default' => '75'
					),
				),
			),
			'design' => array(
				'label' => 'Design and Layout',
				'id' => 'design',
				'description' => 'Design options',
				'tooltip' => '',
				'layout' => 'grid',
				'fields' => array(
					array(
						'label' => 'Branding',
						'key' => 'branding_heading',
						'type' => 'section_heading'
					),
					array(
						'label' => 'Primary Color',
						'key' => 'primary_color',
						'type' => 'color_picker',
						'default' => '#32373c'
					),
					array(
						'label' => 'Secondary Color',
						'key' => 'secondary_color',
						'type' => 'color_picker',
						'default' => '#f82645'
					),
					array(
						'label' => 'Background Color',
						'key' => 'background_color',
						'type' => 'color_picker',
						'default' => '#f6f6f6'
					),
					array(
						'label' => 'Courses',
						'key' => 'courses',
						'type' => 'section_heading'
					),
					array(
						'label' => 'Course List Display',
						'key' => 'course_display',
						'type' => 'radio',
						'options' => array(
							'list' => 'List',
							'grid' => 'Grid'
						),
						'default' => 'list',
					),
					array(
						'label' => 'Email',
						'key' => 'email_heading',
						'type' => 'section_heading'
					),
					array(
						'label' => 'Email Footer',
						'key' => 'email_footer',
						'type' => 'textarea',
						'default' => "Sincerely, \r\n".get_bloginfo('name'),
						'description' => ''
					),
					/*array(
						'label' => 'Highlight Color',
						'key' => 'highlight_color',
						'type' => 'color_picker',
						'default' => '#004f5a'
					),*/
				),
			),
			'labels' => array(
				'label' => 'Labels',
				'id' => 'labels',
				'description' => 'Override default labels',
				'tooltip' => '',
				'layout' => 'grid',
				'fields' => array(
					array(
						'label' => 'Course (Singular)',
						'key' => 'course_singular',
						'type' => 'text',
						'default' => 'Course',
						'description' => ''
					),
					array(
						'label' => 'Course (Plural)',
						'key' => 'course_plural',
						'type' => 'text',
						'default' => 'Courses',
						'description' => ''
					),
					array(
						'label' => 'Lesson (Singular)',
						'key' => 'lesson_singular',
						'type' => 'text',
						'default' => 'Lesson',
						'description' => ''
					),
					array(
						'label' => 'Lesson (Plural)',
						'key' => 'lesson_plural',
						'type' => 'text',
						'default' => 'Lessons',
						'description' => ''
					),
					array(
						'label' => 'Topic (Singular)',
						'key' => 'topic_singular',
						'type' => 'text',
						'default' => 'Topic',
						'description' => ''
					),
					array(
						'label' => 'Topic (Plural)',
						'key' => 'topic_plural',
						'type' => 'text',
						'default' => 'Topics',
						'description' => ''
					),
					array(
						'label' => 'Exam (Singular)',
						'key' => 'exam_singular',
						'type' => 'text',
						'default' => 'Exam',
						'description' => ''
					),
					array(
						'label' => 'Exam (Plural)',
						'key' => 'exam_plural',
						'type' => 'text',
						'default' => 'Exams',
						'description' => ''
					),
					array(
						'label' => 'Enroll label',
						'key' => 'enroll_label',
						'type' => 'text',
						'default' => 'Enroll',
						'description' => ''
					),
					array(
						'label' => 'Start exam label',
						'key' => 'exam_start_label',
						'type' => 'text',
						'default' => 'Start Exam',
						'description' => ''
					),
					array(
						'label' => 'Resume exam label',
						'key' => 'exam_resume_label',
						'type' => 'text',
						'default' => 'Resume Exam',
						'description' => ''
					),
					array(
						'label' => 'Save &amp; Continue label',
						'key' => 'exam_save_continue',
						'type' => 'text',
						'default' => 'Save &amp; Continue',
						'description' => ''
					),
				),
			),
			'exams' => array(
				'label' => 'Exams',
				'id' => 'exams',
				'description' => 'Exam options',
				'tooltip' => '',
				'layout' => 'grid',
				'fields' => array(
					array(
						'label' => 'Exam Defaults',
						'key' => 'defaults',
						'type' => 'section_heading',
						'description' => 'The default fields when creating a new exam. Each option can be overidden in individual exams.',
						'default' => '75'
					),
					array(
						'label' => 'Questions per page',
						'key' => 'questions_per_page',
						'type' => 'number',
						'description' => 'Set the default questions per page.',
						'default' => '10'
					),
					array(
						'label' => 'Exam attempts',
						'key' => 'exam_attempts',
						'type' => 'number',
						'description' => 'How many times a learner can retry an exam. Enter -1 for an infinite number of tries.',
						'default' => '3'
					),
					array(
						'label' => 'Enable Save &amp; Continue',
						'key' => 'save_continue_enabled',
						'type' => 'radio',
						'options' => array(
							'active' => 'Enabled',
							'inactive' => 'Disabled'
						),
						'default' => 'active',
						'description' => 'Allows learners to save and resume progress on exams.',
					),
					array(
						'label' => 'Enable Exam Review',
						'key' => 'exam_review_enabled',
						'type' => 'radio',
						'options' => array(
							'active' => 'Enabled',
							'inactive' => 'Disabled'
						),
						'default' => 'active',
						'description' => 'Allows learners to review their exam answers.',
					),
					array(
						'label' => 'Exams are graded',
						'key' => 'exam_is_graded',
						'type' => 'radio',
						'options' => array(
							'graded' => 'Graded',
							'auto' => 'Auto-Pass'
						),
						'default' => 'graded',
						'description' => 'Whether a learner is required to get a passing percentage.',
					),
					array(
						'label' => 'Exams are graded using',
						'key' => 'exam_is_graded_using',
						'type' => 'radio',
						'options' => array(
							'percentage' => 'Percentage',
							'points' => 'Points'
						),
						'default' => 'percentage',
						'description' => 'How an exam is graded for completion',
					),
					array(
						'label' => 'Pass percentage',
						'key' => 'pass_percentage',
						'type' => 'number',
						'description' => 'The default pass percentage when using graded exams.',
						'default' => '75'
					),
					array(
						'label' => 'Pass points',
						'key' => 'pass_points',
						'type' => 'number',
						'description' => 'The default pass points required to complete an exam.',
						'default' => '100'
					),
					array(
						'label' => 'No further exam attempts remaining action',
						'key' => 'exam_attempt_action',
						'type' => 'select',
						'description' => 'Reset current lesson progress: Forces learner to redo current lesson. If used as a course exam this will cause the learner&rsquo;s entire course progress to be reset.<br>Reset course progress: Forces learner to redo entire course<br>Unenroll learner: Forces a learner to reenroll or purchase the course again.',
						'options' => array(
                            'reset-lesson' => 'Reset current lesson progress',
							'reset-course' => 'Reset course progress',
                            'unenroll-learner' => 'Unenroll learner',
							'no-action' => 'No action',
                        ),
                        'default' => 'reset-lesson',
					),
					
				),
			),
		);
		if(flms_is_module_active('course_credits')) {
			$course_credits = new FLMS_Module_Course_Credits();
			$this->plugin_fields['labels']['fields'] = array_merge($this->plugin_fields['labels']['fields'],  $course_credits->get_course_credit_labels());
			
			$this->plugin_fields['course_credits'] = array(
				'label' => 'Course Credits',
				'id' => 'course_credits',
				'description' => 'Set your defaults for course credits and reporting',
				'tooltip' => '',
				'fields' => $course_credits->get_course_credits_fields(false,true),
				'layout' => 'grid text-top',
			);
		}
		if(flms_is_module_active('course_certificates')) {
			$course_certificates = new FLMS_Module_Course_Certificates();
			$this->plugin_fields['labels']['fields'] = array_merge($this->plugin_fields['labels']['fields'],  $course_certificates->get_course_certificate_labels());
		}
		if(flms_is_module_active('course_taxonomies')) {
			$course_taxonomies = new FLMS_Module_Course_Taxonomies();
            $course_tax_fields = $course_taxonomies->get_taxonomy_options();
			$this->plugin_fields['design']['fields'] = array_merge($this->plugin_fields['design']['fields'], $course_tax_fields);

			$this->plugin_fields['course_taxonomies'] = array(
				'label' => 'Course Taxonomies',
				'id' => 'course_taxonomies',
				'description' => 'Set course taxonomies such as course author or field of study',
				'tooltip' => '',
				'fields' => $course_taxonomies->get_course_taxonomies_fields(false,true),
				'layout' => 'grid text-top',
			);
		}
		if(flms_is_module_active('course_materials')) {
			$course_materials = new FLMS_Module_Course_Materials();
			$this->plugin_fields['design']['fields'] = array_merge($this->plugin_fields['design']['fields'],  $course_materials->get_course_materials_settings_options());
			$this->plugin_fields['labels']['fields'] = array_merge($this->plugin_fields['labels']['fields'],  $course_materials->get_course_material_labels());
		}
		if(flms_is_module_active('course_metadata')) {
			$course_metadata = new FLMS_Module_Course_Metadata();
			//$this->plugin_fields['labels']['fields'] = array_merge($this->plugin_fields['labels']['fields'],  $course_credits->get_course_credit_labels());
			
			$this->plugin_fields['course_metadata'] = array(
				'label' => 'Course Metadata',
				'id' => 'course_metadata',
				'description' => 'Set metadata fields for course versions',
				'tooltip' => '',
				'fields' => $course_metadata->get_course_metadata_fields(false,true),
				'layout' => 'grid text-top',
			);
		}
		if(flms_is_module_active('groups')) {
			$groups = new FLMS_Module_Groups();
			$this->plugin_fields['labels']['fields'] = array_merge($this->plugin_fields['labels']['fields'],  $groups->get_group_label_options());
		}
		if(flms_is_module_active('woocommerce')) {
			$woocommerce_module = new FLMS_Module_Woocommerce();
			$woo_fields = $woocommerce_module->get_woocommerce_module_fields();
			$this->plugin_fields['woocommerce'] = array(
				'label' => 'WooCommerce',
				'id' => 'woocommerce',
				'description' => 'Set your custom labels below',
				'tooltip' => '',
				'layout' => 'grid',
				'fields' => $woo_fields,
			);
		}
		if(flms_is_module_active('rest')) {
			$rest = new FLMS_Module_REST();
			$this->plugin_fields['rest'] = array(
				'label' => 'REST',
				'id' => 'rest',
				'description' => '',
				'tooltip' => '',
				'layout' => 'standard',
				'fields' => $rest->get_settings_fields(),
			);
		}

		if(flms_is_module_active('white_label')) {
			$white_label = new FLMS_Module_White_Label();
			$this->plugin_fields['white_label'] = array(
				'label' => 'White Labeling',
				'id' => 'white_labels',
				'description' => 'Set your custom labels below',
				'tooltip' => '',
				'layout' => 'standard',
				'fields' => $white_label->get_white_label_fields(),
			);
		}
		$this->plugin_fields['modules'] = array(
			'label' => 'Fragments',
			'id' => 'modules',
			'description' => 'Fragments extend the functionality of the LMS. Select which features you would like to enable.',
			'tooltip' => '',
			'fields' => apply_filters('flms_modules', 
				array(
					array(
						'label' => 'Course Certificates',
						'key' => 'course_certificates',
						'type' => 'radio',
						'options' => array(
							'active' => 'Active',
							'inactive' => 'Inactive'
						),
						'default' => 'inactive',
						'flag_check' => '',
						'description' => 'Deliver a certificate of completion when a custom completes a course'
					),
					array(
						'label' => 'Course Materials',
						'key' => 'course_materials',
						'type' => 'radio',
						'options' => array(
							'active' => 'Active',
							'inactive' => 'Inactive'
						),
						'default' => 'inactive',
						'flag_check' => '',
						'description' => 'Upload attachments for pre and post enrolled users'
					),
					array(
						'label' => 'Course Credits',
						'key' => 'course_credits',
						'type' => 'radio',
						'options' => array(
							'active' => 'Active',
							'inactive' => 'Inactive'
						),
						'default' => 'inactive',
						'flag_check' => '',
						'description' => 'Assign credits to courses and report on credits acquired'
					),
					array(
						'label' => 'Course Credits Financial Fields Addon',
						'key' => 'course_credits_financial',
						'type' => 'radio',
						'options' => array(
							'active' => 'Active',
							'inactive' => 'Inactive'
						),
						'default' => 'inactive',
						'flag_check' => '',
						'description' => 'Add credits and reporting specific to financial professionals'
					),
					array(
						'label' => 'Course Taxonomies',
						'key' => 'course_taxonomies',
						'type' => 'radio',
						'options' => array(
							'active' => 'Active',
							'inactive' => 'Inactive'
						),
						'default' => 'inactive',
						'flag_check' => '',
						'description' => 'Assign custom taxonomies to your courses such as course author or field of study'
					),
					array(
						'label' => 'Course Taxonomy Royalties',
						'key' => 'course_taxonomy_royalties',
						'type' => 'radio',
						'options' => array(
							'active' => 'Active',
							'inactive' => 'Inactive'
						),
						'default' => 'inactive',
						'flag_check' => '',
						'description' => 'Assign royalty percentages to taxonomies and report on them using Woocommerce sales'
					),
					array(
						'label' => 'Course Metadata',
						'key' => 'course_metadata',
						'type' => 'radio',
						'options' => array(
							'active' => 'Active',
							'inactive' => 'Inactive'
						),
						'default' => 'inactive',
						'flag_check' => '',
						'description' => 'Create text fields for your course version'
					),
					array(
						'label' => 'Course Numbers',
						'key' => 'course_numbers',
						'type' => 'radio',
						'options' => array(
							'active' => 'Active',
							'inactive' => 'Inactive'
						),
						'default' => 'inactive',
						'flag_check' => '',
						'description' => 'Assign and display course numbers to your course versions'
					),
					array(
						'label' => 'Groups',
						'key' => 'groups',
						'type' => 'radio',
						'options' => array(
							'active' => 'Active',
							'inactive' => 'Inactive'
						),
						'default' => 'inactive',
						'flag_check' => '',
						'description' => 'Create groups, invite users to your group and monitor their progress'
					),
					array(
						'label' => 'Woocommerce Integration',
						'key' => 'woocommerce',
						'type' => 'radio',
						'options' => array(
							'active' => 'Active',
							'inactive' => 'Inactive'
						),
						'default' => 'inactive',
						'flag_check' => '',
						'description' => 'Sell your courses through Woocommerce'
					),
					array(
						'label' => 'REST Endpoints',
						'key' => 'rest',
						'type' => 'radio',
						'options' => array(
							'active' => 'Active',
							'inactive' => 'Inactive'
						),
						'default' => 'inactive',
						'flag_check' => '',
						'description' => 'Interact with courses through REST endpoints'
					),
					array(
						'label' => 'Advanced Custom Fields',
						'key' => 'acf',
						'type' => 'radio',
						'options' => array(
							'active' => 'Active',
							'inactive' => 'Inactive'
						),
						'default' => 'inactive',
						'flag_check' => '',
						'description' => 'Version ACF fields in your course content. This module is buggy at best and considered an experimental feature.'
					),
					array(
						'label' => 'White Labeling',
						'key' => 'white_label',
						'type' => 'radio',
						'options' => array(
							'active' => 'Active',
							'inactive' => 'Inactive'
						),
						'default' => 'inactive',
						'flag_check' => '',
						'description' => 'Add your own branding to Fragments LMS'
					),
					
				),
			),
		);
		
		$this->plugin_fields['advanced'] = array(
			'label' => 'Advanced',
			'id' => 'advanced',
			'description' => '',
			'tooltip' => '',
			'layout' => 'standard',
			'fields' => array(
				array(
					'label' => 'Delete plugin data on deactivation',
					'key' => 'delete_plugin_data_on_deactivate',
					'type' => 'checkbox',
					'checkbox_label' => 'Delete plugin data on deactivation',
					'checked' => '',
					'flag_check' => '',
					'description' => 'Warning: enabling this will delete all course content and plugin settings when the plugin is deactivated. It is intended to clean up your install when you no longer need this plugin.',
					'default' => ''
				),
			),
		);
		$this->plugin_fields['debug'] = array(
			'label' => 'Debug',
			'id' => 'debug',
			'description' => 'This area is just for debugging',
			'tooltip' => '',
			'fields' => array(
				array(
					'label' => 'Debug data',
					'key' => 'debug',
					'type' => 'debug',
					'default' => ''
				),
			),
		);
		$this->plugin_fields = apply_filters('flms_plugin_fields', $this->plugin_fields);
	}

	/**
	 * Register settings for plugins
	 * @return void
	 */
	public function flms_register_settings() {
		register_setting($this->settings_name, $this->settings_name);
		
	}

	public function get_settings_name() {
		return $this->settings_name;
	}

	/**
	 * Get plugin settings for use as global variable
	 * @return array
	 */
	public function get_settings() {
		if ( ! get_option($this->settings_name) ) {
			$this->set_flms_default_settings();
		}
		return get_option($this->settings_name);
	}

	/**
	 * Get a setting value
	 */
	public function get_flms_setting($field) {
		global $flms_settings;
		$field_value = '';
		if(isset( $flms_settings[$field] )) {
			$field_value = $flms_settings[$field];
		}
		return $field_value;
	}

	/**
	 * Set default settings for plugin and flush rewrites so everything functions correctly
	 */
	public function set_flms_default_settings() {
		
		/*$flms_settings = array();
		foreach($this->plugin_fields as $field_category => $field_group) { 
			foreach($field_group['fields'] as $field) {
				$flms_settings[$field_category] = array($field['key'] => $field['default']);
			}
		}
		$post_types = flms_get_plugin_post_types();
		foreach($this->post_types as $post_type) { 
			$post_type_name = $post_type['permalink'];
			$flms_settings["custom_post_types"]["{$post_type_name}_permalink"] = $post_type_name;
			$flms_settings["post_type_references"]["{$post_type_name}"] = $post_type['internal_permalink'];
		}
		update_option('flms_settings',$flms_settings);
		flush_rewrite_rules();*/
		
	}

	/**
	 * Remove tmp fields and update default labels on change
	 */
	public function filter_settings_before_save($value, $old_value, $option) {
		//remove 'tmp' from credits and taxonomies 
		if(is_array($value)) {
			foreach($value as $k => $v) {
				if(strpos($k,'tmp') !== false) {
					unset($value[$k]);
				}
			}
		}

		//check if taxonomies changed
		if(isset($value['course_taxonomies'])) {
			$new_taxonomies = $value['course_taxonomies'];

			//check if slugs changed
			$tax_array = array();
			foreach($new_taxonomies as $k => $v) {
				$slug = sanitize_title_with_dashes($v['slug']);
				$singular = $v['name-singular'];
				$plural = $v['name-plural'];
				$hierarchal = $v['hierarchal'];
				$filter_status = $v['filter-status'];
				$status = $v['status'];
				$tax_array["$slug"] = array(
					'name-plural' => $plural,
					'name-singular' => $singular,
					'slug' => $slug,
					'hierarchal' => $hierarchal,
					'filter-status' => $filter_status,
					'status' => $status
				);
				
			}
			$value['course_taxonomies'] = $tax_array;

			$new_taxonmy_keys = array_keys($tax_array);
			if(isset($old_value['course_taxonomies'])) {
				$old_taxonomy_keys = array_keys($old_value['course_taxonomies']);
				$remove_taxonomies = array();
				//delete old terms
				//flms_debug($new_taxonmy_keys,'new');
				//flms_debug($old_taxonomy_keys,'old');
				foreach($old_taxonomy_keys as $taxonomy) {
					if(!in_array($taxonomy, $new_taxonmy_keys)) {
						$terms = get_terms( array(
							'taxonomy' => $taxonomy,
							'hide_empty' => false
						) );
						foreach ( $terms as $term ) {
							wp_delete_term($term->term_id, $taxonomy); 
						}     
					}
				}
			}
			
			flush_rewrite_rules();

		} else {
			if(isset($old_value['course_taxonomies'])) {
				$old_taxonomy_keys = array_keys($old_value['course_taxonomies']);
				foreach($old_taxonomy_keys as $taxonomy) {
					$terms = get_terms( array(
						'taxonomy' => $taxonomy,
						'hide_empty' => false
					) );
					foreach ( $terms as $term ) {
						wp_delete_term($term->term_id, $taxonomy); 
					}     
				}
			}
		}
		return $value;
	}

	public function update_default_settings($old_value, $value, $option) {
		global $flms_settings;
		$flms_settings = $value;
		$has_changes = false;
		if(is_array($value)) {
			if(flms_is_module_active('course_credits')) {
				
				$course_credits = new FLMS_Module_Course_Credits();
				$labels =  $course_credits->get_course_credits_fields(true);
				foreach($labels as $label) {
					if(!isset($value['labels']["{$label['key']}"])) {
						$value['labels']["{$label['key']}"] = $label['label'];
						$has_changes = true;
					}
					if(!isset($value['course_credits']["{$label['key']}"])) {
						$default_settings = array();
						foreach($label['group_fields'] as $field) {
							$default_settings[$field['key']] = $field['default'];
						}
						$value['course_credits']["{$label['key']}"] = $default_settings;
						$has_changes = true;
					}
				}
			}
			if(flms_is_module_active('woocommerce')) {
				$woocommerce_module = new FLMS_Module_Woocommerce();
				$woo_fields = $woocommerce_module->get_woocommerce_module_fields();
				foreach($woo_fields as $field) {
					if(!isset($value['woocommerce']["{$field['key']}"]) && $field['type'] != 'section_heading') {
						$value['woocommerce']["{$field['key']}"] = $field['default'];
						$has_changes = true;
					}
				}
			}
		} 
		if($has_changes) {
			$flms_settings = $value;
			update_option($this->settings_name, $value);
		}
		return $value;
	}

	/**
	 * Display settings page
	 * @return void
	 */
	public function flms_settings_page() {
		global $flms_settings;
		
		if(get_option('flms_welcome') == 'no') {
			wp_admin_notice('Welcome to Fragments LMS! Please review the plugin settings below to get started.', array(
				'type'               => 'success',
				'dismissible'        => true,
				));
			delete_option('flms_welcome');
		}
		$errors = get_settings_errors();
		$output = '';
		if(!empty($errors)) {
			foreach($errors as $error) {
				if($error['code'] == 'settings_updated' && $error['type'] == 'success') {
					$error['message'] = 'Settings updated. Please review setting options after enabling a new module, as new settings may be available.';
				}
				$css_id    = sprintf(
					'setting-error-%s',
					esc_attr( $error['code'] )
				);
				$css_class = sprintf(
					'notice notice-%s settings-error is-dismissible',
					esc_attr( $error['type'] )
				);
				$output .= "<div id='$css_id' class='$css_class'> \n";
				$output .= "<p><strong>{$error['message']}</strong></p>";
				$output .= "</div> \n";
				//settings_errors($error);
			}
		}
		echo $output;
		//settings_errors();
		
		?><div class="wrap">
			
			<h1><?= __(FLMS_PLUGIN_NAME .' Settings', 'flms') ?></h1>
			<form class="form-table" method="post" action="options.php">
				<?php settings_fields($this->settings_name); ?>
				<div id="fragment-settings" class="fragment-settings flms-styling flms-settings-page">
					<ul class="tab-selector">
						<?php 
						$tabct = 1;
						foreach($this->plugin_fields as $field_category => $field_group) //odd whitespace when not doing this funky workaround
						{ ?><li class="<?php if($tabct == 1) { echo 'is-active'; } ?>">
								<button class="setting-group-button wp-ui-highlight" data-group="<?php echo $field_group['id']; ?>">
									<?php echo $field_group['label']; ?>
								</button>
							</li><?php 
							$tabct++;
						} ?>		
						<li class="save-settings"><?php submit_button('Save Settings') ?></li>
					</ul>
					<div class="tab-content">
						<?php 
						$tabct = 1;
						foreach($this->plugin_fields as $field_category => $field_group) {  ?>
							<div id="<?php echo $field_group['id'] ;?>" class="setting-area-content <?php if($tabct == 1) { echo 'is-active'; } ?> <?php if(isset($field_group['layout'])) { echo 'layout-'.$field_group['layout']; }?>">
								<div class="setting-area-<?php echo $field_group['id'] ;?>">
									<!--<h3><?php echo $field_group['label']; ?></h3>-->
									<div class="setting-area-desc flms-styled-heading"><?php echo $field_group['description']; ?></div>
									<div class="setting-area-fields">
										<?php foreach($field_group['fields'] as $field) {
											flms_print_field_input($field,$field_category);
										} ?>
									</div>
								</div>
							</div><?php 
							$tabct++;
						} ?>
					</div>
				</div>

				<?php 
				/**
				 * Hidden fields for our cpt permalink so it can be saved alongside these options, but are managed in the 'Permalinks' settings page
				 */
				foreach($this->post_types as $post_type) { 
					$post_type_name = $post_type['permalink'];
					$field_value = '';
					if(isset( $flms_settings['custom_post_types']["{$post_type_name}_permalink"])) {
						$field_value = $flms_settings['custom_post_types']["{$post_type_name}_permalink"];
					} else {
						$field_value = $post_type_name;
					}
					?>
					<input type="hidden" name="flms_settings[custom_post_types][<?php echo $post_type_name; ?>_permalink]" value="<?php echo $field_value; ?>" /><?php 
					
				} ?>
				<?php 
				if(isset($flms_settings["post_type_references"])) {
					foreach($flms_settings["post_type_references"] as $flag_name => $flag_value) { ?>
						<input type="hidden" name="flms_settings[post_type_references][<?php echo $flag_name; ?>]" value="<?php echo $flag_value; ?>" /><?php 
						
					} 
				} ?>
				<?php 
				/**
				 * Hidden fields for our global options since those are set in various places on the site
				 */
				if(isset($flms_settings["global_flags"])) {
					foreach($flms_settings["global_flags"] as $flag_name => $flag_value) { ?>
						<input type="hidden" name="flms_settings[global_flags][<?php echo $flag_name; ?>]" value="<?php echo $flag_value; ?>" /><?php 
						
					} 
				} ?>
				
			</form>
		</div><?php
	}

	/**
	 * Check for changes in settings and see if we need to do any further action
	 */
	public function settings_change_hook( $value, $old_value, $option ) {
		global $flms_settings;
		$value = maybe_unserialize( $value );
		$versions_active = $value["global_flags"]["has_course_versions"];
		$versions_module_active = $value["modules"]["course_versions"];
		if($versions_module_active == 'inactive' && $versions_active) {
			$this->clean_old_versions();
			$value["global_flags"]["has_course_versions"] = false;
		} 
		return $value;
	}

	/**
	 * Clean up versions when the module is disabled
	 */
	public function clean_old_versions() {
		//TODO
		//Clean up old versions and sort unenrolling, etc
		//Flush permalinks so we use versions in the urls
	}

	
}
global $flms_settings;
$flms = new FLMS_Settings();
$flms_settings = $flms->get_settings();