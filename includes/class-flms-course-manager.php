<?php
/**
 * Fragment LMS Setup.
 *
 * @package FLMS\Classes
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * FLMS Course manager class
 */
class FLMS_Course_Manager {

	public $post_types = array();
	public $capability = 'manage_options';

	/**
	 * The Constructor.
	 */
	public function __construct() {
		$this->post_types = flms_get_plugin_post_types();
		add_action( 'add_meta_boxes', array($this,'flms_register_meta_boxes') );
		add_action( 'admin_enqueue_scripts', array($this,'enqueue_flms_admin_scripts') );
		add_action('save_post', array($this,'save_course_content'));
		add_action( 'before_delete_post', array($this,'trash_clean_course_postdata'),10,2);
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action('template_redirect', array($this, 'redirect_empty_version_content'));
	}

	/**
	 * Add meta boxes to flms-courses
	 * @since 1.0.0
	 * @return void
	 */
	public function flms_register_meta_boxes() {
		global $flms_settings;
		$course_name = 'Course';
		if(isset($flms_settings['labels']["course_singular"])) {
			$course_name = $flms_settings['labels']["course_singular"];
		}
		$lesson_name = 'Lesson';
		if(isset($flms_settings['labels']["lesson_singular"])) {
			$lesson_name = $flms_settings['labels']["lesson_singular"];
		}
		$topic_name = 'Topic';
		if(isset($flms_settings['labels']["topic_singular"])) {
			$topic_name = $flms_settings['labels']["topic_singular"];
		}
		$exam_name = 'Exam';
		if(isset($flms_settings['labels']["exam_singular"])) {
			$exam_name = $flms_settings['labels']["exam_singular"];
		}
		add_meta_box( 'flms_course-manager', __( FLMS_PLUGIN_NAME . ' '.$course_name.' Management', 'flms' ), array($this,'flms_course_metabox'), 'flms-courses', 'normal', 'high' );
		if(flms_is_module_active('course_taxonomy_royalties')) {
			$royalties = new FLMS_Module_Course_Taxonomies();
			add_meta_box( 'flms_course-manager-royalties', __( $course_name.' Royalties', 'flms' ), array($royalties,'flms_course_royalties_metabox'), 'flms-courses', 'side', 'default' );
		}
		add_meta_box( 'flms_course-manager', __( FLMS_PLUGIN_NAME .' '.$lesson_name.' Management', 'flms' ), array($this,'flms_lessons_metabox'), 'flms-lessons', 'normal', 'high' );
		//add_meta_box( 'flms_course-manager', __( FLMS_PLUGIN_NAME .' '.$topic_name.' Management', 'flms' ), array($this,'flms_topics_metabox'), 'flms-topics', 'normal', 'high' );
		add_meta_box( 'flms-exam-options', __( $exam_name.' Options', 'flms' ), array($this,'flms_lessons_exam_options_metabox'), 'flms-exams', 'normal', 'default' );
		//add_meta_box( 'flms_course-manager', __( FLMS_PLUGIN_NAME .' Exam Management', 'flms' ), array($this,'flms_lessons_exams_metabox'), 'flms-exams', 'normal', 'high' );
	}

	/**
	 * Hook to show admin notices
	 */
	public function admin_notices() {
		if ( ! isset( $_GET['error-removing-lesson'] ) ) {
		  return;
		}
		?>
		<div class="error">
		   <p><?php esc_html_e( 'This lesson belongs to one or more versions and could not be removed. Please remove it from all versions of your course before disassociating the course.', 'flms' ); ?></p>
		</div>
		<?php
	}

