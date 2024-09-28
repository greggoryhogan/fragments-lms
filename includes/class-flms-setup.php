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
class FLMS_Setup {

	static $admin_color_scheme = [];
	public $post_types = array();
	public $capability = 'manage_options';
	/**
	 * The Constructor.
	 */
	public function __construct() {
		$this->post_types = flms_get_plugin_post_types();
		add_action('init', array($this,'register_post_types'));
		add_action('init', array($this,'add_rewrite_tags'));
		add_action('init', array($this,'add_rewrite_rules'));
		add_action('admin_menu',array($this,'flms_admin_menu_items'));
		add_action( 'admin_enqueue_scripts', array($this,'enqueue_flms_admin_scripts') );
		add_filter('post_type_link', array($this,'flms_permalink_replacement'), 10, 4);
		add_action('admin_head', array($this,'admin_colors_head'));
        add_action('admin_footer', array($this,'admin_footer_colors'));
		add_filter( 'query_vars', array($this,'add_version_query_var'));
		add_action('admin_notices',array($this,'post_type_helper_notice'));
		add_filter('manage_flms-lessons_posts_columns',array($this,'flms_lesson_custom_columns'));
		add_filter('manage_flms-topics_posts_columns',array($this,'flms_topic_custom_columns'));
		add_filter('manage_flms-exams_posts_columns',array($this,'flms_exams_custom_columns'));
		add_filter('manage_flms-questions_posts_columns',array($this,'flms_questions_custom_columns'));
		add_filter('manage_flms-lessons_posts_custom_column',array($this,'flms_custom_column_data'), 10, 2);
		add_filter('manage_flms-topics_posts_custom_column',array($this,'flms_custom_column_data'), 10, 2);
		add_filter('manage_flms-exams_posts_custom_column',array($this,'flms_custom_column_data'), 10, 2);
		add_filter('manage_flms-questions_posts_custom_column',array($this,'flms_custom_column_data'), 10, 2);
		add_action('admin_bar_menu', array($this, 'admin_bar_versions'), 100);
		add_action( 'parent_file', array($this,'flms_taxonomy_menu_highlight') );
		add_action('admin_init',array($this,'set_course_version_from_get'));
		add_filter( 'post_row_actions', array($this,'flms_row_actions'), 10, 2 );
		add_filter( 'page_row_actions', array($this,'flms_row_actions'), 10, 2 );
		
		//login activity
		//add_action( 'wp_login', 'flms_user_last_active', 10, 2 );
		add_action( 'wp_head', array($this, 'flms_user_last_active') );

		add_action( 'show_user_profile', array($this, 'flms_user_profile_fields') );
		add_action( 'edit_user_profile', array($this, 'flms_user_profile_fields') );
        add_action( 'personal_options_update', array($this,'flms_save_user_profile_fields') );
        add_action( 'edit_user_profile_update', array($this,'flms_save_user_profile_fields') );
		if(flms_is_module_active('course_credits')) {
			$course_credits = new FLMS_Module_Course_Credits();
			$course_credits->init_profile_fields();
		}
		if(flms_is_module_active('rest')) {
			$rest = new FLMS_Module_REST();
			add_action( 'rest_api_init', array($rest,'register_rest_endpoints'));
		}

		//import crons
		$cron = new FLMS_Cron();
		//add_filter( 'cron_schedules', array($cron, 'flms_cron_times') );
		add_action( 'admin_notices', array($cron, 'cron_notices'));
		add_action( 'flms_import_courses', array($cron, 'import_courses'), 10, 6 );
		add_action( 'flms_import_exams', array($cron, 'import_exams'), 10, 6 );
		add_action( 'flms_import_questions', array($cron, 'import_questions'), 10, 6 );
		add_action( 'flms_import_user_data', array($cron, 'import_user_data'), 10, 6 );

		if(flms_is_module_active('woocommerce')) {
			$woo = new FLMS_Module_Woocommerce();
			$woo->flms_init_woo_actions_and_filters();
		}
	}

	public function flms_user_last_active() {
		$user_id = get_current_user_id();
		if($user_id > 0) {
			update_user_meta( $user_id, 'flms_last_active', current_time('mysql') );
		}
	}

