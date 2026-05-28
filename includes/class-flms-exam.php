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
class FLMS_Exam {

	public int $exam_id;
	public int $flms_active_version;

	/**
	 * The Constructor.
	 */
	public function __construct(int $post_id) {
		global $flms_course_version_content, $flms_exam_id, $flms_exam_version_content, $flms_active_version;
		$flms_exam_id = absint($post_id);
		$exam_settings = get_post_meta($flms_exam_id, "flms_exam_settings_$flms_active_version", true);
		//echo '</pre>'.print_r($exam_settings,true).'</pre>';
		$flms_exam_version_content = get_post_meta($flms_exam_id,'flms_version_content',true);	
		//echo '<pre>'.print_r($flms_exam_version_content,true).'</pre>';
		global $flms_course_id, $flms_active_version, $flms_course_version_content, $wp, $flms_version_index, $flms_latest_version;
		//$exam_post = get_post($flms_exam_id);
		$flms_course_id = flms_get_course_id($flms_exam_id);
		if($flms_course_id > 0) {
			$course = new FLMS_Course($flms_course_id);
			if(!is_array($flms_exam_version_content)) {
				$flms_exam_version_content = array();
			} else {
				krsort($flms_exam_version_content);
			}
		} else {
			$flms_exam_version_content = array();
		}
		
	}
	