	public function flms_lessons_exam_options_metabox() {
		wp_enqueue_style('flms-questions');
		global $post, $flms_settings, $flms_active_version;
		$course_version = $flms_active_version;
		$exam_settings = get_post_meta($post->ID, "flms_exam_settings_$flms_active_version", true);
		if($exam_settings == '') {
			$exam_settings = $this->get_default_exam_settings();
		}
		echo '<div class="flms-tabs exam-tabs theme-color">';
			echo '<div class="tab is-active" data-tab="#options">Settings</div>';
			echo '<div class="tab" data-tab="#labels">Labels</div>';
			echo '<div class="tab" data-tab="#flms-exam-questions">Questions</div>';
		echo '</div>';
		echo '<div class="flms-tab-section is-active" id="options">';
			echo '<div>';
				echo '<label class="has-tooltip">Exam Type ';
				echo '<div class="flms-tooltip" data-tooltip="<strong>Standard:</strong> You select which questions appear, in what order<br><strong>Sample Draw:</strong> Define the questions and pick a random number of questions from the bank.<br><strong>Category Sample Draw:</strong> Select questions by category and pick a random number of questions from each category.<br><strong>Cumulative:</strong> Create an exam by picking random questions previous exams."></div>';
				echo '</label>';
				$options = array(
					'standard' => 'Standard',
					'sample-draw' => 'Sample Draw',
					'category-sample-draw' => 'Category Sample Draw',
					'cumulative' => 'Cumulative',
				);
				echo '<select name="flms_exam_type" id="flms_exam_type" class="flms-full-width">';
				foreach($options as $k => $v) {
					echo '<option value="'.$k.'"';
					if($k == $exam_settings['exam_type']) {
						echo ' selected';
					}
					echo '>'.$v.'</option>';
				}
				echo '</select>';
			echo '</div>';

			echo '<div class="sample-draw-exam-option">';
				$default = $exam_settings['questions_per_page'];
				if(isset($exam_settings['sample-draw-question-count'])) {
					$default = $exam_settings['sample-draw-question-count'];
				}
				echo '<label>Questions to Draw</label>';
				echo '<p class="description"></p>';
				echo '<input type="number" name="flms_sample_draw_question_count" value="'.$default.'" class="flms-full-width" />';
			echo '</div>';

			echo '<div>';
				echo '<label class="has-tooltip">Course content access ';
				echo '<div class="flms-tooltip" data-tooltip="<strong>Open book:</strong> Course content can be accessed while the user is taking an exam<br><strong>Closed book:</strong> Course content is restricted until the exam is completed."></div>';
				echo '</label>';
				$options = array(
					'open' => 'Open book',
					'closed' => 'Closed book',
				);
				echo '<select name="flms_exam_course_content_access" id="flms_exam_course_content_access" class="flms-full-width">';
				foreach($options as $k => $v) {
					echo '<option value="'.$k.'"';
					if($k == $exam_settings['course_content_access']) {
						echo ' selected';
					}
					echo '>'.$v.'</option>';
				}
				echo '</select>';
			echo '</div>';
			
			echo '<div>';
				echo '<label>Exam Attempts</label>';
				echo '<p class="description"></p>';
				echo '<input type="number" name="flms_exam_attempts" value="'.$exam_settings['exam_attempts'].'" class="flms-full-width" />';
			echo '</div>';

			
			/*echo '<div class="sample-draw-exam-option">';
				echo 'Sample Draw option';
			echo '</div>';*/

			echo '<div>';
				echo '<label>Questions per page</label>';
				echo '<p class="description"></p>';
				echo '<input type="number" name="flms_questions_per_page" value="'.$exam_settings['questions_per_page'].'" class="flms-full-width" />';
			echo '</div>';

			echo '<div>';
				echo '<label>Questions Order</label>';
				echo '<p class="description"></p>';
				$options = array(
					'linear' => 'Linear',
					'random' => 'Random',
				);
				$question_order = 'linear';
				if(isset($exam_settings['question_order'])) {
					$question_order = $exam_settings['question_order'];
				}
				echo '<select name="flms_question_order" class="flms-full-width">';
				foreach($options as $k => $v) {
					echo '<option value="'.$k.'"';
					if($k == $question_order) {
						echo ' selected';
					}
					echo '>'.$v.'</option>';
				}
				echo '</select>';
			echo '</div>';
			
			echo '<div>';
				echo '<label>Pass percentage</label>';
				echo '<p class="description"></p>';
				echo '<input type="number" name="flms_pass_percentage" value="'.$exam_settings['pass_percentage'].'" class="flms-full-width" />';
			echo '</div>';

			echo '<div>';
				echo '<label>Pass points</label>';
				echo '<p class="description"></p>';
				echo '<input type="number" name="flms_pass_points" value="'.$exam_settings['pass_points'].'" class="flms-full-width" />';
			echo '</div>';
			
			echo '<div>';
				echo '<label>Save &amp; Continue</label>';
				echo '<p class="description"></p>';
				$options = array(
					'active' => 'Enabled',
					'inactive' => 'Disabled',
				);
				echo '<select name="flms_save_continue_enabled" class="flms-full-width">';
				foreach($options as $k => $v) {
					echo '<option value="'.$k.'"';
					if($k == $exam_settings['save_continue_enabled']) {
						echo ' selected';
					}
					echo '>'.$v.'</option>';
				}
				echo '</select>';
			echo '</div>';

			echo '<div>';
				echo '<label>Enable Exam Review</label>';
				echo '<p class="description"></p>';
				$options = array(
					'active' => 'Enabled',
					'inactive' => 'Disabled',
				);
				echo '<select name="flms_exam_review_enabled" class="flms-full-width">';
				foreach($options as $k => $v) {
					echo '<option value="'.$k.'"';
					if($k == $exam_settings['exam_review_enabled']) {
						echo ' selected';
					}
					echo '>'.$v.'</option>';
				}
				echo '</select>';
			echo '</div>';
			
			echo '<div>';
				echo '<label>Exams are graded</label>';
				echo '<p class="description"></p>';
				$options = array(
					'graded' => 'Graded',
					'auto' => 'Auto-Pass',
				);
				echo '<select name="flms_exam_is_graded" class="flms-full-width">';
				foreach($options as $k => $v) {
					echo '<option value="'.$k.'"';
					if($k == $exam_settings['exam_is_graded']) {
						echo ' selected';
					}
					echo '>'.$v.'</option>';
				}
				echo '</select>';
			echo '</div>';

			echo '<div>';
				echo '<label>Exams are graded using</label>';
				echo '<p class="description"></p>';
				$options = array(
					'percentage' => 'Percentage',
					'points' => 'Points',
				);
				echo '<select name="flms_exam_is_graded_using" class="flms-full-width">';
				foreach($options as $k => $v) {
					echo '<option value="'.$k.'"';
					if($k == $exam_settings['exam_is_graded_using']) {
						echo ' selected';
					}
					echo '>'.$v.'</option>';
				}
				echo '</select>';
			echo '</div>';

			echo '<div>';
				echo '<label>No further exam attempts remaining action</label>';
				echo '<p class="description"></p>';
				$options = array(
					'reset-lesson' => 'Reset current lesson progress',
					'reset-course' => 'Reset course progress',
					'unenroll-learner' => 'Unenroll learner',
					'no-action' => 'No action',
				);
				echo '<select name="flms_exam_attempt_action" class="flms-full-width">';
				foreach($options as $k => $v) {
					echo '<option value="'.$k.'"';
					if($k == $exam_settings['exam_attempt_action']) {
						echo ' selected';
					}
					echo '>'.$v.'</option>';
				}
				echo '</select>';
			echo '</div>';
			
			$time_limit = 0;
			if(isset($exam_settings['time_limit'])) {
				$time_limit = $exam_settings['time_limit'];
			}
			echo '<div>';
				echo '<label class="has-tooltip">Exam Time Limit ';
				echo '<div class="flms-tooltip" data-tooltip="Time (in seconds) a user has to complete the exam. 0 for no time limit. Seconds will be converted to time for frontend display."></div>';
				echo '</label>';
				echo '<p class="description"></p>';
				echo '<input type="number" name="flms_time_limit" value="'.$time_limit.'" class="flms-full-width" />';
			echo '</div>';
		echo '</div>';
		echo '<div class="flms-tab-section" id="labels">';
		
			echo '<div>';
				echo '<label>Start Exam label</label>';
				echo '<p class="description"></p>';
				echo '<input type="text" name="flms_start_exam_label" value="'.$exam_settings['exam_start_label'].'" class="flms-full-width" />';
			echo '</div>';
			
			echo '<div>';
				echo '<label>Resume Exam label</label>';
				echo '<p class="description"></p>';
				echo '<input type="text" name="flms_resume_exam_label" value="'.$exam_settings['exam_resume_label'].'" class="flms-full-width" />';
			echo '</div>';

			echo '<div>';
				echo '<label>Exam label override</label>';
				echo '<p class="description"></p>';
				echo '<input type="text" name="flms_exam_label_override" value="'.$exam_settings['exam_label_override'].'" class="flms-full-width" />';
			echo '</div>';

			
		echo '</div>';
		echo '<div class="flms-tab-section" id="flms-exam-questions">';
			echo '<div id="cumulative-exam-questions" class="exam-questions-section">';
				$course_id = flms_get_course_id($post->ID);
				$course = new FLMS_Course($course_id);
				global $flms_active_version;
				$flms_active_version = $course_version;
				$exams = $course->get_all_exams_from_course();
				if(!empty($exams)) {
					$post_key = array_search($post->ID, $exams);
					if($post_key !== false) {
						unset($exams[$post_key]);
					}
				}
				if(empty($exams)) {
					echo 'There are no other exams in this course to create a cumulative exam.';
				} else {
					if(isset($exam_settings['cumulative_exam_questions'])) {
						$exam_question_settings = $exam_settings['cumulative_exam_questions'];
					} else {
						$exam_question_settings = array();
					}
					echo '<div class="setting-area-fields cumulative-settings-fields">';
						echo '<p class="description">Select the number of questions from each exam in the course. Enter -1 to pull all questions from the exam, or 0 to exclude it.</p>';
						echo '<div class="settings-field headings">';
							echo '<label class="heading">Exam Name</label>';
							//echo '<label class="heading">Include</label>';
							echo '<label class="heading">Number of Questions</label>';
						echo '</div>';
						foreach($exams as $exam) {
							$count = 0;
							if(isset($exam_question_settings[$exam])) {
								$count = $exam_question_settings[$exam];
							}
							echo '<div class="settings-field">';
								echo '<label>'.get_the_title($exam).'</label>';
								//echo '<input type="checkbox" name="cumulative_exam_exams[]" value="'.$exam.'" />';
								echo '<div  data-label="Number of Questions"><input type="number" name="cumulative_exam_exams_questions['.$exam.']" value="'.$count.'" /></div>';
							echo '</div>';
						}
					echo '</div>';
				}
			echo '</div>';
			echo '<div id="standard-exam-questions" class="exam-questions-section">';
				global $post;
				$questions = new FLMS_Questions;
				$course_id = flms_get_course_id($post->ID);
				$version = $this->get_course_editing_version($course_id);
				$versioned_content = get_post_meta($post->ID,'flms_version_content',true);
				if(!is_array($versioned_content)) {
					$versioned_content = array();
				}
				if($version == null) {
					$version = count($versioned_content); //apply to latest version
				}
				if(isset($exam_settings["exam_questions"])) {
					$current_questions = $exam_settings["exam_questions"];
				} else {
					$current_questions = array();
				}

				$question_select_type = 'manual';
				if(isset($exam_settings['question_select_type'])) {
					$question_select_type = $exam_settings['question_select_type'];
				}
				?>
				<div class="has-toggleable-content">
					<div class="flex gap-md column toggles-sub-section">
						<div class="flms-field radio radio-toggle flex-1">
							<input type="radio" name="standard_question_select_type" id="standard_question_select_type_manual" value="manual" <?php if($question_select_type == 'manual') echo 'checked=""'; ?> data-toggle="#manual-questions,#manual-explanation">
							<label for="standard_question_select_type_manual">Manual</label>						
							<input type="radio" name="standard_question_select_type" id="standard_question_select_type_category" value="category" <?php if($question_select_type == 'category') echo 'checked=""'; ?>  data-toggle="#category-questions,#category-explanation">
							<label for="standard_question_select_type_category">By Category</label>
						</div>
						<div class="category-explanations flms-italic">
							<div id="manual-explanation" class="conditional-toggle is-hidden">You have full control over the order of the questions. Questions can be added in bulk by selecting one or more question categories.</div>	
							<div id="category-explanation" class="conditional-toggle is-hidden">Questions will automatically be added to the exam based on the categories selected based on their menu order.</div>
						</div>
					</div>
				
					<?php 
					echo '<div class="conditional-toggle is-hidden flex" id="category-questions">';
						$current_categories = array();
						if(isset( $exam_settings['exam_question_categories'])) {
							$current_categories = $exam_settings['exam_question_categories'];
						}
						
						echo $questions->get_question_taxonies(true, $current_categories, 'standard_exam_questions[]', true);
					echo '</div>';
					echo '<div class="conditional-toggle is-hidden flex" id="manual-questions">';
						echo '<div id="selected-questions" class="sortable-questions">';
							//$return .= '<div class="exam-question">Question Bank</div>';
							foreach($current_questions as $question_id) {
								$question = new FLMS_Question($question_id);
								echo $question->get_editor_output();
							}
						echo '</div>';
						echo '<div class="questions-container flms-simple-sidebar sidebar-right">';
							echo '<div class="questions-toggle">';
								echo '<div class="toggle-title has-chevon is-active">Search Questions</div>';
								echo '<div class="question-option is-active">';
									echo '<div id="search-questions">';
										echo '<input type="text" placeholder="Question name" id="search-questions-input" />';
									echo '</div>';
									echo '<div id="searched-questions"></div>';
									echo '<button id="add-searched-question-to-bank" class="is-inactive button button-primary">Add Selected Questions</button>';
								echo '</div>';
							echo '</div>';
							echo '<div class="questions-toggle">';
								echo '<div class="toggle-title has-chevon">All Questions</div>';
									echo '<div class="question-option">';
										echo '<div id="question-bank">';
											echo $questions->question_bank($current_questions);
										echo '</div>';
									echo '<div class="question-action"><button id="add-question-to-bank" class="button button-primary">Add Selected Questions</button></div>';
								echo '</div>';
								
							echo '</div>';
							echo '<div class="questions-toggle">';
								echo '<div class="toggle-title has-chevon">Question Categories</div>';
									echo '<div id="question-categories" class="question-option">';
										echo $questions->get_question_taxonies(true);
									echo '<div class="question-action"><button id="add-categories-to-bank" class="button button-primary">Add from Categories</button></div>';
								echo '</div>';
							echo '</div>';
							
						echo '</div>';

					echo '</div>'; ?>
				</div><?php 
			echo '</div>';
			echo '<div id="category-same-draw-questions" class="exam-questions-section">';
				global $post;
				$questions = new FLMS_Questions;
				$course_id = flms_get_course_id($post->ID);
				$version = $this->get_course_editing_version($course_id);
				$versioned_content = get_post_meta($post->ID,'flms_version_content',true);
				if(!is_array($versioned_content)) {
					$versioned_content = array();
				}
				if($version == null) {
					$version = count($versioned_content); //apply to latest version
				}
				
				?>
				<div>
					
				
					<?php 
					echo '<div class="flex">';
						$current_categories = array();
						if(isset( $exam_settings['exam_question_categories'])) {
							$current_categories = $exam_settings['exam_question_categories'];
						}
						//print_r($current_categories);
						$numbers_array = array();
						if(isset( $exam_settings['exam_question_categories_numbers'])) {
							$numbers_array = $exam_settings['exam_question_categories_numbers'];
						}
						//print_r($numbers_array);
						echo $questions->get_question_taxonies(true, $current_categories, 'category_sample_exam_questions[]', true, true, $numbers_array);
					echo '</div>';
					 ?>
				</div><?php 
			echo '</div>';
			echo '<input type="hidden" name="flms-post-type" value="flms-exams" />';
			
		echo '</div>';
		
	}
	/**
	 * Show admin notice for not being able to remove a lesson
	 */
	public function add_notice_query_var( $location ) {
		remove_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );
		return add_query_arg( array( 'error-removing-lesson' => true ), $location );
	}

	/**
	 * Course settings for the course post type
	 */
	public function flms_course_metabox() {
		global $post, $flms_settings;
		$course_name = 'Course';
		if(isset($flms_settings['labels']["course_singular"])) {
			$course_name = $flms_settings['labels']["course_singular"];
		}
		$metabox_fields = array(
			'course-content' => array(
				'label' => "$course_name Content",
				'id' => 'course-content',
				'description' => '',
				'tooltip' => '',
				'callback' => $this->get_course_content()
			),
			'course-preview' => array(
				'label' => "$course_name Preview",
				'id' => 'course-preview',
				'description' => '',
				'tooltip' => '',
				'callback' => $this->get_course_preview()
			),
			'version-options' => array(
				'label' => "$course_name Settings",
				'id' => 'version-settings',
				'description' => '',
				'tooltip' => '',
				
				'callback' => $this->get_version_options('standard')
			),
			/*'course-settings' => array(
				'label' => "$course_name Options",
				'id' => 'course-options',
				'description' => '',
				'tooltip' => '',
				'callback' => $this->get_course_settings()
			),*/
		);
		if(flms_is_module_active('course_credits')) {
			$course_materials = new FLMS_Module_Course_Materials();
			$metabox_fields['course-materials'] = array(
				'label' => "$course_name Materials",
				'id' => 'version-course-materials',
				'description' => '',
				'tooltip' => '',
				'callback' => $course_materials->get_course_materials_course_settings()
			);
		}
		if(flms_is_module_active('course_credits')) {
			$course_credits = new FLMS_Module_Course_Credits();
			$metabox_fields['course-credits'] = array(
				'label' => "$course_name Credits",
				'id' => 'version-course-credits',
				'description' => '',
				'tooltip' => '',
				'layout' => 'grid',
				'callback' => $course_credits->get_course_credit_course_settings()
			);
		}
		if(flms_is_module_active('course_metadata')) {
			$course_metadata = new FLMS_Module_Course_Metadata();
			$course_credit_fields = $course_metadata->get_course_metadata_settings_fields();
			if(!empty($course_credit_fields)) {
				$metabox_fields['course-metadata'] = array(
					'label' => "$course_name Metadata",
					'id' => 'version-course-metadata',
					'description' => '',
					'tooltip' => '',
					'callback' => $course_metadata->get_course_metadata_settings('standard')
				);
			}
			
		}
		if(flms_is_module_active('woocommerce')) {
			$metabox_fields['product-options'] = array(
				'label' => "Purchase Options",
				'id' => 'product_options',
				'description' => '',
				'tooltip' => '',
				'callback' => $this->get_product_options()
			);
		}
		$this->flms_settings_output($metabox_fields);
	}

	public function get_course_content() {
		global $flms_course_id, $flms_active_version, $flms_course_version_content;
		$return = '<div class="course-content">';
			$preview_content = '';
			//print_r($flms_course_version_content);
			if(isset($flms_course_version_content[$flms_active_version]['post_content'])) {
				$preview_content = $flms_course_version_content[$flms_active_version]['post_content'];
			}
			ob_start();
			wp_editor($preview_content, "$flms_course_id-post-content");
			$return .= ob_get_clean();
		$return .= '</div>';
		$return .= '<div class="all-lessons">';
		$return .= $this->list_course_lessons();
		$return .= '</div>';
		$return .= '<div class="all-exams flex column no-gap">';
		$return .= $this->get_post_type_exams($flms_course_id);
		$return .= '</div>';
		return $return;
	}

	public function get_course_preview() {
		global $flms_course_id, $flms_active_version, $flms_course_version_content;
		$return = '<div class="course-preview">';
			$return .= '<p class="description" style="margin-bottom: 20px;">Display alternate content for unenrolled users. Leave this field empty to use the course content.</p>';
			ob_start();
			$preview_content = '';
			if(isset($flms_course_version_content[$flms_active_version]['course_preview'])) {
				$preview_content = $flms_course_version_content[$flms_active_version]['course_preview'];
			}
			wp_editor($preview_content, "$flms_course_id-preview-content");
			$return .= ob_get_clean();
		$return .= '</div>';
		return $return;
	}

	public function get_course_settings() {
		global $post;
		$active_version = get_post_meta($post->ID,'flms_course_active_version',true);
		$versions = get_post_meta($post->ID,'flms_version_content',true);
		//$course_versioned_content["{$active_version}"]["course_settings"] = $content;
		$return = '<div class="course-settings">';
			$return .= '<div class="settings-field">';
				$return .= '<label>Course access ';
				$return .= '<div class="flms-tooltip" data-tooltip="<strong>Open:</strong> Any logged in user can enroll in the course.<br><strong>Purchase:</strong> The course must be purchased to enroll. Requires an ecommerce module to be enabled in the <a href=&quot;'.admin_url('admin.php?page=flms-setup').'&quot; target=&quot;_blank&quot;>plugin settings</a>. Assign this course to a product for users to enroll."></div>';
				$return .= '</label>';
				$return .= '<p class="description">Select how users enroll in this course</p>';
				$return .= '<select name="course_access">';
					$current_access = '';
					if(isset($versions["$active_version"]['course_settings']['course_access'])) {
						$current_access = $versions["$active_version"]['course_settings']['course_access'];
					}
					$access_types = array(
						'open' => 'Open',
						'purchase' => 'Purchase',
					);
					foreach($access_types as $k => $v) {
						$return .= '<option value="'.$k.'"';
						if($current_access == $k) {
							$return .= ' selected';
						}
						$return .= '>'.$v.'</option>';
					}
				$return .= '</select>';
			$return .= '</div>';

			$return .= '<div class="settings-field">';
				$return .= '<label>Course progression ';
				$return .= '<div class="flms-tooltip" data-tooltip="<strong>Linear:</strong> Lessons must be completed in the order defined.<br><strong>Freeform:</strong> Lessons can be completed in any order."></div>';
				$return .= '</label>';
				$return .= '<p class="description">Select how users are able to navigate the course.</p>';
				$return .= '<select name="course_progression">';
					$current_progression = '';
					if(isset($versions["$active_version"]['course_settings']['course_progression'])) {
						$current_progression = $versions["$active_version"]['course_settings']['course_progression'];
					}
					$progress_types = array(
						'linear' => 'Linear',
						'freeform' => 'Freeform',
					);
					foreach($progress_types as $k => $v) {
						$return .= '<option value="'.$k.'"';
						if($current_progression == $k) {
							$return .= ' selected';
						}
						$return .= '>'.$v.'</option>';
					}
				$return .= '</select>';
			$return .= '</div>';
			if(flms_is_module_active('course_certificates')) {
				if(!wp_script_is( 'select2', 'enqueued' )) {
					wp_enqueue_style( 'select2');
					wp_enqueue_script( 'select2');
				}
				wp_enqueue_script( 'flms-certificates');
				$return .= '<div class="settings-field">';
					$return .= '<label>Course certificate(s)</label>';
					$return .= '<p class="description">Grant customer a certificate upon course completion</p>';
					$certificates = array();
					if(isset($versions["$active_version"]['course_certificates'])) {
						$certificates = $versions["$active_version"]['course_certificates'];
					}
					$return .= '<select name="flms-course-certificates[]" id="flms-course-certificates" multiple="multiple">';
						$options = flms_get_certificate_select_box();
						foreach($options as $k => $v) {
							$return .= '<option value="'.$k.'"';
							if(in_array($k, $certificates)) {
								$return .= ' selected';
							}
							$return .= '>'.$v.'</option>';
						}
					$return .= '</select>';
				$return .= '</div>';
					
			}
		$return .= '</div>';
		return $return;
	}

	public function get_product_options() {
		global $post;
		$return = '';
		if(flms_is_module_active('woocommerce')) {
			$woo = new FLMS_Module_Woocommerce();
			$current_settings = get_post_meta($post->ID,'flms_course_product_options', true);
			//echo '<pre>'.print_r($current_settings, true) .'</pre>';
			if($current_settings == '') {
				$current_settings = $woo->get_course_product_defaults();
			}
			$return .= '<div class="settings-field">';
				$return .= '<label>Product Type ';
				//$return .= '<div class="flms-tooltip" data-tooltip="<strong>Open:</strong> Any logged in user can enroll in the course.<br><strong>Purchase:</strong> The course must be purchased to enroll. Requires an ecommerce module to be enabled in the <a href=&quot;'.admin_url('admin.php?page=flms-setup').'&quot; target=&quot;_blank&quot;>plugin settings</a>. Assign this course to a product for users to enroll."></div>';
				$return .= '</label>';
				$return .= '<p class="description">Select the product display for this course</p>';
				$return .= '<select name="product_type" id="flms-course-product-type">';
					$current_type = $current_settings['product_type'];
					$access_types = array(
						'simple' => 'Simple',
						'variable' => 'Variable',
					);
					foreach($access_types as $k => $v) {
						$return .= '<option value="'.$k.'"';
						if($current_type == $k) {
							$return .= ' selected';
						}
						$return .= '>'.$v.'</option>';
					}
				$return .= '</select>';
			$return .= '</div>';

			$default_variation_attributes = $current_settings['variation_attributes'];

			//echo '<pre>'.print_r($current_settings, true).'</pre>';
			//simple prices
			$return .= '<div class="settings-field flms-simple-price-settings';
			if($current_type != 'simple') {
				$return .= ' flms-is-hidden';
			}
			$return .= '">';
				$return .= '<p class="description">Set your course price below</p>';
				//show the variations
				$return .= '<div class="course-product-simple">';
					$woo = new FLMS_Module_Woocommerce();
					$return .= $woo->get_course_product_prices($post->ID);
				$return .= '</div>';
			$return .= '</div>';

			$return .= '<div class="flms-variable-price-settings';
			if($current_type != 'variable') {
				$return .= ' flms-is-hidden';
			}
			$return .= '">';
				$attribute_ids = array();
				$return .= '<div class="settings-field">';
					$return .= '<label>Variation Attributes ';
					//$return .= '<div class="flms-tooltip" data-tooltip="<strong>Open:</strong> Any logged in user can enroll in the course.<br><strong>Purchase:</strong> The course must be purchased to enroll. Requires an ecommerce module to be enabled in the <a href=&quot;'.admin_url('admin.php?page=flms-setup').'&quot; target=&quot;_blank&quot;>plugin settings</a>. Assign this course to a product for users to enroll."></div>';
					$return .= '</label>';
					$return .= '<p class="description">Select which attributes to used to generate product variations. Attributes are managed with <a href="'.admin_url('edit.php?post_type=product&page=product_attributes').'">Woocommerce settings</a>.</p>';
					$return .= '<div class="flms-flex flex-wrap label-has-margin">';
						$attributes = wc_get_attribute_taxonomies();
						if(is_array($attributes)) {
							if(empty($attributes)) {
								$return .= '<a href="'.admin_url('edit.php?post_type=product&page=product_attributes').'">Create some attributes</a> before using this option.';
							} else {
								foreach($attributes as $attribute) {
									$return .= '<div class="flex-1">';
									$return .= '<div class="flms-label">'.$attribute->attribute_label.'</div>';
										$taxonomy = 'pa_'.$attribute->attribute_name;
										$terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => 0));
										if(!empty($terms)) {
											foreach($terms as $term) {
												$checked = '';
												if(isset($default_variation_attributes[$taxonomy])) {
													if(in_array($term->term_id,$default_variation_attributes[$taxonomy])) {
														$checked = ' checked="checked"';
														$attribute_ids[] = $term->term_id;
													}
												}
												$return .= '<label><input type="checkbox" class="flms-course-product-attribute" name="course_product_attributes['.$taxonomy.'][]" value="'.$term->term_id.'" '.$checked.' />'.$term->name.'</label>';
												//$return .= '<label><input type="number" name="course_product_attribute_price['.$taxonomy.']['.$term->term_id.']" value="0.00" />';
											}
										}
									$return .= '</div>';
								}
							}
						}
					$return .= '</div>';
					$return .= '<button id="update-flms-variations" class="button button-secondary mt-5">Update Variations</button>';
					
				$return .= '</div>';

				//variations
				$return .= '<div class="settings-field">';
					$return .= '<p class="description">Set your course prices below</p>';
					//show the variations
					$return .= '<div>';
						/*$product_id = get_post_meta($post->ID,'flms_woocommerce_product_id',true);
						if($product_id == '') {
							//there is no product
							$return .= '<button id="create-course-product" class="button button-secondary">Generate Variations</button>';
						} else {
							$product = wc_get_product( $product_id );
							$atts = $product->get_attributes();
							//$return .= print_r($atts,true);
							$variations = $product->get_children();
							if(!empty($variations)) {
								foreach($variations as $variation_id) {
									$variation = wc_get_product($variation_id);
									$return .= $variation->get_name() . ' '.$variation->get_regular_price().' '.$variation->get_sale_price().'<br>';
								}
							}
						}*/

						$woo = new FLMS_Module_Woocommerce();
						$return .= '<div id="course_product_variations">';
							$return .= $woo->get_course_product_variations($post->ID, $attribute_ids);
						$return .= '</div>';
					$return .= '</div>';
				$return .= '</div>';
			$return .= '</div>';
		} else {
			$return .= 'Please enable an ecommerce module to sell your courses.';
		}
		return $return;
	}

	public function get_version_options($layout) {
		$course_id = get_the_ID();
		$return = '<div class="version-options">';
			$active_version = get_post_meta($course_id,'flms_course_active_version',true);
			$versions = get_post_meta($course_id,'flms_version_content',true);
			if(!is_array($versions)) {
				$versions = array();
				$active_version = 1;
				$versions[$active_version] = array(
					'version_name' => 'Version 1',
					'version_permalink' => 'version-1',
				);
			}

			global $flms_latest_version, $flms_course_version_content;
			//echo '<pre>'.print_r($flms_course_version_content[$flms_latest_version], true).'</pre>';
			//Sort by descending value (krsort because we use v1,v2.. to store the data instead of using numbers)
			krsort($versions);
			if(is_array($versions)) {
				$return .= '<div class="settings-field">';
					$return .= '<div class="setting-field-label">';
						$return .= 'Version Name ';
						if($layout == 'grid') {
							$return .= '<div class="flms-tooltip" data-tooltip="Change the name of this version, used when a newer course version is published"></div>';
						}
					$return .= '</div>';
					if($layout != 'grid') {
						$return .= '<p class="description">Change the name of this version, used when a newer course version is published</p>';
					}
					$return .= '<input name="version-name" value="'.$versions["{$active_version}"]['version_name'].'" type="text" />';
				$return .= '</div>';

				$permalink = trailingslashit(get_permalink($course_id));
				$return .= '<div class="settings-field">';
					$return .= '<div class="setting-field-label">';
						$return .= 'Version Permalink ';
						if($layout == 'grid') {
							$return .= '<div class="flms-tooltip" data-tooltip="Change the permalink in the url, used when a newer course version is published. Current url: '.$permalink.'"></div>';
						}
					$return .= '</div>';
					if($layout != 'grid') {
						$return .= '<p class="description">Change the permalink in the url, used when a newer course version is published. Current url: '.$permalink.'</p>';
					}
					$return .= '<input name="version-permalink" value="'.$versions["{$active_version}"]['version_permalink'].'" type="text" />';
				$return .= '</div>';

				$return .= '<div class="settings-field">';
					$return .= '<div class="setting-field-label">';
						$return .= 'Version Status ';
						$return .= '<div class="flms-tooltip" data-tooltip="Draft course versions are not visible to enrollees."></div>';
					$return .= '</div>';
					if($layout != 'grid') {
						$return .= '<p class="description"></p>';
					}
					$status_value = 'draft';
					if(isset($versions["{$active_version}"]['version_status'])) {
						$status_value = $versions["{$active_version}"]['version_status'];
					}
					$options = array(
						'draft' => 'Draft',
						'publish' => 'Published',
					);
					$return .= '<select name="version-status">';
						foreach($options as $k => $v) {
							$return .= '<option value="'.$k.'"';
							if($k == $status_value) {
								$return .= ' selected';
							}
							$return .= '>'.$v.'</option>';
						}
					$return .= '</select>';
					
				$return .= '</div>';
				$return .= $this->get_course_settings();
				if(flms_is_module_active('course_numbers')) {
					$return .= '<div class="settings-field">';
						$return .= '<div class="setting-field-label">Course number';
						if($layout == 'grid') {
							$return .= ' <div class="flms-tooltip" data-tooltip="The global course number for this version"></div>';
						}
						$return .= '</div>';
						if($layout != 'grid') {
							$return .= '<p class="description">The global course number for this version</p>';
						}
						$default = '';
						if(isset($versions["$active_version"]['course_numbers']['global'])) {
							$default = $versions["$active_version"]['course_numbers']['global'];
						}
						$return .= '<input type="text" name="global_course_number" placeholder="Course number" value="'.$default.'" />';
					$return .= '</div>';
						
				}
				
				if(count($versions) > 1) {
					$return .= '<div class="settings-field field-separator"></div>';
					
					$return .=  '<div class="settings-field breakout-grid">';
					$return .=  '<button id="delete-flms-version" class="button button-primary" data-version="'.$active_version.'">Delete this course version</button>';
					$return .= '<p><strong>Warning:</strong> Deleting a course version <strong>cannot</strong> be undone.</p>';
					$return .=  '</div>';
				}
			}
		$return .= '</div>';
		return $return;
	}
	public function get_lesson_list_html($post_id = 0, $post_type = null, $parent_id = 0, $ajax = false, $wrap = false) {
		$editing_post_type = '';
		if(isset($_GET['post'])) {
			$current_post_id = absint($_GET['post']);
			$editing_post_type = get_post_type($editing_post_type);
		}
		global $flms_course_id, $flms_active_version;
		$lesson_list = '';
		if($post_id > 0) {
			
			if($wrap) {
				$lesson_list .= '<ul id="flms-content-list-'.$post_type.'-'.$post_id.'" class="ui-sortable post-type-'.$post_type.'">';
			}
			$default = '';
			if($post_type == 'flms-lessons') {
				$default = 'is-active';
				$sample = false;
				$sample_meta_values = get_post_meta($post_id,'flms_is_sample_lesson');
				//print_r($sample_meta_values);
				if(is_array($sample_meta_values)) {
					$sample_meta_key = "$flms_course_id:$flms_active_version";
					if(in_array($sample_meta_key,$sample_meta_values)) {
						$sample = true;
					}
				}
			} else if ($editing_post_type == 'flms-topics') {
				$default = 'is-active';
			}
			$lesson_list .= '<li data-'.$post_type.'-id="'.$post_id.'" class="'.$default;
			if($editing_post_type == $post_type) {
				$lesson_list .= ' current-post-type';
			}
			$lesson_list .= '">';
				if($editing_post_type != $post_type) {
					$lesson_list .= '<div class="item-header">';
						$lesson_list .= '<div class="handle"></div>';
						
						
						$lesson_list .= '<div class="title">';
						$lesson_list .= get_the_title($post_id); // .' - '.$post_id;
						if($post_type == 'flms-lessons') {
							if($sample) {
								$lesson_list .= ' (Sample Lesson)';
							}
						}
						$lesson_list .= '<span class="description">ID: '.$post_id.'</span>';
						$lesson_list .= '<input type="hidden" name="selected-'.$post_type.'';
						if($parent_id > 0) {
							$lesson_list .= '['.$parent_id.']';
						}
						$lesson_list .= '[]" value="'.$post_id.'" />';
						/*(if($post_type != 'flms-exams' && $editing_post_type != 'flms-topics') {
							$lesson_list .= '<div class="toggle"></div>';
						}*/
						if($post_type == 'flms-lessons') {
							$lesson_list .= '<div class="toggle"></div>';
						}
						$lesson_list .= '</div>'; //get_post_type($post_id).':
						$lesson_list .= '<div class="edit">';
							$lesson_list .= '<a href="'.get_edit_post_link( $post_id ).'" target="_blank"></a>';
						$lesson_list .= '</div>';
						$lesson_list .= '<div class="remove remove-post-from-course"></div>';
					$lesson_list .= '</div>';
				}
				$lesson_list .= '<div class="item-detail">';
					if($post_type == 'flms-lessons') {
						$lesson_list .= $this->list_lesson_topics($post_id, $ajax);
						$lesson_list .= $this->create_content_modal('flms-topics', $post_id, true);
						$lesson_list .= $this->get_post_type_exams($post_id, $ajax);
						$lesson_list .= $this->lesson_sample_option($post_id, $sample);
					} else if($post_type == 'flms-topics') {
						//$lesson_list .= $this->get_post_type_exams($post_id, $ajax); //uncomment to allow exams in topics
					}
				$lesson_list .= '</div>';
			$lesson_list .= '</li>';
			if($wrap) {
				$lesson_list .= '</ul>';
			}

		} 
		return $lesson_list;
	}

	public function lesson_sample_option($post_id, $sample) {
		$tooltip = 'Sample lessons allow non-enrollees to preview course content before enrolling.';
		$checked = '';
		if($sample) {
			$checked = ' checked="checked"';
		}
		$sample = '<div class="flex align-center">';
		$sample .= '<p><label><input type="checkbox" name="flms-sample-lessons[]" value="'.$post_id.'" '.$checked.' /> Sample Lesson</label></p>';
		$sample .= '<div class="flms-tooltip" data-tooltip="'.$tooltip.'"></div>';
		$sample .= '</div>';
		return $sample;
	}

	/**
	 * Show settings section to add lessons to a course
	 */
	public function list_course_lessons() {
		global $post, $flms_active_version;
		
		$course = new FLMS_Course($post->ID);
		$lessons = $course->get_lessons();

		$lesson_list = '';
		$lesson_post_fields = '';
		if(!empty($lessons)) {
			foreach($lessons as $lesson) {
				$lesson_list .= $this->get_lesson_list_html($lesson,'flms-lessons');
			}
		} else {
			//do something for no lessons
		}
		
		$callback = '<div>';
			$callback .= '<ul class="sortable-lessons" id="flms-content-list-flms-lessons-'.get_the_ID().'">';
			if($lesson_list != '') {
				$callback .= $lesson_list;
			}
			$callback .= '</ul>';
			$callback .= $this->create_content_modal('flms-lessons', get_the_ID());
		$callback .= '</div>';
		$callback .= '<input type="hidden" name="flms-post-type" value="'.get_post_type($post->ID).'" />';
		return $callback;
	}

	/**
	 * Show settings section to add lessons to a course
	 */
	public function list_lesson_topics($lesson_id, $all = false) {
		global $post;
		$lessons = array();
		if(!$all) {
			$course_id = get_post_meta($lesson_id, 'flms_course', true);
			$active_version = get_post_meta($course_id,'flms_course_active_version',true);
			if($active_version != '') {
				$versions = get_post_meta($lesson_id,'flms_version_content',true);	
				if(is_array($versions)) {
					foreach($versions as $k => $v) {
						if($k == $active_version) {
							if(isset($v["lesson_topics"])) {
								$lessons = $v["lesson_topics"];
							} else {
								$lessons = array();
							}
						}
					}
				}
			}
		} else {
			//find all topics that are associated with a lesson
			$args = array(
				'post_type' => 'flms-topics',
				'posts_per_page' => -1,
				'post_status' => 'any',
				'meta_query' => array(
					array(
						'key' => 'flms-lesson',
						'value' => $lesson_id,
						'compare' => '=',
					),
				),
			);
			$query = new WP_Query($args);
			if ($query->have_posts()) {
				while ($query->have_posts()) {
					$query->the_post();
					$lessons[] = get_the_ID();
				}
			}
			wp_reset_postdata();
		}
		
		$lesson_list = '';
		if(!empty($lessons)) {
			foreach($lessons as $lesson) {
				if($lesson > 0) {
					$lesson_list .= $this->get_lesson_list_html($lesson,'flms-topics',$lesson_id, $all); 
				}
			}
		} else {
			//do something for no lessons
		}
		
		$callback = '<div>';
			$callback .= '<ul class="sortable-topics" id="flms-content-list-flms-topics-'.$lesson_id.'" data-parent-id="'.$lesson_id.'">';
			if($lesson_list != '') {
				$callback .= $lesson_list;
			}
			$callback .= '</ul>';
		$callback .= '</div>';
		return $callback;
	}

	/**
	 * Course settings for the lessons and topics post types
	 */
	public function flms_lessons_metabox() {
		global $post, $flms_settings;
		$lesson_name = 'Lesson';
		if(isset($flms_settings['labels']["lesson_singular"])) {
			$lesson_name = $flms_settings['labels']["lesson_singular"];
		}
		$metabox_fields = array(
			'course-content' => array(
				'label' => "$lesson_name Content",
				'id' => 'lesson-content',
				'description' => '',
				'tooltip' => '',
				'callback' => $this->output_lesson_content()
			),
			'course-access' => array(
				'label' => 'Lesson Video',
				'id' => 'lesson-video',
				'description' => '',
				'tooltip' => '',
				'layout' => 'grid',
				'callback' => $this->output_lesson_video_manager()
			),
		);
		$this->flms_settings_output($metabox_fields);
	}

	/**
	 * Course settings for the lessons and topics post types
	 */
	public function flms_topics_metabox() {
		global $post, $flms_settings;
		$lesson_name = 'Topic';
		if(isset($flms_settings['labels']["topic_singular"])) {
			$lesson_name = $flms_settings['labels']["topic_singular"];
		}
		$metabox_fields = array(
			'course-content' => array(
				'label' => "$lesson_name Content",
				'id' => 'lesson-content',
				'description' => '',
				'tooltip' => '',
				'callback' => $this->output_topic_content()
			),
			/*'course-access' => array(
				'label' => 'Lesson Access',
				'id' => 'lesson-access',
				'description' => '',
				'tooltip' => '',
				'callback' => $this->output_lesson_access()
			),*/
		);
		$this->flms_settings_output($metabox_fields);
	}

	public function output_lesson_content() {
		global $post;
		$callback = $this->get_lesson_list_html($post->ID, $post_type = 'flms-lessons', 0, false, true);
		$callback .= '<input type="hidden" name="flms-post-type" value="'.get_post_type($post->ID).'" />';
		return $callback;
	}

	public function output_lesson_video_manager() {
		wp_enqueue_media();
		wp_enqueue_script( 'flms-media-uploader');
		global $post;
		$post_type = get_post_type($post->ID);
		$course_id = flms_get_course_id($post->ID);
		$active_version = $this->get_course_editing_version($course_id);
		$versioned_content = get_post_meta($post->ID,'flms_version_content',true);
		if(isset($versioned_content["{$active_version}"]["video_settings"])) {
			$video_settings = $versioned_content["{$active_version}"]["video_settings"];
		} else {
			$video_settings = flms_get_video_settings_default_fields();
		}
		//print_r($video_settings);
		$callback = '';
		//if($editing_post_type != 'flms-courses' && ($post_type == 'flms-lessons' || $post_type == 'flms-topics')) {
			global $flms_settings;
			if($post_type == 'flms-lessons') {
				$pt_label = $flms_settings['labels']['lesson_singular'];
			} else {
				$pt_label = $flms_settings['labels']['topic_singular'];
			}
			//$callback = '<div class="setting-area-content layout-grid is-active">';
				/*$uploads_dir = wp_get_upload_dir();
				$placeholder = $uploads_dir['url'].'/your-video.mp4';*/
			

			$placeholder = 'Video url or iframe';
			$url = '';
			if(isset($video_settings['video_url'])) {
				$url = $video_settings['video_url'];
			}
			$callback .= '<div class="settings-field align-label-top">';
				$callback .= '<div class="setting-field-label">'.$pt_label.' video <div class="flms-tooltip" data-tooltip="Embed a video after the content. Currently supports Youtube, Vimeo and local videos."></div></div>';
				$callback .= '<div class="flex column gap-sm">';
					$callback .= '<textarea name="flms-video-url" placeholder="'.$placeholder.'" id="flms-video-url">'.$url.'</textarea>';
					$callback .= '<button id="flms-upload-media" class="button button-primary align-self-start" data-media-uploader-target="#flms-video-url">Upload</button>';
				$callback .= '</div>';
			$callback .= '</div>';

			//make it lowercase now that it's not part of the label and only used in descriptions
			$pt_label = strtolower($pt_label);

			$ratio = 'widescreen';
			$video_ratios = array(
				'widescreen' => '16:9 (widescreen)',
				'fullscreen' => '4:3 (fullscren)',
				'thirtyfivemm' => '3:2 (35mm)',
				'cinematic-widescreen' => '21:9 (cinematic widescreen)',
				'square' => '1:1 (square)',
				'instagram' => '4:5 (instagram)',
				'vertical' => '9:16 (vertical)',
			);
			if(isset($video_settings['video_ratio'])) {
				$ratio = $video_settings['video_ratio'];
			}
			$callback .= '<div class="settings-field">';
				$callback .= '<div class="setting-field-label">Aspect ratio <div class="flms-tooltip" data-tooltip="Selecting an aspect ratio will maintain it&rsquo;s responsive size."></div></div>';
				$callback .= '<select name="flms-video-ratio">';
				foreach($video_ratios as $k => $v) {
					$callback .= '<option value="'.$k.'"';
					if($k == $ratio) {
						$callback .= ' selected';
					}
					$callback .= '>'.$v.'</option>';
				}
				$callback .= '</select>';
			$callback .= '</div>';

			$controls = 1;
			$control_options = array(
				'0' => 'Disabled',
				'1' => 'Enabled',
			);
			if(isset($video_settings['controls'])) {
				$controls = $video_settings['controls'];
			}
			$callback .= '<div class="settings-field">';
				$callback .= '<div class="setting-field-label">Video controls <div class="flms-tooltip" data-tooltip="Show or hide the default controls for the video. A play/pause button will always be present."></div></div>';
				$callback .= '<select name="flms-video-controls">';
				foreach($control_options as $k => $v) {
					$callback .= '<option value="'.$k.'"';
					if($k == $controls) {
						$callback .= ' selected';
					}
					$callback .= '>'.$v.'</option>';
				}
				$callback .= '</select>';
			$callback .= '</div>';

			$enabled = 0;
			$video_options = array(
				'0' => 'Disabled',
				'1' => 'Enabled',
			);
			if(isset($video_settings['force_full_video'])) {
				$enabled = $video_settings['force_full_video'];
			}
			$callback .= '<div class="settings-field">';
				$callback .= '<div class="setting-field-label">Full watch required <div class="flms-tooltip" data-tooltip="Require the video to be watched in it&rsquo;s entirety in order to complete the '.$pt_label.'. Enabling will disable controls. Please note, browser support for Picture in Picture can enable users to still skip through YouTube and Vimeo embeds."></div></div>';
				$callback .= '<select name="flms-video-status">';
				foreach($video_options as $k => $v) {
					$callback .= '<option value="'.$k.'"';
					if($k == $enabled) {
						$callback .= ' selected';
					}
					$callback .= '>'.$v.'</option>';
				}
				$callback .= '</select>';
			$callback .= '</div>';

			$autocomplete = 0;
			$auto_options = array(
				'0' => 'Disabled',
				'1' => 'Enabled',
			);
			if(isset($video_settings['autocomplete'])) {
				$autocomplete = $video_settings['autocomplete'];
			}
			$callback .= '<div class="settings-field">';
				$callback .= '<div class="setting-field-label">Autocomplete lesson <div class="flms-tooltip" data-tooltip="Automatically complete the '.$pt_label.' when the video finishes and move to the next section."></div></div>';
				$callback .= '<select name="flms-video-autocomplete">';
				foreach($auto_options as $k => $v) {
					$callback .= '<option value="'.$k.'"';
					if($k == $autocomplete) {
						$callback .= ' selected';
					}
					$callback .= '>'.$v.'</option>';
				}
				$callback .= '</select>';
			$callback .= '</div>';

			
			//$callback .= '</div>';

		//}
		return $callback;		
	}

	public function update_video_content($post_id, $active_version, $postdata) {
		if(isset($postdata['flms-video-url'])) {
			$video = wp_kses($postdata['flms-video-url'],'flms-video');
		} else {
			$video = '';
		}
		$lesson_settings = array(
			'video_url' => $video,
			'video_ratio' => sanitize_text_field($postdata['flms-video-ratio']),
			'controls' => absint($postdata['flms-video-controls']),
			'force_full_video' => absint($postdata['flms-video-status']),
			'autocomplete' => absint($postdata['flms-video-autocomplete']),
		);
		$versioned_content = get_post_meta($post_id,'flms_version_content',true);
		$versioned_content["{$active_version}"]["video_settings"] = $lesson_settings;
		update_post_meta($post_id,'flms_version_content',$versioned_content);
	}

	public function output_topic_content() {
		global $post;
		//$callback = $this->get_lesson_list_html($post->ID, $post_type = 'flms-topics', 0, false, true);
		$callback = '<input type="hidden" name="flms-post-type" value="'.get_post_type($post->ID).'" />';
		return $callback;
	}

	/**
	 * Meta box for exams
	 */
	public function flms_lessons_exams_metabox() {
		global $post, $flms_settings;
		$metabox_fields = array(
			'questions' => array(
				'label' => 'Questions',
				'id' => 'questions',
				'description' => '',
				'tooltip' => '',
				'callback' => $this->exam_question_management()
			),
		);
		$this->flms_settings_output($metabox_fields);
		
	}

	public function exam_question_management() {
		
	}

	/** 
	 * Get post type exam IDS
	 */
	public function get_post_type_exam_ids($post_id = 0, $all = false) {
		if($post_id == 0) {
			global $post;
			$post_id = $post->ID;
		} else {
			$post = get_post($post_id);
		}
		$lessons = array();
		if(!$all) {
			$course_id = flms_get_course_id($post->ID);
			$active_version = $this->get_course_editing_version($course_id);
			if($active_version != '') {
				$versions = get_post_meta($post_id,'flms_version_content',true);	
				if(is_array($versions)) {
					if(isset($versions["{$active_version}"]["post_exams"])) {
						$lessons = $versions["{$active_version}"]["post_exams"];
					} else {
						$lessons = array();
					}
				}
			}
		} else {
			//find all exams that are associated with a lesson
			$args = array(
				'post_type' => 'flms-exams',
				'posts_per_page' => -1,
				'post_status' => 'any',
				'meta_query' => array(
					array(
						'key' => 'flms_exam_parent_id',
						'value' => $post_id,
						'compare' => '=',
					),
				),
			);
			$query = new WP_Query($args);
			if ($query->have_posts()) {
				while ($query->have_posts()) {
					$query->the_post();
					$lessons[] = get_the_ID();
				}
			}
			wp_reset_postdata();
		}
		return $lessons;

	}
	/**
	 * Show settings section for lesson / topic
	 */
	public function get_post_type_exams($post_id = 0, $all = false) {
		
		$lessons = $this->get_post_type_exam_ids($post_id, $all);
		
		$lesson_list = '';
		if(!empty($lessons)) {
			foreach($lessons as $lesson) {
				$lesson_list .= $this->get_lesson_list_html($lesson,'flms-exams', $post_id); //show course
			}
		} else {
			//do something for no lessons
		}
		$callback = '<ul class="sortable-exams" id="flms-content-list-flms-exams-'.$post_id.'" data-parent-id="'.$post_id.'">';
		if($lesson_list != '') {
			$callback .= $lesson_list;
		}
		$callback .= '</ul>';
		$callback .= $this->create_content_modal('flms-exams', $post_id, $post_id);
		return $callback;
	}


	public function create_content_modal($post_type, $course_id, $has_parent = false) {
		$label = flms_get_post_type_label($post_type);
		$callback = '<div class="add-new-content">';
			$callback .= '<button class="button button-primary" data-modal-trigger="#add-'.$course_id.'-'.$post_type.'-content" data-type="'.$post_type.'">Add '.$label.'</button>';
		$callback .= '</div>';
		$callback .= '<div id="add-'.$course_id.'-'.$post_type.'-content" class="modal">';
			$callback .= '<div class="modal-content">';
				$callback .= '<div class="toggle-div is-active" id="existing-'.$post_type.'">';
					$callback .= '<div class="flex">';
					$callback .= '<input type="text" id="exams-input" class="autocomplete search-existing-content" placeholder="Search for a';
					if($post_type == 'flms-exams') {
						$callback .= 'n';
					}
					$callback .= ' '.strtolower($label).'" data-type="'.$post_type.'" data-course="'.$course_id.'" data-has-parent="'.$has_parent.'" />';	
					$callback .= '</div>';
					$callback .= '<div class="or text-center">Or</div>';
				$callback .= '</div>';
				$callback .= '<div class="toggle-div" id="create-'.$post_type.'">';
				$callback .= '<div class="flex">';
					$callback .= '<input id="new-'.$post_type.'-name" placeholder="'.$label.' title" type="text" />';
					$callback .= '<span class="spinner"></span>';
					$callback .= '<button id="insert-new-'.$post_type.'" class="create-content-submit button button primary" data-post-type="'.$post_type.'" data-course-id="'.$course_id.'">Create '.$label.'</button>';
				$callback .= '</div>';
				$callback .= '</div>';
				$callback .= '<button class="button button-primary text-center toggle-top" data-toggle-trigger="#existing-'.$post_type.',#create-'.$post_type.'">Create New '.$label.'</button>';
				
				$callback .= '<div class="modal-footer">';
				$callback .= '<div class="cancel text-center"></div>';
				$callback .= '</div>';
			$callback .= '</div>';
		$callback .= '</div>';
		return $callback;
	}
	

	public function get_course_editing_version($course_id) {
		$active_version = get_post_meta($course_id,'flms_course_active_version',true);
		if($active_version == '') {
			$active_version = 1;
			update_post_meta($course_id,'flms_course_active_version',$active_version);
		}
		return $active_version;
	}
	/**
	 * Save the lessons to the course when saving the page
	 */
	public function save_course_content($post_id) {
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
		if (!current_user_can('edit_post', $post_id)) return;
		remove_action('save_post', array($this,'save_course_content'));
		$post = get_post($post_id);
		//update course for a lesson
		if(isset($_POST['flms-post-type'])) {
			$course_id = '';
			if($_POST['flms-post-type'] == 'flms-courses') {
				$course_id = $post->ID;
				$active_version = $this->get_course_editing_version($course_id);
				
				$selected_lessons = array();
				if (isset($_POST['selected-flms-lessons']) && is_array($_POST['selected-flms-lessons'])) {
					// Sanitize and save the selected lesson IDs as a custom field
					$selected_lessons = array_map('absint', $_POST['selected-flms-lessons']);
				}
				$remove = array();
				if(isset($_POST['deselected-flms-lessons']) && is_array($_POST['deselected-flms-lessons'])) {
					$remove = array_map('absint', $_POST['deselected-flms-lessons']);
				}
				$this->update_course_lessons($post_id, $selected_lessons, true, $remove, $active_version);

				//iterate child elements of lessons
				$lessons_to_process = array_merge($selected_lessons, $remove);
				$this->update_post_lessons_and_topics($lessons_to_process, $active_version);
				
				//update course exams
				$exams_add = array();
				if (isset($_POST['selected-flms-exams']["$post_id"]) && is_array($_POST['selected-flms-exams']["$post_id"])) {
					$exams_add = array_map('absint', $_POST['selected-flms-exams']["$post_id"]);
				}
				$exams_remove = array();
				if (isset($_POST['deselected-flms-exams']["$post_id"]) && is_array($_POST['deselected-flms-exams']["$post_id"])) {
					$exams_remove = array_map('absint', $_POST['deselected-flms-exams']["$post_id"]);
				}
				$this->update_exam_associations($post_id, $exams_add, true, $exams_remove, $active_version); 

				$version_post_content = '';
				if(isset($_POST["$post_id-post-content"])) {
					$version_post_content = $_POST["$post_id-post-content"];
					$this->update_version_content($post_id, $active_version, $version_post_content);
				}

				$version_preview = '';
				if(isset($_POST["$post_id-preview-content"])) {
					$version_preview = $_POST["$post_id-preview-content"];	
					$this->update_version_preview($post_id, $active_version, $version_preview);
				}

				//update version name and slug
				$version_name = '';
				if(isset($_POST['version-name'])) {
					$version_name = $_POST['version-name'];
				}
				$version_permalink = '';
				if(isset($_POST['version-permalink'])) {
					$version_permalink = $_POST['version-permalink'];
				} 
				$version_status = 'draft';
				if(isset($_POST['version-status'])) {
					$version_status = $_POST['version-status'];
				}
				$this->update_version_attributes($post_id, $active_version, $version_name, $version_permalink, $version_status);


				//update course access
				$course_access = '';
				if(isset($_POST['course_access'])) {
					$course_access = $_POST['course_access'];
				}
				$course_progression = '';
				if(isset($_POST['course_progression'])) {
					$course_progression = $_POST['course_progression'];
				} 
				$this->update_version_access($post_id, $active_version, $course_access, $course_progression);

				//Update sample lessons
				$sample_lessons = array();
				if (isset($_POST['flms-sample-lessons']) && is_array($_POST['flms-sample-lessons'])) {
					$sample_lessons = array_map('absint', $_POST['flms-sample-lessons']);
				}
				$this->update_course_sample_lessons($post_id, $sample_lessons, $active_version); 

				if(flms_is_module_active('course_credits')) {
					$course_credits = new FLMS_Module_Course_Credits();
					$course_credits->update_course_credits($post_id, $active_version, $_POST);
				}

				if(flms_is_module_active('course_numbers')) {
					$course_numbers = new FLMS_Module_Course_Numbers();
					$course_numbers->update_course_numbers($post_id, $active_version, $_POST);
				}

				if(flms_is_module_active('course_metadata')) {
					$course_numbers = new FLMS_Module_Course_Metadata();
					$course_numbers->update_course_metadata($post_id, $active_version, $_POST);
				}

				if(flms_is_module_active('acf')) {
					$acf = new FLMS_Module_ACF();
					$acf->save_version_fields($post_id, $active_version, $_POST);
				}

				if(flms_is_module_active('course_certificates')) {
					if (isset($_POST['flms-course-certificates']) && is_array($_POST['flms-course-certificates'])) {
						$certificates = array_map('absint', $_POST['flms-course-certificates']);
						$course = new FLMS_Course($course_id);
						global $flms_active_version;
						$flms_active_version = $active_version;
						$course->update_course_version_field('course_certificates', $certificates);
					}
				}

				if(flms_is_module_active('course_materials')) {
					$course_materials = new FLMS_Module_Course_Materials();
					$course_materials->update_course_materials($post_id, $active_version, $_POST);
				}

				if(flms_is_module_active('woocommerce')) {
					$woo = new FLMS_Module_Woocommerce();
					$woo->save_course_product_settings($post_id, $_POST);
					$woo->create_update_course_product($course_id);
				}

				if(flms_is_module_active('course_taxonomy_royalties')) {
					$royalties = new FLMS_Module_Course_Taxonomies();
					$royalties->save_royalty_amounts($post_id, $_POST);
				}

				$this->update_course_query_metadata($post_id);

			} else if($_POST['flms-post-type'] == 'flms-lessons') {
				$course_id = flms_get_course_id($post->ID);
				$active_version = $this->get_course_editing_version($course_id);
				$this->update_post_lessons_and_topics($post->ID, $active_version);

				//update lesson exams
				$exams_add = array();
				if (isset($_POST['selected-flms-exams']["$post_id"]) && is_array($_POST['selected-flms-exams']["$post_id"])) {
					$exams_add = array_map('absint', $_POST['selected-flms-exams']["$post_id"]);
				}
				$exams_remove = array();
				if (isset($_POST['deselected-flms-exams']["$post_id"]) && is_array($_POST['deselected-flms-exams']["$post_id"])) {
					$exams_remove = array_map('absint', $_POST['deselected-flms-exams']["$post_id"]);
				}
				$this->update_exam_associations($post_id, $exams_add, true, $exams_remove, $active_version); 

				$this->update_video_content($post_id, $active_version, $_POST);

			} else if($_POST['flms-post-type'] == 'flms-topics') {
				$course_id = flms_get_course_id($post->ID);
				$active_version = $this->get_course_editing_version($course_id);
				
				//update lesson exams
				$exams_add = array();
				if (isset($_POST['selected-flms-exams']["$post_id"]) && is_array($_POST['selected-flms-exams']["$post_id"])) {
					$exams_add = array_map('absint', $_POST['selected-flms-exams']["$post_id"]);
				}
				$exams_remove = array();
				if (isset($_POST['deselected-flms-exams']["$post_id"]) && is_array($_POST['deselected-flms-exams']["$post_id"])) {
					$exams_remove = array_map('absint', $_POST['deselected-flms-exams']["$post_id"]);
				}
				$this->update_exam_associations($post_id, $exams_add, true, $exams_remove, $active_version); 

			} else if($_POST['flms-post-type'] == 'flms-exams') {
				//$post = get_post($post_id);
				$course_id = flms_get_course_id($post->ID);
				$active_version = $this->get_course_editing_version($course_id);
				$this->update_exam_settings($post_id, $active_version, $_POST);
				
				
			} else if($_POST['flms-post-type'] == 'flms-certificates') {
				if(flms_is_module_active('course_certificates')) {
					$course_certificates = new FLMS_Module_Course_Certificates();
					$course_certificates->save_settings($post->ID, $_POST);
				}
			} else if($_POST['flms-post-type'] == 'flms-groups') {
				if(flms_is_module_active('groups')) {
					$groups = new FLMS_Module_Groups();
					$groups->save_settings($post->ID, $_POST);
				}
			}

			//clear course steps transient
			if($course_id != '') {
				$course = new FLMS_Course($course_id);
				global $flms_active_version;
				$flms_active_version = $active_version;
				$course->update_course_steps();
			}
		
			
		}
		//add hook back
		//add_action('save_post', array($this,'save_course_content'));
	}

	public function update_course_query_metadata($course_id) {
		global $wpdb;
		$table = FLMS_COURSE_QUERY_TABLE;
		
		//remove old data
		$wpdb->delete( $table, array( 'course_id' => $course_id ) );
		
		//update with new data
		$meta_keys = array(
			'course_id',
			'meta_key',
			'meta_value',
		);
		$title = get_the_title($course_id);
		$time = current_time('mysql');
		$values = array();

		$course = new FLMS_Course($course_id);
		global $flms_latest_version, $flms_course_version_content;

		$values[] = array(
			$course_id,
			'course_name',
			$title
		);
		$values[] = array(
			$course_id,
			'last_updated',
			$time,
		);
		if(isset($flms_course_version_content[$flms_latest_version])) {
			$latest_data = $flms_course_version_content[$flms_latest_version];
			if(isset($latest_data['course_credits'])) {
				$credits_sort = array();
				foreach($latest_data['course_credits'] as $k => $v) {
					$credit_value = absint($v);
					if(absint($v) > 0) {
						$values[] = array(
							$course_id,
							$k,
							$credit_value,
						);
						if($credit_value > 0) {
							$credits_sort[] = $credit_value;
						}
					}
				}
				if(!empty($credits_sort)) {
					$values[] = array(
						$course_id,
						'min_credits',
						min($credits_sort),
					);
					$values[] = array(
						$course_id,
						'max_credits',
						max($credits_sort),
					);
				}
			}
			if(isset($latest_data['course_metadata'])) {
				foreach($latest_data['course_metadata'] as $k => $v) {
					if($v != '') {
						$values[] = array(
							$course_id,
							$k,
							$v,
						);
					}
				}
			}
		}
		if(flms_is_module_active('course_taxonomies')) {
			$taxonomies = new FLMS_Module_Course_Taxonomies();
			$course_taxonomies = $taxonomies->get_course_taxonomies_array();
			if(!empty($course_taxonomies)) {
				foreach($course_taxonomies as $taxonomy_slug => $taxonomy_values) {
					if(is_array($taxonomy_values)) {
						foreach($taxonomy_values as $value) {
							if($value != '') {
								$values[] = array(
									$course_id,
									$taxonomy_slug,
									$value,
								);
							}
						}
					}
				}
			}
		}

		//Add product type

		$insert_array = array();
		foreach($values as $value) {
			$insert_array[] = "( '". implode("','", $value) ." ')";
		}
		$insert_string = implode(',',$insert_array);
		
		$wpdb->query("INSERT INTO $table ( " . implode(',', $meta_keys) . " ) VALUES $insert_string");
	}

	public function get_default_exam_settings() {
		global $flms_settings;
		$default_settings = array(
			'exam_type' => 'standard',
			'course_content_access' => 'open',
			'exam_attempts' => $flms_settings['exams']['exam_attempts'],
			'pass_percentage' => $flms_settings['exams']['pass_percentage'],
			'pass_points' => $flms_settings['exams']['pass_points'],
			'questions_per_page' => $flms_settings['exams']['questions_per_page'],
			'exam_start_label' => $flms_settings['labels']['exam_start_label'],
			'exam_resume_label' => $flms_settings['labels']['exam_resume_label'],
			'save_continue_enabled' => $flms_settings['exams']['save_continue_enabled'],
			'exam_is_graded' => $flms_settings['exams']['exam_is_graded'],
			'exam_is_graded_using' => $flms_settings['exams']['exam_is_graded_using'],
			'exam_attempt_action' => $flms_settings['exams']['exam_attempt_action'],
			'exam_review_enabled' => $flms_settings['exams']['exam_review_enabled'],
			'exam_label_override' => $flms_settings['labels']['exam_singular'],
			'question_select_type' => 'manual',
			'exam_questions' => array(),
			'exam_question_categories' => array()
		);
		return $default_settings;
	}

	public function update_exam_settings($post_id, $active_version, $data) {
		$settings = $this->get_default_exam_settings();
		if(isset($data['flms_exam_type'])) {
			$exam_type = sanitize_text_field($data['flms_exam_type']);
			$settings['exam_type'] = $exam_type;
		}
		if(isset($data['flms_exam_course_content_access'])) {
			$course_content_access = sanitize_text_field($data['flms_exam_course_content_access']);
			$settings['course_content_access'] = $course_content_access;
		}
		if(isset($data['flms_sample_draw_question_count'])) {
			$settings['sample-draw-question-count'] = (int) $data['flms_sample_draw_question_count'];
		}
		if(isset($data['flms_exam_attempts'])) {
			$settings['exam_attempts'] = (int) $data['flms_exam_attempts'];
		}
		if(isset($data['flms_pass_percentage'])) {
			$settings['pass_percentage'] = absint($data['flms_pass_percentage']);
		}
		if(isset($data['flms_pass_points'])) {
			$settings['pass_points'] = absint($data['flms_pass_points']);
		}
		if(isset($data['flms_questions_per_page'])) {
			$settings['questions_per_page'] = absint($data['flms_questions_per_page']);
		}
		if(isset($data['flms_time_limit'])) {
			$settings['time_limit'] = (int) $data['flms_time_limit'];
		}
		if(isset($data['flms_question_order'])) {
			$settings['question_order'] = $data['flms_question_order'];
		}
		if(isset($data['flms_start_exam_label'])) {
			$settings['exam_start_label'] = sanitize_text_field($data['flms_start_exam_label']);
		}
		if(isset($data['flms_resume_exam_label'])) {
			$settings['exam_resume_label'] = sanitize_text_field($data['flms_resume_exam_label']);
		}
		if(isset($data['flms_save_continue_enabled'])) {
			$settings['save_continue_enabled'] = sanitize_text_field($data['flms_save_continue_enabled']);
		}
		if(isset($data['flms_exam_is_graded'])) {
			$settings['exam_is_graded'] = sanitize_text_field($data['flms_exam_is_graded']);
		}
		if(isset($data['flms_exam_is_graded_using'])) {
			$settings['exam_is_graded_using'] = sanitize_text_field($data['flms_exam_is_graded_using']);
		}
		if(isset($data['flms_exam_attempt_action'])) {
			$settings['exam_attempt_action'] = sanitize_text_field($data['flms_exam_attempt_action']);
		}
		if(isset($data['flms_exam_review_enabled'])) {
			$settings['exam_review_enabled'] = sanitize_text_field($data['flms_exam_review_enabled']);
		}
		if(isset($data['flms_exam_label_override'])) {
			$settings['exam_label_override'] = sanitize_text_field($data['flms_exam_label_override']);
		}
		if($exam_type == 'standard' || $exam_type == 'sample-draw') {
			if(isset($data['standard_question_select_type'])) {
				$question_select_type = sanitize_text_field($data['standard_question_select_type']);
				$settings['question_select_type'] = $question_select_type;
				//if($question_select_type == 'manual') {
					if (isset($data['selected-flms-questions']) && is_array($data['selected-flms-questions'])) {
						// Sanitize and save the selected lesson IDs as a custom field
						$questions = array_map('absint', $data['selected-flms-questions']);
					} else {
						$questions = array();
					}
					$settings['exam_questions'] = $questions;
					
				//} else {
					if(isset($data['standard_exam_questions']) && is_array($data['standard_exam_questions'])) {
						$categories = array_map('absint',$data['standard_exam_questions']);
					} else {
						$categories = array();
					}
					$settings['exam_question_categories'] = $categories;
				//}
			}
		} else if($exam_type == 'category-sample-draw') {
			if(isset($data['category_sample_exam_questions']) && is_array($data['category_sample_exam_questions'])) {
				$categories = array_map('absint',$data['category_sample_exam_questions']);
			} else {
				$categories = array();
			}
			$settings['exam_question_categories'] = $categories;

			$question_numbers = array();
			if(!empty($categories)) {
				foreach($categories as $category_id) {
					if(isset($data['question_count_'.$category_id]) ) {
						$question_numbers[$category_id] = absint($data['question_count_'.$category_id]);
					}		
				}
			}
			$settings['exam_question_categories_numbers'] = $question_numbers;
		} else {
			//cumulative
			if(isset($data['cumulative_exam_exams_questions']) && is_array($data['cumulative_exam_exams_questions'])) {
				$settings['cumulative_exam_questions'] = array_map('absint',$data['cumulative_exam_exams_questions']);
			}
		}

		$update = update_post_meta($post_id, "flms_exam_settings_$active_version", $settings);
	}

	public function update_course_sample_lessons($post_id, $sample_lessons, $active_version) {
		$versioned_content = get_post_meta($post_id,'flms_version_content',true);
		$lesson_meta = "$post_id:$active_version";
		if(isset($versioned_content[$active_version]['sample_lessons'])) {
			//remove old samples
			foreach($versioned_content[$active_version]['sample_lessons'] as $sample_lesson) {
				delete_post_meta($sample_lesson,'flms_is_sample_lesson',$lesson_meta);
			}
		}
		//add new samples
		$versioned_content[$active_version]['sample_lessons'] = $sample_lessons;
		if(!empty($sample_lessons)) {
			foreach($sample_lessons as $sample_lesson) {
				add_post_meta($sample_lesson,'flms_is_sample_lesson',$lesson_meta);
			}
		}
		update_post_meta($post_id,'flms_version_content',$versioned_content);
	}
	
	public function update_version_content($post_id,$active_version, $content) {
		$course_versioned_content = get_post_meta($post_id,'flms_version_content',true);
		if(!is_array($course_versioned_content)) {
			$course_versioned_content = array();
		}
		$course_versioned_content["{$active_version}"]['post_content'] = wp_kses_post($content);
		update_post_meta($post_id,'flms_version_content',$course_versioned_content);
	}

	public function update_version_preview($post_id,$active_version, $content) {
		$course_versioned_content = get_post_meta($post_id,'flms_version_content',true);
		if(!is_array($course_versioned_content)) {
			$course_versioned_content = array();
		}
		$course_versioned_content["{$active_version}"]['course_preview'] = wp_kses_post($content);
		update_post_meta($post_id,'flms_version_content',$course_versioned_content);

		//see if the_content needs to be updatyed
		if($content == '') {
			if(isset($course_versioned_content["{$active_version}"]['post_content'])) {
				$content = $course_versioned_content["{$active_version}"]['post_content'];
			}
		}
		$flms_latest_version = '';
		foreach($course_versioned_content as $k => $v) {
			if(isset($v['version_status'])) {
				$status_value = $v['version_status'];
				if($status_value == 'publish') {
					$flms_latest_version = $k;
					break;
				}
			}
		}
		if($flms_latest_version == '') {
			$flms_latest_version = array_key_first($course_versioned_content);
		}
		if($flms_latest_version == $active_version) {
			$args = array(
				'ID' => $post_id,
				'post_content' => $content
			);
			$updated = wp_update_post( $args );
		}
	}

	/**
	 * Change the course version name and permalink
	 */
	public function update_version_attributes($post_id,$active_version,$version_name,$version_permalink, $version_status) {
		$course_versioned_content = get_post_meta($post_id,'flms_version_content',true);
		if(!is_array($course_versioned_content)) {
			$course_versioned_content = array();
		}
		//dont allow empty
		if($version_name == '') {
			$version_name = $course_versioned_content["{$active_version}"]['version_name'];
		}
		$course_versioned_content["{$active_version}"]['version_name'] = sanitize_text_field($version_name);
		if($version_permalink == '') {
			$version_permalink = $course_versioned_content["{$active_version}"]['version_permalink'];
		}
		$course_versioned_content["{$active_version}"]['version_permalink'] = sanitize_title_with_dashes($version_permalink);
		
		if($version_status == '') {
			$version_status = $course_versioned_content["{$active_version}"]['version_status'];
		}
		$course_versioned_content["{$active_version}"]['version_status'] = sanitize_text_field($version_status);

		update_post_meta($post_id,'flms_version_content',$course_versioned_content);
		
	}

	/**
	 * Update version access
	 */
	public function update_version_access($post_id, $active_version, $course_access, $course_progression) {
		$course_versioned_content = get_post_meta($post_id,'flms_version_content',true);
		if(is_array($course_versioned_content)) {
			//dont allow empty
			if($course_access == '') {
				$version_name = $course_versioned_content["{$active_version}"]['course_access'];
			}
			if($course_progression == '') {
				$course_progression = $course_versioned_content["{$active_version}"]['course_progression'];
			}
			$content = array(
				'course_access' => sanitize_text_field($course_access),
				'course_progression' => sanitize_title_with_dashes($course_progression),
			);
			$course_versioned_content["{$active_version}"]["course_settings"] = $content;
			update_post_meta($post_id,'flms_version_content',$course_versioned_content);
		}
	}

	public function update_post_lessons_and_topics($lessons_to_process, $active_version) {
		if(!is_array($lessons_to_process)) {
			$lessons_to_process = array($lessons_to_process);
		}
		//process lessons
		if(!empty($lessons_to_process)) {
			foreach($lessons_to_process as $lesson_id) {
				$topics_add = array();
				if (isset($_POST['selected-flms-topics']["$lesson_id"]) && is_array($_POST['selected-flms-topics']["$lesson_id"])) {
					$topics_add = array_map('absint', $_POST['selected-flms-topics']["$lesson_id"]);
				}
				$topics_remove = array();
				if (isset($_POST['deselected-flms-topics']["$lesson_id"]) && is_array($_POST['deselected-flms-topics']["$lesson_id"])) {
					$topics_remove = array_map('absint', $_POST['deselected-flms-topics']["$lesson_id"]);
				}
				$this->update_lesson_topics($lesson_id, $topics_add, true, $topics_remove, $active_version);
				
				$exams_add = array();
				if (isset($_POST['selected-flms-exams']["$lesson_id"]) && is_array($_POST['selected-flms-exams']["$lesson_id"])) {
					$exams_add = array_map('absint', $_POST['selected-flms-exams']["$lesson_id"]);
				}
				$exams_remove = array();
				if (isset($_POST['deselected-flms-exams']["$lesson_id"]) && is_array($_POST['deselected-flms-exams']["$lesson_id"])) {
					$exams_remove = array_map('absint', $_POST['deselected-flms-exams']["$lesson_id"]);
				}
				$this->update_exam_associations($lesson_id, $exams_add, true, $exams_remove, $active_version); 

				//loop through again using the topics instead of lessons
				$topics_to_process = array_merge($topics_add, $topics_remove);
				if(!empty($topics_to_process)) {
					$this->update_post_lessons_and_topics($topics_to_process, $active_version);
				}

			}
		}
	}

	/**
	 * Clean up the post data on course or lesson deletion - this doesn't work
	 */
	public function trash_clean_course_postdata($post_id, $post) {
		global $post;
		if(!isset($post)) {
			return;
		}
		if(!in_array($post->post_type, flms_get_plugin_post_type_internal_permalinks())) {
			return;
		}
		global $wpdb;
		$table = FLMS_COURSE_QUERY_TABLE;
		//remove old data
		$wpdb->delete( $table, array( 'course_id' => $post_id ) );

		if ( $post->post_type == 'flms-lessons') {
			$lessons = array($post_id);
			$course_id = get_post_meta($post_id,'flms_course',true);
			if($course_id != '') {
				$this->update_course_lessons($course_id, array(), false, $lessons);	
			}
		} else if ($post->post_type == 'flms-courses') {
			$versioned_content = get_post_meta($post_id,'flms_version_content',true);
			if(is_array($versioned_content)) {
				foreach($versioned_content as $k => $v) {
					if(isset($v["course_lessons"])) {
						$saved_lessons = $v["course_lessons"]; 
						foreach($saved_lessons as $lesson_id) {
							delete_post_meta($lesson_id,'flms_course');
						}
					}
				}
			}
			$saved_lessons = get_post_meta($post_id, 'course_lessons');
			if(!empty($saved_lessons)) {
				foreach($saved_lessons as $lesson_id) {
					delete_post_meta($lesson_id,'flms_course');
				}
			}
		}
	}

	/**
	 * Update course lessons
	 */
	public function update_course_lessons($course_id, $lessons = array(), $replace_lessons = false, $remove = array(), $version = null, $admin_notice = false) {
		//Create array of courses to check for course versions
		if(!is_array($lessons)) {
			$lessons = array($lessons);
		}
		$old_course_ids = array();
		foreach($lessons as $lesson_id) {
			$old_course_id = get_post_meta($lesson_id,'flms_course',true);
			if(!in_array($old_course_id,$old_course_ids)) {
				$old_course_ids[] =  $old_course_id;
			}
		}
		
		//Iterate other courses and see if new posts need disassociations
		foreach($old_course_ids as $old_course_id) {
			if($course_id != $old_course_id) {
				//Remove form old course
				$versioned_content = get_post_meta($old_course_id,'flms_version_content',true);
				if(!is_array($versioned_content)) {
					$versioned_content = array();
				}
				//Check if it's anywhere else in the versions and if not then delete it
				foreach($lessons as $lesson_id) {
					$save_association = false;
					foreach($versioned_content as $k => $v) {
						$saved_lessons = $v["course_lessons"]; //revisit
						if(is_array($saved_lessons)) {
							if(in_array($lesson_id,$saved_lessons)) {
								$save_association = true;
							}
						}
					}
					if(!$save_association) {
						delete_post_meta($lesson_id,'flms_course');
					} else {
						if($admin_notice) {
							add_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );
						}
					}
				}
				
				update_post_meta($old_course_id,'flms_version_content',$versioned_content);	
			}
			
		}
		//if(!$save_association) {
		if($course_id != '') {
			//Add to new course
			$versioned_content = get_post_meta($course_id,'flms_version_content',true);
			if(!is_array($versioned_content)) {
				$versioned_content = array();
			}
			if($version == null) {
				$version = count($versioned_content); //apply to latest version
			}
			if(!isset($versioned_content["$version"]["course_lessons"])) {
				$versioned_content["$version"]["course_lessons"] = array();
			}
			if(!$replace_lessons) {
				$saved_lessons = $versioned_content["$version"]["course_lessons"];
				foreach($lessons as $lesson_id) {
					if(!in_array($lesson_id,$saved_lessons)) {
						$saved_lessons[] = $lesson_id;
					}
				}
			} else {
				$saved_lessons = $lessons;
			}
			if(!empty( $remove ) ) {
				$saved_lessons = array_diff($saved_lessons, $remove);
			}
			foreach($saved_lessons as $lesson_id) {
				update_post_meta($lesson_id,'flms_course',$course_id);
			}
			$versioned_content["{$version}"]["course_lessons"] = $saved_lessons;
			update_post_meta($course_id,'flms_version_content',$versioned_content);

			//Remove course association for removed items
			foreach($remove as $lesson_id) {
				$save_association = false;
				foreach($versioned_content as $k => $v) {
					$saved_lessons = $v["course_lessons"]; //revisit
					if(is_array($saved_lessons)) {
						if(in_array($lesson_id,$saved_lessons)) {
							$save_association = true;
						}
					}
				}
				//remove lesson exams and topics
				$lesson_versions = get_post_meta($lesson_id,'flms_version_content',true);
				if(isset($lesson_versions["$version"]["lesson_topics"])) {
					$topics = $lesson_versions["$version"]["lesson_topics"];
					$this->update_lesson_topics($lesson_id, array(), true, $topics, $version); 
				}
				if(isset($lesson_versions["$version"]["post_exams"])) {
					$this->update_exam_associations($lesson_id,array(), true, $lesson_versions["$active_version"]["post_exams"], $version);
				}
			
				if(!$save_association) {
					delete_post_meta($lesson_id,'flms_course');
				} else {
					if($admin_notice) {
						add_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );
					}
				}
			}
			
		}		
	}
	
	/**
	 * Remove lessons from a course
	 */
	public function remove_lessons_from_course($lessons, $course_id, $admin_notice = false) {
		$active_version = get_post_meta($course_id,'flms_course_active_version',true);
		if($active_version == '') {
			$active_version = 1;
			update_post_meta($course_id,'flms_course_active_version',$active_version);
		}
		$versioned_content = get_post_meta($course_id,'flms_version_content',true);
		if(!is_array($versioned_content)) {
			$versioned_content = array();
		}
		$current_lessons = $versioned_content["{$active_version}"]["course_lessons"];
		if(is_array($current_lessons)) {
			$new_lesson_array = array_diff($current_lessons, $lessons);
			$versioned_content["{$active_version}"]["course_lessons"] = $new_lesson_array;
		}
		//Check if it's anywhere else in the versions and if not then delete it
		foreach($lessons as $lesson_id) {
			$save_association = false;
			foreach($versioned_content as $k => $v) {
				$saved_lessons = $v["course_lessons"]; //revisit
				if(is_array($saved_lessons)) {
					if(in_array($lesson_id,$saved_lessons)) {
						$save_association = true;
					}
				}
			}
			if(!$save_association) {
				delete_post_meta($lesson_id,'flms_course');
			} else {
				if($admin_notice) {
					add_filter( 'redirect_post_location', array( $this, 'add_notice_query_var' ), 99 );
				}
			}
		}
		
		update_post_meta($course_id,'flms_version_content',$versioned_content);
	
	}

	/**
	 * Update all topics in lesson
	 */
	
	public function update_lesson_topics($lesson_id, $topics = array(), $replace_lessons = false, $remove = array(), $version = null, $admin_notice = false)  {
		//Create array of courses to check for course versions
		if(!is_array($topics)) {
			$topics = array($topics);
		}
		
		//update lesson version content
		$versioned_content = get_post_meta($lesson_id,'flms_version_content',true);
		if(!is_array($versioned_content)) {
			$versioned_content = array();
		}
		if($version == null) {
			$version = count($versioned_content); //apply to latest version
		}
		krsort($versioned_content);
		if(!isset($versioned_content["$version"]["lesson_topics"])) {
			$versioned_content["$version"]["lesson_topics"] = array();
		}
		if(!$replace_lessons) {
			$saved_lessons = $versioned_content["$version"]["lesson_topics"];
			if(!empty($topics)) {
				foreach($topics as $topic_id) {
					if(!in_array($topic_id,$saved_lessons)) {
						$saved_lessons[] = $topic_id;
					}
				}
			}
		} else {
			$saved_lessons = $topics;
		}
		if(!empty( $remove ) ) {
			$saved_lessons = array_diff($saved_lessons, $remove);
		}
		$versioned_content["{$version}"]["lesson_topics"] = $saved_lessons;
		update_post_meta($lesson_id,'flms_version_content',$versioned_content);
		

		foreach($remove as $topic_id) {
			delete_post_meta( $topic_id, 'flms_topic_parent_ids');
			delete_post_meta($topic_id, 'flms_course');
		}
		foreach($topics as $topic_id) {
			delete_post_meta($topic_id, 'flms_topic_parent_ids');
			delete_post_meta($topic_id, 'flms_course');
		}

		$all_topics_to_process = array_merge($topics, $remove);
		
		$course_id = flms_get_course_id($lesson_id);
		$course_versioned_content = get_post_meta($course_id,'flms_version_content',true);
		if(!is_array($course_versioned_content)) {
			$course_versioned_content = array();
		}
		$remove_new_parents = array();
		$add_new_parents = array();
		global $flms_active_version;
		foreach($course_versioned_content as $k => $v) {
			if(isset($v['course_lessons'])) {
				$lessons = $v['course_lessons'];
				if(is_array($lessons)) {
					foreach($lessons as $lesson) {
						$lesson_version_name = "$lesson:$k";
						$lesson_class = new FLMS_Lesson($lesson);
						
						$flms_active_version = $k;
						$lesson_topics = $lesson_class->get_lesson_version_topics();
						if(is_array($lesson_topics)) {
							foreach($lesson_topics as $topic_id) {
								update_post_meta($topic_id,'flms_course',$course_id);
								$existing_parents = get_post_meta( $topic_id, 'flms_topic_parent_ids',true );
								if(!is_array($existing_parents)) {
									$existing_parents = array();
								}
								if(!in_array($lesson_version_name, $existing_parents)) {
									$existing_parents[] = $lesson_version_name;
								}
								update_post_meta($topic_id, 'flms_topic_parent_ids', $existing_parents);
							}
						}
					}
				}
			}
		}
		$flms_active_version = $version;

	}


	/**
	 * Update Exam associations
	 */
	public function update_exam_associations($post_id, $exams_to_add = array(), $replace_lessons = false, $remove = array(), $version = null, $admin_notice = false) {
		//Create array of courses to check for course versions
		if(!is_array($exams_to_add)) {
			$exams_to_add = array($exams_to_add);
		}
		if(!is_array($remove)) {
			$remove = array($remove);
		}
		
		//update lesson version content
		$versioned_content = get_post_meta($post_id,'flms_version_content',true);
		if(!is_array($versioned_content)) {
			$versioned_content = array();
		}
		if($version == null) {
			$version = count($versioned_content); //apply to latest version
		}
		krsort($versioned_content);
		if(!isset($versioned_content["$version"]["post_exams"])) {
			$versioned_content["$version"]["post_exams"] = array();
		}
		if(!$replace_lessons) {
			$saved_lessons = $versioned_content["$version"]["post_exams"];
			if(!empty($exams_to_add)) {
				foreach($exams_to_add as $topic_id) {
					if(!in_array($topic_id,$saved_lessons)) {
						$saved_lessons[] = $topic_id;
					}
				}
			}
		} else {
			$saved_lessons = $exams_to_add;
		}
		if(!empty( $remove ) ) {
			$saved_lessons = array_diff($saved_lessons, $remove);
		}
		$versioned_content["{$version}"]["post_exams"] = $saved_lessons;
		update_post_meta($post_id,'flms_version_content',$versioned_content);
		
		foreach($remove as $topic_id) {
			delete_post_meta( $topic_id, 'flms_exam_parent_ids');
			delete_post_meta($topic_id, 'flms_course');
		}
		foreach($exams_to_add as $topic_id) {
			delete_post_meta($topic_id, 'flms_exam_parent_ids');
			delete_post_meta($topic_id, 'flms_course');
		}

		$all_topics_to_process = array_merge($exams_to_add, $remove);
		
		//$this_post = get_post($post_id);
		$course_id = flms_get_course_id($post_id);
		$course_versioned_content = get_post_meta($course_id,'flms_version_content',true);
		if(!is_array($course_versioned_content)) {
			$course_versioned_content = array();
		}
		global $flms_active_version;
		foreach($course_versioned_content as $k => $v) {
			if(isset($course_versioned_content[$k]['course_lessons'])) {
				$lessons = $v['course_lessons'];
				if(is_array($lessons)) {
					foreach($lessons as $lesson) {
						$lesson_versioned_content = get_post_meta($lesson,'flms_version_content',true);
						if($lesson_versioned_content == '') {
							$lesson_versioned_content = array();
							$lesson_versioned_content[1] = array();
						}
						foreach($lesson_versioned_content as $i => $j) {
							$lesson_version_name = "$lesson:$i";
							if(isset($lesson_versioned_content[$i]['post_exams'])) {
								foreach($lesson_versioned_content[$i]['post_exams'] as $exam_id) {
									$existing_parents = get_post_meta( $exam_id, 'flms_exam_parent_ids',true );
									if(!is_array($existing_parents)) {
										$existing_parents = array();
									}
									if(!in_array($lesson_version_name, $existing_parents)) {
										$existing_parents[] = $lesson_version_name;
									}
									update_post_meta($exam_id, 'flms_exam_parent_ids', $existing_parents);
								}
							}
						}
					}
				}
			}
			if(isset($course_versioned_content[$k]['post_exams'])) {
				$lesson_version_name = "$course_id:$k";
				foreach($course_versioned_content[$k]['post_exams'] as $exam_id) {
					$existing_parents = get_post_meta( $exam_id, 'flms_exam_parent_ids',true );
					if(!is_array($existing_parents)) {
						$existing_parents = array();
					}
					if(!in_array($lesson_version_name, $existing_parents)) {
						$existing_parents[] = $lesson_version_name;
					}
					update_post_meta($exam_id, 'flms_exam_parent_ids', $existing_parents);
				}
			}
		}
		$flms_active_version = $version;


	}
	
	/**
	 * Add js to query lessons and topics
	 */
	public function enqueue_flms_admin_scripts() {
		global $post, $flms_settings;
		$current_post_type = get_post_type();
		$post_types = flms_get_plugin_post_types();
		
		foreach($post_types as $post_type) {
			if($current_post_type == $post_type['internal_permalink']) {
				// Enqueue jQuery UI core
				wp_enqueue_script('jquery-ui-core');
				// Enqueue jQuery UI autocomplete
				wp_enqueue_script('jquery-ui-autocomplete');
				// Enqueue admin js that handles lesson / topic selection for courses / lessons
				wp_enqueue_script(
					'flms-admin-course-manager',
					FLMS_PLUGIN_URL . 'assets/js/admin-course-manager.js',
					array('jquery','jquery-ui-autocomplete','jquery-ui-sortable'),
					false,
					true
				);
				wp_localize_script( 'flms-admin-course-manager', 'flms_admin_course_manager', array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'settings' => $flms_settings,
					'lesson_list_html' => $this->get_lesson_list_html(),
					'post_id' => $post->ID,
				));

				//leave our loop
				break;
			}
		}
	}

	
	/**
	 * Redirect to version course page if the post isn't associated with this version
	 */
	public function redirect_empty_version_content() {
		global $post, $wp;
		
		if(!isset($post->ID) || is_admin()) {
			return;
		}
		if(!flms_is_flms_post_type($post)) {
			return;
		}
		
		//course redirect
		if($post->post_type == 'flms-courses') {
			$course = new FLMS_Course($post->ID);
			global $flms_active_version, $flms_latest_version;
			$versions = $course->get_versions();
			//if(isset($wp->query_vars['course-version'])) {
				//$version = $wp->query_vars['course-version'];
				//$active_version = get_version_index_from_slug($post->ID,$version);
				if(!isset($versions["{$flms_active_version}"])) {
					$version_count = 0;
					foreach($versions as $k => $v) {
						if(isset($versions["$k"]['version_status'])) {
							if($versions["$k"]['version_status'] == 'publish') {
								$permalink = trailingslashit( get_permalink($post->ID));
								if($version_count > 0) {
									$permalink .= $versions["$k"]['version_permalink'];
								}
								wp_redirect($permalink);
								exit;
							}
						}
						$version_count++;
					}
					wp_redirect(get_bloginfo('url'));
					exit;
					
				}
			//}
			$status = 'draft';
			if(isset($versions["{$flms_active_version}"]['version_status'])) {
				$status = $versions["{$flms_active_version}"]['version_status'];
			}
			if($status == 'draft' && !current_user_can('edit_posts')) {
				$version_count = 0;
				foreach($versions as $k => $v) {
					if(isset($versions["$k"]['version_status'])) {
						if($versions["$k"]['version_status'] == 'publish') {
							$permalink = trailingslashit( get_permalink($post->ID));
							if($version_count > 0 && $k != $flms_latest_version) {
								$permalink .= $versions["$k"]['version_permalink'];
							}
							wp_redirect($permalink);
							exit;
						}
					}
					$version_count++;
				}
				wp_redirect(get_bloginfo('url'));
				exit;
			}
			
		} else {
			
			//other types
			if($post->post_type == 'flms-lessons') {
				$parent_id = get_post_meta($post->ID,'flms_course',true);
			} else if($post->post_type == 'flms-topics') {
				$parent_id = flms_get_topic_version_parent($post->ID);
			} else if($post->post_type == 'flms-exams') {
				global $wp;
				if(isset($wp->query_vars['print-exam-id'])) {
					$post = get_post($wp->query_vars['print-exam-id']);
					//$post_id = $wp->query_vars['print-exam-id'];
				}
				$parent_id = flms_get_exam_version_parent($post->ID);
			}
			if($parent_id === 0 || $parent_id == '') {
				wp_redirect(get_bloginfo('url'));
				exit;
			} else {
				//$parent = get_post($parent_id);
				$course_id = flms_get_course_id($parent_id);
				$course = new FLMS_Course($course_id);
				$parent_versions = get_post_meta($parent_id,'flms_version_content',true);
				//if(isset($wp->query_vars['course-version'])) {
					$active_version = $course->get_active_version();
					if(!isset($parent_versions["{$active_version}"])) {
						wp_redirect(get_permalink($parent_id));
						exit;
					}
					if($post->post_type == 'flms-lessons') {
						if(!isset($parent_versions["{$active_version}"]["course_lessons"])) {
							wp_redirect(get_permalink($parent_id));
							exit;
						}
						if(is_array($parent_versions["{$active_version}"]['course_lessons'])) {
							if(!in_array($post->ID,$parent_versions["$active_version"]['course_lessons'])) {
								wp_redirect(get_permalink($parent_id));
								exit;
							}
						}
					} else if($post->post_type == 'flms-topics') {
						if(!isset($parent_versions["{$active_version}"]["lesson_topics"])) {
							wp_redirect(get_permalink($parent_id));
							exit;
						}
						if(is_array($parent_versions["{$active_version}"]['lesson_topics'])) {
							if(!in_array($post->ID,$parent_versions["$active_version"]['lesson_topics'])) {
								wp_redirect(get_permalink($parent_id));
								exit;
							}
						}
					} else if($post->post_type == 'flms-exams') {
						if(!isset($parent_versions["{$active_version}"]["post_exams"])) {
							wp_redirect(get_permalink($parent_id));
							exit;
						}
						if(is_array($parent_versions["{$active_version}"]['post_exams'])) {
							if(!in_array($post->ID,$parent_versions["$active_version"]['post_exams'])) {
								print_r($parent_versions);
						exit;
								wp_redirect(get_permalink($parent_id));
								exit;
							}
						}
					}
				//}
				
			}
		}
		
	}

	/**
	 * Output fields for settings in metaboxes
	 */
	private function flms_settings_output($metabox_fields) { ?>
		<div class="fragment-settings">
			<ul class="tab-selector">
				<?php 
				$tabct = 1;
				foreach($metabox_fields as $field_category => $field_group) { ?>
					<li id="tab-select-<?php echo $field_group['id']; ?>" class="<?php if($tabct == 1) { echo 'is-active'; } ?>">
						<button class="setting-group-button" data-group="<?php echo $field_group['id']; ?>">
							<?php echo $field_group['label']; ?>
						</button>
					</li><?php 
					$tabct++;
				} ?>		
			</ul>
			<div class="tab-content">
				<?php 
				$tabct = 1;
				foreach($metabox_fields as $field_category => $field_group) { ?>
					<div id="<?php echo $field_group['id'] ;?>" class="setting-area-content <?php if($tabct == 1) { echo 'is-active'; } ?> <?php if(isset($field_group['layout'])) { echo 'layout-'.$field_group['layout']; }?>">
						<div class="setting-area-<?php echo $field_group['id'] ;?>">
							<?php if(isset($field_group['description'])) {
								if($field_group['description'] != '') { ?>
									<div class="setting-area-desc"><?php echo $field_group['description']; ?></div>	
								<?php }
							} ?>
							<div class="setting-area-fields">
								<?php echo $field_group['callback']; ?>
							</div>
						</div>
					</div><?php 
					$tabct++;
				} ?>
			</div>
		</div><?php
	}

	public function copy_course_content($post_id, $source_version, $active_version, $versions = null ) {
		
		$versions = get_post_meta($post_id,'flms_version_content',true);
	
		if(isset($versions["$source_version"]["post_content"])) {
			$versions["$active_version"]["post_content"] = $versions["$source_version"]["post_content"];
		}

		if(isset($versions["{$source_version}"]["course_settings"])) {
			$versions["{$active_version}"]["course_settings"] = $versions["{$source_version}"]["course_settings"];
		}

		if(isset($versions["{$source_version}"]["version_status"])) {
			$versions["{$active_version}"]["version_status"] = $versions["{$source_version}"]["version_status"];
		}
		
		//make new versions for lessons
		if(isset($versions["$source_version"]["course_lessons"])) {
			$versions["$active_version"]["course_lessons"] = $versions["$source_version"]["course_lessons"];
			$lessons = $versions["$source_version"]["course_lessons"];
			foreach($lessons as $lesson_id) {
				$lesson_versions = get_post_meta($lesson_id,'flms_version_content',true);
				$lesson_versions["{$active_version}"] = $lesson_versions["$source_version"];
				update_post_meta($lesson_id,'flms_version_content',$lesson_versions);
				//see if sample lesson
				$sample = get_post_meta($lesson_id,'flms_is_sample_lesson');
				if(is_array($sample)) {
					$sample_meta_key = "$post_id:$source_version";
					if(in_array($sample_meta_key,$sample)) {
						$new_sample_meta_key = "$post_id:$active_version";
						add_post_meta($lesson_id,'flms_is_sample_lesson',$new_sample_meta_key);
					}
				}
				//update topics
				if(isset($lesson_versions["$source_version"]["lesson_topics"])) {
					$topics = $lesson_versions["$source_version"]["lesson_topics"];
					//copy topic content
					foreach($topics as $topic_id) {
						$topic_versions = get_post_meta($topic_id,'flms_version_content',true);
						if(is_array($topic_versions)) {
							$topic_versions["{$active_version}"] = $topic_versions["$source_version"];
							update_post_meta($topic_id,'flms_version_content',$topic_versions);
						}
					}
				}
				//update exams
				if(isset($lesson_versions["$source_version"]["post_exams"])) {
					$exams = $lesson_versions["$source_version"]["post_exams"];
					$lesson_versions["$active_version"]["post_exams"] = $exams;
					foreach($exams as $exam_id) {
						$exam = new FLMS_Exam($exam_id);
						$settings = get_post_meta($exam_id, "flms_exam_settings_$source_version", true);
						update_post_meta($exam_id, "flms_exam_settings_$active_version", $settings);
					}
				}
				
			}
		}
		
		//update exams
		if(isset($versions["$source_version"]["post_exams"])) {
			$exams = $versions["$source_version"]["post_exams"];
			$versions["$active_version"]["post_exams"] = $exams;
			foreach($exams as $exam) {
				$settings = get_post_meta($exam, "flms_exam_settings_$source_version", true);
				update_post_meta($exam, "flms_exam_settings_$active_version", $settings);
			}
		}

		if(flms_is_module_active('course_credits')) {
			if(isset($versions["$source_version"]["course_credits"])) {
				$versions["$active_version"]["course_credits"] = $versions["$source_version"]["course_credits"];
			}
			
		}

		update_post_meta($post_id,'flms_version_content',$versions);

		if(isset($versions["$active_version"]["course_lessons"])) {
			$lessons = $versions["$active_version"]["course_lessons"];
			foreach($lessons as $lesson_id) {
				$lesson_versions = get_post_meta($lesson_id,'flms_version_content',true);
				if(isset($lesson_versions["$active_version"]["lesson_topics"])) {
					$topics = $lesson_versions["$active_version"]["lesson_topics"];
					$this->update_lesson_topics($lesson_id, $topics, true, array(), $active_version); 
				}
				if(isset($lesson_versions["$active_version"]["post_exams"])) {
					$this->update_exam_associations($lesson_id,$lesson_versions["$active_version"]["post_exams"], true, array(), $active_version);
				}
			}
		}

		if(isset($versions["$active_version"]["post_exams"])) {
			$this->update_exam_associations($post_id,$versions["$active_version"]["post_exams"], true, array(), $active_version);
		}

		/*$course = new FLMS_Course($post_id);
		global $flms_active_version;
		$flms_active_version = $source_version;
		$lessons = $course->get_lessons();
		foreach($lessons as $lesson_id) {
			$lesson = new FLMS_Lesson($lesson_id);
			global $flms_active_version;
			$flms_active_version = $source_version;
			$lesson_topics = $lesson->get_lesson_version_topics();
			$this->update_lesson_topics($lesson_id, $lesson_topics, true, array(), $active_version); 
			$lesson_exams = $lesson->get_lesson_version_exams();
			$this->update_exam_associations($lesson_id, $lesson_exams, true, array(), $active_version); 
		}
		$exams = $course->get_course_version_exams();
		$this->update_exam_associations($post_id, $exams, true, array(), $active_version);*/

		$flms_active_version = $active_version;
		return $versions["$active_version"];
	}

	public function delete_course_version($post_id, $version, $versions = null) {
		if($versions == null) {
			$versions = get_post_meta($post_id,'flms_version_content',true);
		}

		//make new versions for lessons
		if(isset($versions["$version"]["course_lessons"])) {
			$lessons = $versions["$version"]["course_lessons"];
			foreach($lessons as $lesson_id) {
				$lesson_versions = get_post_meta($lesson_id,'flms_version_content',true);
				//update topics
				if(isset($lesson_versions["$version"]["lesson_topics"])) {
					$topics = $lesson_versions["$version"]["lesson_topics"];
					foreach($topics as $topic_id) {
						$topic_versions = get_post_meta($topic_id,'flms_version_content',true);
						if(is_array($topic_versions)) {
							//update exams
							$topic_exams = $this->get_post_type_exam_ids($topic_id);
							if(is_array($topic_exams)) {
								foreach($topic_exams as $exam_id) {
									$exam_versions = get_post_meta($exam_id,'flms_version_content',true);
									unset($exam_versions["$version"]);
									update_post_meta($exam_id,'flms_version_content',$exam_versions);
								}
							}
							unset($topic_versions["$version"]);
							update_post_meta($topic_id,'flms_version_content',$topic_versions);
						}
					}
				}
				//update exams
				$lesson_exams = $this->get_post_type_exams($lesson_id);
				if(is_array($lesson_exams)) {
					foreach($lesson_exams as $exam_id) {
						$exam_versions = get_post_meta($exam_id,'flms_version_content',true);
						unset($exam_versions["$version"]);
						update_post_meta($exam_id,'flms_version_content',$exam_versions);
					}
				}
				unset($lesson_versions["{$version}"]);
			}
			
		}
		
		//update exams
		$course_exams = $this->get_post_type_exam_ids($post_id);
		if(is_array($course_exams)) {
			foreach($course_exams as $exam_id) {
				$exam_versions = get_post_meta($exam_id,'flms_version_content',true);
				unset($exam_versions["$version"]);
				update_post_meta($exam_id,'flms_version_content',$exam_versions);
			}
		}

		//remove this versions
		unset($versions[$version]);
		update_post_meta($post_id,'flms_version_content',$versions);

		//set new active version to latest not
		update_post_meta($post_id,'flms_course_active_version',array_key_last($versions));
		
	}
}
new FLMS_Course_Manager();