	/**
	 * Register post types for plugin
	 */
	public function register_post_types() {
		global $flms_settings;
		$rewrite_parent = '';
		$rewrite_parent_cpt = '';
		foreach($this->post_types as $post_type) {
			if(isset($post_type['register'])) {
				if($post_type['register'] === true) {
					$post_type_slug = $post_type['permalink'];
					$rewrite = $post_type['rewrite'];
					
					$default_permalink = $post_type['permalink'];
					$singular_name = $post_type['name'];
					if(isset($flms_settings['labels']["{$default_permalink}_singular"])) {
						$singular_name = $flms_settings['labels']["{$default_permalink}_singular"];
					}
					$singular_lowercase = strtolower($singular_name);

					$plural_name = $post_type['plural_name'];
					if(isset($flms_settings['labels']["{$default_permalink}_plural"])) {
						$plural_name = $flms_settings['labels']["{$default_permalink}_plural"];
					}
					$plural_lowercase = strtolower($plural_name);

					$labels = array(
						"name" => __( "$plural_name", "" ),
						"singular_name" => __( "$singular_name", "flms" ),
						'all_items' => __( "$plural_name", "flms" ),
						'edit_item' => __( "Edit $singular_name", "flms" ),
						'update_item' => __( "Update $singular_name", "flms" ),
						'add_new' => __( "Add New $singular_name", "flms" ),
						'add_new_item' => __( "Add New $singular_name", "flms" ),
						'new_item_name' => __( "New $singular_name", "flms" ),
						'menu_name' => __( "$singular_name", "flms" ),
						'back_to_items' => __( "&laquo; All $plural_name", "flms" ),
						'not_found' => __( "No $plural_lowercase found.", "flms" ),
						'not_found_in_trash' => __( "No $plural_lowercase found in trash.", "flms" ),
					);
					$args = array(
						"label" => __( "$plural_name", "flms" ),
						"labels" => $labels,
						"description" => "",
						"public" => true,
						"publicly_queryable" => true,
						"show_ui" => true,
						"show_in_rest" => true,
						"rest_base" => "",
						"show_in_menu" => FLMS_PLUGIN_SLUG,
						"exclude_from_search" => false,
						"capability_type" => "page",
						"map_meta_cap" => true,
						"query_var" => true,
						'hierarchical' => true,
						'has_archive' => $rewrite,
						"rewrite" => array( "slug" => $rewrite, ), //"with_front" => false 
						//"taxonomies" => array( "supplier" ),
					);
					if($post_type['internal_permalink'] != 'flms-courses') {
						$args['capabilities'] = array(
							'create_posts' => false, // Removes support for the "Add New" function ( use 'do_not_allow' instead of false for multisite set ups )
							'edit_post'          => 'edit_post' ,
							'read_post'          => 'read_post' ,
							'delete_post'        => 'delete_post',
							'edit_posts'         => 'edit_posts' ,
							'edit_others_posts'  => 'edit_others_posts',
							'delete_posts'       => 'delete_posts',
							'publish_posts'      =>'publish_posts',
							'read_private_posts' => 'read_private_posts'
						);
						$args['map_meta_cap'] = true;
						$args['supports'] = array('title','editor','thumbnail','custom_fields');
					} else {
						$args['supports'] = array('title','author','excerpt','thumbnail','custom_fields'); //'editor',
						$args['capabilities'] = array(
							'edit_post'          => 'edit_post' ,
							'read_post'          => 'read_post' ,
							'delete_post'        => 'delete_post',
							'edit_posts'         => 'edit_posts' ,
							'edit_others_posts'  => 'edit_others_posts',
							'delete_posts'       => 'delete_posts',
							'publish_posts'      =>'publish_posts',
							'read_private_posts' => 'read_private_posts'
						);
					}
					register_post_type( "{$post_type['internal_permalink']}", $args );
				}
			}
			
		}

		//Register questions cpt
		$labels = array(
			"name" => __( "Questions", "" ),
			"singular_name" => __( "Question", "flms" ),
			'all_items' => __( "Questions", "flms" ),
			'edit_item' => __( "Edit Question", "flms" ),
			'update_item' => __( "Update Question", "flms" ),
			'add_new' => __( "Add New Question", "flms" ),
			'add_new_item' => __( "Add New Question", "flms" ),
			'new_item_name' => __( "New Question", "flms" ),
			'menu_name' => __( "Questions", "flms" ),
			'back_to_items' => __( "&laquo; All Questions", "flms" ),
			'not_found' => __( "No questions found.", "flms" ),
			'not_found_in_trash' => __( "No questions found in trash.", "flms" ),
		);
		$args = array(
			"label" => __( "Questions", "flms" ),
			"labels" => $labels,
			"description" => "",
			"public" => false,
			"publicly_queryable" => false,
			"show_ui" => true,
			"show_in_rest" => true,
			"rest_base" => "",
			"has_archive" => false,
			'show_in_nav_menus' => false,
			"show_in_menu" => FLMS_PLUGIN_SLUG,
			"exclude_from_search" => false,
			"capability_type" => "page",
			"map_meta_cap" => true,
			"query_var" => true,
			'hierarchical' => true,
			'has_archive' => false,
			"rewrite" => false,
			"supports" => array('title','editor','custom_fields'),
			'capabilities' => array(
				'edit_post'          => 'edit_post' ,
				'read_post'          => 'read_post' ,
				'delete_post'        => 'delete_post',
				'edit_posts'         => 'edit_posts' ,
				'edit_others_posts'  => 'edit_others_posts',
				'delete_posts'       => 'delete_posts',
				'publish_posts'      =>'publish_posts',
				'read_private_posts' => 'read_private_posts'
			),
			//"taxonomies" => array( "supplier" ),
		);
		register_post_type( "flms-questions", $args );

		//Register questions taxonomy
		$labels = array(
			"name" => __( "Question Categories", "" ),
			"singular_name" => __( "Question Category", "" ),
		);
	
		$args = array(
			"label" => __( "Question Categories", "" ),
			"labels" => $labels,
			"public" => false,
			"label" => "Question Categories",
			"hierarchical" => true,
			"show_ui" => true,
			"show_in_menu" => FLMS_PLUGIN_SLUG,
			"show_in_nav_menus" => false,
			"query_var" => true,
			"rewrite" => false,
			"show_admin_column" => 0,
			"show_in_rest" => false,
			"show_in_quick_edit" => true,
			
		);
		register_taxonomy( "flms-question-categories", array( "flms-questions" ), $args );

		if(flms_is_module_active('course_certificates')) {
			$course_certificates = new FLMS_Module_Course_Certificates();
			$course_certificates->register_cpt();
		}

		if(flms_is_module_active('groups')) {
			$groups = new FLMS_Module_Groups();
			$groups->register_cpt();
		}

		if(flms_is_module_active('course_taxonomies')) {
			$course_taxonomies = new FLMS_Module_Course_Taxonomies();
			$course_taxonomies->register_taxonomies();
		}

	}

	/**
	 * Add rewrite tags
	 */
	public function add_rewrite_tags() {
		add_rewrite_tag('%flms-courses%', '([^/]+)');
    	add_rewrite_tag('%version%', '([^/]+)');
		add_rewrite_tag('%flms-exam-page%', '([^/]+)');
		add_rewrite_tag('%course-version%', '([^&]+)');
		global $flms_settings;
		/*if(is_array($flms_settings["custom_post_types"])) {
			foreach($flms_settings["custom_post_types"] as $post_type) {
				add_rewrite_tag("%$post_type%", '([^/]+)');		
			}
		}*/
		$course_permalink = $flms_settings["custom_post_types"]["course_permalink"];
		$lesson_permalink = $flms_settings["custom_post_types"]["lesson_permalink"];
		$topic_permalink = $flms_settings["custom_post_types"]["topic_permalink"];
		$exam_permalink = $flms_settings["custom_post_types"]["exam_permalink"];

		add_rewrite_tag("%$course_permalink%", '([^/]+)');
		add_rewrite_tag("%$lesson_permalink%", '([^/]+)');
		add_rewrite_tag("%$topic_permalink%", '([^/]+)');
		add_rewrite_tag("%$exam_permalink%", '([^/]+)');
		//print exam
		//add_rewrite_tag("%print-$exam_permalink%", '([^/]+)');
	}