	public function get_exam_version_content() {
		global $flms_settings, $flms_exam_id, $flms_exam_version_content, $flms_course_id, $flms_active_version, $current_user, $flms_user_progress;
		$exam_settings = get_post_meta($flms_exam_id, "flms_exam_settings_$flms_active_version", true);
		$exam_label = $exam_settings['exam_label_override'];
		$lc_exam_label = strtolower($exam_label);
		$content = '<div id="exam-into-content">';

		$content .= apply_filters('flms_before_exam_content', '');

		if(apply_filters('flms_exam_content_before_actions', true)) {
			if(!isset($flms_exam_version_content["$flms_active_version"]["post_content"])) {
				
			} else {
				$content .= wpautop($flms_exam_version_content["$flms_active_version"]["post_content"]);
			}
		}

		$content .= apply_filters('flms_before_exam_actions', '');
		$show_time_limit = apply_filters('flms_show_time_limit_in_content', true);
		if($show_time_limit) {
			if(isset($exam_settings['time_limit'])) {
				$time_limit = absint($exam_settings['time_limit']);
				if($time_limit > 0) {
					//there is a limit, show time limit if not started, time remaining if resuming
					$exam_identifier = "$flms_exam_id:$flms_active_version";
					$meta_key = "flms_{$exam_identifier}_exam_time_remaining";
					$time_remaining = get_user_meta($current_user->ID, $meta_key, true);
					//echo $time_remaining;
					if($time_remaining == '') {
						$time = flms_seconds_to_time($time_limit);
						$message = "<p>You will have $time to complete this $lc_exam_label.</p>";
						$content .= apply_filters('flms_exam_content_time_limit', $message, $time);
					} else {
						$time = flms_seconds_to_time($time_remaining);
						$message = "<p class='flms-exam-time-remaining'>You have $time remaining to complete this $lc_exam_label.</p>";
						$content .= apply_filters('flms_exam_content_time_limit', $message, $time);
					}
				}
			}
		}

		$exam_questions = flms_get_exam_questions($flms_exam_id, $flms_active_version);
		if(empty($exam_questions)) {
			if(current_user_can('administrator')) {
				$content .= '<p>This exam has no questions.</p><p><a href="'.get_edit_post_link( $flms_exam_id ).'" class="button button-primary">Edit the exam</a></p>';
			} else {
				$content .= '<p>There is an error with this exam. Please contact your site administrator.</p>';
			}
			$content .= '</div>';
		} else {
			$flms_user_progress = flms_get_user_activity($current_user->ID, $flms_course_id, $flms_active_version);
			$steps_completed = maybe_unserialize($flms_user_progress['steps_completed']);
			$course_identifier = "$flms_exam_id:$flms_active_version";
			$passed = false;
			if(flms_is_step_complete($steps_completed, $flms_exam_id)) {
				$passed = true;
			}
			$passed = apply_filters('flms_customer_passed_exam', $passed, $current_user->ID, $flms_user_progress, $flms_course_id, $flms_active_version);
			
			$save_enabled = $exam_settings['save_continue_enabled'];
			if($save_enabled == '') {
				$save_enabled = $flms_settings['exams']['save_continue_enabled'];
			}
			
			$continue_exam = false;
			if($current_user->ID > 0) {
				$in_progress = get_user_meta($current_user->ID, "flms_{$course_identifier}_exam_in_progress", true);
				if($in_progress != '') {
					$continue_exam = true;
				}
			}
			//show previous attempt
			$meta_key = "flms_{$course_identifier}_exam_attempts";
			$attempts = get_user_meta($current_user->ID, $meta_key, true); 
			if($attempts == '') {
				$attempts = (int) 0;
			}
			$meta_key = "flms_{$course_identifier}_exam_attempt_{$attempts}";
			$last_attempt = get_user_meta($current_user->ID, $meta_key, true);
			if($last_attempt == '') {
				$last_attempt_num = $attempts - 1;
				$meta_key = "flms_{$course_identifier}_exam_attempt_{$last_attempt_num}";
				$last_attempt = get_user_meta($current_user->ID, $meta_key, true);
			}
			
			$max_attempts = $exam_settings['exam_attempts'];
			if($max_attempts == '') {
				$max_attempts = 1;
			}
			$meta_key = "flms_{$course_identifier}_extra_exam_attempts";
			$additional_attempts = max(get_user_meta($current_user->ID, $meta_key, true), 0);
			$remaining = max(($max_attempts + $additional_attempts) - $attempts, 0);
			//$remaining = 1;
			
			$buttons = '';
			if($last_attempt != '') {
				$graded = $exam_settings['exam_is_graded'];
				if($graded == 'auto') {
					$text = "You have completed this $lc_exam_label";
					$text = apply_filters('flms_exam_auto_complete_text', $text, $flms_exam_id, $flms_active_version);
					$content .= wpautop($text);
				} else {
					$minimum = $exam_settings['pass_percentage'];
					$score = $last_attempt['score'];
					/*if($passed) {
						echo '<h2>Passed!</h2>';
					} else if($last_attempt != '') {
						if($score < $minimum) {
							echo '<h2>Failed</h2>';
						}
					}*/
					//$score = 100 * round($last_attempt['score'],2);
					$score_string = preg_replace('/(^| )a ([8])/', '$1an $2', 'a '.$score);
					$exam_label = flms_get_label('exam_singular');
					if($passed) {
						$exam_string = '<h3>'.$exam_label.' passed!</h3>';
						$exam_string .= '<p>You scored %exam_score%% (%number_correct% of %total_questions% questions).</p>';
						//get potential content from editor
						if(isset($exam_settings['passed-exam-content'])) {
							//add extra check to make sure it's not empty
							if($exam_settings['passed-exam-content'] != '') {
								$exam_string = wpautop(html_entity_decode($exam_settings['passed-exam-content']));
							}
						}
						
						//replace tmp strings
						$exam_string = str_replace('%exam_score%',$score_string, $exam_string);
						$exam_string = str_replace('%number_correct%',$last_attempt['correct'], $exam_string);
						$exam_string = str_replace('%total_questions%',$last_attempt['total'], $exam_string);
						$exam_string = do_shortcode($exam_string); 

						$exam_feedback = apply_filters('flms_exam_passed_string',$exam_string, $flms_exam_id, $score, $last_attempt['correct'], $last_attempt['total']);
						$content .= $exam_feedback;
					} else {
						$exam_string = '<h3>You did not pass the '.strtolower($exam_label).'.</h3>';
						$exam_string .= '<p>You scored %exam_score%% on your last attempt (%number_correct% of %total_questions% questions). The minimum passing grade is %exam_passing_grade%%.</p>';
						$exam_string .= '<p>%attempts_remaining%</p>';
						
						if(isset($exam_settings['failed-exam-content'])) {
							//add extra check to make sure it's not empty
							if($exam_settings['failed-exam-content'] != '') {
								$exam_string = wpautop(html_entity_decode($exam_settings['failed-exam-content']));
							}
						}

						//replace tmp strings
						$exam_string = str_replace('%exam_score%',$score_string, $exam_string);
						$exam_string = str_replace('%number_correct%',$last_attempt['correct'], $exam_string);
						$exam_string = str_replace('%total_questions%',$last_attempt['total'], $exam_string);
						$exam_string = str_replace('%exam_passing_grade%',$minimum, $exam_string);
						$exam_string = do_shortcode($exam_string); 

						if($attempts > 0) {
							if($max_attempts == -1) {
								$exam_string = str_replace('%attempts_remaining%','You can attempt this exam as many times as you would like.', $exam_string);
							} else if($remaining == 0) {
								$no_remaining = apply_filters('flms_no_attempts_remaining','No attempts remaining.');
								$exam_string = str_replace('%attempts_remaining%',$no_remaining, $exam_string);
							} else {
								$continue_text = '';
								if($continue_exam) {
									$continue_text = 'An '.strtolower($exam_label).' is in progress. ';
								}
								if($remaining == 1) {
									$exam_string = str_replace('%attempts_remaining%',"$remaining attempt remaining", $exam_string);
								} else {
									$exam_string = str_replace('%attempts_remaining%',"$remaining attempts remaining", $exam_string);
								}
								
							}
						} else {
							$exam_string = str_replace('%attempts_remaining%','', $exam_string);
						}

						$exam_feedback = apply_filters('flms_exam_failed_string',$exam_string, $flms_exam_id, $score, $last_attempt['correct'], $last_attempt['total'], $remaining);
						$content .= $exam_feedback;
					}
					
					
					//if($passed) {
						
					//}
					//echo '<pre>'.print_r($last_attempt,true).'</pre>';
				}
				
				
					if(($exam_settings['exam_review_enabled'] == 'active' || $exam_settings['exam_review_enabled'] == 1) && !$continue_exam) {
						$review_exam_text = apply_filters('flms_review_exam_button_text', 'Review');
						if($passed) {
							if(apply_filters('flms_review_completed_exam', true, $flms_course_id, $flms_active_version, $flms_exam_id)) {
								$buttons .= '<button id="review_exam" class="button button-primary">'.$review_exam_text.'</button>';
							}
						} else {
							if(apply_filters('flms_review_incomplete_exam', true, $flms_course_id, $flms_active_version, $flms_exam_id)) {
								$buttons .= '<button id="review_exam" class="button button-primary">'.$review_exam_text.'</button>';
							}
						}
					}
					
					if($passed) {
						$completed = flms_user_completed_course($flms_course_id, $flms_active_version);
						if($completed) {
							if(flms_is_module_active('course_certificates')) {
								if(apply_filters('flms_show_course_certificate_in_exam', true)) {
									$flms_template = new FLMS_Template();
									$buttons .= $flms_template->flms_get_all_course_certificates(1, false, false, 'exam');
									/*$course_certificates = new FLMS_Module_Course_Certificates();
									$label = $course_certificates->get_certificate_label();
									$rewrite = $course_certificates->get_certificate_permalink();
									$link = '/'.$rewrite.'/'.$flms_course_id.'/'.$flms_active_version.'/'.$current_user->ID;
									$label = 'View '.$label;
									//link needs updating
									$content .= '<button class="button button-secondary flms-button-has-link" data-flms-button-link="'.$link.'" data-name="'.$label.'">'.$label.'</button>';
									*/
								}
							}
						}
					}

			}

			$content .= '<section id="exam-actions">';
				$content .= $buttons;
				$exam_link = '';
				$show_print_exam = true;
				if(isset($exam_settings['print_exam_enabled'])) {
					$print_exam_status = $exam_settings['print_exam_enabled'];
					if($print_exam_status == 'inactive') {
						$show_print_exam = false; 
					}
				}

				$show_print_exam = apply_filters('flms_show_print_exam_button',$show_print_exam, $flms_exam_id, $flms_course_id, $flms_active_version);
				if($show_print_exam) {
					$exam_label = flms_get_label('exam_singular');
					$print_label = apply_filters('flms_print_exam_label', "Print $exam_label");
					$exam_permalink = $flms_settings["custom_post_types"]["exam_permalink"];
					$print_link = trailingslashit(get_bloginfo('url')).'print-'.$exam_permalink.'/'.$flms_exam_id.'/'.$flms_active_version.'/';
					$exam_link = '<button data-flms-button-link="'.$print_link.'" data-name="'.$print_label.'" class="flms-button-has-link button button-secondary">'.$print_label.'</button>';
				}
				if($continue_exam && !$passed) {
					$exam_label = $exam_settings['exam_resume_label'];
					if($exam_label == '') {
						$exam_label = $flms_settings['labels']['exam_resume_label'];
					}
					$content .= '<button id="resume_exam" class="button button-primary">'.$exam_label.'</button>';
					$content .= $exam_link;
				} else {
					$exam_label = $exam_settings['exam_start_label'];
					if($exam_label == '') {
						$exam_label = $flms_settings['labels']['exam_start_label'];
					}
					
					
					if(!$passed) {
						if($remaining > 0 || $max_attempts == -1) {
							if($last_attempt != '') {
								$exam_label = apply_filters('flms_retry_exam_label', $exam_label, $flms_exam_id, $flms_course_id, $flms_active_version );
							} else {
								$exam_label = apply_filters('flms_start_exam_label', $exam_label, $flms_exam_id, $flms_course_id, $flms_active_version );
							}
							$show_start_exam = apply_filters('flms_show_start_exam_button', true, $flms_exam_id, $flms_course_id, $flms_active_version, $current_user );
							if($show_start_exam) {
								$content .= '<button id="start_exam" class="button button-primary">'.$exam_label.'</button>';
							} else {
								$content .= apply_filters('flms_start_button_disabled_text', '', $flms_exam_id, $flms_course_id, $flms_active_version, $current_user);
							}
							$content .= $exam_link;
						}
					} 
				}
			$content .= '</section>';

			$content .= apply_filters('flms_after_exam_buttons', '', $flms_exam_id, $passed, $current_user->ID, $flms_user_progress, $flms_course_id, $flms_active_version);
			$content .= '</div>';
			$content .= '<div id="current-exam"></div>';
		}

		if(apply_filters('flms_exam_content_after_actions', false)) {
			if(!isset($flms_exam_version_content["$flms_active_version"]["post_content"])) {
				
			} else {
				$content .= wpautop($flms_exam_version_content["$flms_active_version"]["post_content"]);
			}
		}
		
		return $content;
	}

