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
class FLMS_Question {

	public int $question_id;
	public $question_type = '';

	/**
	 * The Constructor.
	 */
	public function __construct(int $post_id, $type = '') {
		$this->question_id = absint($post_id);
		if($type != '') {
			$this->question_type = $type;
		}
	}

	public function question_display($echo = false, $count = '', $user_answers = array(), $review = 0, $exam_is_graded = 'graded', $passed = false, $display_type = 'exam') {
		//$post = setup_postdata( $this->question_id );
		$post_question = get_post($this->question_id);
		$question_content = '';
		if(isset($post_question->post_content)) {
			$question_content = $post_question->post_content;
		}
		if($this->question_type == '') {
			$this->question_type = get_post_meta($this->question_id,'flms_question_type', true );
		}
		
		//$answer = $this->get_question_answer();
		$answer = maybe_unserialize(get_post_meta($this->question_id,'flms_question_answer', true ));
		//echo '<pre>'.print_r($answer,true).'</pre>';
		$user_answer = false;
		$correct = 0;
		if(is_array($user_answers)) {
			if($review == 0) {
				if(array_key_exists($this->question_id, $user_answers)) {
					$user_answer = $user_answers[$this->question_id];
				}
			} else {
				if(array_key_exists($this->question_id, $user_answers)) {
					$user_answer = $user_answers[$this->question_id]['response'];
					$correct = $user_answers[$this->question_id]['correct'];
				}
			}
		}
		$question_classes = 'flms-question flms-question-'.$this->question_id;
		$editable = '';
		if($review == 1) {
			$editable = ' disallow-input';
		}
		$return = '<div class="'.$question_classes.'" data-id="'.$this->question_id.'" data-type="'.$this->question_type.'"';
		if($display_type == 'print') {
			$return .= ' nobr="true"';
		}
		$return .= '>';
			//$return .= print_r($user_answers,true);
			if($this->question_type != 'prompt' && $display_type != 'print') {
				if($count != '') {
					$return .= '<div class="question-count">'.$count.'.</div>';
				}
			}
			if($display_type == 'print' && $this->question_type != 'prompt') {
				$return .= '<table><tr><td width="25">'.$count.'.</td><td width="100%">'.$question_content.'</td></tr></table>';
			}
			$return .= '<div class="question-content">';
				if($display_type != 'print') {
					$return .= '<div class="question-text">'.$question_content.'</div>';
					$return .= '<div class="flms-answer type-'.$this->question_type.$editable.'">';
				} 
				switch($this->question_type) {
					case 'single-choice':
						if(is_array($answer)) {
							foreach($answer as $option) {
								$return .= '<div class="answer-option">';
									$return .= '<label><input type="radio" name="question-'.$this->question_id.'" value="'.$option['answer'].'"';
									if($user_answer !== false) {
										if($user_answer == $option['answer']) {
											$return .= ' checked="checked"';
										}
									}
									$return .= '> ';
									if($display_type == 'print') {
										$return .= '&nbsp;&nbsp;';
									}
									if(current_user_can('administrator') || ($review == 1 && $exam_is_graded == 'graded' && $passed)) {
										if($option['correct'] == 1) {
											$return .= '<strong>';
										}
									}
									if($review == 2 && ($user_answer == $option['answer'])) {
										$return .= '<em>';
									}
									$return .= $option['answer'];
									if($review == 2 && ($user_answer == $option['answer'])) {
										$return .= '</em>';
									}
									if(current_user_can('administrator') || ($review == 1 && $exam_is_graded == 'graded' && $passed)) {
										if($option['correct'] == 1) {
											$return .= '</strong>';
										}
									}
									$return .= '</label>';
								$return .= ' </div>';
							}
						}			
						break;
					case 'multiple-choice':
						if(is_array($answer)) {
							foreach($answer as $option) {
								$return .= '<div class="answer-option">';
									$return .= '<label><input type="checkbox" name="question-'.$this->question_id.'" value="'.$option['answer'].'"';
									if($user_answer !== false) {
										if(in_array($option['answer'],$user_answer)) {
											$return .= ' checked="checked"';
										}
									}
									$return .= '> ';
									if($display_type == 'print') {
										$return .= '&nbsp;&nbsp;';
									}
									if(current_user_can('administrator') || ($review == 1 && $exam_is_graded == 'graded' && $passed)) {
										if($option['correct'] == 1) {
											$return .= '<strong>';
										}
									}
									$return .= $option['answer'];
									if(current_user_can('administrator') || ($review == 1 && $exam_is_graded == 'graded' && $passed)) {
										if($option['correct'] == 1) {
											$return .= '</strong>';
										}
									}
									$return .= '</label>';
								$return .= ' </div>';
							}
						}			
						break;
					case 'free-choice':

						break;
					case 'fill-in-the-blank':
						$option_text = '<input type="text" name="question-'.$this->question_id.'"';
						if($user_answer !== false) {
							$option_text .= ' value="'.$user_answer.'"';
						}
						$option_text .= '/>';
						$return .= $answer['before'] . $option_text . $answer['after'];
						break;
					case 'assessment':
						$before = '';
						if (preg_match('/^(.*?)[{]/', $answer, $matches) == 1) {
							if($matches[1] != '') {
								$before = '<div class="before">'.$matches[1].'</div>';
							}
						}
						if (preg_match('/[{](.*?)[}]/', $answer, $matches) == 1) {
							$options = $matches[1];
							preg_match_all('/\[(.*?)\]/', $options, $matches);
							$option_text = '';
							if(isset($matches[1])) {
								$option_text .= '<div class="answer-option">';
								foreach($matches[1] as $k => $option_input) {
									$option_text .= '<label>';
									$checked = '';
									$defaults = explode('*',$option_input);
									if(count($defaults) > 1) {
										$checked = ' checked="checked"';
										$value = $defaults[1];
									} else {
										$checked = '';
										$value = $defaults[0];
									}
									if($user_answer !== false) {
										if($user_answer == $option_input) {
											$checked = ' checked="checked"';
										} else {
											$checked = '';
										}
									}
									$output_value = true;
									if($value == '') {
										$value = $k;
										$output_value = false;
									}
									$option_text .= '<input type="radio" name="question-'.$this->question_id.'" name="question-'.$this->question_id.'-'.$option_input.'" value="'.$value.'" '.$checked.'>';
									if($output_value) {
										$option_text .= $value;
									}
									$option_text .= '</label>';
								}
								$option_text .= '</div>';
							}
							//print_r($matches[1]);
							
						}
						$after = '';
						if (preg_match('/[}](.*)/', $answer, $matches) == 1) {
							if($matches[1] != '') {
								$after = '<div class="after">'.$matches[1].'</div>';
							}
						}
						$return .= $before . $option_text . $after;
						break;
					case 'essay':
						wp_enqueue_editor();
						$return .= '<div class="answer-option"><textarea name="question-'.$this->question_id.'" id="question'.$this->question_id.'" rows="15">';
						if($user_answer !== false) {
							$return .= $user_answer;
						}
						$return .= '</textarea></div>';
						break;
				}
				if($display_type != 'print') {
					$return .= '</div>';
				}
				if($review == 1 && $exam_is_graded == 'graded' && $passed) {
					if($this->question_type != 'prompt') {
						$return .= '<div class="question-feedback">';
							if($correct == 1) {
								$return .= '<span class="correct">Correct</span>';
							} else {
								$return .= '<span class="incorrect">Incorrect</span>';
							}
						$return .= '</div>';
					}
				}
			$return .= '</div>';
		$return .= '</div>';
		
		if($echo) {
			echo $return;
		} else {
			return $return;
		}
		
	}