	/**
	 * Add rewrite rules for course structure
	 * This could probably be simplified, but it works!
	 */
	public function add_rewrite_rules() {
		global $flms_settings;
		// Handle URLs with 'version' for child pages
		$course_permalink = $flms_settings["custom_post_types"]["course_permalink"];
		$lesson_permalink = $flms_settings["custom_post_types"]["lesson_permalink"];
		$topic_permalink = $flms_settings["custom_post_types"]["topic_permalink"];
		$exam_permalink = $flms_settings["custom_post_types"]["exam_permalink"];

		
		//lesson as child of course, optional version
		add_rewrite_rule(
			"^{$course_permalink}/([^/]+)/(?:([^/]+)/)?{$lesson_permalink}/([^/]+)/?$",
			'index.php?flms-courses=$matches[1]&course-version=$matches[2]&flms-lessons=$matches[3]',
			'top'
		);
	
		//topic as child of lesson, optional version
		add_rewrite_rule(
			"^{$course_permalink}/([^/]+)/(?:([^/]+)/)?{$lesson_permalink}/([^/]+)/{$topic_permalink}/([^/]+)/?$",
			'index.php?flms-courses=$matches[1]&course-version=$matches[2]&flms-lessons=$matches[3]&flms-topics=$matches[4]',
			'top'
		);

		//exam as child of course
		add_rewrite_rule(
			"^{$course_permalink}/([^/]+)/(?:([^/]+)/)?{$exam_permalink}/([^/]+)/?$",
			'index.php?flms-courses=$matches[1]&course-version=$matches[2]&flms-exams=$matches[3]',
			'top'
		);
		//exam as child of course w/ pagination
		add_rewrite_rule(
			"^{$course_permalink}/([^/]+)/(?:([^/]+)/)?{$exam_permalink}/([^/]+)/page/([^/]+)/?$",
			'index.php?flms-courses=$matches[1]&course-version=$matches[2]&flms-exams=$matches[3]&flms-exam-page=$matches[4]',
			'top'
		);

		//exam as child of lesson
		add_rewrite_rule(
			"^{$course_permalink}/([^/]+)/(?:([^/]+)/)?{$lesson_permalink}/([^/]+)/{$exam_permalink}/([^/]+)/?$",
			'index.php?flms-courses=$matches[1]&course-version=$matches[2]&flms-lessons=$matches[3]&flms-exams=$matches[4]',
			'top'
		);
		//exam as child of lesson w/ pagination
		add_rewrite_rule(
			"^{$course_permalink}/([^/]+)/(?:([^/]+)/)?{$lesson_permalink}/([^/]+)/{$exam_permalink}/([^/]+)/page/([^/]+)/?$",
			'index.php?flms-courses=$matches[1]&course-version=$matches[2]&flms-lessons=$matches[3]&flms-exams=$matches[4]&flms-exam-page=$matches[5]',
			'top'
		);

		//exam as child of topic
		/*add_rewrite_rule(
			"^{$course_permalink}/([^/]+)/(?:([^/]+)/)?{$lesson_permalink}/([^/]+)/{$topic_permalink}/([^/]+)/{$exam_permalink}/([^/]+)/?$",
			'index.php?flms-courses=$matches[1]&course-version=$matches[2]&flms-lessons=$matches[3]&flms-topics=$matches[4]&flms-exams=$matches[5]',
			'top'
		);
		//exam as child of topic
		add_rewrite_rule(
			"^{$course_permalink}/([^/]+)/(?:([^/]+)/)?{$lesson_permalink}/([^/]+)/{$topic_permalink}/([^/]+)/{$exam_permalink}/([^/]+)/?$",
			'index.php?flms-courses=$matches[1]&course-version=$matches[2]&flms-lessons=$matches[3]&flms-topics=$matches[4]&flms-exams=$matches[5]',
			'top'
		);*/
		
		//exam as child of topic w/ pagination
		add_rewrite_rule(
			"^{$course_permalink}/([^/]+)/(?:([^/]+)/)?{$lesson_permalink}/([^/]+)/{$topic_permalink}/([^/]+)/{$exam_permalink}/([^/]+)/page/([^/]+)/?$",
			'index.php?flms-courses=$matches[1]&course-version=$matches[2]&flms-lessons=$matches[3]&flms-topics=$matches[4]&flms-exams=$matches[5]&flms-exam-page=$matches[6]',
			'top'
		);
	
		// course with 'version'
		add_rewrite_rule(
			"^{$course_permalink}/([^/]+)/([^/]+)/?$",
			'index.php?flms-courses=$matches[1]&course-version=$matches[2]',
			'top'
		);
	
		// course without 'version'
		add_rewrite_rule(
			"^{$course_permalink}/([^/]+)/?$",
			'index.php?flms-courses=$matches[1]',
			'top'
		);

		//lesson
		add_rewrite_rule(
			"^{$lesson_permalink}/([^/]+)/?$",
			'index.php?flms-lessons=$matches[1]',
			'top'
		);

		//topic
		add_rewrite_rule(
			"^{$topic_permalink}/([^/]+)/?$",
			'index.php?flms-topics=$matches[1]',
			'top'
		);

		//exam
		add_rewrite_rule(
			"^{$exam_permalink}/([^/]+)//?$",
			'index.php?flms-exams=$matches[1]',
			'top'
		);
		//exam w/ pagination
		add_rewrite_rule(
			"^{$exam_permalink}/([^/]+)/page/([^/]+)/?$",
			'index.php?flms-exams=$matches[1]&flms-exam-page=$matches[2]',
			'top'
		);

		//printing exam
		add_rewrite_rule(
			"^print-{$exam_permalink}/([^/]+)/([^/]+)/?$",
			'index.php?post_type=flms-exams&print-exam-id=$matches[1]&print-exam-version=$matches[2]',
			'top'
		);
		if(flms_is_module_active('course_certificates')) {
			$course_certificates = new FLMS_Module_Course_Certificates();
			$course_certificates->register_rewrite_rule();
		}	
		if(flms_is_module_active('groups')) {
			$groups = new FLMS_Module_Groups();
			$groups->register_rewrite_rule();
		}

		//flush_rewrite_rules();

	}