	public function save_exam_progress($user_id, $answers) {
		global $flms_active_version, $flms_exam_id;
		$exam_identifier = "$flms_exam_id:$flms_active_version";
		//save answers
		if(!empty($answers)) {
			$meta_key = "flms_{$exam_identifier}_exam_answers";
			$existing = get_user_meta($user_id, $meta_key, true);
			if(!is_array($existing)) {
				$existing = array();
			}
			foreach($answers as $k => $v) {
				if($v !== '') {
					$existing[$k] = $v;
				}
			}
			update_user_meta($user_id, $meta_key, $existing);
		}
		//track save time of user exam
		flms_track_exam_time($user_id, $exam_identifier);
	}

	public function grade_exam($user_id, $admin_updating_exam_answers = false) {
		global $flms_active_version, $flms_exam_id, $flms_course_id;
		$exam_identifier = "$flms_exam_id:$flms_active_version";

		$exam_settings = get_post_meta($flms_exam_id, "flms_exam_settings_$flms_active_version", true);
		$exam_type = 'standard';
		if(isset($exam_settings["exam_type"])) {
			$exam_type = $exam_settings["exam_type"];
		}

		$reset_questions = 1;
		if($exam_type == 'sample-draw' || $exam_type == 'category-sample-draw') {
			if(isset($exam_settings["reset_questions_during_failure"])) {
				$reset_questions = $exam_settings["reset_questions_during_failure"];
			}
		}
		
		$current_exam_answers = "flms_{$exam_identifier}_exam_answers";
		//copy answers to exam attemp answers
		$existing = get_user_meta($user_id, $current_exam_answers, true);
		if($existing == '') {
			$existing = array();
		}

		
		$total_correct = 0;
		
		$versions = get_post_meta($flms_exam_id,'flms_version_content',true);
		$total_question_ids = array();

		$exam_questions = maybe_unserialize(get_user_meta($user_id, "flms_current_exam_questions_{$flms_exam_id}_$flms_active_version", true)); 

		if(!empty($exam_questions)) {
			$total_question_counter = 0;
			foreach($exam_questions as $question_id) {
				$type = get_post_meta($question_id,'flms_question_type', true );
				if($type != 'prompt') {
					$total_question_counter++;
					$total_question_ids[$total_question_counter] = $question_id;
				}
			}
		} 
		$total_questions = count($total_question_ids);

		$graded_using = $exam_settings['exam_is_graded_using'];
		$exam_answers = array();
		$report_data = array();
		$graded_question_ids = array();
		foreach($existing as $question_id => $user_answer) {
			$question = new FLMS_Question($question_id);
			$type = $question->get_question_type();
			if($type != 'prompt') {
				$graded_question_ids[] = $question_id;
				//get question report data, check if correct
				$graded_feedback = $question->grade_question(htmlspecialchars($user_answer));
				//add data to master report data array
				$report_data[$question_id] = $graded_feedback['report_data'];
				//is exam graded
				if($exam_settings['exam_is_graded'] == 'graded') {	
					$correct = $graded_feedback['question_correct'];
				} else {
					$correct = 1;
				}
				if($correct == 1) {
					$total_correct++;
				} else {

				}
				$exam_answers[$question_id] = array(
					'response' => $user_answer,
					'correct' => $correct
				);
			}
		}
		$graded_questions = count($graded_question_ids);
		if(apply_filters('flms_force_all_exam_questions_before_grading', false)) {
			if($graded_questions != $total_questions) {
				if($graded_questions < $total_questions) {
					$unanswered = $total_questions - $graded_questions;
					$prompt = "You have $unanswered unanswered ";
					if($unanswered == 1) {
						$prompt .= 'question';
					} else {
						$prompt .= 'questions';
					}
					if(apply_filters('flms_show_missing_questions', true)) {
						foreach($total_question_ids as $number => $question_id) {
							if(in_array($question_id, $graded_question_ids)) {
								unset($total_question_ids[$number]);
							}
						}
						/*if(count($total_question_ids) == 1) {
							$prompt .= '<br>Please answer question';
						} else {
							$prompt .= '<br>Please answer questions';
						}*/
						$keys = array_keys($total_question_ids);
						$prompt .= ': '.flms_natural_language_join($keys);
						
					}
					$prompt .= ". Please complete all questions before submitting the exam.";
					
					$prompt =  apply_filters('ungraded_questions_prompt',$prompt, $unanswered);
					$data = array(
						'submission_error' => 1,
						'unanswered_questions' => $prompt
					);
					return $data;
				}
			}
		}

		//they answered all, contionue as normal
		delete_user_meta($user_id, "flms_{$exam_identifier}_exam_in_progress");
		
		//now maybe delete the saved answers, any non standard exam must have answers reset because the questions will change between attempts
		$course_id = flms_get_course_id($flms_exam_id);
		$reset_questions_global = 1;
		$global_exam_question_reset_option = apply_filters('flms_reset_exam_answers_after_attempt', true, $course_id, $flms_active_version, $flms_exam_id);
		if($global_exam_question_reset_option === false) {
			$reset_questions_global = 0;
		}
		if( $reset_questions_global == 1 || ($exam_type != 'standard' && $reset_questions > 0)) {
			delete_user_meta($user_id, $current_exam_answers);
		} 
		//delete course access restrictions if one is set
		$meta_key = 'flms_content_restricted_by_exam';
		$has_restrictions = get_user_meta($user_id, $meta_key);
		if(!empty($has_restrictions)) {
			$meta_value = json_encode(
				array(
					'course' => "$flms_course_id",
					'exam' => "$flms_exam_id",
					'version' => "$flms_active_version"
				)
			);
			delete_user_meta( $user_id, $meta_key, $meta_value );
		}
		
		$elapsed_key= "flms_{$exam_identifier}_exam_elapsed_time";
		$elapsed = get_user_meta($user_id, $elapsed_key, true);
		delete_user_meta($user_id, $elapsed_key);
		delete_user_meta($user_id, "flms_{$exam_identifier}_exam_last_active");
		delete_user_meta($user_id, "flms_{$exam_identifier}_exam_time_remaining");

		//get attempt number
		$meta_key = "flms_{$exam_identifier}_exam_attempts";
		$attempts = get_user_meta($user_id, $meta_key, true);

		if($total_correct > 0) {
			$score = 100 * round($total_correct / $total_questions, 2);
		} else {
			$score = 0;
		}
		$meta_key = "flms_{$exam_identifier}_exam_attempt_{$attempts}";
		$data = array(
			'exam_id' => $flms_exam_id,
			'version' => $flms_active_version,
			'answers' => $exam_answers,
			'correct' => $total_correct,
			'total' => $total_questions,
			'score' => $score,
			'elapsed_time' => $elapsed,	
		);
		update_user_meta($user_id, $meta_key, $data);

		$data['submission_error'] = 0;
		//remove old attempts
		if(!$admin_updating_exam_answers) {
			for ($i = 1; $i < $attempts; $i++) {
				$meta_key = "flms_{$exam_identifier}_exam_attempt_{$i}";
				delete_user_meta($user_id, $meta_key);
			}
		}

		//complete exam?
		$exam_complete = false;
		
		if($graded_using == 'percentage') {
			$pass_percentage = $exam_settings['pass_percentage'];
			if($pass_percentage == '') {
				$pass_percentage = $flms_settings['exams']['flms_pass_percentage'];
			}
			if($score >= $pass_percentage) {
				$exam_complete = true;
			}
		} else {
			//TODO: Add points to exam questions so we can calculate, pass them for now
			$exam_complete = true;
		}
		
		$course_progress = new FLMS_Course_Progress();

		$data['redirect'] = ''; //default redirect action
		$exam_log_id = false;
		if($exam_complete) {
			//delete exam answers if they weren't before
			$meta_key = "flms_{$exam_identifier}_exam_answers";
			delete_user_meta($user_id, $meta_key);
			
			//update question reporting data
			foreach($existing as $question_id => $user_answer) {
				if(isset($report_data[$question_id])) {
					update_post_meta($question_id,'flms_question_report_data',$report_data[$question_id]);
				}
			}
			//update user activity
			$exam_log_id = $course_progress->log_user_activity($user_id, $flms_exam_id, $flms_active_version, "completed");
			
			flms_update_user_activity($flms_exam_id, $user_id, $flms_course_id, $flms_active_version);

			//remove old additional attempts
			$meta_key = "flms_{$exam_identifier}_extra_exam_attempts";
			delete_user_meta($user_id, $meta_key);

			do_action('flms_after_exam_completed', $flms_exam_id, $flms_active_version, $user_id );
			
			
		} else {
			if(!$admin_updating_exam_answers) {

				$exam_log_id = $course_progress->log_user_activity($user_id, $flms_exam_id, $flms_active_version, "failed");
				//see if they have remaining attempts
				$max_attempts = $exam_settings['exam_attempts'];
				$meta_key = "flms_{$exam_identifier}_extra_exam_attempts";
				$additional_attempts = max(get_user_meta($user_id, $meta_key, true), 0);
				$max_attempts += $additional_attempts;

				if($max_attempts == '') {
					$max_attempts = 1;
				}

				if($max_attempts == $attempts) {
					//do something if they have no attempts remaining.
					$action = $exam_settings['exam_attempt_action'];
					
					switch($action) {
						case 'reset-lesson':
							//TODO Reset lesson action
							break;
						case 'reset-course':
							$course_progress->reset_course_progress($user_id, $flms_course_id, $flms_active_version);
							$link = trailingslashit(flms_get_course_version_permalink( $flms_course_id, $flms_active_version)).'?progress-reset=1&reason=exam-result';
							$data['redirect'] = $link;

							$course_progress->log_user_activity($user_id, $flms_course_id, $flms_active_version, "reset");
							break;
						case 'unenroll-learner':
							$course_progress->unenroll_user($user_id, $flms_course_id, $flms_active_version);
							$data['redirect'] = trailingslashit(get_permalink( get_option('woocommerce_myaccount_page_id') )) . get_option('flms_my_courses_endpoint').'?user-unenrolled='.$flms_course_id.'-'.$flms_active_version.'&reason=exam-result';
							$course_progress->log_user_activity($user_id,$flms_course_id, $flms_active_version, "unenrolled");
							break;
						case 'no-action':
							//do nothing
							break;
					}
					//remove old additional attempts
					$meta_key = "flms_{$exam_identifier}_extra_exam_attempts";
					delete_user_meta($user_id, $meta_key);
					
				}
			}
			
			do_action('flms_after_exam_failed', $flms_exam_id, $flms_active_version, $user_id );

		}

		if($exam_log_id != false) {
			$course_progress->save_exam_completion_time($exam_log_id, $flms_exam_id, $flms_active_version, $user_id );
		}

		do_action('flms_after_exam_attempt', $flms_exam_id, $flms_active_version, $user_id );

		return $data;
	}