	public function get_editor_output() {
		$return = '<div data-id="'.$this->question_id.'" class="exam-question type-'.$this->get_question_type().' hide-answer">';
			$return .= '<div class="handle"></div>';
			$return .= $this->question_display();
			$return .= '<input type="hidden" name="selected-flms-questions[]" value="'.$this->question_id.'" />';
			$return .= '<div class="actions">';
				if($this->get_question_type() != 'prompt') {
					$return .= '<div class="toggle"></div>';
				}
				$return .= '<div class="edit"><a href="'.get_edit_post_link($this->question_id).'" target="_blank"></a></div>';
				$return .= '<div class="remove"></div>';
			$return .= '</div>';
		$return .= '</div>';
		return $return;
		
	}
	private function get_assessment_options($answer) {
		$start = '{';
		$end = '}';
		$answer = ' ' . $answer;
		$ini = strpos($answer, $start);
		if ($ini == 0) return '';
		$ini += strlen($start);
		$len = strpos($answer, $end, $ini) - $ini;
		return substr($answer, $ini, $len);
	}

	
	public function get_question_answer() {
		$answer = maybe_unserialize(get_post_meta($this->question_id,'flms_question_answer', true ));
		//echo '<pre>'.print_r($answer,true).'</pre>';
		$type = $this->get_question_type();
		if(is_array($answer)) {
			if($type == 'single-choice' || $type == 'multiple-choice') {
				//return $answer;
				$answers = array();
				foreach($answer as $answer_option) {
					if($answer_option['correct'] == 1) {
						$answers[] = $answer_option['answer'];
					}
				}
				return $answers;
			} else if($type == 'essay' || $type == 'assessment') {
				return '';
			} else if(isset($answer['correct'])) {
				return $answer['correct'];
			}
		}
		return false;
	}