	public function flms_row_actions( $actions, $post ) {
		if(!flms_is_flms_post_type($post)) {
			return $actions;
		}
		$new_actions = array();
		$new_actions['id'] = 'ID: '.$post->ID;
		$course_id = flms_get_course_id($post->ID);
		if($course_id > 0) {
			$course = new FLMS_Course($course_id);
			global $flms_latest_version;
			$new_actions['published-version'] = 'Version: '.$flms_latest_version;
		}
		
		return array_merge($new_actions, $actions);
	}

	/**
	 * Create admin sidebar
	 */
	public function flms_admin_menu_items() {
		//Default icon
		$menu_icon = 'data:image/svg+xml;base64,' . base64_encode( file_get_contents( FLMS_ABSPATH . 'assets/bolts.svg' ) );
		//See if there is custom icon
		if(defined('FLMS_PLUGIN_DASHICON')) {
			if(FLMS_PLUGIN_DASHICON != 'default') {
				$menu_icon = 'dashicons-'.FLMS_PLUGIN_DASHICON;
			}
		} 
		add_menu_page( FLMS_PLUGIN_NAME, FLMS_PLUGIN_NAME, 'edit_posts', FLMS_PLUGIN_SLUG, '', $menu_icon, 3  );
		// add additional options to plugin admin menu
		add_submenu_page(FLMS_PLUGIN_SLUG,'Setup','Setup','install_plugins', 'flms-setup', array($this,'flms_setup'),0);
		add_submenu_page(FLMS_PLUGIN_SLUG,'Question Categories','Question Categories','edit_posts', 'edit-tags.php?taxonomy=flms-question-categories');
		if(flms_is_module_active('course_certificates')) {
			add_submenu_page(FLMS_PLUGIN_SLUG, 'Certificates','Certificates','edit_posts', 'edit.php?post_type=flms-certificates');
		}
		if(flms_is_module_active('groups')) {
			add_submenu_page(FLMS_PLUGIN_SLUG, 'Groups','Groups','edit_posts', 'edit.php?post_type=flms-groups');
		}
		if(flms_is_module_active('course_taxonomies')) {
			$course_taxonomies = new FLMS_Module_Course_Taxonomies();
			$course_taxonomies->register_taxonomy_menu_pages();
		}
		add_submenu_page(FLMS_PLUGIN_SLUG,'Exam Editor','Exam Editor','install_plugins', 'flms-exam-editor', array($this,'flms_exam_editor'), PHP_INT_MAX);

	}

	public function flms_exam_editor() {
		?><div class="wrap">
			<h1><?= __(FLMS_PLUGIN_NAME .' Exam Editor', 'flms') ?></h1>
			<?php 
			$exam_id = $exam_version = $user_id = 0;
			if(isset($_GET['exam_id'])) {
				$exam_id = absint($_GET['exam_id']);
			}
			if(isset($_GET['exam_version'])) {
				$exam_version = absint($_GET['exam_version']);
			}
			if(isset($_GET['user_id'])) {
				$user_id = absint($_GET['user_id']);
			}
			if($exam_id > 0 && $exam_version > 0 && $user_id > 0) {
				$user = get_user_by('id',$user_id);
				if($user !== false) { 
					$user = get_user_by('id',$user_id);
					echo '<h2>'.get_the_title($exam_id).'</h2>';
					echo '<p>Version: '.$exam_version.', User: '.$user->display_name.' (<a href="mailto:'.$user->user_email.'">'.$user->user_email.'</a>)</p>';
					
					$exam = new FLMS_Exam($exam_id);
					global $flms_active_version;
					$flms_active_version = $exam_version;
					$exam_identifier = "$exam_id:$exam_version";
					$exam_settings = get_post_meta($exam_id, "flms_exam_settings_$flms_active_version", true);
					$course_id = flms_get_course_id($exam_id);
					$flms_user_progress = flms_get_user_activity($user_id, $course_id, $exam_version);
					$steps_completed = maybe_unserialize($flms_user_progress['steps_completed']);
					$passed = false;
					$passed_text = 'User did not pass exam';
					if(flms_is_step_complete($steps_completed, $exam_id)) {
						$passed_text = 'User passed exam';
						$passed = true;
					}
					$meta_key = "flms_{$exam_identifier}_exam_attempts";
					$attempts = get_user_meta($user_id, $meta_key, true);
					if($attempts >= 0) {
						$meta_key = "flms_{$exam_identifier}_exam_attempt_{$attempts}";
						$last_attempt = get_user_meta($user_id, $meta_key, true);
						if($last_attempt != '') {
							$score = $last_attempt['score'];
							echo '<p>Score: '.$score.'% ('.$last_attempt['correct'].' of '.$last_attempt['total'].' questions). '.$passed_text.'<sup>*</sup> </p>';
						}
						if(!$passed) {
							$max_attempts = $exam_settings['exam_attempts'];
							if($max_attempts == '') {
								$max_attempts = 1;
							}
							$exam_attempts = $max_attempts;
							$meta_key = "flms_{$exam_identifier}_extra_exam_attempts";
							if(isset($_POST['additional_attempts'])) {
								$additional_attempts = absint($_POST['additional_attempts']);
								update_user_meta($user_id, $meta_key, $additional_attempts);
							} else {
								$additional_attempts = max(get_user_meta($user_id, $meta_key, true), 0);
							}
							$max_attempts += $additional_attempts;
							
							$remaining = max(($max_attempts + $additional_attempts) - $attempts, 0);
							echo '<form method="post" action="'.trailingslashit(get_bloginfo('url')).'wp-admin/'.basename($_SERVER['REQUEST_URI']).'">';
							echo "<p>Attempts: $attempts of $max_attempts";
							if($additional_attempts > 0) {
								echo " ($exam_attempts from exam, $additional_attempts additional granted)";
							}
							echo "</p>";
							echo '<div class="flms-flex align-center"><span>Additional attempts:</span><input type="number" value="'.$additional_attempts.'" name="additional_attempts" class="flex-auto" /><input type="submit" class="flex-auto button button-primary" value="Update additional attempts" /></div>';
							echo '</form>';
						}
						echo '<hr class="flms-hr">';
						echo $exam->edit_user_exam($user_id, $attempts);
					} else {
						echo 'User has no exam attempts to update.';
					}
				} else {
					echo '<p>Invalid user ID!</p>';
				}
				
			} else {
				echo '<p>The exam cannot be edited as it is missing pertinant information.';
			}
			?>
		</div>
		<?php 
	}