	public function get_exam_versions() {
		global $flms_course_id, $flms_exam_version_content, $flms_active_version;
		$course = new FLMS_Course($flms_course_id);
		$versions = $course->get_versions();
		
		$exam_versions = array();
		if(is_array($versions)) {
			foreach($versions as $version_index => $version_data) {
				$exam_versions[$version_index] = $version_data['version_name'];
			}
		}
		return $exam_versions;
	}

	public function get_exam_question_ids() {
		global $flms_exam_version_content, $flms_active_version, $flms_exam_id;
		$exam_settings = get_post_meta($flms_exam_id, "flms_exam_settings_$flms_active_version", true);
		$exam_questions = array();
		if(isset($exam_settings["exam_type"])) {
			$exam_type = $exam_settings["exam_type"];
			if($exam_type == 'cumulative') {
				//TODO: CUMUILATIVE 
			} else if($exam_type == 'sample_draw') {
				//TODO: SAMPLE DRAW
			} else {
				if(isset($exam_settings['question_select_type'])) {
					$question_select_type = $exam_settings['question_select_type'];
					if($question_select_type == 'manual') {
						//standard exam
						if(isset($exam_settings["exam_questions"])) {
							$exam_questions = $exam_settings["exam_questions"];
						} 
					} else {
						if(isset($exam_settings["exam_question_categories"])) {
							$categories = $exam_settings["exam_question_categories"];
							//by category
							$exam_questions = get_posts(
								array(
									'post_type' => 'flms-questions',
									'numberposts' => -1,
									'post_status' => 'publish',
									'fields' => 'ids',
									'orderby' => 'menu_order',
									'order' => 'asc',
									'tax_query' => array(
										array(
											'taxonomy' => 'flms-question-categories',
											'field' => 'term_id',
											'terms' => $categories,
											'operator' => 'IN',
										)
									 )
								)
							);
							
						} 
					}
				}
			}
		}
		return $exam_questions;
	}

