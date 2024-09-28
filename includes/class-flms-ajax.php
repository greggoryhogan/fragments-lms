<?php
/**
 * Fragment LMS Ajax
 *
 * @package FLMS\Classes
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Ajax class
 */
class FLMS_Ajax {

	public $ajax_functions = array();

	/**
	 * The Constructor.
	 */
	public function __construct() {
		$this->ajax_functions = array(
			'set_currently_editing_version',
			'create_new_course_version',
			'search_lessons',
			'search_courses',
			'search_flms_users',
			'search_lessons_from_topic',
			'search_existing_content',
			'insert_flms_content',
			'get_lesson_list_html',
			'associate_content_search',
			'associate_content_save',
			'search_questions',
			'get_questions_page',
			'delete_course_version',
			'add_questions_to_bank',
			'add_question_categories_to_bank',
			'copy_versioned_content',
			'paginate_exam',
			'enroll_user_in_course',
			'search_flms_courses',
			'update_flms_woocommerce_checkout',
			'create_custom_credit_type',
			'create_custom_course_taxonomy',
			'create_custom_course_metadata',
			'complete_step',
			'grade_exam',
			'save_exam',
			'generate_report',
			'save_report',
			'export_report',
			'get_saved_report',
			'delete_report',
			'get_report_type_fields',
			'get_reporting_course_versions',
			'get_course_reporting_fields',
			'get_course_status_reporting_fields',
			'get_exam_versions',
			'flms_upload_file',
			'export_content',
			'import_content',
			'import_map_columns',
			'get_export_type_fields',
			'delete_export',
			'delete_all_exports',
			'get_course_completed_users',
			'reset_user_course_progress',
			'unenroll_user_in_course',
			'reset_completed_course',
			'complete_course',
			'insert_course_material',
			'get_reporting_course_credit_options',
			'get_reporting_royalty_by_taxonomy_options',
			'generate_group_code',
			'check_group_code',
			'get_course_product_variation_options',
			'create_group_frontend',
			'update_group_frontend',
			'assign_seats_frontend',
			'check_join_group_code',
			'delete_group',
			'leave_group',
			'user_group_enroll',
			'manager_invitation'
		);
		foreach($this->ajax_functions as $ajax_function) {
			add_action( "wp_ajax_nopriv_{$ajax_function}", array($this, "{$ajax_function}_callback" ) );
			add_action( "wp_ajax_{$ajax_function}", array($this, "{$ajax_function}_callback" ));
		}
	}

	/**
	 * Set current version to edit
	 */
	public function set_currently_editing_version_callback() {
		$version = sanitize_text_field($_POST['version']);
		$post_id = sanitize_text_field($_POST['post_id']);
		$version_updated = update_post_meta($post_id,'flms_course_active_version',$version);
		echo json_encode(
			array(
				'version_updated' => $version
			)
		);
		wp_die();
	}

	/**
	 * Create new course version
	 */
	public function create_new_course_version_callback() {
		$post_id = sanitize_text_field($_POST['post_id']);
		$version_name = sanitize_text_field($_POST['version_name']);
		$version_permalink = sanitize_text_field($_POST['version_permalink']);
		$copy_version = absint($_POST['copy_version']);
		$source_version = absint($_POST['source']);
		$count = absint($_POST['version-count']);
		

		if($copy_version == 1) {
			$course_manager = new FLMS_Course_Manager();
			$course_manager->copy_course_content($post_id, $source_version, $count);
		}
		
		$versions = get_post_meta($post_id,'flms_version_content',true);
		
		//no way this happens
		if(!is_array($versions)) {
			$versions = array();
		}

		if($version_name == '') {
			$version_name = 'Version '.$count .' (empty)';
		}
		if($version_permalink == '') {
			$version_permalink = 'version-'.$count;
		}
		if($copy_version != 1) {
			$versions["{$count}"]["course_lessons"]	= array();
		}

		//Update version name and permalink
		$versions["{$count}"]["version_name"] = "{$version_name}";
		$versions["{$count}"]["version_permalink"] = "{$version_permalink}";
		$versions["{$count}"]["version_status"] = 'draft';
		
		update_post_meta($post_id,'flms_version_content',$versions);

		$version_updated = update_post_meta($post_id,'flms_course_active_version',$count);

		echo json_encode(
			array(
				'version_updated' => $count
			)
		);
		wp_die();
	}