	/**
	 * Change menu highlight for question categories
	 */
	function flms_taxonomy_menu_highlight( $parent_file ) {
        global $current_screen;

        $taxonomy = $current_screen->taxonomy;
        if ( $taxonomy == 'flms-question-categories' ) {
            $parent_file = FLMS_PLUGIN_SLUG;
        }
		if ( in_array( $current_screen->base, array( 'post', 'edit' ) ) && ('flms-certificates' == $current_screen->post_type || 'flms-groups' == $current_screen->post_type ) ) {
			$parent_file = FLMS_PLUGIN_SLUG;
		}
		if ( $current_screen->base == 'admin_page_flms-exam-editor') {
			print_r($current_screen);
			$parent_file = FLMS_PLUGIN_SLUG;
			return $parent_file;
		}

		if(flms_is_module_active('course_taxonomies')) {
			$course_taxonomies = new FLMS_Module_Course_Taxonomies();
			$taxonomy_slugs = $course_taxonomies->get_taxonomy_slugs();
			if ( in_array($taxonomy, $taxonomy_slugs)) {
				$parent_file = FLMS_PLUGIN_SLUG;
			}
		}

        return $parent_file;
    }

	/**
	 * Load the admin page functionality in our submenu page
	 * */	
	public function flms_setup() {
		$settings = new FLMS_Settings();
		$settings->flms_settings_page();
	}

	/**
	 * Disable autosave for our posts
	 */
	public function enqueue_flms_admin_scripts() {
		global $post, $flms_settings;
		$current_post_type = get_post_type();
		$post_types = flms_get_plugin_post_types();
		foreach($post_types as $post_type) {
			if($current_post_type == $post_type['internal_permalink']) {
				//Disable autosave for our post types
				//autosave messes with our dynamic loading of versions and wp thinks there is an autosave that is different than the current version, which there is but we don't want to load it
				wp_dequeue_script( 'autosave' );
				//leave our loop
				break;
			}
		}
	}


