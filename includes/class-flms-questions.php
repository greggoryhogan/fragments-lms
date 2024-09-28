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
class FLMS_Questions {

	private $question_id = '';
	private $question_type = '';
	public $capability = 'manage_options';

	/**
	 * The Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array($this,'set_question_attributes') );
		add_action( 'admin_enqueue_scripts', array($this,'enqueue_question_assets') );
		add_action( 'add_meta_boxes', array($this,'flms_register_meta_boxes') );
		add_action('save_post', array($this,'save_question_content'));
	}

	public function flms_register_meta_boxes() {
		add_meta_box( 'flms-question', __( 'Question Type', 'textdomain' ), array($this,'flms_question_metabox'), 'flms-questions', 'side', 'default' );
		add_meta_box( 'flms-answers', __( 'Question Answers', 'textdomain' ), array($this,'flms_answer_metabox'), 'flms-questions', 'normal', 'default' );
		add_meta_box( 'flms-question-preview', __( 'Question Preview', 'textdomain' ), array($this,'flms_question_preview_metabox'), 'flms-questions', 'normal', 'default' );
		remove_meta_box('flms-question-categoriesdiv', 'flms-questions', 'side');
		add_meta_box( 'flms-question-categoriesdiv', 'Question Categories', 'post_categories_meta_box', 'flms-questions', 'side', 'low', array( 'taxonomy' => 'flms-question-categories' ));
	}

	public function set_question_attributes() {
		if( !isset($_GET['post']) ) {
			return;
		}
		$post_id = absint($_GET['post']);
		$post = get_post($post_id);
		if($post->post_type != 'flms-questions') {
			return;
		}
		$this->question_id = $post_id;
		$this->question_type = get_post_meta($post->ID,'flms_question_type',true);
		
	}
	
	public function enqueue_question_assets() {
		wp_enqueue_style( 'jquery-ui-style' );
		wp_enqueue_script('jquery-ui-core');
		wp_enqueue_script('jquery-ui-autocomplete');
		wp_enqueue_script(
			'flms-admin-questions',
			FLMS_PLUGIN_URL . 'assets/js/admin-questions.js',
			array('jquery','jquery-ui-autocomplete'),
			false,
			true
		);
		wp_localize_script( 'flms-admin-questions', 'flms_admin_questions', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		));
	}

	public function save_question_content($post_id) {
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
		if (!current_user_can('edit_post', $post_id)) return;
		if(isset($_POST['flms_question_type'])) {
			$question_type = sanitize_text_field( $_POST['flms_question_type']);
			update_post_meta($post_id,'flms_question_type', $question_type );
			switch($question_type) {
				case 'single-choice':
				case 'multiple-choice':
					if(isset($_POST['single-choice-content'])) {
						$correct_answer = '';
						if(isset($_POST['question-correct'])) {
							if(is_array($_POST['question-correct'])) {
								$correct_answer = array_map('absint', $_POST['question-correct']);
							} else {
								$correct_answer = sanitize_text_field( $_POST['question-correct']);
							}
						}
						$assessment_content = array();
						foreach($_POST['single-choice-content'] as $k => $input) {
							$correct = 0;
							if(is_array($correct_answer)) {
								if(in_array($k,$correct_answer)) {
									$correct = 1;
								}
							} else {
								if($k == $correct_answer) {
									$correct = 1;
								} 
							}
							
							$assessment_content[] = array(
								'answer' => htmlspecialchars($input),
								'correct' => $correct,
							);
						}
						update_post_meta($post_id,'flms_question_answer', $assessment_content );
					} 
					break;
				case 'free-choice':
	
					break;
				case 'fill-in-the-blank':
					if(isset($_POST['fill-in-the-blank-content'])) {
						$fitb_content = htmlspecialchars($_POST['fill-in-the-blank-content']);
						$before = '';
						if (preg_match('/^(.*?)[{]/', $fitb_content, $matches) == 1) {
							if($matches[1] != '') {
								$before = $matches[1];
							}
						}
						$after = '';
						if (preg_match('/[}](.*)/', $fitb_content, $matches) == 1) {
							if($matches[1] != '') {
								$after = $matches[1];
							}
						}
						$answers = '';
						if (preg_match('/[{](.*?)[}]/', $fitb_content, $matches) == 1) {
							$answers = $matches[1];
							preg_match_all('/\[(.*?)\]/', $answers, $matches);
							if(isset($matches[1])) {
								if(!empty($matches[1])) {
									$answers = $matches[1];
								}
							}
						}
						$answer_content = array(
							'text' => $fitb_content,
							'before' => $before,
							'after' => $after,
							'correct' => $answers
						);
						
						update_post_meta($post_id,'flms_question_answer', $answer_content );
					}
					break;
				case 'assessment':
					if(isset($_POST['assessment-content'])) {
						$assessment_content = htmlspecialchars($_POST['assessment-content']);
						update_post_meta($post_id,'flms_question_answer', $assessment_content );
					}
					break;
				case 'essay':
					break;
			}
		}
	}

	public function flms_question_preview_metabox() {
		if($this->question_id == '') {
			echo '<p class="description">Save your question to generate a preview.</p>';
			return;
		}
		echo '<p class="description">Update the question to generate a new preview.</p>';
		$question = new FLMS_Question($this->question_id);
		$question->question_display(true);
	}

	public function flms_question_metabox() {
		$question_types = $this->get_question_types();
		echo '<div class="radio-group vertical">';
			foreach($question_types as $question_type) {
				$checked = '';
				if($this->question_type == $question_type['type']) {
					$checked = ' checked="checked"';
				} else if($question_type['default'] == 1) {
					$checked = ' checked="checked"';
				}
				echo '<div class="radio-option">';
					echo '<input type="radio" name="flms_question_type" id="question-type-'.$question_type['type'].'" value="'.$question_type['type'].'" '.$checked.' />';
					echo '<label for="question-type-'.$question_type['type'].'">'.$question_type['label'].'</label>';
				echo '</div>';
			}
		echo '</div>';
	}

	public function flms_answer_metabox() {
		$question_types = $this->get_question_types();
		$current_question_type = get_post_meta($this->question_id,'flms_question_type',true);
		$answer_value = get_post_meta($this->question_id,'flms_question_answer', true );
		echo '<div class="answer-options">';
			foreach($question_types as $question_type) {
				if($question_type['type'] == $current_question_type || ($question_type['type'] == 'single-choice' && $current_question_type == 'multiple-choice')) {
					$value = $answer_value;
				} else {
					$value = '';
				}
				$active = '';
				if($this->question_type != '') {
					if($this->question_type == $question_type['type'] || ($this->question_type == 'multiple-choice' && $question_type['type'] == 'single-choice')) {
						$active = ' is-active';
					}
				} else if($question_type['default'] == 1) {
					$active = ' is-active';
				}
				if($question_type['type'] != 'multiple-choice') {
					echo '<div class="answer-option '.$active.'" id="answer-type-'.$question_type['type'].'">';
						echo $this->question_type_output($question_type['type'], $value);
					echo '</div>';
				}
			}
		echo '</div>';
	}

	private function get_question_types() {
		$question_types = array(
			array(
				'label' => 'Single choice',
				'type' => 'single-choice',
				'default' => 1
			),
			array(
				'label' => 'Multiple choice',
				'type' => 'multiple-choice',
				'default' => 0
			),
			array(
				'label' => 'Fill in the blank',
				'type' => 'fill-in-the-blank',
				'default' => 0
			),
			array(
				'label' => 'Assessment',
				'type' => 'assessment',
				'default' => 0
			),
			array(
				'label' => 'Essay',
				'type' => 'essay',
				'default' => 0
			),
			array(
				'label' => 'Prompt',
				'type' => 'prompt',
				'default' => 0
			)
			/*
			array(
				'label' => 'Free choice',
				'type' => 'free-choice',
				'default' => 0
			),*/
		);
		return $question_types;
	}

	private function question_type_output($type, $value) {
		switch($type) {
			case 'single-choice':
			case 'multiple-choice':
				$return = '<p class="description">Add two or more options.</p>';
				if($this->question_type != '') {
					$type = $this->question_type;
				}
				$return .= '<div class="answer-options-container '.$type.'">';
				if(is_array($value)) {
					$clone_index = count($value);
					foreach($value as $k => $answer_option) {
						$return .= $this->single_multiple_choice_fields($type, $k, false, $answer_option);
					}
				} else {
					$clone_index = 2;
					$return .= $this->single_multiple_choice_fields($type, 1);
				}
				$return .= '</div>';
				
				$return .= '<button id="add-answer-option" class="button button-primary">Add Answer</button>';
				$return .= $this->single_multiple_choice_fields($type, $clone_index, true);
				return $return;
				break;
			case 'free-choice':

				break;
			case 'fill-in-the-blank':
					$return = '<p class="description">Enter a statement and wrap the option with {}. Multiple options can be grouped using [].</p>';
					$return .= '<p class="description">Example:<br>';
					$return .= 'I {understand} html<br>';
					$return .= 'I {[understand][love][despise]} html</p>'; 
					$text = '';
					if(is_array($value)) {
						if(isset($value['text'])) {
							$text = $value['text'];
						}
					}
					$return .= '<textarea name="fill-in-the-blank-content" rows="8">'.$text.'</textarea>';
					return $return;
					break;
				break;
			case 'assessment':
				$return = '<p class="description">Enter your assessment, enclosing the assessment options in {} and individual options in []. To set the default option, prepend it with an asterisk, *</p>';
				$return .= '<p class="description">Example:<br>';
				$return .= '{ [I strongly agree] [I somewhat agree] [I am indifferent] [I somewhat disagree] [I disagree] }<br>';
				$return .= 'I agree { [5] [4] [*3] [2] [1] } I disagree</p>'; 
				$return .= '<textarea name="assessment-content" rows="8">'.$value.'</textarea>';
				return $return;
				break;
			case 'essay':
				//wp_enqueue_editor();
				return '<p class="description">A text box will be displayed for the learner to enter their answer.</p>';
				break;
			case 'prompt':
				return '<p class="description">The content will be displayed without a question number. This question type is not scored.</p>';
				break;
		}
	}

	public function single_multiple_choice_fields($type, $input_count, $clone = false, $answer_option = '') {
		$return = '<div class="answer-input type-single-choice"';
		$question_count = 1;
		if($clone) {
			$return .= ' id="input-clone"';
			$question_count = -1;
		}
		$return .= '>';
			$return .= '<div class="answer-options">';
			$checked = '';
			if(is_array($answer_option)) {
				if($answer_option['correct'] == 1) {
					$checked = ' checked="checked"';
				}
			}
			$return .= '<label><input ';
				switch($type) {
					case 'single-choice':
						$return .= 'type="radio"';
						break;
					case 'multiple-choice':
						$return .= 'type="checkbox"';
						break;
					default:
						$return .= 'type="radio"';
						break;
				}
				$return .= ' name="question-correct';
				if($type == 'multiple-choice') {
					$return .= '[]';
				}
				$return .= '" value="'.$input_count.'" '.$checked.'> Correct</label>';
			$return .= '</div>';
			$return .= '<div class="answer-text">';
				$return .= '<textarea rows="2"';
				if(!$clone) {
					$return .= ' name="single-choice-content[]"';
				}
				$return .= '>';
				if(is_array($answer_option)) {
					$return .= $answer_option['answer'];
				}
				$return .= '</textarea>';
				$return .= '<a class="remove" href="#">Remove</a>';
			$return .= '</div>';
		$return .= '</div>';
		return $return;
	}

	public function question_bank($current_questions = array()) {
		$paged = 1;
		return $this->question_bank_query_output($current_questions, $paged);
	}

	public function question_bank_query_output($current_questions, $paged) {
		global $post;
		$tmp_post = $post;
		$questions = '<div class="question-options">';

		$args = array(
			'post_type'      => 'flms-questions',
			'posts_per_page' => 20,
			'paged'          => $paged,
		);
		$questions_query = new WP_Query( $args );
		if($questions_query->have_posts()) {
			while($questions_query->have_posts()) {
				$questions_query->the_post();
				$questions .= '<div class="exam-question';
				if(in_array(get_the_ID(),$current_questions)) {
					$questions .= ' is-active';
				}
				$questions .= '"><label>';
				$questions .= '<input type="checkbox" value="'.get_the_ID().'"';
				if(in_array(get_the_ID(),$current_questions)) {
					$questions .= ' checked="checked"';
				}
				$questions .= ' />';
				$questions .= mb_strimwidth(get_the_title(), 0, 100, "...");;
				//$questions .= '<input type="hidden" name="tmp-selected-flms-questions[]" value="'.get_the_ID().'" />';
				$questions .= '</label></div>';
			}
			$questions .= '</div>';
			$questions .= $this->question_bank_navigation($questions_query,$paged);
		} else {
			$questions .= '</div>';
		}
		wp_reset_postdata(); //doesn't work... for 13 years now - https://core.trac.wordpress.org/ticket/18408
		$post = $tmp_post; //reassign post because wp is annoying
		return $questions;
	}

	public function get_question_taxonies($checkbox = false, $checked_array = array(), $parent = false, $show_link = false, $show_number_input = false, $numbers_array = array()) {
		$terms = get_terms( array(
			'taxonomy'   => 'flms-question-categories',
			'hide_empty' => false,
		));
		$return = '';
		//print_r($numbers_array);
		if(!empty($terms)) {
			$return .= '<ul class="flms-question-categories">';
			foreach($terms as $term) {
				$return .= '<li';
				if(in_array($term->term_id, $checked_array)) {
					$return .= ' class="flms-highlighted"';
				}
				$return .= '>';
				if($checkbox) {
					$return .= '<label>';
					$name = '';
					if($parent != false) {
						$name = 'name="'.$parent.'"';
					}
					$checked = '';
					if(in_array($term->term_id, $checked_array)) {
						$checked = 'checked="checked"';
					}
					$return .= '<input type="checkbox" value="'.$term->term_id.'" '.$name.' '.$checked.' />';
				}
				$return .= $term->name;
				if($checkbox) {
					$return .= '</label>';
				}
				if($show_number_input) {
					$number_value = 0;
					if(isset($numbers_array[$term->term_id])) {
						$number_value = $numbers_array[$term->term_id];
					}
					$name = '';
					if($parent != false) {
						$name = 'name="question_count_'.$term->term_id.'"';
					}
					$return .= '<input type="number" value="'.$number_value.'" min="0" '.$name.' />';
				}
				if($show_link) {
					$return .= '<a href="'.admin_url('edit.php?flms-question-categories='.$term->slug).'&post_type=flms-questions" target="_blank">View all questions</a>';;
				}
				$return .= '</li>';
			}
			$return .= '</ul>';
		}
		return $return;
	}

	private function question_bank_navigation($questions_query, $paged = 1) {
		$navigation = '';
		if ( $questions_query->max_num_pages > 1 ) {

			$max   = intval( $questions_query->max_num_pages );

			/** Add current page to the array */
			if ( $paged >= 1 )
				$links[] = $paged;

			/** Add the pages around the current page to the array */
			if ( $paged >= 5 ) {
				$links[] = $paged - 1;
				$links[] = $paged - 2;
				$links[] = $paged - 3;
				$links[] = $paged - 4;
			}

			if ( ( $paged + 4 ) <= $max ) {
				$links[] = $paged + 4;
				$links[] = $paged + 3;
				$links[] = $paged + 2;
				$links[] = $paged + 1;
			}

			$navigation .= '<div><ul class="questions-pagination flms-pagination flex">';

			/** Previous Post Link */
			if($paged > 1) {
				$last = $paged - 1;
				$navigation .= '<a href="#" data-page="'.$last.'">&laquo;&nbsp;Prev</a>';
			}

			/** Link to first page, plus ellipses if necessary */
			if ( ! in_array( 1, $links ) ) {
				$navigation .= '<a href="#" data-page="1">1</a>';
				if ( ! in_array( 2, $links ) )
					$navigation .= '<li>…</li>';
			}

			/** Link to current page, plus 2 pages in either direction if necessary */
			sort( $links );
			foreach ( (array) $links as $link ) {
				//$class = $paged == $link ? ' class="active"' : '';
				if($link == $paged) {
					$navigation .= '<span class="current">'.$link.'</span>';
				} else {
					$navigation .= '<a href="#" data-page="'.$link.'">'.$link.'</a>';
				}
				
			}

			/** Link to last page, plus ellipses if necessary */
			if ( ! in_array( $max, $links ) ) {
				if ( ! in_array( $max - 1, $links ) )
					$navigation .= '<li>…</li>' . "\n";

				$class = $paged == $max ? ' class="active"' : '';
				$navigation .= '<a href="#" data-page="'.$max.'">'.$max.'</a>';
			}

			/** Next Post Link */
			if($paged < $questions_query->max_num_pages) {
				$next = $paged + 1;
				$navigation .= '<a href="#" data-page="'.$next.'">Next&nbsp;&raquo;</a>';
			}
			$navigation .= '</ul></div>';
			

			/*$pages = $questions_query->max_num_pages;
			$navigation .= '<div class="questions-pagination flms-pagination flex">';
			$current = 0;
			$range = 2;
			$showitems = 6;
			if($paged > 1) {
				$last = $paged - 1;
				$navigation .= '<a href="#" data-page="'.$last.'">&laquo;&nbsp;Prev</a>';
			}*/
			
			/*while($current < $questions_query->max_num_pages) {
				$current++;
				if($current == $paged) {
					$navigation .= '<span class="current">'.$current.'</span>';
				} else {
					$navigation .= '<a href="#" data-page="'.$current.'">'.$current.'</a>';
				}
			}*/
			/*for ($i = 1; $i <= $pages; $i++) {
				if ((!($i >= $paged + $range + 1 || $i <= $paged - $range - 1) || $pages <= $showitems)) {
					if($i == $paged) {
						$navigation .= '<span class="current">'.$i.'</span>';
					} else {
						$navigation .= '<a href="#" data-page="'.$i.'">'.$i.'</a>';
					}
				}
			  }
			if($paged < $questions_query->max_num_pages) {
				$next = $paged + 1;
				$navigation .= '<a href="#" data-page="'.$next.'">Next&nbsp;&raquo;</a>';
			}
			$navigation .= '</div>';*/
		}
		return $navigation;
	}

	public function flms_output_exam_questions($post_id, $exam_questions, $user_id, $exam_identifier, $limit = 10, $starting_index = 0, $start_count = 0, $page = 1, $review = 0, $exam_is_graded = 'graded', $display_type = 'exam') {
		global $flms_settings;
		$questions = '';
		$exam_count = count($exam_questions);
		$current_question_index = $starting_index;
		$next = $page + 1;
		$prev = $page - 1;
		$user_answers = array();
		
		if($user_id > 0) {
			if($review == 0) {
				$meta_key = "flms_{$exam_identifier}_exam_answers";
				$user_answers = get_user_meta($user_id, $meta_key, true);
			} else {
				$meta_key = "flms_{$exam_identifier}_exam_attempts";
				$attempts = get_user_meta($user_id, $meta_key, true);
				if($attempts == '') {
					$attempts = 1;
				}
				$meta_key = "flms_{$exam_identifier}_exam_attempt_{$attempts}";
				$user_history = get_user_meta($user_id, $meta_key, true);
				if($user_history != '') {
					$user_answers = $user_history['answers'];
				} else {
					$user_answers = array();
				}
			}
			
		}
		
		$course_data = explode(':',$exam_identifier);
		$exam_id = $course_data[0];
		$course_id = flms_get_course_id($exam_id);
		$course_version = $course_data[1];
		$flms_user_progress = flms_get_user_activity($user_id, $course_id, $course_version);
		$steps_completed = maybe_unserialize($flms_user_progress['steps_completed']);
		$passed = false;
		if(flms_is_step_complete($steps_completed, $post_id)) {
			$passed = true;
		}
		$exam_settings = get_post_meta($exam_id, "flms_exam_settings_$course_version", true);
		$time_limit = 0;
		if(isset($exam_settings['time_limit'])) {
			$time_limit = $exam_settings['time_limit'];
		}
		
		if($time_limit > 0 && $review == 0) {
			$meta_key = "flms_{$exam_identifier}_exam_time_remaining";
			$time_remaining = absint(get_user_meta($user_id, $meta_key, true)) * 1000;
			$questions .= flms_get_exam_timer_html();
		} else {
			$time_remaining = -1;
		}

		//$questions .= '<ol>'; // start="x"
		$question_count = $start_count;
		for($i = 0; $i < $limit; $i++) {
			
			if(isset($exam_questions[$current_question_index])) {
				
				$question_type = get_post_meta($exam_questions[$current_question_index],'flms_question_type', true );
				if($question_type != 'prompt') {
					$question_count++;
				} 
				$question = new FLMS_Question($exam_questions[$current_question_index], $question_type);
				//THIS IS A PROBLEM THAT BREAKS AJAX
				$questions .= $question->question_display(false, $question_count, $user_answers, $review, $exam_is_graded, $passed, $display_type);
				$current_question_index++;
			} 
			if($i == ($exam_count - 1)) {
				break;
			}
			
		}
		
		//$questions .= '</ol>';

		if($display_type != 'print') {
			$questions .= '<div class="flms-navigation exam-navigation">';
				if($page > 1) {
					$questions .= '<button class="button button-primary exam-pagination previous" data-page="'.$prev.'">Previous</button>';	
				}
				if($review == 0) {
					$save_enabled = get_post_meta($post_id,'flms_save_continue_enabled',true);
					if($save_enabled == '') {
						$save_enabled = $flms_settings['exams']['save_continue_enabled'];
					}
					if($save_enabled == 'active') {
						$exam_label = $flms_settings['labels']['exam_save_continue'];
						$questions .= '<button class="button button-primary save-and-continue-exam" id="save_exam">'.$exam_label.'</button>';
					}
				} else if($review == 2) {
					$questions .= '<button class="button button-primary" id="update-exam-user-answers">Update exam answers</button><div>&nbsp;</div>';
					$questions .= '<div>&nbsp;</div>'; //to force content left
				} else {
					$questions .= '<button class="button button-primary';
					if($current_question_index == $exam_count) {	
						$questions .= ' last-page';	
					}
					$questions .= '" id="complete-review">Finish review</button>';
				}
				if($current_question_index < $exam_count) {	
					$questions .= '<button class="button button-primary exam-pagination" data-page="'.$next.'">Next</button>';
				} else {
					if($review == 0) {
						$questions .= '<input type="submit" value="Submit" id="submit_exam" />';
					}
				}
			$questions .= '</div>';
			if($review == 2) {
				$profile_link = '<a href="'.admin_url('user-edit.php?user_id='.$user_id.'#user-active-courses').'">profile page</a>';
				$questions .= '<p><em>*Once a user has passed an exam, you cannot change their answers to a <u>failed</u> result, only correct misinterpreted questions</em>. You can reset a user&rsquo;s course progress on their '.$profile_link.'.</p>';
			}
		}
		
		
		
		return array(
			'uid' => $user_id,
			'questions' => $questions,
			'start_count' => $start_count,
			'exam_question_count' => $question_count,
			'page' => $page,
			'time_remaining' => $time_remaining
		);
		/*if($display_type == 'print') {
			return array(
				'uid' => $user_id,
				'questions' => $questions,
				'start_count' => $start_count,
				'exam_question_count' => $question_count,
				'page' => $page,
			);
		} else {
			wp_send_json(array(
				'uid' => $user_id,
				'questions' => $questions,
				'start_count' => $start_count,
				'exam_question_count' => $question_count,
				'page' => $page,
			));
		}*/
	}

	
}
new FLMS_Questions();