	/**
	 * Search for lessons or topics in wp admin
	 */
	public function search_lessons_callback() {
		$term = sanitize_text_field($_GET['term']);
		$course_id = sanitize_text_field($_GET['course_id']);
		$page = isset($_GET['page']) ? absint($_GET['page']) : 1;
		$exclude = isset( $_GET['exclude'] ) ? (array) $_GET['exclude'] : array();
		//sanitize the exclusion array
		$exclude = array_map( 'esc_attr', $exclude ); 
		
		$results = array();
		$posts_per_page = 10; // Adjust as needed
	
		$query = new WP_Query(array(
			'post_type' => 'flms-lessons',
			'posts_per_page' => $posts_per_page,
			's' => $term,
			'paged' => $page,
			'post__not_in' => $exclude,
			'meta_query' => array(
				'relation' => 'OR',
				array(
					'key' => 'flms_course',
					'value' => $course_id,
					'compare' => 'NOT EXISTS'
				),
				array(
					'key' => 'flms_course',
					'value' => $course_id,
					'compare' => '='
				),
			),
		));
	
		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				$results[] = array(
					'label' => get_the_title(),
					'value' => get_the_ID(),
				);
			}
		} else {
			$results[] = array(
				'label' => 'No unassociated lessons found.',
				'value' => 0,
			);
		}
	
		wp_reset_postdata();
	
		$total_pages = $query->max_num_pages;
	
		wp_send_json(array(
			'results' => $results,
			'total_pages' => $total_pages,
		));
	}


	/**
	 * Search for courses
	 */
	public function search_courses_callback() {
		$term = sanitize_text_field($_GET['term']);
		$page = isset($_GET['page']) ? absint($_GET['page']) : 1;
	
		$results = array();
		$posts_per_page = 10; // Adjust as needed
	
		$query = new WP_Query(array(
			'post_type' => 'flms-courses',
			'posts_per_page' => $posts_per_page,
			's' => $term,
			'paged' => $page,
		));
	
		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				$results[] = array(
					'label' => get_the_title(),
					'value' => get_the_ID(),
				);
			}
		} else {
			$results[] = array(
				'label' => 'No courses found',
				'value' => 0,
			);
		}
	
		wp_reset_postdata();
	
		$total_pages = $query->max_num_pages;
	
		wp_send_json(array(
			'results' => $results,
			'total_pages' => $total_pages,
		));
	}

	public function search_flms_users_callback() {
		global $current_user;
		$term = sanitize_text_field($_GET['term']);
		$page = isset($_GET['page']) ? absint($_GET['page']) : 1;
	
		$results = array();
		$posts_per_page = 10; // Adjust as needed
	
		$query = new WP_User_Query(array(
			'number' => $posts_per_page,
			'search' => '*'.esc_attr( $term ).'*',
			'paged' => $page,
		));
		
		if ( ! empty( $query->get_results() ) ) {
			foreach ( $query->get_results() as $user ) {
				if($current_user->ID == $user->ID) {
					$results[] = array(
						'label' => $user->display_name .' (You)',
						'value' => $user->ID,
					);
				} else {
					$results[] = array(
						'label' => $user->display_name .' ('.$user->user_email.')',
						'value' => $user->ID,
					);
				}
			}
		} else {
			$results[] = array(
				'label' => 'No users found',
				'value' => 0,
			);
		}

		
	
		wp_reset_postdata();
	
		$total_pages = $query->max_num_pages;
	
		wp_send_json(array(
			'results' => $results,
			'total_pages' => $total_pages,
		));
	}

	public function search_lessons_from_topic_callback() {
		$term = sanitize_text_field($_GET['term']);
		$course_id = sanitize_text_field($_GET['course_id']);
		$results = array();
		if($course_id == '') {
			$results[] = array(
				'label' => 'Please associate a course.',
				'value' => 0,
			);
			wp_send_json(array(
				'results' => $results,
				'total_pages' => 0,
			));
			wp_die();
		}
		$page = isset($_GET['page']) ? absint($_GET['page']) : 1;
	
		
		$posts_per_page = 10; // Adjust as needed
	
		$query = new WP_Query(array(
			'post_type' => 'flms-lessons',
			'posts_per_page' => $posts_per_page,
			's' => $term,
			'paged' => $page,
			'post__not_in' => $exclude,
			'meta_query' => array(
				array(
					'key' => 'flms_course',
					'value' => $course_id,
					'compare' => '='
				),
			),
		));
	
		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				$results[] = array(
					'label' => get_the_title(),
					'value' => get_the_ID(),
				);
			}
		} else {
			$results[] = array(
				'label' => 'No unassociated lessons found.',
				'value' => 0,
			);
		}
	
		wp_reset_postdata();
	
		$total_pages = $query->max_num_pages;
	
		wp_send_json(array(
			'results' => $results,
			'total_pages' => $total_pages,
		));
	}

	/**
	 * Search for lessons or topics in wp admin
	 */
	public function search_existing_content_callback() {
		$term = sanitize_text_field($_GET['term']);
		$content_id = sanitize_text_field($_GET['course_id']);
		//$course = get_post($content_id);
		$course_id = flms_get_course_id($content_id); 
		$page = isset($_GET['page']) ? absint($_GET['page']) : 1;
		$exclude = isset( $_GET['exclude'] ) ? (array) $_GET['exclude'] : array();
		$post_type = sanitize_text_field($_GET['post_type']);
		//sanitize the exclusion array
		$exclude = array_map( 'esc_attr', $exclude ); 
		//$exclude = array();
		if($post_type == 'flms-topics') {
			unset($exclude[$course_id]);
		}
		//$exclude = array();
		
		$results = array();
		$posts_per_page = 10; // Adjust as needed
	
		$args = array(
			'post_type' => $post_type,
			'posts_per_page' => $posts_per_page,
			's' => $term,
			'paged' => $page,
			'post__not_in' => $exclude,
		);
		if($post_type == 'flms-lessons') {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key' => 'flms_course',
					'value' => $course_id,
					'compare' => '='
				),
				array(
					'key' => 'flms_course',
					'compare' => 'NOT EXISTS'
				),
			);
		} else if($post_type == 'flms-topics') {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key' => 'flms_course',
					'value' => $course_id,
					'compare' => '='
				),
				array(
					'key' => 'flms_course',
					'compare' => 'NOT EXISTS'
				),
			);
		} else if($post_type == 'flms-exams') {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key' => 'flms_course',
					'value' => $course_id,
					'compare' => '='
				),
				array(
					'key' => 'flms_course',
					'compare' => 'NOT EXISTS'
				),
			);
		}

		/*if($post_type == 'flms-exams') {
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key' => 'flms_exam_parent_id',
					'value' => $course_id,
					'compare' => 'NOT EXISTS'
				),
				array(
					'key' => 'flms_exam_parent_id',
					'value' => $course_id,
					'compare' => '='
				),
			);
		}*/
		$query = new WP_Query($args);
		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				$results[] = array(
					'label' => get_the_title(),
					'value' => get_the_ID(),
				);
			}
		} else {
			$results[] = array(
				'label' => 'No unassociated content found.',
				'value' => 0,
			);
		}
	
		wp_reset_postdata();
	
		$total_pages = $query->max_num_pages;
	
		wp_send_json(array(
			'results' => $results,
			'total_pages' => $total_pages,
		));
	}

	public function insert_flms_content_callback() {
		$post_type = sanitize_text_field($_POST['post_type']);
		$title = sanitize_text_field($_POST['lesson_title']);
		if($title == '') {
			$title = 'Untitled';
		}
		$args = array(
			'post_title' => $title,
			'post_type' => $post_type,
			'post_status' => 'publish'
		);
		$post_id = wp_insert_post( $args, true );
		if(!is_wp_error($post_id)){
			//the post is valid
			$course_id = sanitize_text_field($_POST['course_id']);
			$course_manager = new FLMS_Course_Manager();
			$active_version = $course_manager->get_course_editing_version($course_id);
			if($post_type == 'flms-topics') {
				$course_manager->update_lesson_topics($course_id, $post_id, false, array(), $active_version);
				$lesson_response = $course_manager->get_lesson_list_html($post_id,$post_type,$course_id);
			} else if($post_type == 'flms-lessons') {
				$course_manager->update_course_lessons($course_id, $post_id, false, array(), $active_version);
				$lesson_response = $course_manager->get_lesson_list_html($post_id,$post_type);
			} else if($post_type == 'flms-exams') {
				$course_manager->update_exam_associations($course_id, $post_id, false, array(),$active_version);
				$lesson_response = $course_manager->get_lesson_list_html($post_id,$post_type,$course_id);
			}
			
			wp_send_json(array(
				'lesson_response' => $lesson_response
			));
		} else {
			//there was an error in the post insertion, 
			//echo $post_id->get_error_message();
			wp_send_json(array(
				'lesson_response' => $post_id->get_error_message()
			));
			wp_die();
		}
		
	}

	public function get_lesson_list_html_callback() {
		$post_id = sanitize_text_field($_POST['post_id']);
		$course_id = sanitize_text_field($_POST['course_id']);
		$post_type = sanitize_text_field($_POST['post_type']);
		$course_manager = new FLMS_Course_Manager();
		if($post_type == 'flms-lessons') {
			$html = $course_manager->get_lesson_list_html($post_id,$post_type, 0, true);
		} else {
			$html = $course_manager->get_lesson_list_html($post_id,$post_type,$course_id, true);
		}
		wp_send_json(array(
			'html' => $html
		));
		wp_die();
	}

	public function associate_content_search_callback() {
		$term = sanitize_text_field($_GET['term']);
		$post_id = sanitize_text_field($_GET['post_id']);
		$page = isset($_GET['page']) ? absint($_GET['page']) : 1;
		$post_type = $_GET['post_type'];
		$results = array();
		$posts_per_page = 10; // Adjust as needed
		
		if($post_type == 'any') {
			$post_type = array(
				'flms-courses',
				'flms-lessons',
				'flms-topics'
			);
		}
		$args = array(
			'post_type' => $post_type,
			'posts_per_page' => $posts_per_page,
			's' => $term,
			'paged' => $page,
		);
		$query = new WP_Query($args);
		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				$results[] = array(
					'label' => get_the_title(),
					'value' => get_the_ID(),
				);
			}
		} else {
			$results[] = array(
				'label' => 'Nothing found.',
				'value' => 0,
			);
		}
	
		wp_reset_postdata();
	
		$total_pages = $query->max_num_pages;
	
		wp_send_json(array(
			'results' => $results,
			'total_pages' => $total_pages,
		));
	}

	public function associate_content_save_callback() {
		$post_id = sanitize_text_field($_POST['post_id']);
		$associate_post_id = sanitize_text_field($_POST['associate_post_id']);
		$course_manager = new FLMS_Course_Manager();
		$post_type = get_post_type($post_id);
		if($post_type == 'flms-topics') {
			//associating  a topic to a lesson
			$course_id = get_post_meta($associate_post_id,'flms_course',true);
			$active_version = $course_manager->get_course_editing_version($course_id);
			$course_manager->update_lesson_topics($associate_post_id, $post_id, false, array(), $active_version);
		} else if($post_type == 'flms-lessons') {
			//associating a lesson to course
			$active_version = $course_manager->get_course_editing_version($associate_post_id);
			$course_manager->update_course_lessons($associate_post_id, $post_id, false , array(), $active_version);
		} else if($post_type == 'flms-exams') {
			//assocating an exam to anything
			$assocating_post_type = get_post_type($associate_post_id);
			if($post_type == 'flms-topics') {
				$lesson_id = flms_get_topic_version_parent($associate_post_id);
				$course_id = get_post_meta($lesson_id,'flms_course',true);
				$active_version = $course_manager->get_course_editing_version($course_id);
			} else if($post_type == 'flms-lessons') {
				$course_id = get_post_meta($associate_post_id,'flms_course',true);
				$active_version = $course_manager->get_course_editing_version($associate_post_id);
			} else {
				$active_version = $course_manager->get_course_editing_version($associate_post_id);
			}
			$course_manager->update_exam_associations($associate_post_id, $post_id, false, array(),$active_version);
		}
		wp_die();
	}
	
	public function search_questions_callback() {
		$searchterm = sanitize_text_field($_GET['searchterm']);
		$page = absint($_GET['page']);
		$args = array(
			'post_type' => 'flms-questions',
			'posts_per_page' => 10,
			's' => $searchterm,
			'fields' => 'id',
			'paged' => $page,
		);
		$question_query = new WP_Query( $args );
		$results = '';
		if($question_query->have_posts()) {
			while ($question_query->have_posts()) {
				$question_query->the_post();
				$post_id = get_the_ID();
				$results .= '<div class="exam-question"><label><input type="checkbox" value="'.$post_id.'">'.get_the_title($post_id).'</label></div>';
			}
		} else {
			$results .= '<p>Nothing found.</p>';
		}
		$total_pages = $question_query->max_num_pages;
		wp_send_json(array(
			'results' => $results,
			'total_pages' => $total_pages,
		));		
		wp_reset_postdata();
	}

	public function get_questions_page_callback() {
		$paged = absint($_GET['page']);
		if($_GET['current_questions'] != NULL) {
			$current_questions = array_map('absint', $_GET['current_questions'] );
		} else {
			$current_questions = array();
		}
		$question_bank = new FLMS_Questions();
		$question_bank_query_output = $question_bank->question_bank_query_output($current_questions, $paged);
		wp_send_json(array(
			'questions_html' => $question_bank_query_output
		));
		wp_die();
	}

	public function delete_course_version_callback() {
		$post_id = absint($_POST['post_id']);
		$version = absint($_POST['version']);
		$course_manager = new FLMS_Course_Manager();
		$course_manager->delete_course_version($post_id, $version);
		echo json_encode(
			array(
				'version_updated' => $version
			)
		);
		wp_die();
	}

	public function add_questions_to_bank_callback() {
		$questions = $_GET['questions'];
		$html = '';
		if(is_array($questions)) {
			foreach($questions as $question_id) {
				$question = new FLMS_Question($question_id);
				$html .= $question->get_editor_output();
			}
		}
		wp_send_json(array(
			'html' => $html
		));
		wp_die();
	}

	public function add_question_categories_to_bank_callback() {
		if(isset($_GET['current_questions'])) {
			$current_questions = array_map('absint',$_GET['current_questions']);
		} else {
			$current_questions = array();
		}
		$question_categories = array_map('absint',$_GET['question_categories']);
		$html = '';
		$question_output = new FLMS_Questions();
		$args = array(
			'post_type' => 'flms-questions',
			'tax_query' => array(
				array(
					'taxonomy' => 'flms-question-categories',
					'field' => 'ID',
					'terms' => $question_categories
				)
			)
		);
		$question_query = new WP_Query( $args );
		if($question_query->have_posts()) {
			while ($question_query->have_posts()) {
				$question_query->the_post();
				$question_id = get_the_ID();
				if(!in_array($question_id,$current_questions)) {
					$question = new FLMS_Question($question_id);
					$html .= $question->get_editor_output();
				}
			}
		}
		wp_reset_postdata();

		wp_send_json(array(
			'html' => $html
		));
		wp_die();
	}

	/** Copy content from one version to another */
	public function copy_versioned_content_callback() {
		$post_id = absint($_POST['post_id']);
		$version = absint($_POST['version']); //source version
		$active_version = absint($_POST['active_version']);
		$course_manager = new FLMS_Course_Manager();
		$course_manager->copy_course_content($post_id, $version, $active_version);
		echo json_encode(
			array(
				'version_updated' => $version
			)
		);
		wp_die();
	}

	public function paginate_exam_callback() {
		$user_id = absint($_GET['user_id']);
		$exam_id = absint($_GET['exam_id']);
		$review = absint($_GET['review']);
		$page = absint($_GET['page']);
		$reset_timer = absint($_GET['reset_timer']);
		$question_counter = absint($_GET['question_counter']);
		$version_index = absint($_GET['version_index']);
		$reset_exam_progress = absint($_GET['reset_exam_progress']);
		$versions = get_post_meta($exam_id,'flms_version_content',true);

		$exam_settings = get_post_meta($exam_id, "flms_exam_settings_$version_index", true);
		$limit = $exam_settings['questions_per_page'];
		$exam_is_graded = $exam_settings['exam_is_graded'];
		$start = $limit * ($page - 1);
		$nextpage = $page + 1;
		$questions = new FLMS_Questions();
		if(isset($_GET['answers'])) {
			$answers = $_GET['answers'];
		} else {
			$answers = array();
		}
		$exam_identifier = "$exam_id:$version_index";

		if($review == 0) {
			if($reset_exam_progress == 1) {
				//delete exam questions
				delete_user_meta($user_id, "flms_current_exam_questions_$exam_id");

				update_user_meta($user_id, "flms_{$exam_identifier}_exam_in_progress", 1);
				
				//iterate attempts
				$meta_key = "flms_{$exam_identifier}_exam_attempts";
				$attempts = get_user_meta($user_id, $meta_key, true);
				if($attempts == '') {
					$attempts = 0;
				}
				$previous_attempt = $attempts;
				$attempts += 1;
				update_user_meta($user_id, $meta_key, $attempts);

				//remove course content access if exam is closed book
				if(isset($exam_settings['course_content_access'])) {
					$access = $exam_settings['course_content_access'];
					if($access == 'closed') {
						$meta_key = "flms_content_restricted_by_exam";
						$course_id = flms_get_course_id($exam_id);
						$meta_value = json_encode(
							array(
								'course' => "$course_id",
								'exam' => "$exam_id",
								'version' => "$version_index"
							)
						);
						add_user_meta($user_id, $meta_key, $meta_value);
					}
				}
			}
			
			//save answers
			if(!empty($answers)) {
				$exam = new FLMS_Exam($exam_id);
				global $flms_active_version;
				$flms_active_version = $version_index;
				$exam->save_exam_progress($user_id,$answers);
			}

			//track start time of user exam
			//if($start_timer == 1) {
				flms_track_exam_time($user_id, $exam_identifier, $reset_timer);
			//}
			
		}
		$exam_questions = maybe_unserialize(get_user_meta($user_id, "flms_current_exam_questions_$exam_id", true)); 
		//$exam_questions = ''; //for debugging
		if($exam_questions == '') {
			$exam_questions = flms_get_exam_questions($exam_id, $version_index);
			update_user_meta($user_id, "flms_current_exam_questions_$exam_id", maybe_serialize($exam_questions));
		}

		//TODO: check if we're autofilling exam responses from a previous attempt


		$response = $questions->flms_output_exam_questions($exam_id, $exam_questions, $user_id, $exam_identifier, $limit, $start, $question_counter, $page, $review, $exam_is_graded, 'exam', $exam_settings);
		wp_send_json($response);
		wp_die();
	}

	public function save_exam_callback() {
		$user_id = absint($_GET['user_id']);
		$exam_id = absint($_GET['exam_id']);
		if(isset($_GET['answers'])) {
			$answers = $_GET['answers'];
		} else {
			$answers = array();
		}
		$version_index = absint($_GET['version_index']);
		$exam = new FLMS_Exam($exam_id);
		global $flms_active_version;
		$flms_active_version = $version_index;
		$exam->save_exam_progress($user_id,$answers);
		wp_die();
	}

	public function grade_exam_callback() {
		$user_id = absint($_GET['user_id']);
		$exam_id = absint($_GET['exam_id']);
		$exam_update = false;
		if(isset($_GET['exam_update'])) {
			$exam_update = absint($_GET['exam_update']);
		}
		if(isset($_GET['answers'])) {
			$answers = $_GET['answers'];
		} else {
			$answers = array();
		}
		$version_index = absint($_GET['version_index']);
		$exam = new FLMS_Exam($exam_id);
		global $flms_active_version;
		$flms_active_version = $version_index;
		$exam->save_exam_progress($user_id,$answers);
		$grade = $exam->grade_exam($user_id, $exam_update);
		$grade_feedback = '<p>You scored a '.$grade['score'].'% on this exam.</p>';
		$exam_redirect = $grade['redirect'];
		wp_send_json(
			array(
				'grade_feedback' => $grade_feedback,
				'exam_redirect' => $exam_redirect
			)
		);
		wp_die();
	}

	public function enroll_user_in_course_callback() {
		$user_id = absint($_POST['user_id']);
		$course_id = absint($_POST['course_id']);
		$active_version = absint($_POST['version']);
		$course_progress = new FLMS_Course_Progress();
		$response = $course_progress->enroll_user($user_id, $course_id, $active_version);
		wp_send_json($response);
		wp_die();
	}

	public function reset_user_course_progress_callback() {
		$user_id = absint($_POST['user_id']);
		$course_id = absint($_POST['course_id']);
		$version = absint($_POST['version']);
		$course_progress = new FLMS_Course_Progress();
		$response = $course_progress->reset_course_progress($user_id, $course_id, $version);
		wp_send_json($response);
	}
	
	public function unenroll_user_in_course_callback() {
		$user_id = absint($_POST['user_id']);
		$course_id = absint($_POST['course_id']);
		$version = absint($_POST['version']);
		$course_progress = new FLMS_Course_Progress();
		$response = $course_progress->unenroll_user($user_id, $course_id, $version);
		wp_send_json($response);
	}

	public function reset_completed_course_callback() {
		$user_id = absint($_POST['user_id']);
		$course_id = absint($_POST['course_id']);
		$version = absint($_POST['version']);
		$course_progress = new FLMS_Course_Progress();
		$response = $course_progress->reset_user_completed_course($user_id, $course_id, $version);
		wp_send_json($response);
	}

	public function complete_course_callback() {
		$user_id = absint($_POST['user_id']);
		$course_id = absint($_POST['course_id']);
		$version = absint($_POST['version']);
		$course_progress = new FLMS_Course_Progress();
		$response = $course_progress->complete_course($user_id, $course_id, $version);
		wp_send_json($response);
	}


	public function search_flms_courses_callback() {
		$term = sanitize_text_field($_GET['term']);
		$page = isset($_GET['page']) ? absint($_GET['page']) : 1;
		$results = array();
		$posts_per_page = 10; // Adjust as needed
	
		$args = array(
			'post_type' => 'flms-courses',
			'posts_per_page' => $posts_per_page,
			's' => $term,
			'paged' => $page,
		);
		$query = new WP_Query($args);
		if ($query->have_posts()) {
			while ($query->have_posts()) {
				$query->the_post();
				$post_id = get_the_ID();
				$course = new FLMS_Course($post_id);
				$course_versions = $course->get_versions();
				if(is_array($course_versions)) {
					foreach($course_versions as $k => $v) {
						if(is_numeric($k)) {
							$name = $course->get_course_version_name($k);
							$identifier = "$post_id:$k";
							$results[] = array(
								'label' => $name,
								'value' => $identifier,
								'course_id' => $post_id,
								'version' => $k
							);
						}
					}
				}
			}
		} else {
			$results[] = array(
				'label' => 'No courses found.',
				'value' => 0,
				'course_id' => -1,
				'version' => -1
			);
		}
	
		wp_reset_postdata();

		$total_pages = $query->max_num_pages;
		wp_send_json(array(
			'results' => $results,
			'total_pages' => $total_pages,
		));		
	}

	public function update_flms_woocommerce_checkout_callback() {
		$woocommerce_module = new FLMS_Module_Woocommerce();
		$credits = $woocommerce_module->flms_cart_credit_table();
		wp_send_json(array(
			'cart_credits' => $credits,
		));		
	}

	public function create_custom_credit_type_callback() {
		$name = sanitize_text_field($_POST['name']);
		$status = sanitize_text_field($_POST['status']);
		$license = sanitize_text_field($_POST['license']);
		$fee_type = sanitize_text_field($_POST['fee_type']);
		$fee = (int) $_POST['fee'];
		$description = sanitize_text_field($_POST['description']);
		$parent = sanitize_text_field($_POST['parent']);
		$course_credits = new FLMS_Module_Course_Credits();
		$form_fields = $course_credits->get_custom_credit_fields();
		$credit_name = preg_replace('/[^\w-]/', '', strtolower(trim($name)));
		$update_form_fields = $course_credits->replace_tmp_fields($credit_name, $form_fields, $name, $status, $license, $fee_type, $fee, $description, $parent);
		
		ob_start();
		$field_group = "course_credits";
		foreach($update_form_fields as $form_field) {
			flms_print_field_input($form_field, $field_group, $credit_name);
		}
		$form = ob_get_clean();
		
		$data = '<div class="settings-field '.$credit_name.' flms-field-group">';
			$data .= '<div class="setting-field-label"><h3>'.$name.'</h3></div>';
			$data .= '<div class="flms-field group">';
				$data .= '<div class="sortable-group">';
					$data .= '<div>';
						$data .= $form;
					$data .= '</div>';
					$data .= '<div class="handle ui-sortable-handle"></div>';
				$data .= '</div>';
			$data .= '</div>';
		$data .= '</div>';
		wp_send_json(array(
			'new_credit' => $data,
		));	
		//tmp-course-credit
	}

	public function create_custom_course_taxonomy_callback() {
		$singular_name = sanitize_text_field($_POST['singular_name']);
		$plural_name = sanitize_text_field($_POST['plural_name']);
		$slug = sanitize_title_with_dashes(sanitize_text_field($_POST['slug']));
		$hierarchal = sanitize_text_field($_POST['hierarchal']);
		$filter_status = sanitize_text_field($_POST['filter-status']);
		$status = sanitize_text_field($_POST['status']);
		
		$course_taxonomies = new FLMS_Module_Course_Taxonomies();
		$form_fields = $course_taxonomies->get_custom_taxonomy_fields();
		$update_form_fields = $course_taxonomies->replace_tmp_fields($form_fields, $singular_name, $plural_name, $slug, $hierarchal, $filter_status, $status);
		
		ob_start();
		$field_group = "course_taxonomies";
		foreach($update_form_fields as $form_field) {
			flms_print_field_input($form_field, $field_group, $slug);
		}
		$form = ob_get_clean();
		
		$data = '<div class="settings-field '.$slug.' flms-field-group">';
			$data .= '<div class="setting-field-label"><h3>'.$plural_name.'</h3></div>';
			$data .= '<div class="flms-field group">';
				$data .= '<div class="sortable-group">';
					$data .= '<div>';
						$data .= $form;
					$data .= '</div>';
					$data .= '<div class="handle ui-sortable-handle"></div>';
				$data .= '</div>';
			$data .= '</div>';
		$data .= '</div>';
		wp_send_json(array(
			'fields' => print_r($update_form_fields, true),
			'new_taxonomy' => $data,
		));	
	}

	public function create_custom_course_metadata_callback() {
		$name = sanitize_text_field($_POST['name']);
		$slug = sanitize_title_with_dashes(sanitize_text_field($_POST['slug']));
		if($slug == '') {
			$slug = sanitize_title_with_dashes(sanitize_text_field($_POST['name']));
		}
		$status = sanitize_text_field($_POST['status']);
		$description = sanitize_text_field($_POST['description']);
		
		$course_metadata = new FLMS_Module_Course_Metadata();
		$update_form_fields = $course_metadata->replace_tmp_fields($name, $slug, $description, $status);
		
		ob_start();
		$field_group = "course_metadata";
		foreach($update_form_fields as $form_field) {
			flms_print_field_input($form_field, $field_group, $slug);
		}
		$form = ob_get_clean();
		
		$data = '<div class="settings-field '.$slug.' flms-field-group">';
			$data .= '<div class="setting-field-label"><h3>'.$name.'</h3></div>';
			$data .= '<div class="flms-field group">';
				$data .= '<div class="sortable-group">';
					$data .= '<div>';
						$data .= $form;
					$data .= '</div>';
					$data .= '<div class="handle ui-sortable-handle"></div>';
				$data .= '</div>';
			$data .= '</div>';
		$data .= '</div>';
		wp_send_json(array(
			'fields' => print_r($update_form_fields, true),
			'new_taxonomy' => $data,
		));	
	}

	public function complete_step_callback() {
		global $current_user;
		$user_id = $current_user->ID;
		$post_id = absint($_POST['current_post']);
		$course_id = absint($_POST['course_id']);
		$version = absint($_POST['version']);
		$redirect = absint($_POST['redirect']);
		$course = new FLMS_Course($course_id);
		global $flms_active_version;
		$flms_active_version = $version;
		$activity = flms_update_user_activity($post_id, $user_id, $course_id, $version);

		$permalink = get_permalink($redirect);
		$course_permalink = get_permalink($course_id);
		$course_version_permalink = $course->get_course_version_permalink($version);
		$permalink = str_replace($course_permalink, $course_version_permalink, $permalink);
		
		wp_send_json(
			array(
				'user_id' => $user_id,
				'post_id' => $post_id,
				'course_id' => $course_id,
				'success' => $activity,
				'redirect' => $permalink,
				'course_permalink' => $course_permalink,
				'course_version_permalink' => $course_version_permalink
			)
		);
	}

	public function generate_report_callback() {
		$reports = new FLMS_Reports();
		$params = array();
    	parse_str($_POST['fields'], $params);	
		$report = $reports->generate_report($params);
		wp_send_json(
			array(
				'report_content' => $report['report_content'],
				'report_data' => $report['report_data']
			)
		);
		wp_die();
	}

	public function save_report_callback() {
		$report_data = $_POST['report_data'];
		$report_name = sanitize_text_field($_POST['report_name']);
		$active_report = (int) $_POST['active_report'];
		$reports = new FLMS_Reports();
		$reports->save_report($report_name, $report_data, $active_report);
		/*wp_send_json(
			array(
				'report_content' => 'This is report content',
				'report_data' => maybe_serialize( $report_data )
			)
		);*/
		wp_die();
	}

	public function export_report_callback() {
		$report_data = $_POST['report_data'];
		$reports = new FLMS_Reports();
		$file_info = $reports->export_report($report_data);
		if(is_array($file_info)) {
			wp_send_json(
				array(
					'success' => 1,
					'filepath' => $file_info['filepath'],
					'filename' => $file_info['filename'],
				)
			);
		} else {
			wp_send_json(
				array(
					'success' => 0
				)
			);
		}
		wp_die();
	}

	public function get_saved_report_callback() {
		$key = absint($_POST['key']);
		$reports = new FLMS_Reports();
		$report = $reports->get_saved_report($key);
		wp_send_json(
			array(
				'report_content' => $report['report_content'],
				'report_data' => $report['report_data'],
				'report_name' => stripslashes($report['report_name']),
				'report_information' => $report['report_information']
			)
		);
		wp_die();
	}

	public function delete_report_callback() {
		$active_report = (int) $_POST['active_report'];
		$reports = new FLMS_Reports();
		$reports->delete_report($active_report);
	}

	public function get_report_type_fields_callback() {
		global $flms_settings;
		$type = sanitize_text_field($_GET['type']);
		$report_fields = '';
		switch($type) {
			case 'course_progress':
				//get courses
				//get exams
				$args = array(
					'post_type'      => 'flms-courses',
					'posts_per_page' => -1,
					'orderby' => 'title',
					'order' => 'ASC'
				);
				$courses = get_posts($args);
				if(!empty($courses)) {
					$report_fields .= '<div>';
					$report_fields .= '<label>'.$flms_settings['labels']['course_singular'].'</label>';
					$report_fields .= '<select id="flms-course-select" name="flms-course-select">';
					$report_fields .= '<option value="0">Select '.$flms_settings['labels']['course_singular'].'</option>';
					foreach($courses as $course) {
						$report_fields .= '<option value="'.$course->ID.'">'.$course->post_title.'</option>';
					}
					$report_fields .= '</select>';
					$report_fields .= '</div>';
				}
				break;
			case 'answers':
				//get courses
				//get exams
				$args = array(
					'post_type'      => 'flms-courses',
					'posts_per_page' => -1,
					'orderby' => 'title',
					'order' => 'ASC'
				);
				$courses = get_posts($args);
				if(!empty($courses)) {
					$report_fields .= '<div>';
					$report_fields .= '<label>'.$flms_settings['labels']['course_singular'].'</label>';
					$report_fields .= '<select id="flms-course-select" name="flms-course-select">';
					$report_fields .= '<option value="0">Select '.$flms_settings['labels']['course_singular'].'</option>';
					foreach($courses as $course) {
						$report_fields .= '<option value="'.$course->ID.'">'.$course->post_title.'</option>';
					}
					$report_fields .= '</select>';
					$report_fields .= '</div>';
				}
				break;
			case 'course_credits':
				if(flms_is_module_active('course_credits')) {
					$course_credits_module = new FLMS_Module_Course_Credits();
					$fields = $course_credits_module->get_course_credits_fields(true, true);
					$report_fields .= '<div>';
					$report_fields .= '<label>Credit Type</label>';
					$report_fields .= '<select id="flms-course-credit-select" name="course-credit-select">';
					$report_fields .= '<option value="-1">Select credit type</option>';
					foreach($fields as $field) {
						$label = $field['label'];
						$label = flms_get_label($field['key']);
						$key = $field['key'];
						$report_fields .= '<option value="'.$key.'">'.$label.'</option>';
					}
					$report_fields .= '</select>';
					$report_fields .= '</div>';
				}
				break;
			case 'royalties':
				if(flms_is_module_active('woocommerce')) {
					$report_fields .= '<div>';
					$report_fields .= '<label>Taxonomy</label>';
					$report_fields .= '<select id="flms-taxonomy-select" name="taxonomy-select">';
					$report_fields .= '<option value="-1">Select taxonomy</option>';
					$fields = get_object_taxonomies( 'flms-courses', 'objects' );
					foreach ( $fields as $taxonomy_slug => $taxonomy ){
						$report_fields .= '<option value="'.$taxonomy_slug.'">'.$taxonomy->label.'</option>';
						/*$terms = get_terms( $taxonomy_slug, 'hide_empty=0' );
						if ( !empty( $terms ) ) {
							$out[] = "<strong>" . $taxonomy->label . "</strong>\n<ul>";
							foreach ( $terms as $term ) {
							$out[] =
								"  <li>"
							.    $term->name
							. "  </li>\n";
							}
							$out[] = "</ul>\n";

							$key = $field['key'];
							$report_fields .= '<option value="'.$taxonomy_slug.'">'.$taxonomy->label.'</option>';
						}*/

					}
					$report_fields .= '</select>';
					$report_fields .= '</div>';
				}
				break;
		}
		wp_send_json(
			array(
				'report_fields' => $report_fields
			)
		);
		wp_die();
	}

	public function get_reporting_course_versions_callback() {
		global $flms_settings;
		$course_id = absint($_GET['course']);
		$course = new FLMS_Course($course_id);
		$versions = $course->get_versions();
		$report_fields = '';
		if(!empty($versions)) {
			$report_fields .= '<div id="flms-report-course-version">';
			$report_fields .= '<label>'.ucwords($flms_settings['labels']['course_singular']).' version</label>';
			$report_fields .= '<select id="flms-version-select" name="flms-version-select">';
			$report_fields .= '<option value="-1">Select version</option>';
			foreach($versions as $index => $version) {
				if(is_numeric($index)) {
					$report_fields .= '<option value="'.$index.'">'.$version['version_name'].'</option>';
				}
			}
			$report_fields .= '</select>';
			$report_fields .= '</div>';
		}
		wp_send_json(
			array(
				'report_fields' => $report_fields
			)
		);
		wp_die();
	}

	public function get_course_reporting_fields_callback() {
		global $flms_settings, $flms_active_version;
		$primary_action = sanitize_text_field($_GET['primary_action']);
		$course_id = absint($_GET['course']);
		$version = (int) $_GET['version'];
		$report_fields = '';
		if($primary_action == 'answers') {
			$course = new FLMS_Course($course_id);
			$flms_active_version = $version; 
			$lessons = $course->get_lessons();
			$exams = array();
			foreach($lessons as $lesson_id) {
				$lesson = new FLMS_Lesson($lesson_id);
				$new_exams = $lesson->get_lesson_version_exams();
				$exams = array_merge($exams, $new_exams);
			}
			$course_exams = $course->get_course_version_exams();
			$exams = array_merge($exams, $course_exams);
			if(!empty($exams)) {
				$report_fields .= '<div id="flms-report-exam-select">';
				$report_fields .= '<label>'.$flms_settings['labels']['exam_singular'].'</label>';
				$report_fields .= '<select id="flms-exam-select" name="flms-exam-select">';
				$report_fields .= '<option value="0">Select '.$flms_settings['labels']['exam_singular'].'</option>';
				foreach($exams as $exam) {
					$report_fields .= '<option value="'.$exam.'">'.get_the_title($exam).'</option>';
				}
				$report_fields .= '</select>';
				$report_fields .= '</div>';
			}
		}
		if($primary_action == 'course_progress') {
			$report_fields .= '<div id="flms-course-progress-status">';
			$report_fields .= '<label>'.$flms_settings['labels']['course_singular'].' status</label>';
			$report_fields .= '<select id="flms-course-status-select" name="flms-course-status-select">';
				$report_fields .= '<option value="0">Select '.$flms_settings['labels']['course_singular'].' status</option>';
				//$report_fields .= '<option value="any">Any</option>';
				$report_fields .= '<option value="incomplete">Incomplete</option>';
				$report_fields .= '<option value="completed">Completed</option>';
			
			$report_fields .= '</select>';
			$report_fields .= '</div>';
		}
		wp_send_json(
			array(
				'report_fields' => $report_fields
			)
		);
		wp_die();
	}

	public function get_course_status_reporting_fields_callback() {
		global $flms_settings, $flms_active_version;
		$primary_action = sanitize_text_field($_GET['primary_action']);
		$course_id = absint($_GET['course']);
		$version = (int) $_GET['version'];
		$status = sanitize_text_field($_GET['status']);
		$report_fields = '';
		if($primary_action == 'course_progress') {
			$report_fields .= '<div id="start-date-container">';
			if($status != 'completed') {
				$report_fields .= '<label>Enrolled Start Date</label>';
			} else {
				$report_fields .= '<label>Completed Start Date</label>';
			}
			$start_date = date('Y-m-d', strtotime('-30 days'));
			$report_fields .= '<input type="date" name="date-start" id="flms-date-start" value="'.$start_date.'" class="full-width-input" />';
			$report_fields .= '</div>';
			$report_fields .= '<div id="end-date-container">';
			if($status != 'completed') {
				$report_fields .= '<label>Enrolled End Date</label>';
			} else {
				$report_fields .= '<label>Completed End Date</label>';
			}
			$end_date = date('Y-m-d');
			$report_fields .= '<input type="date" name="date-end" id="flms-date-end" value="'.$end_date.'" class="full-width-input" />';
			$report_fields .= '</div>';
		}
		wp_send_json(
			array(
				'report_fields' => $report_fields
			)
		);
		wp_die();
	}

	public function get_reporting_course_credit_options_callback() {
		$credit_type = sanitize_text_field($_GET['credit_type']);
		$report_fields = '';
		$report_fields .= '<div id="start-date-container">';
		$report_fields .= '<label>Start Date</label>';
		$start_date = date('Y-m-d', strtotime('-30 days'));
		$report_fields .= '<input type="date" name="date-start" id="flms-date-start" value="'.$start_date.'" class="full-width-input" />';
		$report_fields .= '</div>';
		$report_fields .= '<div id="end-date-container">';
		$report_fields .= '<label>End Date</label>';
		$end_date = date('Y-m-d');
		$report_fields .= '<input type="date" name="date-end" id="flms-date-end" value="'.$end_date.'" class="full-width-input" />';
		$report_fields .= '</div>';

		global $flms_settings;
		$reporting_fee_status = 'none';
		if(isset($flms_settings['course_credits'][$credit_type]['reporting-fee-status'])) {
			$reporting_fee_status = $flms_settings['course_credits'][$credit_type]['reporting-fee-status'];
			if($reporting_fee_status != 'none') {
				if(flms_is_module_active('woocommerce')) {
					$report_fields .= '<div id="reporting-fee-container">';
					$report_fields .= '<label>Accepted Reporting Fee</label>';
					$report_fields .= '<select id="flms-reporting-fee-select" name="reporting-fee-select" class="full-width-input">';
					//$report_fields .= '<option value="-1">Any</option>';
					$report_fields .= '<option value="1">Yes</option>';
					$report_fields .= '<option value="0">No</option>';
					$report_fields .= '</select>';
					$report_fields .= '</div>';
				}
			}
		}

		wp_send_json(
			array(
				'report_fields' => $report_fields
			)
		);
		wp_die();
	}

	public function get_reporting_royalty_by_taxonomy_options_callback() {
		$taxonomy_slug = sanitize_text_field($_GET['taxonomy_slug']);
		$report_fields = '';
		$terms = get_terms( $taxonomy_slug, 'hide_empty=0' );
		if ( !empty( $terms ) ) {
			$taxonomy = get_taxonomy( $taxonomy_slug );
			$report_fields .= '<div id="reporting-fee-container">';
			$report_fields .= '<label>'.$taxonomy->labels->singular_name.'</label>';
			$report_fields .= '<select id="flms-selected-taxonomy" name="selected-taxonomy" class="full-width-input">';
				$report_fields .= '<option value="-1">Select '.strtolower($taxonomy->labels->singular_name).'</option>';
			foreach ( $terms as $term ) {
				$report_fields .= '<option value="'.$term->term_id.'">'.$term->name.'</option>';
			}
			$report_fields .= '</select>';
			$report_fields .= '</div>';
		}

		
		$report_fields .= '<div id="start-date-container">';
		$report_fields .= '<label>Start Date</label>';
		$start_date = date('Y-m-d', strtotime('-30 days'));
		$report_fields .= '<input type="date" name="date-start" id="flms-date-start" value="'.$start_date.'" class="full-width-input" />';
		$report_fields .= '</div>';
		$report_fields .= '<div id="end-date-container">';
		$report_fields .= '<label>End Date</label>';
		$end_date = date('Y-m-d');
		$report_fields .= '<input type="date" name="date-end" id="flms-date-end" value="'.$end_date.'" class="full-width-input" />';
		$report_fields .= '</div>';

		wp_send_json(
			array(
				'report_fields' => $report_fields
			)
		);
		wp_die();
	}

	public function get_exam_versions_callback() {
		global $flms_settings;
		$exam_id = absint($_GET['exam']);
		$exam = new FLMS_Exam($exam_id);
		$versions = $exam->get_exam_versions();
		$report_fields = '';
		if(!empty($versions)) {
			$report_fields .= '<div id="flms-report-exam-version">';
			$report_fields .= '<label>'.$flms_settings['labels']['exam_singular'].' Version</label>';
			$report_fields .= '<select id="flms-exam-version" name="flms-exam-version">';
			$report_fields .= '<option value="-1">Select a version</option>';
			foreach($versions as $k => $v) {
				$report_fields .= '<option value="'.$k.'">'.$v.'</option>';
			}
			$report_fields .= '</select>';
			$report_fields .= '</div>';
		} else {
			$report_fields .= 'empty';
		}
		wp_send_json(
			array(
				'report_fields' => $report_fields
			)
		);
		wp_die();
	}

	public function export_content_callback() {
		$type = sanitize_text_field($_POST['type']);
		$items = $_POST['items'];
		$exporter = new FLMS_Exporter();
		$dir = $exporter->get_export_dir();
		$url = $exporter->get_export_dir_url();
		$timezone_str = wp_timezone_string();
        $currentDateTime = new DateTime('now', new DateTimeZone($timezone_str));
		$time = $currentDateTime->format('m-d-Y-Gis');
		$prefix = strtolower(str_replace(' ','',FLMS_PLUGIN_NAME)).'-';
		$separator = apply_filters('flms_csv_separator', "\t");
		switch ($type) {
			case 'plugin-settings':
				global $flms_settings;
				$filename = $prefix.'plugin-settings-'.$time.'.txt';
				$file = $dir . '/'. $filename;
				$open = fopen( $file, "w" ); 
				fwrite($open,json_encode(maybe_unserialize($flms_settings)));
				fclose($open);
				break;
			case 'courses':
				global $flms_settings;
				$selected_courses = array_map('absint', $items);
				$filename = $prefix.'courses-export-'.$time.'.csv';
				$file = $dir . '/'. $filename;
				$open = fopen( $file, "w" ); 
				$header_fields = implode($separator,flms_get_import_export_columns($type));
				$header = "$header_fields";
				$header .= "\n";
				fwrite($open,$header);
				$query = new WP_Query(array(
					'post_type' => 'flms-courses',
					'posts_per_page' => -1,
					'post__in' => $selected_courses,
					'post_status' => array('publish', 'pending', 'draft', 'future', 'private'),
				));
				$course_taxonomies = new FLMS_Module_Course_Taxonomies();
				$tax_fields = $course_taxonomies->get_course_taxonomies_fields(true);
				if($query->have_posts()) {
					while($query->have_posts()) {
						$query->the_post();
						$fields = '';
						$id = get_the_ID();
						$title = get_the_title();
						$status = get_post_status();
						$course_settings = maybe_unserialize(get_post_meta(get_the_ID(),'flms_version_content',true));
						if(is_array($course_settings)) {
							foreach($course_settings as $version => $version_settings) {
								if(is_numeric($version)) {
									$fields = array($id,$title,$status,$version);
									$fields_to_process = array(
										'version_name',
										'version_permalink',
										'version_status',
										'post_content',
										'course_preview',
										'course_settings',
										'course_lessons',
										'post_exams',
									);
									/* 'course_lessons',
										'lesson_topics',
										'post_exams', */
									if(flms_is_module_active('course_numbers')) {
										$fields_to_process[] = 'course_numbers';
									}
									if(flms_is_module_active('course_credits')) {
										$fields_to_process[] = 'course_credits';
										$course_credits = new FLMS_Module_Course_Credits();
										$credit_fields = $course_credits->get_course_credit_fields();
										global $flms_course_id, $flms_active_version;
										$flms_course_id = $id;
										$flms_active_version = $version;
									}
									if(flms_is_module_active('course_taxonomies')) {
										$fields_to_process[] = 'course_taxonomies';
										//flms_debug($fields_to_process,'fields');
									}
									if(flms_is_module_active('course_materials')) {
										$fields_to_process[] = 'course_materials';
										//flms_debug($fields_to_process,'fields');
									}
									if(flms_is_module_active('woocommerce')) {
										$fields_to_process[] = 'product_data';
									}
									foreach($fields_to_process as $field) {
										if(isset($version_settings[$field])) {
											if($field == 'course_settings') {
												foreach($version_settings[$field] as $course_setting) {
													$fields[] = "$course_setting";
												}
											} else if($field == 'course_numbers') {
												if(isset($version_settings['course_numbers']['global'])) {
													$fields[] = $version_settings['course_numbers']['global'];
												} else {
													$fields[] = "";
												}
											} else if($field == 'course_materials') {
												$materials = $version_settings[$field];
												$materials_output = array();
												if(!empty($materials)) {
													foreach($materials as $material) {
														$file_title = $material['title'];
														$file_status = $material['status'];
														$file_path = $material['file'];
														$materials_output[] = '{'.$file_path.'}{'.$file_status.'}{'.$file_title.'}';
													}
												} else {
													//Do something?
												}
												$fields[] = implode('|', $materials_output);
											} else if($field == 'course_credits') {
												foreach($credit_fields as $credit_field) {
													if(isset($version_settings["$field"]["$credit_field"])) {
														$fields[] = $version_settings["$field"]["$credit_field"];
													} else {
														$fields[] = "0";		
													}
													if(flms_is_module_active('course_numbers')) {
														if(isset($version_settings['course_numbers']["$credit_field"])) {
															$fields[] = $version_settings['course_numbers']["$credit_field"];
														} else {
															$fields[] = "";
														}
													}
												}
											} else if(is_array($version_settings[$field])) {
												$string = implode('|', $version_settings[$field]);
												$fields[] = "$string";
											} else {
												$fields[] = "$version_settings[$field]";
											}
											
										} else if ($field == 'version_status') {
											$fields[] = "draft";
										} else if($field == 'course_taxonomies') {
											foreach($tax_fields as $tax_field) {
												$slug = $tax_field['key'];
												$terms = get_the_terms($id, $slug);
												$default_tax_fields = "";
												if(!is_wp_error( $terms )) {
													if($terms != false) {
														if(!empty($terms)) {
															$set = array();
															foreach($terms as $term) {
																$set[] = $term->term_id;
															}
															$default_tax_fields = implode('|', $set);
														}
													}
												}
												$fields[] = $default_tax_fields;
											}
											
										} else if($field == 'product_data') {
											$product_data = get_post_meta($id,'flms_course_product_options', true);
											$product_type = '';
											$price = '';
											$attributes = '';
											if(is_array($product_data)) {
												if(!empty($product_data)) {
													if(isset($product_data['product_type'])) {
														$product_type = $product_data['product_type'];
													}
													if($product_type == 'simple') {
														if(isset($product_data['simple_prices'])) {
															$simple_prices = $product_data['simple_prices'];
															$price = implode('|',$simple_prices);
														}	
													}
													if($product_type == 'variable') {
														if(isset($product_data['variation_attributes'])) {
															$atts = $product_data['variation_attributes'];
															$atts_output = array();
															if(!empty($atts)) {
																foreach($atts as $taxonomy => $value) {
																	$atts_output[] = "$taxonomy:".implode('/',$value);
																}
															} 
															$attributes = implode('|', $atts_output);
														}
														if(isset($product_data['variation_prices'])) {
															$atts = $product_data['variation_prices'];
															$price_output = array();
															if(!empty($atts)) {
																foreach($atts as $variation_id => $value) {
																	$price_output[] = "$variation_id:".implode('/',$value);
																}
															} 
															$price = implode('|', $price_output);
														}
													}
												}
											}
											$fields[] = $product_type;
											$fields[] = $attributes;
											$fields[] = $price;
										} else {
											$fields[] = "";
										}
									}
									fputcsv($open,$fields,$separator);
								}
							}
							
						}
						
					}
				}
				
				fclose($open);
				break;
			case 'lessons':
				global $flms_settings;
				$selected_lessons = array_map('absint', $items);
				$filename = $prefix.'lessons-export-'.$time.'.csv';
				$file = $dir . '/'. $filename;
				$open = fopen( $file, "w" ); 
				$header_fields = implode($separator,flms_get_import_export_columns($type));
				$header = "$header_fields";
				$header .= "\n";
				fwrite($open,$header);
				$query = new WP_Query(array(
					'post_type' => 'flms-lessons',
					'posts_per_page' => -1,
					'post__in' => $selected_lessons,
					'post_status' => array('publish', 'pending', 'draft', 'future', 'private'),
				));
				if($query->have_posts()) {
					while($query->have_posts()) {
						$query->the_post();
						$fields = '';
						$id = get_the_ID();
						$lesson = new FLMS_Lesson($id);
						$title = get_the_title();
						global $flms_course_id;
						$course = new FLMS_Course($flms_course_id);
						$course_title = get_the_title($flms_course_id);
						global $flms_lesson_version_content;
						$lesson_settings = $flms_lesson_version_content;
						
						
						if(is_array($lesson_settings)) {
							
							foreach($lesson_settings as $version => $version_settings) {
								global $flms_active_version;
								$flms_active_version = $version;
								$lessons = $course->get_lessons();
								$lesson_order = array_search($id, $lessons);
								if($lesson_order === false) {
									$lesson_order = 0;
								}
								$sample_lesson = 0;
								if($lesson->lesson_is_sample()) {
									$sample_lesson = 1;	
								}
								$fields = array($id,$title,$course_title,$version,$lesson_order,$sample_lesson);
								$fields_to_process = array(
									'post_content',
									'status',
									'video_settings',
									'lesson_topics',
									'post_exams',
								);
								foreach($fields_to_process as $field) {
									if($field == 'status') {
										$fields[] = get_post_status();
									} else {
										if(isset($version_settings[$field])) {
											if($field == 'video_settings') {
												foreach($version_settings[$field] as $video_setting) {
													$fields[] = "$video_setting";
												}
											} else if(is_array($version_settings[$field])) {
												$string = implode('|', $version_settings[$field]);
												$fields[] = "$string";
											} else {
												$fields[] = "$version_settings[$field]";
											}
										} else {
											if($field == 'video_settings') {
												//need to fill a bunch of empty slots
												$video_settings = flms_get_video_settings_default_fields();
												foreach($video_settings as $setting) {
													$fields[] = "";	
												}
											} else {
												$fields[] = "";
											}
										}
									}
								}
								fputcsv($open,$fields,$separator);
							}
							
						}
						
					}
				}
				wp_reset_postdata();
				fclose($open);
				break;
			case 'topics':
				global $flms_settings;
				$selected_lessons = array_map('absint', $items);
				$filename = $prefix.'topics-export-'.$time.'.csv';
				$file = $dir . '/'. $filename;
				$open = fopen( $file, "w" ); 
				$header_fields = implode($separator,flms_get_import_export_columns($type));
				$header = "$header_fields";
				$header .= "\n";
				fwrite($open,$header);
				$query = new WP_Query(array(
					'post_type' => 'flms-topics',
					'posts_per_page' => -1,
					'post__in' => $selected_lessons,
					'post_status' => array('publish', 'pending', 'draft', 'future', 'private'),
				));
				if($query->have_posts()) {
					while($query->have_posts()) {
						$query->the_post();
						$fields = '';
						$id = get_the_ID();
						$topic = new FLMS_Topic($id);
						$lesson_ids = get_post_meta($id,'flms_topic_parent_ids',true);
					//print_r($lesson_ids);
						$topic_version_array = array();
						if(!empty($lesson_ids)) {
							foreach($lesson_ids as $lesson) {
								$lesson_data = explode(':',$lesson);
								$lesson_id = $lesson_data[0];
								$lesson_version = $lesson_data[1];
								$topic_version_array[$lesson_version] = $lesson_id;
							}
						}
						//$lesson = new FLMS_Lesson($id);
						$title = get_the_title();
						global $flms_course_id;
						//$course = new FLMS_Course($flms_course_id);
						$status = get_post_status();
						global $flms_topic_version_content;
						$lesson_settings = $flms_topic_version_content;
						$course_versions = get_post_meta($flms_course_id,'flms_version_content',true);
						
						if(is_array($lesson_settings)) {
							
							foreach($course_versions as $version => $version_settings) {
								global $flms_active_version;
								$flms_active_version = $version;
								$lesson_order = 0;
								$lesson_id = 0;
								$lesson_name = "";
								if(isset($topic_version_array[$version])) {
									$lesson_id = $topic_version_array[$version];
									$lesson_name = get_the_title($lesson_id);
									$lesson = new FLMS_Lesson($lesson_id);
									$flms_active_version = $version;
									$lesson_topics = $lesson->get_lesson_version_topics();
									$lesson_order = array_search($id, $lesson_topics);
									if($lesson_order === false) {
										$lesson_order = 0;
									}
								}
								$fields = array($id,$title,$lesson_name,$version,$lesson_order);
								$fields_to_process = array(
									'post_content',
								);
								foreach($fields_to_process as $field) {
									if(isset($lesson_settings["$version"][$field])) {
										if(is_array($lesson_settings["$version"][$field])) {
											$string = implode('|', $lesson_settings["$version"][$field]);
											$fields[] = "$string";
										} else {
											$fields[] = $lesson_settings["$version"][$field];
										}
									} else {
										$fields[] = "";
									}
								}
								$fields[] = get_post_status();
								fputcsv($open,$fields,$separator);
							}
							
						}
						
					}
				} 
				wp_reset_postdata();
				fclose($open);
				break;
			case 'exams':
				global $flms_settings;
				$selected_lessons = array_map('absint', $items);
				$filename = $prefix.'exams-export-'.$time.'.csv';
				$file = $dir . '/'. $filename;
				$open = fopen( $file, "w" ); 
				$header_fields = implode($separator,flms_get_import_export_columns($type));
				$header = "$header_fields";
				$header .= "\n";
				fwrite($open,$header);
				$query = new WP_Query(array(
					'post_type' => 'flms-exams',
					'posts_per_page' => -1,
					'post__in' => $selected_lessons,
					'post_status' => array('publish', 'pending', 'draft', 'future', 'private'),
				));
				
				if($query->have_posts()) {
					while($query->have_posts()) {
						$query->the_post();
						$fields = '';
						$id = get_the_ID();
						$exam = new FLMS_Exam($id);
						$associated_ids = get_post_meta($id,'flms_exam_parent_ids',true);
						$version_array = array();
						if(!empty($associated_ids)) {
							foreach($associated_ids as $associated_id) {
								$lesson_data = explode(':',$associated_id);
								$lesson_id = $lesson_data[0];
								if(is_numeric($lesson_id)) {
									if(isset($lesson_data[1])) {
										$lesson_version = $lesson_data[1];
										$version_array[$lesson_version] = $lesson_id;
									}
								}
							}
						}
						//$lesson = new FLMS_Lesson($id);
						$title = get_the_title();
						$status = get_post_status();
						global $flms_course_id;
						//$course = new FLMS_Course($flms_course_id);
						if($flms_course_id > 0) {
							$course_title = get_the_title($flms_course_id);
						} else {
							$course_title = '';
						}
						global $flms_exam_version_content;
						$lesson_settings = $flms_exam_version_content;
						$course_versions = get_post_meta($flms_course_id,'flms_version_content',true);
						
						if(is_array($lesson_settings)) {
							if($flms_course_id == 0) {
								$course_versions = array();
								$course_versions[1] = array();
							}
							foreach($course_versions as $version => $version_settings) {
								if(is_numeric($version)) {
									global $flms_active_version;
									$flms_active_version = $version;
									$lesson_order = 0;
									$lesson_id = 0;
									$lesson_name = "";
									$lesson_type = "";
									if(isset($version_array[$version])) {
										$lesson_id = $version_array[$version];
										$lesson_name = get_the_title($lesson_id);
										$lesson_type = get_post_type($lesson_id);
										$post_version_data = get_post_meta($lesson_id, 'flms_version_content',true);
										$lesson_order = 0;
										if(isset($post_version_data["$version"]['post_exams'])) {
											$lesson_order = array_search($id, $post_version_data["$version"]['post_exams']);
											if($lesson_order === false) {
												$lesson_order = 0;
											}
										}
									}
									//ID\tExam Name\tCourse Name\tAssociated Content\tVersion\tExam Order\tPost Content\Exam Questions";
									$fields = array($id,$title,$course_title,$lesson_name,$lesson_type,$version,$lesson_order);
									$fields_to_process = array(
										'post_content',
									);
									foreach($fields_to_process as $field) {
										if(isset($lesson_settings["$version"][$field])) {
											if(is_array($lesson_settings["$version"][$field])) {
												$string = implode('|', $lesson_settings["$version"][$field]);
												$fields[] = "$string";
											} else {
												$fields[] = $lesson_settings["$version"][$field];
											}
										} else {
											$fields[] = "";
										}
									}
									$fields[] = $status;
									$exam_settings = get_post_meta($id, "flms_exam_settings_$version", true);
									if(is_array($exam_settings)) {
										$fields_to_process = array(
											'exam_type',
											'question_select_type',
											'exam_questions',
											'exam_question_categories',
											'sample-draw-question-count',
											'cumulative_exam_questions',
											'question_order',
											'exam_attempts',
											'questions_per_page',
											'save_continue_enabled',
											'exam_review_enabled',
											'exam_is_graded',
											'exam_is_graded_using',
											'pass_percentage',
											'pass_points',
											'exam_attempt_action',
											'exam_label_override',
											'exam_start_label',
											'exam_resume_label',
										);
										foreach($fields_to_process as $field) {
											if(isset($exam_settings[$field])) {
												if($field == 'cumulative_exam_questions') {
													//array
													$strings = array();
													foreach($exam_settings[$field] as $exam_id => $question_count) {
														$strings[] = "$exam_id:$question_count" ;
													}
													$string = implode('|', $strings);
													$fields[] = "$string";
												} else if(is_array($exam_settings[$field])) {
													$string = implode('|', $exam_settings[$field]);
													$fields[] = "$string";
												} else {
													if($exam_settings[$field] == 'active') {
														$exam_settings[$field] = 1;
													} else if($exam_settings[$field] == 'inactive') {
														$exam_settings[$field] = 0;
													}
													$fields[] = $exam_settings[$field];
												}
											} else {
												$fields[] = "";
											}
										}
									}
									fputcsv($open,$fields,$separator);
								}
							}
							
						}
						
					}
				} 
				wp_reset_postdata();
				fclose($open);
				break;
			case 'questions':
				//Questions export
				global $flms_settings;
				$selected_question_categories = array_map('absint', $items);
				$filename = $prefix.'questions-export-'.$time.'.csv';
				$file = $dir . '/'. $filename;
				$open = fopen( $file, "w" ); 
				$header_fields = implode($separator,flms_get_import_export_columns('questions'));
				$header = "$header_fields";
				$header .= "\n";
				//fwrite($open,"\"sep=\t\"\n");
				fwrite($open,$header);

				foreach($selected_question_categories as $question_category) {
					//uncategorized
					if($question_category == 0) {
						$args = array(
							'post_type' => 'flms-questions',
							'post_status' => 'any',
							'posts_per_page' => -1,
							'orderby' => 'menu_order',
							'order' => 'asc',
							'tax_query' => array(
								array(
									'taxonomy' => 'flms-question-categories',
									'field'    => 'id',
									'operator' => 'NOT EXISTS',
								)
							)
						);
					} else {
						$args = array(
							'post_type' => 'flms-questions',
							'post_status' => 'any',
							'posts_per_page' => -1,
							'orderby' => 'menu_order',
							'order' => 'asc',
							'tax_query' => array(
								array(
									'taxonomy' => 'flms-question-categories',
									'field'    => 'term_id',
									'terms' => array($question_category)
								)
							)
						);
					}
				

					$query = new WP_Query($args);
					if($query->have_posts()) {
						while($query->have_posts()) {
							$query->the_post();
							$fields = '';
							$id = get_the_ID();
							$question = new FLMS_Question($id);
							$title = get_the_title();
							$status = get_post_status();
							$categories = get_the_terms($id,'flms-question-categories');
							if($categories !== false) {
								$question_cats = array();
								foreach($categories as $category) {
									$question_cats[] = $category->name;
								}
								$categories = implode('|', $question_cats);
							} else {
								$categories = "";
							}
							
							$question_type = get_post_meta($id,'flms_question_type', true );
							$content = get_the_content();
							$answers = $question->get_question_answer();
							if(is_array($answers)) {
								$answers = implode('|', $answers);
							}
							if($question_type == 'fill-in-the-blank') {
								$answers = '';
							}
							$options = $question->get_question_export_options();
							if(is_array($options)) {
								$options = implode('|', $options);
							}
							//$fields = "$id\t$title\t$categories\t$question_type\t$content\t$status\t$options\t$answers\n";
							$fields = array($id,$title,$categories,$question_type,$content,$status,$options,$answers);
							fputcsv($open,$fields,$separator);

							
						}
					} 
					wp_reset_postdata();
				}
				fclose($open);
				break;
			case 'user-data':
				//Questions export
				$filename = $prefix.'users-export-'.$time.'.csv';
				$file = $dir . '/'. $filename;
				$open = fopen( $file, "w" ); 
				$header_fields = implode($separator,flms_get_import_export_columns('user-data'));
				$header = "$header_fields";
				$header .= "\n";
				//fwrite($open,"\"sep=\t\"\n");
				fwrite($open,$header);
				$user_ids = array_map('absint', $items);
				$args = array(
					'number' => -1,
					'include' => $user_ids
				);
				$users = get_users($args);
				foreach($users as $user) {
					$fields = array();
					$fields[] = $user->ID;
					$fields[] = $user->user_login;
					$fields[] = $user->display_name;
					$fields[] = $user->first_name;
					$fields[] = $user->last_name;
					$fields[] = $user->user_email;
					$active_courses = flms_get_user_active_courses($user->ID);
					$active_course_list = array();
					foreach($active_courses as $active_course) {
						$course_id = $active_course['course_id'];
						$course_version = $active_course['course_version'];
						$active_course_list[] = "$course_id:$course_version";
					}
					$fields[] = implode('|',$active_course_list);
					$completed_courses = flms_get_user_completed_courses($user->ID);
					$completed_course_list = array();
					foreach($completed_courses as $completed_course) {
						$course_id = $completed_course['course_id'];
						$course_version = $completed_course['course_version'];
						$completed_course_list[] = "$course_id:$course_version";
					}
					$fields[] = implode('|',$completed_course_list);
					if(flms_is_module_active('course_credits')) {
						$course_credits = new FLMS_Module_Course_Credits();
						$credit_fields = $course_credits->get_course_credit_fields();
						foreach($credit_fields as $credit_field) {
							$has_value = get_user_meta( $user->ID, "flms_has-license-$credit_field", true);
							if($has_value != '') {
								$value = get_user_meta( $user->ID, "flms_license-$credit_field", true);
								$fields[] = "active:$value";
							} else {
								$fields[] = "";
							}
							
							
						}
					}
					if(flms_is_module_active('woocommerce')) {
						$billing_address_fields = WC()->countries->get_address_fields('','billing_');
						foreach($billing_address_fields as $index => $field) {
							$fields[] = get_user_meta($user->ID, $index, true);
						}
						$shipping_address_fields = WC()->countries->get_address_fields('','shipping_');
						foreach($shipping_address_fields as $index => $field) {
							$fields[] = get_user_meta($user->ID, $index, true);
						}
					}
					fputcsv($open,$fields,$separator);
				}
				fclose($open);
				break;
				
		}
		$export_list = $exporter->get_export_list();
		wp_send_json(
			array(
				'filepath' => $url . '/'. $filename,
				'filename' => $filename,
				'export_list' => $export_list,
			)
		);
	}

	public function import_content_callback() {
		$type = sanitize_text_field($_POST['type']);
		$import_action = sanitize_text_field($_POST['import_action']);
		$attachment_id = absint($_POST['file']);
		$user_id = absint($_POST['user_id']);
		$file_url = flms_local_attachment_url($attachment_id);
		$field_indexes = $_POST['field_indexes'];
		$files_to_unlink = $_POST['files_to_unlink'];
		$separator = apply_filters('flms_csv_separator', "\t");
		if($type != 'plugin-settings') {
			$deliminator = flms_detect_csv_elimiter($file_url);
			if($deliminator == false) {
				wp_send_json( array(
					'success' => 1,
					'errors' => 'File upload error. Could not find the field deliminator in the csv. Please check your import file.',
				));
			}
		}

		do_action('flms_before_import_content', $type, $import_action, $attachment_id, $field_indexes, $user_id, $files_to_unlink);
		switch ($type) {
			case 'plugin-settings':
				$flms_class = new FLMS_Settings();
				$import_settings = file_get_contents($file_url);
				if($import_settings != '') {
					update_option($flms_class->get_settings_name(), json_decode($import_settings,true));
					wp_send_json( array(
						'success' => 1,
						'errors' => '',
					));
				} else {
					wp_send_json( array(
						'success' => 0,
						'errors' => '',
					));
				}
				wp_die();
				break;
			case 'courses':
				//init cron event
				$file_url = flms_local_attachment_url($attachment_id);
				$data = array(
					'file' => $file_url,
					'import_action' => $import_action,
					'processed_rows' => 0,
					'field_indexes' => $field_indexes,
					'errors' => array(),
					'user_id' => $user_id,
				);
				wp_schedule_single_event( time(), 'flms_import_courses', $data );
				wp_send_json( array(
					'success' => 1
				));
				wp_die();
				break;
			case 'lessons':
				$errors = array();
				if (($handle = fopen($file_url, 'r')) !== false) {
					$rows = 0;
					$course_manager = new FLMS_Course_Manager();
					while (($data = fgetcsv($handle, 1000, $deliminator)) !== false) {
						if(++$rows > 1) { //skip headers
							
							if($field_indexes['Course Name'] != -2) {
								$course = $data[$field_indexes['Course Name']];
								if($course == '') {
									$version_error = $data[$field_indexes['Title']] .' version '. $data[$field_indexes['Version']];
									$errors[] = "Skipped $version_error. No course specified.";
								} else {
									$posts = get_posts(array('post_type' => 'flms-courses', 'title' => $course));
									if(!empty($posts)) {
										foreach($posts as $flms_post) {
											$continue = true;
											$version = $data[$field_indexes['Version']];
											$versioned_content = get_post_meta($flms_post->ID,'flms_version_content',true);
											if(!isset($versioned_content["$version"])) {
												$errors[] = "Version $version does not exists for $course.";
											} else {
												global $flms_active_version;
												$flms_active_version = $version;
												$lesson_id = $data[$field_indexes['ID']];
												$question_args = array();
												if($field_indexes['Title'] != -2) {
													$question_args['post_title'] = $data[$field_indexes['Title']];
												}
												if($field_indexes['Post Content'] != -2) {
													$question_args['post_content'] = $data[$field_indexes['Post Content']];
												}
												if($field_indexes['Status'] != -2) {
													$question_args['post_status'] = $data[$field_indexes['Status']];
												}
												//insert or update title, content
												if($import_action == 'insert') {
													if ( FALSE === get_post_status( $lesson_id ) ) {
														// The post does not exist, ok to insert
														$question_args['post_type'] = 'flms-lessons';
														$lesson_id = wp_insert_post( $question_args );
														if( is_wp_error( $lesson_id ) ) {
															$errors[] = 'Error inserting lesson "'.$title.'". Error: '.$lesson_id->get_error_message();
															$continue = false;
														} 
													} else {
														$errors[] = 'Skipped inserting lesson "'.$title.'". Lesson ID exists, remove ID to insert.';
														$continue = false;
													}
												} else {
													//update
													$question_args['ID'] = absint($lesson_id);
													$updated = wp_update_post( $question_args );
													if( is_wp_error( $updated ) ) {
														$errors[] = "Skipped importing lesson '.$lesson_id.'. Error: ".$updated->get_error_message();
														$continue = false;
													}
												}
												if($continue) {

													//correlate the lesson to the course
													$flms_active_version = $version;
													$course_manager->update_course_lessons($flms_post->ID, array($lesson_id), false, array(), $version);

													//sample
													if($field_indexes['Sample Lesson'] != -2) {
														$sample = $data[$field_indexes['Sample Lesson']];
														$versioned_content = get_post_meta($flms_post->ID,'flms_version_content',true);
														$lesson_meta = "$flms_post->ID:$version";
														$sample_lessons = array();
														if(isset($versioned_content[$version]['sample_lessons'])) {
															$sample_lessons = $versioned_content[$version]['sample_lessons'];
														}
														if($sample == 1) {
															$sample_lessons[] = $lesson_id;
														} else {
															unset($sample_lessons[$lesson_id]);
														}
														$flms_active_version = $version;
														$course_manager->update_course_sample_lessons($flms_post->ID, $sample_lessons, $version);
													}

													//$this->update_course_lessons($post_id, $selected_lessons, true, $remove, $active_version);
													if($field_indexes['Topics'] != -2) {
														$topics_inputs = explode('|',$data[$field_indexes['Topics']]);
														$topics = array();
														if(!empty($topics_inputs)) {
															foreach($topics_inputs as $topic) {
																if($topic != '') {
																	$topic_id = flms_get_cpt_id($topic, 'flms-topics');
																	if($topic_id !== false) {
																		$topics[] = $topic_id;
																	} else {
																		$errors[] = "$topic does not exist.";
																	}
																}
															}
														}
														$flms_active_version = $version;
														$course_manager->update_lesson_topics($lesson_id, $topics, true, array(), $version);
													}

													//update exams
													if($field_indexes['Exams'] != -2) {
														$exam_inputs = explode('|',$data[$field_indexes['Exams']]);
														$exams = array();
														if(!empty($exam_inputs)) {
															foreach($exam_inputs as $exam) {
																if($exam != '') {
																	$exam_id = flms_get_cpt_id($exam, 'flms-exams');
																	if($exam_id !== false) {
																		$exams[] = $exam_id;
																	} else {
																		$errors[] = "$exam does not exist.";
																	}
																}
															}
														}
														$flms_active_version = $version;
														$course_manager->update_exam_associations($lesson_id, $exams, true, $exams, $version);
													}

													$versioned_content = get_post_meta($lesson_id,'flms_version_content',true);
													if(!is_array($versioned_content)) {
														$versioned_content = array();
													}

													//lesson options
													if(isset($versioned_content["{$version}"]["video_settings"])) {
														$video_setting = $versioned_content["{$version}"]["video_settings"];
													} else {
														$video_settings = flms_get_video_settings_default_fields();
													}
													if($field_indexes['Video URL'] != -2) {
														$video_settings['video_url'] = wp_kses($data[$field_indexes['Video URL']],'flms-video');
													}
													if($field_indexes['Aspect Ratio'] != -2) {
														$video_settings['video_ratio'] = $data[$field_indexes['Aspect Ratio']];
													}
													if($field_indexes['Video Controls'] != -2) {
														$video_settings['controls'] = $data[$field_indexes['Video Controls']];
													}
													if($field_indexes['Full watch required'] != -2) {
														$video_settings['force_full_video'] = $data[$field_indexes['Full watch required']];
													}
													if($field_indexes['Autocomplete lesson'] != -2) {
														$video_settings['autocomplete'] = $data[$field_indexes['Autocomplete lesson']];
													}
													
													$versioned_content["{$version}"]["video_settings"] = $video_settings;
													
													//post content
													if($field_indexes['Post Content'] != -2) {
														$versioned_content["$version"]['post_content'] = $data[$field_indexes['Post Content']];
													}
													update_post_meta($lesson_id,'flms_version_content',$versioned_content);

												}
												
												
											}
											
										}
									} else {
										$errors[] = 'Could not update lesson "'.$data[$field_indexes['Title']].'", associated course "'.$course.'" not found.';
									}
								}
							} else {
								$errors[] = 'Skipped importing lesson, no course specified.';
							}
						}
					}
					fclose($handle);
					$error_message = '';
					if(!empty($errors)) {
						$error_message = '<p style="margin-top: 20px;"><strong>Import errors:</strong></p><div class="import-errors">'.implode('<br>',$errors).'</div>';
					} 
					if(is_array($files_to_unlink)) {
						foreach($files_to_unlink as $file) {
							wp_delete_attachment(absint($file), true);
						}
					}
					wp_send_json( array(
						'success' => 1,
						'errors' => $error_message,
					));
					wp_die();
				} else {
					if(is_array($files_to_unlink)) {
						foreach($files_to_unlink as $file) {
							wp_delete_attachment(absint($file), true);
						}
					}
					wp_send_json( array(
						'success' => 0,
						'errors' => $error_message,
					));
					wp_die();
				}
				break;
			case 'topics':
				$errors = array();
				if (($handle = fopen($file_url, 'r')) !== false) {
					$rows = 0;
					$course_manager = new FLMS_Course_Manager();
					while (($data = fgetcsv($handle, 1000, $deliminator)) !== false) {
						if(++$rows > 1) { //skip headers
							
							if($field_indexes['Lesson Name'] != -2) {
								$associated_content = $data[$field_indexes['Lesson Name']];
								if($associated_content == '') {
									$version_error = $data[$field_indexes['Title']] .' version '. $data[$field_indexes['Version']];
									$errors[] = "Skipped $version_error. No lesson specified.";
								} else {
									$posts = get_posts(array('post_type' => 'flms-lessons', 'title' => $associated_content));
									if(!empty($posts)) {
										foreach($posts as $flms_post) {
											$continue = true;
											$version = $data[$field_indexes['Version']];
											$versioned_content = get_post_meta($flms_post->ID,'flms_version_content',true);
											if(!isset($versioned_content["$version"])) {
												$errors[] = "Version $version does not exists for $associated_content.";
											} else {
												$topic_id = $data[$field_indexes['ID']];
												$question_args = array();
												if($field_indexes['Title'] != -2) {
													$question_args['post_title'] = $data[$field_indexes['Title']];
												}
												if($field_indexes['Post Content'] != -2) {
													$question_args['post_content'] = $data[$field_indexes['Post Content']];
												}
												if($field_indexes['Status'] != -2) {
													$question_args['post_status'] = $data[$field_indexes['Status']];
												}
												//insert or update title, content
												if($import_action == 'insert') {
													if ( FALSE === get_post_status( $topic_id ) ) {
														// The post does not exist, ok to insert
														$question_args['post_type'] = 'flms-topics';
														$topic_id = wp_insert_post( $question_args );
														if( is_wp_error( $topic_id ) ) {
															$errors[] = 'Error inserting topic "'.$title.'". Error: '.$topic_id->get_error_message();
															$continue = false;
														} 
													} else {
														$errors[] = 'Skipped inserting topic "'.$title.'". Topic ID exists, remove ID to insert.';
														$continue = false;
													}
												} else {
													//update
													
													$question_args['ID'] = absint($topic_id);
													$updated = wp_update_post( $question_args );
													if( is_wp_error( $updated ) ) {
														$errors[] = "Skipped importing topic '.$topic_id.'. Error: ".$updated->get_error_message();
														$continue = false;
													}
												}
												if($continue) {
													$course_manager->update_lesson_topics($flms_post->ID, array($topic_id), false, array(), $version);
												}
												
												if($field_indexes['Post Content'] != -2) {
													$exam_versioned_content = get_post_meta($topic_id,'flms_version_content',true);
													if(!is_array($exam_versioned_content)) {
														$exam_versioned_content = array();
													}
													$exam_versioned_content["$version"]['post_content'] = $data[$field_indexes['Post Content']];
													update_post_meta($topic_id,'flms_version_content', $exam_versioned_content);
												}
											}
											
										}
									} else {
										$errors[] = 'Could not update topic "'.$data[$field_indexes['Title']].'", associated content "'.$associated_content.'" not found.';
									}
								}
							} else {
								$errors[] = 'Skipped importing topic, no version specified.';
							}
						}
					}
				 
					fclose($handle);
					$error_message = '';
					if(!empty($errors)) {
						$error_message = '<p style="margin-top: 20px;"><strong>Import errors:</strong></p><div class="import-errors">'.implode('<br>',$errors).'</div>';
					} 
					if(is_array($files_to_unlink)) {
						foreach($files_to_unlink as $file) {
							wp_delete_attachment(absint($file), true);
						}
					}
					wp_send_json( array(
						'success' => 1,
						'errors' => $error_message,
					));
					wp_die();
				} else {
					if(is_array($files_to_unlink)) {
						foreach($files_to_unlink as $file) {
							wp_delete_attachment(absint($file), true);
						}
					}
					wp_send_json( array(
						'success' => 0,
						'errors' => $error_message,
					));
					wp_die();
				}
				break;
			case 'exams':
				//init cron event
				$file_url = flms_local_attachment_url($attachment_id);
				$data = array(
					'file' => $file_url,
					'import_action' => $import_action,
					'processed_rows' => 0,
					'field_indexes' => $field_indexes,
					'errors' => array(),
					'user_id' => $user_id,
				);
				wp_schedule_single_event( time(), 'flms_import_exams', $data );
				wp_send_json( array(
					'success' => 1
				));
				wp_die();
				break;
			case 'questions':
				//init cron event
				$file_url = flms_local_attachment_url($attachment_id);
				$data = array(
					'file' => $file_url,
					'import_action' => $import_action,
					'processed_rows' => 0,
					'field_indexes' => $field_indexes,
					'errors' => array(),
					'user_id' => $user_id,
				);
				wp_schedule_single_event( time(), 'flms_import_questions', $data );
				wp_send_json( array(
					'success' => 1
				));
				wp_die();
				break;
			case 'user-data':
				//init cron event
				$file_url = flms_local_attachment_url($attachment_id);
				$data = array(
					'file' => $file_url,
					'import_action' => $import_action,
					'processed_rows' => 0,
					'field_indexes' => $field_indexes,
					'errors' => array(),
					'user_id' => $user_id,
				);
				wp_schedule_single_event( time(), 'flms_import_user_data', $data );
				wp_send_json( array(
					'success' => 1
				));
				wp_die();
				break;

				//Questions export
				$filename = $prefix.'users-export-'.$time.'.csv';
				$file = $dir . '/'. $filename;
				$open = fopen( $file, "w" ); 
				$header_fields = implode($separator,flms_get_import_export_columns('user-data'));
				$header = "$header_fields";
				$header .= "\n";
				//fwrite($open,"\"sep=\t\"\n");
				fwrite($open,$header);
				$user_ids = array_map('absint', $items);
				$args = array(
					'number' => -1,
					'include' => $user_ids
				);
				$users = get_users($args);
				foreach($users as $user) {
					$fields = array();
					$fields[] = $user->ID;
					$fields[] = $user->user_login;
					$fields[] = $user->display_name;
					$fields[] = $user->first_name;
					$fields[] = $user->last_name;
					$fields[] = $user->user_email;
					$active_courses = flms_get_user_active_courses($user->ID);
					$active_course_list = array();
					foreach($active_courses as $active_course) {
						$course_id = $active_course['course_id'];
						$course_version = $active_course['course_version'];
						$active_course_list[] = "$course_id:$course_version";
					}
					$fields[] = implode('|',$active_course_list);
					$completed_courses = flms_get_user_completed_courses($user->ID);
					$completed_course_list = array();
					foreach($completed_courses as $completed_course) {
						$course_id = $completed_course['course_id'];
						$course_version = $completed_course['course_version'];
						$completed_course_list[] = "$course_id:$course_version";
					}
					$fields[] = implode('|',$completed_course_list);
					if(flms_is_module_active('course_credits')) {
						$course_credits = new FLMS_Module_Course_Credits();
						$credit_fields = $course_credits->get_course_credit_fields();
						foreach($credit_fields as $credit_field) {
							$value = get_user_meta( $user->ID, "flms_license-$credit_field", true);
							$fields[] = $value;
						}
					}
					if(flms_is_module_active('woocommerce')) {
						$billing_address_fields = WC()->countries->get_address_fields('','billing_');
						foreach($billing_address_fields as $index => $field) {
							$fields[] = get_user_meta($user->ID, $index, true);
						}
						$shipping_address_fields = WC()->countries->get_address_fields('','shipping_');
						foreach($shipping_address_fields as $index => $field) {
							$fields[] = get_user_meta($user->ID, $index, true);
						}
					}
					fputcsv($open,$fields,$separator);
				}
				fclose($open);
				break;
			default:
				wp_send_json( array(
					'success' => 1,
					'errors' => '',
				));
				wp_die();
				break;

		}
		do_action('flms_after_import_content', $type, $import_action, $attachment_id, $field_indexes, $user_id, $files_to_unlink);
	}

	public function import_map_columns_callback() {
		$type = sanitize_text_field($_POST['type']);
		$type_label = sanitize_text_field($_POST['label']);
		$action = sanitize_text_field($_POST['import_action']);
		$file_id = absint($_POST['file']);
		$header_fields = flms_get_import_export_columns($type);
		
		$csv_url = flms_local_attachment_url($file_id);
		$key_associations = array();

		$csv = array();
		$i = 0;
		if (($handle = fopen($csv_url, "r")) !== false) {
			$deliminator = flms_detect_csv_elimiter($csv_url);
			if($deliminator == false) {
				wp_send_json( array(
					'success' => 0,
					'message' => 'File upload error. Could not find the field deliminator in the csv. Please check your import file.',
				));
			}
			$columns = fgetcsv($handle, 1000, $deliminator);
			foreach($header_fields as $field) {
				$key = array_search($field, $columns);
				/*if($key !== false) {
					$key_associations["$field"] = $key;
				}*/
				$key_associations["$field"] = $key;
			}
			//$coumns_array = explode('\t', $columns);
			fclose($handle);
		}
		$html = '';
		foreach($key_associations as $k => $v) {
			$columns_select = '<select name="flms-map-'.sanitize_title_with_dashes($k).'" data-field="'.$k.'">';
			$columns_select .= '<option value="-1">Select a column to map</option>';
			if($k != 'ID') {
				$columns_select .= '<option value="-2">Do not import</option>';
			}
			foreach($columns as $index => $column) {
				$column_letter = flms_num2alpha($index);
				$columns_select .= '<option value="'.$index.'"';
				if($index == $v && is_numeric($v)) {
					$columns_select .= ' selected';
				}
				$columns_select .= '>'.$column_letter.': '.$column.'</option>';
			}
			$columns_select .= '</select>';
			$html .= '<div class="settings-field">';
			
			$type_label_length = strlen($type_label);
			if(substr($k, 0, $type_label_length) == $type_label) {
				$label = $k;
			} else {
				$label = $type_label.' '.$k;
			}
			$html .= '<div class="setting-field-label">'.$label.':</div><div class="flms-field select">'.$columns_select.'</div>';
			$html .= '</div>';
		}
		wp_send_json( array(
			'success' => 1,
			'column_mapping_options' => $html
		));
		
		wp_die();
	}

	public function flms_upload_file_callback() {
		$uploads_dir = wp_upload_dir();
		if ( isset( $_FILES['import_file'] ) ) {
			if ( $upload = wp_upload_bits( $_FILES['import_file']['name'], null, file_get_contents( $_FILES['import_file']['tmp_name'] ) ) ) {
				$filename = $_FILES['import_file']['name'];
				if (!$upload['error']) {
					$wp_filetype = wp_check_filetype($filename, null );
					$parent_post_id = 0;
					$attachment = array(
						'post_mime_type' => $wp_filetype['type'],
						'post_parent' => $parent_post_id,
						'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
						'post_content' => '',
						'post_status' => 'inherit'
					);
					$attachment_id = wp_insert_attachment( $attachment, $upload['file'], $parent_post_id );
					if (!is_wp_error($attachment_id)) {
						require_once(ABSPATH . "wp-admin" . '/includes/image.php');
						$attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
						wp_update_attachment_metadata( $attachment_id,  $attachment_data );
						wp_send_json( array(
							'success' => 1,
							'error' => $upload['error'],
							'file' => $attachment_id,
						)
						);
						wp_die();
					}
				}
			} 
		}
		wp_send_json( array(
			'success' => 0,
			'error' => 'An error occurred'
			)
		);
		wp_die();
	}

	public function get_export_type_fields_callback() {
		global $flms_settings;
		$type = sanitize_text_field($_GET['type']);
		$report_fields = '';
		switch($type) {
			case 'courses':
				//get courses
				//get exams
				$args = array(
					'post_type'      => 'flms-courses',
					'posts_per_page' => -1,
					'post_status' => array('publish', 'pending', 'draft', 'future', 'private'),
					'orderby' => 'title',
					'order' => 'ASC'
				);
				$courses = get_posts($args);
				if(!empty($courses)) {
					$report_fields .= '<div>';
					$report_fields .= '<label>'.$flms_settings['labels']['course_singular'].'(s)</label>';
					$report_fields .= '<a href="#" id="export-check-all">all</a>';
					$report_fields .= '<div class="bulk-select has-check-all">';
					foreach($courses as $course) {
						$report_fields .= '<label><input type="checkbox" value="'.$course->ID.'" />'.$course->post_title.'</label>';
					}
					$report_fields .= '</div>';
					$report_fields .= '</div>';
				}
				break;
			case 'lessons':
				//get courses
				//get exams
				$args = array(
					'post_type'      => 'flms-lessons',
					'posts_per_page' => -1,
					'post_status' => array('publish', 'pending', 'draft', 'future', 'private')
				);
				$lessons = get_posts($args);
				if(!empty($lessons)) {
					$report_fields .= '<div>';
					$report_fields .= '<label>'.$flms_settings['labels']['lesson_singular'].'(s)</label>';
					$report_fields .= '<a href="#" id="export-check-all">all</a>';
					$report_fields .= '<div class="bulk-select has-check-all">';
					$course_lessons = array();
					foreach($lessons as $lesson) {
						//$course_id = flms_get_course_id($lesson);
						$course_id = flms_get_course_id($lesson->ID);
						$course_lessons[$course_id][] = $lesson->ID;
						//$report_fields .= '<label><input type="checkbox" value="'.$lesson->ID.'" />'.$lesson->post_title.' ('.get_the_title($flms_course_id).')</label>';
					}
					foreach($course_lessons as $k => $v) {
						$report_fields .= '<label><input type="checkbox" value="'.$k.'" />'.get_the_title($k);
						foreach($v as $lesson_id) {
							$report_fields .= '<label><input type="checkbox" value="'.$lesson_id.'" />'.get_the_title($lesson_id).'</label>';
						}
						$report_fields .= '</label>';
					}
					$report_fields .= '</div>';
					$report_fields .= '</div>';
				}
				break;
			case 'topics':
				//get courses
				//get exams
				$args = array(
					'post_type'      => 'flms-topics',
					'posts_per_page' => -1,
					'post_status' => array('publish', 'pending', 'draft', 'future', 'private')
				);
				$lessons = get_posts($args);
				if(!empty($lessons)) {
					$report_fields .= '<div>';
					$report_fields .= '<label>'.$flms_settings['labels']['topic_singular'].'(s)</label>';
					$report_fields .= '<a href="#" id="export-check-all">all</a>';
					$report_fields .= '<div class="bulk-select has-check-all">';
					$course_lessons = array();
					foreach($lessons as $lesson) {
						//$course_id = flms_get_course_id($lesson);
						$course_id = flms_get_course_id($lesson->ID);
						$course_lessons[$course_id][] = $lesson->ID;
						//$report_fields .= '<label><input type="checkbox" value="'.$lesson->ID.'" />'.$lesson->post_title.' ('.get_the_title($flms_course_id).')</label>';
					}
					foreach($course_lessons as $k => $v) {
						if($k > 0) {
							$report_fields .= '<label><input type="checkbox" value="'.$k.'" />'.get_the_title($k);
							foreach($v as $lesson_id) {
								$report_fields .= '<label><input type="checkbox" value="'.$lesson_id.'" />'.get_the_title($lesson_id).'</label>';
							}
							$report_fields .= '</label>';	
						} else {
							foreach($v as $lesson_id) {
								$report_fields .= '<label><input type="checkbox" value="'.$lesson_id.'" />'.get_the_title($lesson_id).'</label>';
							}
						}
						
					}
					$report_fields .= '</div>';
					$report_fields .= '</div>';
				}
				break;
			case 'exams':
				//get courses
				//get exams
				$args = array(
					'post_type'      => 'flms-exams',
					'posts_per_page' => -1,
					'post_status' => array('publish', 'pending', 'draft', 'future', 'private')
				);
				$lessons = get_posts($args);
				if(!empty($lessons)) {
					$report_fields .= '<div>';
					$report_fields .= '<label>'.$flms_settings['labels']['exam_singular'].'(s)</label>';
					$report_fields .= '<a href="#" id="export-check-all">all</a>';
					$report_fields .= '<div class="bulk-select has-check-all">';
					$course_lessons = array();
					foreach($lessons as $lesson) {
						//$course_id = flms_get_course_id($lesson);
						$course_id = flms_get_course_id($lesson->ID);
						$course_lessons[$course_id][] = $lesson->ID;
						//$report_fields .= '<label><input type="checkbox" value="'.$lesson->ID.'" />'.$lesson->post_title.' ('.get_the_title($flms_course_id).')</label>';
					}
					foreach($course_lessons as $k => $v) {
						if($k > 0) {
							$report_fields .= '<label><input type="checkbox" value="'.$k.'" />'.get_the_title($k);
							foreach($v as $lesson_id) {
								$report_fields .= '<label><input type="checkbox" value="'.$lesson_id.'" />'.get_the_title($lesson_id).'</label>';
							}
							$report_fields .= '</label>';	
						} else {
							foreach($v as $lesson_id) {
								$report_fields .= '<label><input type="checkbox" value="'.$lesson_id.'" />'.get_the_title($lesson_id).'</label>';
							}
						}
						
					}
					$report_fields .= '</div>';
					$report_fields .= '</div>';
				}
				break;
			case 'questions':
				//export by question category
				$categories = get_terms('flms-question-categories');
				if(!is_wp_error($categories)) {
					$report_fields .= '<div>';
						$report_fields .= '<label>Question Categories</label>';
						//$report_fields .= '<input type="submit" class="button button-primary export_content" value="Export" />';
						$report_fields .= '<a href="#" id="export-check-all">all</a>';
						$report_fields .= '<div class="bulk-select has-check-all">';
							$args = array(
								'post_type' => 'flms-questions',
								'post_status' => 'any',
								'tax_query' => array(
									array(
										'taxonomy' => 'flms-question-categories',
										'field'    => 'id',
										'operator' => 'NOT EXISTS',
									)
								)
							);
							$tax_query = new WP_Query( $args );
							if($tax_query->have_posts()) {
								$count = $tax_query->found_posts;
								$report_fields .= '<label><input type="checkbox" value="0" />Uncategorized ('.$count.')</label>';
							}
							foreach($categories as $category) {
								$report_fields .= '<label><input type="checkbox" value="'.$category->term_id.'" />'.$category->name .'('.$category->count.')</label>';
							}
						$report_fields .= '</div>';
					$report_fields .= '</div>';
				} 
				break;
			case 'user-data':
				//export by question category
				$args = array(
					'number' => -1,
				);
				$users = get_users($args);
				if(!empty($users)) {
					$report_fields .= '<div>';
						$report_fields .= '<label>Users</label>';
						//$report_fields .= '<input type="submit" class="button button-primary export_content" value="Export" />';
						$report_fields .= '<a href="#" id="export-check-all">all</a>';
						$report_fields .= '<div class="bulk-select has-check-all">';
							foreach($users as $user) {
								$report_fields .= '<label><input type="checkbox" value="'.$user->ID.'" />'.$user->display_name .'('.$user->user_email.')</label>';
							}
						$report_fields .= '</div>';
					$report_fields .= '</div>';
				} 
				break;
		}
		wp_send_json(
			array(
				'report_fields' => $report_fields
			)
		);
		wp_die();
	}

	public function delete_export_callback() {
		$filename = sanitize_text_field($_POST['path']);
		$exporter = new FLMS_Exporter();
		$dir = $exporter->get_export_dir();
		$file = $dir . '/'. $filename;
		$deleted = unlink($file);
		wp_send_json(
			array(
				'deleted' => $deleted
			)
		);
	}

	public function delete_all_exports_callback() {
		$exporter = new FLMS_Exporter();
		$export_dir = $exporter->get_export_dir();
        $export_files = array_diff(scandir($export_dir), array('..', '.', '.DS_Store'));
        if(!empty($export_files)) {
            foreach($export_files as $file) {
				$file_path = $export_dir . '/'. $file;
				$deleted = unlink($file_path);
			}
		}
		wp_send_json(
			array(
				'success' => 1
			)
		);
	}

	public function get_course_completed_users_callback() {
		$course_identifier = $_GET['course_identifier'];
		$course_data = explode(':',$course_identifier);
		$users = get_post_meta($course_data[0], "user_completed_course_version_$course_data[1]");
		$taken_users = array();

		global $wpdb;
		$table = FLMS_ACTIVITY_TABLE;
		$sql_query = $wpdb->prepare("SELECT customer_id FROM $table WHERE course_id=%d AND course_version=%d ORDER BY id DESC", $course_data[0], $course_data[1]);
		$results = $wpdb->get_results( $sql_query ); 
		if(!empty($results)) {
			$user_list = '<label>User</label>';
			$user_list .= '<p class="description">User details will be used to fill the information in the certificate</p>';
			$user_list .= '<select id="course-completed-users" name="course-completed-users" class="flms-full-width">';
			foreach($results as $result) {
				if(!in_array($result->customer_id, $taken_users)) {
					$taken_users[] = $result->customer_id;
					$user = get_user_by('id', $result->customer_id);
					$user_list .= '<option value="'.$user->ID.'">'.$user->display_name.'</option>';
				}
			}
			$user_list .= '</select>';
			$user_list .= '<div class="flms-spacer"></div>';
			wp_send_json(
				array(
					'success' => 1,
					'users' => $user_list,
					'course' => $course_data[0],
					'course_version' => $course_data[1]
				)
			);
		} else {
			wp_send_json(
				array(
					'users' => $users,
					'success' => 0,
					'message' => 'Preview unavailable. No users have completed this course.'
				)
			);
		}

		if(!empty($users)) {
			$user_list = '<label>User</label>';
			$user_list .= '<p class="description">User details will be used to fill the information in the certificate</p>';
			$user_list .= '<select id="course-completed-users" name="course-completed-users" class="flms-full-width">';
			foreach($users as $user_id) {
				if(!in_array($user_id, $taken_users)) {
					$taken_users[] = $user_id;
					$user = get_user_by('id', $user_id);
					$user_list .= '<option value="'.$user->ID.'">'.$user->display_name.'</option>';
				}
			}
			$user_list .= '</select>';
			$user_list .= '<div class="flms-spacer"></div>';
			wp_send_json(
				array(
					'success' => 1,
					'users' => $user_list,
					'course' => $course_data[0],
					'course_version' => $course_data[1]
				)
			);
		} else {
			wp_send_json(
				array(
					'users' => $users,
					'success' => 0,
					'message' => 'Preview unavailable. No users have completed this course.'
				)
			);
		}
		
	}

	public function insert_course_material_callback() {
		$params = array(
			'index' => absint($_POST['count']),
			'title' => sanitize_text_field($_POST['title']),
			'status' => sanitize_text_field($_POST['status']),
			'file' => sanitize_url($_POST['file']),
		);
		$course_materials = new FLMS_Module_Course_Materials();
		$listing = $course_materials->get_course_material_form($params);
		wp_send_json(
			array(
				'listing' => $listing,
			)
		);
	}

	public function create_group_frontend_callback() {
		$name = sanitize_text_field($_GET['name']);
		$group_code = sanitize_text_field($_GET['code']);
		$user_id = absint($_GET['user_id']);

		$args = array(
			'post_title' => $name,
			'post_type' => 'flms-groups',
			'post_status' => 'publish'
		);
		$post_id = wp_insert_post( $args, true );
		if(!is_wp_error($post_id)) {
			$group = get_post($post_id);
			update_post_meta($post_id, 'flms_group_owner', $user_id);
			if(!flms_is_group_code_valid($post_id, $group_code)) {
				$group_code = flms_generate_group_code($post_id);
			}
			update_post_meta($post_id, 'flms_group_code', $group_code);
			add_post_meta($post_id, 'flms_group_member', $user_id);
			$groups = new FLMS_Module_Groups();
			$user = get_user_by('ID', $user_id);
            $my_groups = $groups->get_user_groups($user);
			wp_send_json(
				array(
					'success' => 1,
					'new_group' => $my_groups
				)
			);
		} else {
			wp_send_json(
				array(
					'success' => 0
				)
			);
		}
	}

	public function update_group_frontend_callback() {
		$name = sanitize_text_field($_GET['name']);
		$group_code = sanitize_text_field($_GET['code']);
		$owner_email = sanitize_email($_GET['owner_email']);
		$manager_emails = array();
		if(isset( $_GET['manager_emails'])) {
			$manager_emails = $_GET['manager_emails'];
		}
		$user_id = absint($_GET['user_id']);
		$post_id = absint($_GET['post_id']);
		$slug = sanitize_title_with_dashes($name);
		$new_slug = wp_unique_post_slug($slug,$post_id,'publish','flms-groups',0);
		$args = array(
			'ID' => $post_id,
			'post_title' => $name,
			'post_name' => $new_slug
		);
		$post_id = wp_update_post( $args, true );
		if(!is_wp_error($post_id)) {
			$error = '';
			//get current group code 
			$current_code = get_post_meta($post_id, 'flms_group_code', true);
			if($current_code != $group_code) {
				if(!flms_is_group_code_valid($post_id, $group_code)) {
					$group_code = flms_generate_group_code($post_id);
				}
				update_post_meta($post_id, 'flms_group_code', $group_code);
			}
			$current_owner = get_post_meta($post_id, 'flms_group_owner', true);
			//get new owner by email
			$new_owner = get_user_by('email', $owner_email);
			if($new_owner !== false) {
				$new_user_id = $new_owner->ID;
				if($new_user_id != $current_owner) {
					update_post_meta($post_id, 'flms_group_owner', $new_user_id);
					$error = '?update-success=new-group-owner';
					//send notification to new admin
					$blogname = get_bloginfo('name');
					$message = '<p>You have been assigned as the owner of the group "'.$name.'" on '.get_bloginfo('name').' (<a href="'.get_bloginfo('url').'">'.get_bloginfo('url').'</a>)</p>';
					$message .= '<p>View your group by visiting <a href="'.get_permalink($post_id).'">'.get_permalink($post_id).'</a>.</p>';
					$subject = apply_filters('flms_new_group_ownership_heading', 'New group ownership');
            		flms_notification($owner_email, $subject, $message);
				}
			} else {
				$error = '?update-error=invalid-owner-email';
			}
			$current_managers = get_post_meta($post_id, 'flms_group_manager');
			delete_post_meta($post_id,'flms_group_manager');
			if(is_array($manager_emails)) {
				foreach($manager_emails as $manager_email) {
					$email = sanitize_email(trim($manager_email['value']));
					add_post_meta($post_id,'flms_group_manager', $email);
					$user = get_user_by('email', $email);
					$owner = get_user_by('id', $user_id);
					if($user !== false) {
						add_post_meta($post_id,'flms_group_member', $user->ID);
						if(!in_array($email, $current_managers)) {
							$blogname = get_bloginfo('name');
							$message = '<p>'.$owner->display_name.' has assigned you as a manager of the group "'.$name.'" on '.get_bloginfo('name').' (<a href="'.get_bloginfo('url').'">'.get_bloginfo('url').'</a>)</p>';
							$message .= '<p>View your group by visiting <a href="'.get_permalink($post_id).'">'.get_permalink($post_id).'</a>.</p>';
							$subject = apply_filters('flms_new_group_management_heading', 'New group management');
							flms_notification($owner_email, $subject, $message);
						}
					}
				}
			}
			wp_send_json(
				array(
					'success' => 1,
					'redirect' => get_permalink($post_id) . $error
				)
			);
		} else {
			wp_send_json(
				array(
					'success' => 0
				)
			);
		}
	}

	//Assign seats and remove open seats from user array
	public function assign_seats_frontend_callback() {
		$assignments = $_GET['assignments'];
		$user_id = absint($_GET['user_id']);
		$open_seats = get_user_meta($user_id, 'flms_open_seats', true);
		$updated_seats = array();
		if(is_array($open_seats)) {
            if(!empty($open_seats)) {
                $index_counter = 0;
				foreach($open_seats as $course => $data) {
					$course_data = explode(':',$course);
					$course_id = $course_data[0];
					$course_version = $course_data[1];
					$index = 0;
					foreach($data as $order_info) {
						//$return .='<div>'.get_the_title($course_id).'</div>';
						//available seats
						if(isset($assignments['group-assignment-'.$index_counter])) {
							$group_to_assign = $assignments['group-assignment-'.$index_counter];
							if($group_to_assign > 0) {
								$availabile_seats = $order_info['seats'];
								
								if(isset($assignments['seat-assignment-'.$index_counter])) {
									$seats_to_assign = $assignments['seat-assignment-'.$index_counter];
									if($seats_to_assign > $availabile_seats) {
										$seats_to_assign = $availabile_seats;
									}
									if($seats_to_assign > 0) {

										//update the user's availability
										$availabile_seats = $availabile_seats - $seats_to_assign; //update available
										if($availabile_seats <= 0) {
											unset($open_seats[$course][$index]);
											$open_seats[$course] = array_values($open_seats[$course]);
											if(empty($open_seats[$course])) {
												unset($open_seats[$course]);
											}
										} else {
											$open_seats[$course][$index]['seats'] = $availabile_seats;
										}

										$current_data = get_post_meta($group_to_assign, 'group_courses', true);
										if(!is_array($current_data)) {
											$current_data = array();
										}
										if(!isset($current_data[$course])) {
											$current_data[$course] = array();
										}
										if(!isset($current_data[$course]['seats'])) {
											$current_data[$course]['seats'] = 0;
										}
										$current_data[$course]['seats'] = $current_data[$course]['seats'] + $seats_to_assign;

										if(!isset($current_data[$course]['reporting_fees'])) {
											$current_data[$course]['reporting_fees'] = array();
										}

										if(isset($order_info['reporting_fees'])) {
											foreach($order_info['reporting_fees'] as $k => $v) {
												if(!isset($current_data[$course]['reporting_fees'][$k])) {
													$current_data[$course]['reporting_fees'][$k] = array(
														'accepted' => 0,
														'declined' => 0,
													);
												}
												if($v == 1) {
													$current_data[$course]['reporting_fees'][$k]['accepted'] += $seats_to_assign;
												} else {
													$current_data[$course]['reporting_fees'][$k]['declined'] += $seats_to_assign;
												}
											}
										}
										update_post_meta($group_to_assign, 'group_courses', $current_data);



									}
								}
							}
						}
						$index++;
						$index_counter++;
					}
				
				
				}
            }
        }
		if(!empty($open_seats)) {
			update_user_meta($user_id, 'flms_open_seats', $open_seats);
		} else {
			delete_user_meta($user_id, 'flms_open_seats');
		}
		
		$groups = new FLMS_Module_Groups();
		$user = get_user_by('ID', $user_id);
		$my_groups = $groups->get_user_groups($user);
		wp_send_json(
			array(
				'success' => 1,
				'new_group' => $my_groups
			)
		);
		/*} else {
			wp_send_json(
				array(
					'success' => 0
				)
			);
		}*/
	}

	public function generate_group_code_callback() {
		$post_id = absint($_GET['post_id']);
		wp_send_json(
			array(
				'group_code' => flms_generate_group_code($post_id)
			)
		);
	}

	public function check_group_code_callback() {
		$post_id = absint($_GET['post_id']);
		$group_code = sanitize_text_field($_GET['group_code']);
		wp_send_json(
			array(
				'valid' => flms_is_group_code_valid($post_id, $group_code)
			)
		);
	}

	public function get_course_product_variation_options_callback() {
		$post_id = absint($_GET['post_id']);
		$terms = array_map('absint', $_GET['terms']);
		$woo = new FLMS_Module_Woocommerce();
		wp_send_json(
			array(
				'variations' => $woo->get_course_product_variations($post_id, $terms)
			)
		);
	}

	public function check_join_group_code_callback() {
		$user_id = absint($_GET['user_id']);
		$code = trim(sanitize_text_field($_GET['code']));
		$args = array(
			'posts_per_page'   => 1,
			'post_type' => 'flms-groups',
			'post_status'      => 'publish',
			'meta_query' => array(
				array(
					'key'     => 'flms_group_code',
					'value'   => $code,
				),
			),
		);
		$groups = get_posts($args);
		if(empty($groups)) {
			wp_send_json(
				array(
					'success' => 0,
				)
			);
		} else {
			$group_id = $groups[0]->ID;
			//check if they are already a member
			$owner = get_post_meta($group_id, 'flms_group_owner', true);
			$group_members = get_post_meta($group_id, 'flms_group_member');
			$redirect = '';
			if($user_id == $owner) {
				$message = apply_filters('flms_already_group_owner_message', sprintf('As the owner, you are already a member of this group. <a href="%s" title="Go to group">Go to group&nbsp&raquo;</a>', get_permalink($group_id)));
			} else if(in_array($user_id, $group_members)) {
				$message = apply_filters('flms_already_group_member_message', 'You are already a member of this group.');
			} else {
				//join the group
				add_post_meta($group_id, 'flms_group_member', $user_id);
				$message = apply_filters('flms_joined_group_success_message', 'You have been added to the group. Redirecting you now...');
				$redirect = get_permalink($groups[0]->ID);
			}
			wp_send_json(
				array(
					'success' => 1,
					'redirect' => $redirect,
					'message' => $message
				)
			);
		}
	}

	public function delete_group_callback() {
		$post_id = absint($_GET['post_id']);
		$deleted = wp_delete_post($post_id, true);
		if($deleted !== false) {
			$redirect = trailingslashit(get_permalink( get_option('woocommerce_myaccount_page_id') )) . get_option('flms_my_groups_endpoint').'?group-deleted=1';
			wp_send_json(
				array(
					'success' => 1,
					'redirect' => $redirect,
				)
			);
		} else {
			wp_send_json(
				array(
					'success' => 0,
				)
			);
		}
	}

	public function leave_group_callback() {
		$post_id = absint($_GET['post_id']);
		$user_id = absint($_GET['user_id']);
		//unenroll them from courses
		$group_courses = get_post_meta($post_id, 'group_courses', true);
		if($group_courses != '') {
			if(is_array($group_courses)) {
				foreach($group_courses as $course_info => $course_settings) {
					$course_data = explode(':',$course_info);
					if(isset($course_data[0]) && isset($course_data[0])) {
						$course_id = $course_data[0];
						$course_version = $course_data[1];
						$user_progress = new FLMS_Course_Progress();
						$user_progress->unenroll_user($user_id, $course_id, $course_version);
					}
					if(isset($course_settings['enrolled'])) {
						$enrolled_user_ids = $course_settings['enrolled'];
						$enrolled_user_ids = array_diff($enrolled_user_ids, array($user_id));
						$group_courses[$course_info]['enrolled'] = $enrolled_user_ids;
						//$group_courses[$course_info]['enrolled'] = array(); //clear for debugging
					}
				}
			}
		}
		update_post_meta($post_id, 'group_courses', $group_courses);
		
		//delete them from group
		$user = get_user_by('id', $user_id);
		if($user !== false) {
			$manager_removed = delete_post_meta($post_id, 'flms_group_manager', $user->user_email);
		}
		$removed = delete_post_meta($post_id, 'flms_group_member', $user_id);
		if($removed !== false) {
			$redirect = trailingslashit(get_permalink( get_option('woocommerce_myaccount_page_id') )) . get_option('flms_my_groups_endpoint').'?left-group='.$post_id;
			wp_send_json(
				array(
					'success' => 1,
					'redirect' => $redirect,
				)
			);
		} else {
			wp_send_json(
				array(
					'success' => 0,
				)
			);
		}
	}

	public function manager_invitation_callback() {
		$user_id = absint($_GET['user_id']);
		$user = get_user_by('id', $user_id);
		$group_id = absint($_GET['post_id']);
		$custom_message = sanitize_textarea_field($_GET['message']);
		$managers = get_post_meta($group_id, 'flms_group_manager');
		$name = get_the_title($group_id);
		if(!empty($managers)) {
            $invited = get_post_meta($group_id,'flms_manager_invites');
            foreach($managers as $manager) {
                $manager_user = get_user_by('email', $manager);
                if($manager_user === false) {
                    if(!in_array($manager,$invited)) {
                        add_post_meta($group_id,'flms_manager_invites',$manager);
						$blogname = get_bloginfo('name');
						$message = '<p>'.$user->display_name.' has assigned you as a manager of the group "'.$name.'" on '.get_bloginfo('name').' (<a href="'.get_bloginfo('url').'">'.get_bloginfo('url').'</a>)</p>';
						$message .= '<p>Create an account to claim your management role. After creating your account, you can view the group by visiting <a href="'.get_permalink($group_id).'">'.get_permalink($group_id).'</a> or going to the Groups tab in your account.</p>';
						if($custom_message != '') {
							$message_text_array = preg_split('/\n|\r\n/', $custom_message);
							$new_text = '<p><em>&ldquo;';
							$first_line = true;
							foreach($message_text_array as $line) {
								if(!$first_line) {
									$new_text .= '</em></p><p><em>';
								}
								$new_text .= stripslashes(sanitize_text_field($line));
								$first_line = false;
							}
							$new_text .= '&rdquo;</em></p>';
							$message .= '<p>'.$user->display_name.' left the following message with your invitation:</p>'.$new_text;
						}
						$subject = apply_filters('flms_new_group_management_invitation_heading', 'New group management invitation');
						flms_notification($manager, $subject, $message);
                    }
                } 
            }
            
        }
		wp_send_json(
			array(
				'redirect' => get_permalink($group_id),
			)
		);
	}

	public function user_group_enroll_callback() {
		$user_id = absint($_GET['user_id']);
		$index = absint($_GET['index']);
		$group_id = absint($_GET['group_id']);
		$group_courses = get_post_meta($group_id, 'group_courses', true);
		$success = 0;
		if($group_courses != '') {
			if(is_array($group_courses)) {
				$row = 0;
				foreach($group_courses as $course_info => $course_settings) {
					if($row == $index) {
						$course_data = explode(':',$course_info);
						if(isset($course_data[0]) && isset($course_data[0])) {
							$seats = 0;
                            if(isset($course_settings['seats'])) {
                                $seats = absint($course_settings['seats']);
                            }
							if($seats > 0) {
								$course_id = $course_data[0];
								$course_version = $course_data[1];
								$user_progress = new FLMS_Course_Progress();
								$enroll = $user_progress->enroll_user($user_id, $course_id, $course_version);
								$meta_id = $enroll['id'];
								if(flms_is_module_active('course_credits') && $meta_id > 0) {
                                    global $flms_settings;
                                    $reporting_fees = array();
									if(isset($course_settings['reporting_fees'])) {
										$reporting_fees = $course_settings['reporting_fees'];
									}
									foreach($reporting_fees as $fee_type => $fee_data) {
										//fee data is array of number accepted or declined
										if(!empty($fee_data)) {
											$credit_type = str_replace('_reporting_fee','',$fee_type);
											if(isset($fee_data['accepted'])) {
												$available = $fee_data['accepted'];
												if($available > 0) {
													$accepts = 1;
												} else {
													$accepts = 0;
												}
												global $wpdb;
                                                $wpdb->update( 
													FLMS_REPORTING_TABLE, 
													array( 
														'accepts_reporting_fee' => $accepts,
													), 
													array( 
														'entry_id' => $meta_id,
														'credit_type' => $fee_type
													) 
												);
												$available = $available - 1;
												$reporting_fees[$fee_type]['accepted'] = $available;
											}
										}
									}
									$group_courses[$course_info]['reporting_fees'] = $reporting_fees;
                                }


								if(!isset($course_settings['enrolled'])) {
									$enrolled = array();
								} else {
									$enrolled = $course_settings['enrolled'];
								}
								$enrolled[] = $user_id;

								$seats = $seats - 1;
								$group_courses[$course_info]['enrolled'] = $enrolled;
								$group_courses[$course_info]['seats'] = $seats;
								$success = 1;
							}
						}
					}
					$row++;
				}
				
			}
		}
		//update data
		update_post_meta($group_id, 'group_courses', $group_courses);
		$groups = new FLMS_Module_Groups();
		$new_html = $groups->get_group_member_content($group_id);
		wp_send_json(
			array(
				'success' => $success,
				'new_html' => $new_html,
			)
		);
	}

}
new FLMS_Ajax();