	/** 
	 * Modify the courses, lessons and topics permalinks for frontend viewing
	 */
	public function flms_permalink_replacement($post_link, $post, $leavename, $sample) {
		global $flms_settings, $wp;
		if(is_object($post)) {
			//replace strings in post link
			if ($post->post_type == 'flms-courses') {
				$post_link = str_replace('%courses_permalink%',$flms_settings["custom_post_types"]["course_permalink"],$post_link);
				global $wp;
				$version = '';
				
				//if(is_admin()) {
					//$active_version_permalink = flms_get_course_active_version_data($post->ID, 'version_permalink');
					$course = new FLMS_Course($post->ID);
					if(is_admin()) {
						$active_version = $course->get_active_version();
					} else {
						$version_name = '';
						$active_version = 1;
						if(isset($wp->query_vars['course-version'])) {
							if($wp->query_vars['course-version'] != '') {
								$version_name = $wp->query_vars['course-version'];
								global $flms_course_version_content;
								foreach($flms_course_version_content as $k => $v) {
									if($v['version_name'] == $version_name) {
										$active_version = $k;
										break;
									}
								}
							}
						} else {
							global $flms_latest_version;
							$active_version = $flms_latest_version;
						}
					}
					global $flms_latest_version;
					$active_version_permalink = $course->get_course_version_slug($active_version, false);
					
					
					if($active_version == $flms_latest_version) {
						return $post_link;
					} else {
						return $post_link . $active_version_permalink;
					}
					//return flms_get_permalink($post->ID, $version);
					
				//} 
				return $post_link;
			} else if ($post->post_type == 'flms-lessons') {
				$post_link = str_replace('%lessons_permalink%',$flms_settings["custom_post_types"]["lesson_permalink"],$post_link);
				$course_id = get_post_meta($post->ID,'flms_course',true);
				if ($course_id != '' ) {
					$course = new FLMS_Course($course_id);
					$active_version = $course->get_active_version();
					$active_version_permalink = $course->get_course_version_slug($active_version, true);
					$course_name = $course->get_course_name();
					$post_link = str_replace('%courses_permalink%',$flms_settings["custom_post_types"]["course_permalink"],$post_link);
					$post_link = str_replace('%course%',$course_name,$post_link);
					$post_link = str_replace('%version_name%/',$active_version_permalink,$post_link);
				}  else {
					$post_link = str_replace('%courses_permalink%/%course%/%version_name%/','',$post_link);
				}
				return $post_link;
			} else if ($post->post_type == 'flms-topics') {
				$post_link = str_replace('%topics_permalink%',$flms_settings["custom_post_types"]["topic_permalink"],$post_link);
				$lesson_id = flms_get_topic_version_parent($post->ID);
				if($lesson_id != false) {
					$lesson = get_post($lesson_id);
					$post_link = str_replace('%lessons_permalink%',$flms_settings["custom_post_types"]["lesson_permalink"],$post_link);
					$post_link = str_replace('%lesson%',$lesson->post_name,$post_link);
					$course_id = get_post_meta($lesson_id,'flms_course',true);
					if ($course_id != '' ) {
						$course = new FLMS_Course($course_id);
						$active_version = $course->get_active_version();
						$active_version_permalink = $course->get_course_version_slug($active_version, true);
						$course_name = $course->get_course_name();
						$post_link = str_replace('%courses_permalink%',$flms_settings["custom_post_types"]["course_permalink"],$post_link);
						$post_link = str_replace('%course%',$course_name,$post_link);
						$post_link = str_replace('%version_name%/',$active_version_permalink,$post_link);
					}  
				}
				return $post_link;
			} else if ($post->post_type == 'flms-exams') {
				$post_link = str_replace('%exams_permalink%',$flms_settings["custom_post_types"]["exam_permalink"],$post_link);
				$parent_id = flms_get_exam_version_parent($post->ID);
				if($parent_id > 0) {
					
					$parent = get_post($parent_id);
					$parent_versions = get_post_meta($parent_id,'flms_version_content',true);
					$course_id = flms_get_course_id($parent_id);
					$course = new FLMS_Course($course_id);
					$active_version = $course->get_active_version();
					$active_version_permalink = $course->get_course_version_slug($active_version, true);
					$course_name = $course->get_course_name();
					$post_link = str_replace('%course%',$course_name,$post_link);
					$post_link = str_replace('%courses_permalink%',$flms_settings["custom_post_types"]["course_permalink"],$post_link);
					$post_link = str_replace('%version_name%/',$active_version_permalink,$post_link);
					if(!isset($parent_versions["{$active_version}"]["post_exams"])) {
						$post_link = str_replace('%exams_permalink%',$flms_settings["custom_post_types"]["exam_permalink"],$post_link);
						$post_link = str_replace(array('%courses_permalink%/','%course%/','/%version_name%','%lessons_permalink%/','%lesson%/','%topics_permalink%/','%topic%/'),'',$post_link);
						return $post_link;
					}
					if(is_array($parent_versions["{$active_version}"]["post_exams"])) {
						if(!in_array($post->ID,$parent_versions["$active_version"]['post_exams'])) {
							$post_link = str_replace('%exams_permalink%',$flms_settings["custom_post_types"]["exam_permalink"],$post_link);
							$post_link = str_replace(array('%courses_permalink%/','%course%/','/%version_name%','%lessons_permalink%/','%lesson%/','%topics_permalink%/','%topic%/'),'',$post_link);
							return $post_link;
						}
					}
					
					if($parent->post_type == 'flms-topics') {
						$post_link = str_replace('%topics_permalink%',$flms_settings["custom_post_types"]["topic_permalink"],$post_link);
						$post_link = str_replace('%topic%',$parent->post_name,$post_link);
						$lesson_id = flms_get_topic_version_parent($parent_id);
						if($lesson_id != '') {
							$lesson = get_post($lesson_id);
							$post_link = str_replace('%lessons_permalink%',$flms_settings["custom_post_types"]["lesson_permalink"],$post_link);
							$post_link = str_replace('%lesson%',$lesson->post_name,$post_link);
							
						}
					} else if($parent->post_type == 'flms-lessons') {
						$lesson_id = $parent_id;
						if($lesson_id != '') {
							$lesson = get_post($lesson_id);
							$post_link = str_replace('%lessons_permalink%',$flms_settings["custom_post_types"]["lesson_permalink"],$post_link);
							$post_link = str_replace('%lesson%',$lesson->post_name,$post_link);
							//remove old parts
							$post_link = str_replace(array('%topics_permalink%/','%topic%/'),'',$post_link);
						}
					} else if($parent->post_type == 'flms-courses') {
						//remove old parts
						$post_link = str_replace(array('%lessons_permalink%/','%lesson%/','%topics_permalink%/','%topic%/'),'',$post_link);
					}
				} else {
					$post_link = str_replace('%exams_permalink%',$flms_settings["custom_post_types"]["exam_permalink"],$post_link);
					$post_link = str_replace(array('%courses_permalink%/','%course%/','/%version_name%','%lessons_permalink%/','%lesson%/','%topics_permalink%/','%topic%/'),'',$post_link);
				}
				
			} else if ($post->post_type == 'flms-certificates') {
				//$post_link = str_replace('%certificates_permalink%',$flms_settings["custom_post_types"]["certificate_permalink"],$post_link);
			} else if ($post->post_type == 'flms-groups') {
				$post_link = str_replace('%group_permalink%',$flms_settings["custom_post_types"]["group_permalink"],$post_link);
			}
		}

		return $post_link;
	}

	/**
	 * Register course version query var for pulling appropriate course version
	 */
	public function add_version_query_var($query_vars) {
		global $flms_settings;
		//course version
		$query_vars[] = 'course-version';
		
		//print exam query vars
		$exam_permalink = $flms_settings["custom_post_types"]["exam_permalink"];
		$query_vars[] = "print-{$exam_permalink}";
		$query_vars[] = 'print-exam-id';
		$query_vars[] = 'print-exam-version';
		$query_vars[] = 'print-exam-user-id';
		
		if(flms_is_module_active('course_certificates')) {
			$course_certificates = new FLMS_Module_Course_Certificates();
			$query_vars = $course_certificates->register_query_vars($query_vars);
		}

		if(flms_is_module_active('groups')) {
			$groups = new FLMS_Module_Groups();
			$query_vars = $groups->register_query_vars($query_vars);
		}
		
		return $query_vars;
	}

	/**
	 * Get user color scheme and add it to color pallette
	 */
	public function admin_colors_head() {
		global $_wp_admin_css_colors; 
		global $current_screen;
		if(!$_wp_admin_css_colors) return;
		$color = get_user_meta(get_current_user_id(), 'admin_color', true);
		if(key_exists($color,$_wp_admin_css_colors)) {
			self::$admin_color_scheme = $_wp_admin_css_colors[$color];
		}
		if($current_screen->post_type == 'flms-certificates') {
			//remove_action('media_buttons', 'media_buttons');
		}
	}

	/**
	 * Print user color scheme as css
	 */
	public function admin_footer_colors() {
		if(!self::$admin_color_scheme) return;
		$vars = [''];
		foreach(self::$admin_color_scheme->colors as $key=>$col)
			$vars[] = "\t--wp-admin-color-$key: $col;";
		if(isset(self::$admin_color_scheme->icon_colors)) 
			foreach(self::$admin_color_scheme->icon_colors as $key=>$col)
				$vars[] = "\t--wp-admin-color-icon-$key: $col;";
		$vars[] = '';
		$styles = ":root {".join(PHP_EOL,$vars)."\t}";
		echo '<style id="wp-admin-cols">'.PHP_EOL.$styles.PHP_EOL.'</style>'.PHP_EOL;
	}