	public function get_question_export_options() {
		$answer = maybe_unserialize(get_post_meta($this->question_id,'flms_question_answer', true ));
		$type = $this->get_question_type();
		if(is_array($answer)) {
			if($type == 'single-choice' || $type == 'multiple-choice') {
				//return $answer;
				$answers = array();
				foreach($answer as $answer_option) {
					$answers[] = $answer_option['answer'];
				}
				return $answers;
			} else if($type == 'essay' || $type == 'assessment') {
				return '';
			} else if($type == 'fill-in-the-blank') {
				return $answer['text'];
			}
		} else {
			return $answer;
		} 
		return false;
	}

	public function get_question_type() {
		if($this->question_type != '') {
			return $this->question_type;
		} else {
			$this->question_type =  get_post_meta($this->question_id,'flms_question_type', true );
		}
		return $this->question_type;
	}

	public function get_report_data() {
		$report_data = get_post_meta($this->question_id,'flms_question_report_data',true);
		if($report_data == '') {
			return false;
		}
		return $report_data;
	}

	/**
	 * Check if a user answered the question correct and update reporting data for question
	 */
	public function grade_question($user_answer) {
		$question_type = $this->get_question_type();
		$question_answer = $this->get_question_answer();
		$report_data = $this->get_report_data();
		if(!is_array($report_data)) {
			$report_data = array();
		}
		$question_data = maybe_unserialize(get_post_meta($this->question_id,'flms_question_answer', true ));
		$grade = 0;
		switch($question_type) {
			case 'single-choice':
				if(in_array($user_answer, $question_answer)) {
					$grade = 1;
				}
				if(is_array($question_data)) {
					foreach($question_data as $option) {
						if(!isset($report_data[$option['answer']])) {
							$report_data[$option['answer']] = 0;
						}
						if($option['answer'] == $user_answer) {
							$report_data[$option['answer']] += 1;
						}
					}
				}	
				break;
			case 'multiple-choice':
				sort($user_answer);
  				if(!is_array($question_answer)) {
					$question_answer = array($question_answer);
				}
				sort($question_answer);	
				if($user_answer == $question_answer) {
					$grade = 1;
				}
				if(is_array($question_data)) {
					foreach($question_data as $option) {
						if(!isset($report_data[$option['answer']])) {
							$report_data[$option['answer']] = 0;
						}
						if(in_array($option['answer'], $user_answer)) {
							$report_data[$option['answer']] += 1;
						}
					}
				}	
				break;
			case 'free-choice':
				//doesnt exist right now
				break;
			case 'fill-in-the-blank':
				$sanitized_user_user = trim(strtolower($user_answer));
				if(is_array($question_answer)) {
					foreach($question_answer as $answer_option) {
						if(!isset($report_data[$answer_option])) {
							$report_data[$answer_option] = 0;
						}
						if($sanitized_user_user == trim(strtolower($answer_option))) {
							$grade = 1;
							$report_data[$answer_option] += 1;
							break;
						} 
					}
				} else {
					if(!isset($report_data[$question_answer])) {
						$report_data[$question_answer] = 0;
					}
					if($sanitized_user_user == trim(strtolower($question_answer))) {
						$grade = 1;
						$report_data[$question_answer] += 1;
					} else {

					}
					
				}
				if($grade == 0) {
					//user entered incorrectly
					if(isset($report_data['flms-incorrect-answers'][$sanitized_user_user])) {
						$report_data['flms-incorrect-answers'][$sanitized_user_user] += 1;
					} else {
						$report_data['flms-incorrect-answers'][$sanitized_user_user] = 1;
					}
				}
				break;
			case 'assessment':
				if($user_answer != '') {
					$grade = 1;
					//get options
					if (preg_match('/[{](.*?)[}]/', $question_data, $matches) == 1) {
						$options = $matches[1];
						preg_match_all('/\[(.*?)\]/', $options, $matches);
						$option_text = '';
						if(isset($matches[1])) {
							foreach($matches[1] as $k => $option_input) {
								$defaults = explode('*',$option_input);
								if(count($defaults) > 1) {
									$value = $defaults[1];
								} else {
									$value = $defaults[0];
								}
								if($value == '') {
									$value = $k;
								}
								if(!isset($report_data[$value])) {
									$report_data[$value] = 0;
								}
								if($user_answer == $value) {
									$report_data[$value] += 1;
								}
							}
							
						}
						
						
					}
				}
				break;
			case 'essay':
				if($user_answer != '') {
					$grade = 1;
					$report_data[] = $user_answer;
				}
				break;
		}
		//update_post_meta($this->question_id,'flms_question_report_data',$report_data);
		return array(
			'question_correct' => $grade,
			'report_data' => $report_data,
		);
	}


}