	public function edit_user_exam($user_id, $attempt) {
		global $flms_exam_id, $flms_active_version;
		$exam_identifier = "$flms_exam_id:$flms_active_version";
		$meta_key = "flms_{$exam_identifier}_exam_attempt_{$attempt}";
		$exam_data = get_user_meta($user_id, $meta_key, true);
		$exam_questions = maybe_unserialize(get_user_meta($user_id, "flms_current_exam_questions_{$flms_exam_id}_$flms_active_version", true)); 
		if(!empty($exam_questions)) {
			$questions = new FLMS_Questions();
			$exam_info = $questions->flms_output_exam_questions($flms_exam_id, $exam_questions, $user_id, $exam_identifier, PHP_INT_MAX, 0, 0, 1, 2, 'graded');
			if(isset($exam_info['questions'])) {
				wp_enqueue_style('flms-questions');
				wp_enqueue_script('flms-exams');
				$exam_user = 0;
				$exam_id = 0;
				if(isset($_GET['user_id'])) {
					$exam_user = absint($_GET['user_id']);
				}
				if(isset($_GET['exam_id'])) {
					$exam_id = absint($_GET['exam_id']);
				}
				$exam_version = 1;
				if(isset($_GET['exam_version'])) {
					$exam_version = absint($_GET['exam_version']);
				}
				$exam_data = array(
					'current_page' => 1,
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'exam_id' => $exam_id,
					'version_index' => $exam_version,
					'current_user_id' => $exam_user,
				);
				wp_localize_script( 'flms-exams', 'flms_exams', $exam_data);
				$return = '<div class="exam-review">';
				$return .= '<div class="flex"><div>Answers:</div><em>Submitted answer</em><span>|</span><strong>Correct answer</strong></div>';
				$return .= '<div id="current-exam">'.$exam_info['questions'].'</div>';
				$return .= '</div>';
				return $return;
			}
		}
		return '';
	}
}