	public function module_notices() {
		if(flms_is_module_active('woocommerce') && !is_plugin_active( 'woocommerce/woocommerce.php' )) {
			$args = array(
				'type' => 'warning',
				'dismissible' => true,
			);
			wp_admin_notice(sprintf('The Woocommerce module for %s is activated but Woocommerce is not active. Please <a href="'.admin_url('plugins.php').'">activate Woocommerce</a> or <a href="%s">disable %s&rsquo;s Woocommerce integration</a>.',FLMS_PLUGIN_NAME, FLMS_SETTINGS_URL, FLMS_PLUGIN_NAME), $args);
			
		}
	}

	/** Notice for post type overview pages */
	function post_type_helper_notice(){
		global $flms_settings;
		$this->module_notices();

		$screen = get_current_screen();
		if( $screen->id !='edit-flms-lessons' && $screen->id !='edit-flms-topics' && $screen->id !='edit-flms-exams' ) {
			return;
		}
		$post_type = '';
		if( $screen->id =='edit-flms-lessons') {
			$post_type = 'lesson';
		} else if($screen->id == 'edit-flms-topics') {
			$post_type = 'topic';
		} else if ($screen->id =='edit-flms-exams') {
			$post_type = 'exam';
		}
		if(isset($flms_settings['labels']["{$post_type}_singular"])) {
			$post_type = strtolower($flms_settings['labels']["{$post_type}_singular"]);
		}
		$course_name = 'course';
		if(isset($flms_settings['labels']["course_singular"])) {
			$course_name = strtolower($flms_settings['labels']["course_singular"]);
		}
		if($post_type != '') {
			echo '<div class="updated no-background"><p>To create a new '.$post_type.', add it to your '.$course_name.' from the <a href="'.admin_url( 'edit.php?post_type=flms-courses').'">'.$course_name.' manager</a>.</p></div>';
		}
   	}
   
	/**
	 * Make custom column for lessons
	 */
	public function flms_lesson_custom_columns($columns) {
		unset( $columns['date'] );
		$columns['course'] = __( 'Course', 'flms' );
		$columns['date'] = __( 'Date', 'flms' );
		//$columns['publisher'] = __( 'Publisher', 'your_text_domain' );
		return $columns;
	}

	/**
	 * Custom column for topics
	 */
	public function flms_topic_custom_columns($columns) {
		unset( $columns['date'] );
		$columns['lesson'] = __( 'Lesson', 'flms' );
		$columns['course'] = __( 'Course', 'flms' );
		$columns['date'] = __( 'Date', 'flms' );
		//$columns['publisher'] = __( 'Publisher', 'your_text_domain' );
		return $columns;
	}

	/**
	 * Custom column for exams
	 */
	public function flms_exams_custom_columns($columns) {
		unset( $columns['date'] );
		$columns['exam_cpt'] = __( 'Association', 'flms' );
		//$columns['course'] = __( 'Course', 'flms' );
		$columns['date'] = __( 'Date', 'flms' );
		//$columns['publisher'] = __( 'Publisher', 'your_text_domain' );
		return $columns;
	}

	public function flms_questions_custom_columns($columns) {
		unset( $columns['date'] );
		$columns['question_type'] = __( 'Question Type', 'flms' );
		$columns['question_category'] = __( 'Category', 'flms' );
		//$columns['course'] = __( 'Course', 'flms' );
		$columns['date'] = __( 'Date', 'flms' );
		//$columns['publisher'] = __( 'Publisher', 'your_text_domain' );
		return $columns;
	}

	/**
	 * Manage custom columns
	 */
	public function flms_custom_column_data($column, $post_id) {
		$post = get_post($post_id);
		switch ( $column ) {
			case 'course' :
				$course_id = '';
				if($post->post_type == 'flms-topics') {
					$lesson_id = flms_get_topic_version_parent($post_id);
					$course_id = get_post_meta($lesson_id, 'flms_course',true);
				} else if($post->post_type == 'flms-lessons') {
					$course_id = get_post_meta($post_id, 'flms_course',true);
				} 
				if($course_id != '') {
					echo '<a href="'.get_edit_post_link($course_id).'" title="'.get_the_title($course_id).'">'.get_the_title($course_id).'</a>';
				} else {
					echo 'Not set';
				}
				break;
	
			case 'lesson' :
				$lesson_ids = array();
				if($post->post_type == 'flms-topics') {
					//delete_post_meta($post_id,'flms_topic_parent_ids'); //for testing
					$lesson_ids = get_post_meta($post_id,'flms_topic_parent_ids',true);
					//print_r($lesson_ids);
				} 
				if(!empty($lesson_ids)) {
					$lessonct = 0;
					foreach($lesson_ids as $lesson) {
						$lesson_data = explode(':',$lesson);
						$lesson_id = $lesson_data[0];
						$lesson_version = $lesson_data[1];
						if($lessonct > 0) {
							echo '<br>';
						}
						echo '<a href="'.get_edit_post_link($lesson_id).'&set-course-version='.$lesson_version.'" title="'.get_the_title($lesson_id).'">'.get_the_title($lesson_id).' Version '.$lesson_version.'</a>'; // ('.flms_get_post_type($lesson_id['post_id']).')
						$lessonct++;
					}
				} else {
					echo 'None';
				}
				break;
			case 'exam_cpt' :
				$lesson_ids = array();
				if($post->post_type == 'flms-exams') {
					$lesson_ids = get_post_meta($post_id,'flms_exam_parent_ids',true);
				} 
				if(!empty($lesson_ids)) {
					$lessonct = 0;
					foreach($lesson_ids as $lesson) {
						if($lessonct > 0) {
							echo '<br>';
						}
						if(!is_array($lesson)) {
							$lesson_data = explode(':',$lesson);
							$lesson_id = $lesson_data[0];
							$lesson_version = $lesson_data[1];
							echo '<a href="'.get_edit_post_link($lesson_id).'&set-course-version='.$lesson_version.'" title="'.get_the_title($lesson_id).'">'.get_the_title($lesson_id).' Version '.$lesson_version.'</a>'; // ('.flms_get_post_type($lesson_id['post_id']).')
						} else {
							echo 'Needs maintenance';
						}
						$lessonct++;
					}
				} else {
					echo 'None';
				}
				break;
			case 'question_type' :
				$type = '';
				if($post->post_type == 'flms-questions') {
					$question_type = get_post_meta($post_id,'flms_question_type',true);
					switch($question_type) {
						case 'single-choice':
							$type = 'Single choice';
							break;
						case 'multiple-choice':
							$type = 'Multiple choice';
							break;
						case 'free-choice':
							$type = 'Free choice';
							break;
						case 'fill-in-the-blank':
							$type = 'Fill in the blank';
							break;
						case 'assessment':
							$type = 'Assessment';
							break;
						case 'essay':
							$type = 'Essay';
							break;
						case 'prompt':
							$type = 'Prompt';
							break;
					}
				} 
				echo $type;
				break;
			case 'question_category' :
				$type = '';
				if($post->post_type == 'flms-questions') {
					$terms = get_the_terms($post_id, 'flms-question-categories');
					if(!empty($terms)) {
						$terms_list = array();
						foreach($terms as $term) {
							$terms_list[] = '<a href="'.admin_url('edit.php?flms-question-categories='.$term->slug).'&post_type=flms-questions">'.$term->name.'</a>';
							//$terms_list[] = $term->name;
						}
						$type = implode(', ', $terms_list);
					}
				} 
				echo $type;
				break;

		}
	}

	public function set_course_version_from_get() {
		if(isset($_GET['set-course-version'])) {
			$version = absint($_GET['set-course-version']);
			if(isset($_GET['post'])) {
				$post_id = absint($_GET['post']);
				//$find_post = get_post($post_id);
				$course_id = flms_get_course_id($post_id);
				$version_updated = update_post_meta($course_id,'flms_course_active_version',$version);
				$actual_link = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
				$new_url = str_replace("&set-course-version=$version",'',$actual_link);
				wp_redirect( $new_url );
				exit;
			}
		}
	}
	
	/** 
	 * Add versions dropdown to admin bar 
	 * */
	public function admin_bar_versions($admin_bar){
		global $post, $wp;
		if(!isset($post->ID) || is_admin()) {
			return $admin_bar;
		}
		if(!flms_is_flms_post_type($post)) {
			return $admin_bar;
		}
		$course_id = flms_get_course_id($post->ID);
		$versions = get_post_meta($course_id,'flms_version_content',true);
		ksort($versions);
		if(is_array($versions)) {
			$version = '';
			if(isset($wp->query_vars['course-version'])) {
				$version = $wp->query_vars['course-version'];
			}
			$course = new FLMS_Course($course_id);
			global $flms_active_version, $flms_latest_version;
			$viewing_latest_version = false;
			if($flms_active_version == $flms_latest_version) {
				$viewing_latest_version = true;
			}
			$admin_bar->add_menu( array(
				'id'    => 'flms_course-active-version',
				'title'  => $versions["$flms_active_version"]['version_name'],
				'href'  => get_permalink($course_id), //update
			));

			/*
			$course = new FLMS_Course($post->ID);
					$active_version = $course->get_active_version();
					$active_version_permalink = $course->get_course_version_slug($active_version, false);
					*/

			//reverse sort to put latest version first
			krsort($versions);
			foreach($versions as $k => $v) {
				if(isset($versions["{$k}"]['version_permalink'])) {
					$version_permalink = $versions["{$k}"]['version_permalink'];
					
					if($flms_latest_version ==  $k) {
						$new_permalink = get_permalink($course_id);
					} else {
						$new_permalink = get_permalink($course_id).$version_permalink;
					}
					
					if(isset($versions["{$k}"]['version_name'])) {
						$version_name = $versions["{$k}"]['version_name'];
					} else {
						$version_name = "Version $k";
					}
					$admin_bar->add_menu( array(
						'id'    => 'flms_course-active-version-'.$k,
						'parent' => 'flms_course-active-version',
						'title' => $version_name,
						'href'  => $new_permalink
					));
				} 
			}
		}
		return $admin_bar;
		
	}

	public function flms_user_profile_fields($user) {
		wp_enqueue_script('jquery-ui-core');
		wp_enqueue_script('jquery-ui-autocomplete');
		wp_enqueue_script('flms-admin-profile');
		wp_localize_script('flms-admin-profile','flms_admin_profile', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		));
		global $flms_settings;
		$course_label = ucwords(strtolower($flms_settings['labels']['course_singular'])); 
		$courses_label = ucwords(strtolower($flms_settings['labels']['course_plural']));
		$user_id = $user->ID; ?>
		<h3><?php echo "User $courses_label"; ?></h3>
		<div id="user-active-courses"></div>
		<table class="form-table">
		<tr>
		<?php if(current_user_can('edit_posts')) { ?>
			<tr>
			<th>Enroll in <?php echo $course_label; ?></th>
			<td>
				<?php 
				$courses = flms_get_course_select_box(); 
				?>
				<input type="text" value="" placeholder="Type to search courses" id="user-profile-course-search" class="regular-text" data-course="-1" data-version="-1" />
				<button class="profile-enroll-user button button-primary" id="profile-enroll-user" data-user="<?php echo $user_id; ?>">Enroll</button>
			</td>
			</tr>
		<?php } ?>
		<th>Active <?php echo $courses_label; ?></th>
		<td>
		<?php 
		$course_progress = new FLMS_Course_Progress();
		$active_courses = flms_get_user_active_courses($user_id);
		if(empty($active_courses)) {
			echo '<p>No active '.$course_label.'</p>';
		} else {
			flms_get_user_active_course_list($user_id, $active_courses, true);
		}
		?>
		</td>
		</tr>
		<tr>
		<th>Completed <?php echo $courses_label; ?></th>
		<td>
			<?php 
			$completed_courses = flms_get_user_completed_courses($user_id);
			if(!is_array($completed_courses)) {
				echo '<p>No completed '.$course_label.'</p>';
			} else if(empty($completed_courses)) {
				echo '<p>No completed '.$course_label.'</p>';
			} else {
				flms_get_user_completed_course_list($user_id, $completed_courses, true);
			}
			?>
		</td>
		</tr>
		</table>
		<?php 
	}

	public function flms_save_user_profile_fields($user_id) {
        
    }
	
}
new FLMS_Setup();
