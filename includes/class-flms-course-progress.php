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
class FLMS_Course_Progress {

	/**
	 * The Constructor.
	 */
	public function __construct() {
		
	}

	public function enroll_user($user_id, $course_id, $course_version) {
		$status = $this->get_user_course_status($user_id, $course_id, $course_version);
		if($status['customer_status'] == 'pre-enrollment') {
			//Add to course activity log
			$id = $this->update_user_activity_log($user_id, $course_id, $course_version, 'enrolled'); 
			$response = array(
				'success' => 1,
				'response' => 'Enrollment successful!',
				'id' => $id
			);

			//log reporting field
			if(flms_is_module_active('course_credits') && $id > 0) {
				global $flms_settings;
				$course_credits = new FLMS_Module_Course_Credits();
				$credit_fields = $course_credits->get_course_credit_fields(false);
				foreach($credit_fields as $credit_type) {
					global $wpdb;
					$data = array(
						'entry_id' => $id,
						'credit_type' => $credit_type,
						'accepts_reporting_fee' => 0
					);
					$format = array('%d','%s','%d');
					$wpdb->insert(FLMS_REPORTING_TABLE,$data,$format);			
				}
			}
			return $response;
		} else if ($status['customer_status'] == 'completed') {
			//they were previously enrolled, enroll them again
			$id = $this->update_user_activity_log($user_id, $course_id, $course_version, 'enrolled', true); 
			$response = array(
				'success' => 1,
				'response' => 'Enrollment successful!',
				'id' => $id
			);

			//log reporting field
			if(flms_is_module_active('course_credits') && $id > 0) {
				global $flms_settings;
				$course_credits = new FLMS_Module_Course_Credits();
				$credit_fields = $course_credits->get_course_credit_fields(false);
				foreach($credit_fields as $credit_type) {
					global $wpdb;
					$data = array(
						'entry_id' => $id,
						'credit_type' => $credit_type,
						'accepts_reporting_fee' => 0
					);
					$format = array('%d','%s','%d');
					$wpdb->insert(FLMS_REPORTING_TABLE,$data,$format);			
				}
			}
			
			return $response;
		
		} else {
			$response = array(
				'success' => 1,
				'response' => 'You are currently enrolled in this course.',
				'id' => false,
			);
		}
		return $response;	
	}

	public function get_user_course_status($user_id, $course_id, $course_version) {
		global $wpdb;
		$table = FLMS_ACTIVITY_TABLE;
		$sql_query = $wpdb->prepare("SELECT id, customer_status FROM $table WHERE course_id=%d AND course_version=%d AND customer_id=%d ORDER BY id DESC LIMIT 1", $course_id, $course_version, $user_id);
		$results = $wpdb->get_results( $sql_query, ARRAY_A ); 
		if(!empty($results)) {
			return $results[0];
		} else {
			return array(
				'id' => false,
				'customer_status' => 'pre-enrollment'
			);
		}
	}

	public function update_user_activity_log($user_id, $course_id, $course_version, $status, $reenroll = false) {
		global $wpdb;
		$table = FLMS_ACTIVITY_TABLE;
		$sql_query = $wpdb->prepare("SELECT id FROM $table WHERE course_id=%d AND course_version=%d AND customer_id=%d ORDER BY id DESC LIMIT 1", $course_id, $course_version, $user_id);
		$results = $wpdb->get_results( $sql_query ); 
		if($reenroll) {
			$data = array(
				'course_id' => $course_id,
				'course_version' => $course_version,
				'customer_id' => $user_id,
				'customer_status' => $status,
			);
			$format = array('%d','%d','%d','%s');
			$wpdb->insert($table,$data,$format);
			$id = $wpdb->insert_id;
			$this->reset_course_progress($user_id, $course_id, $course_version);
		} else if(!empty($results)) {
			foreach($results as $result) {
				$id = $result->id;
				$wpdb->update( 
					$table, 
					array( 
						'customer_status' => $status,
					), 
					array( 
						'id' => $id
					) 
				);
				if($status == 'completed') {
					$wpdb->update( 
						$table, 
						array( 
							'completion_date' => current_time('mysql'),
						), 
						array( 
							'id' => $id
						) 
					);	
				} else if($status == 'pre-enrollment') {
					$wpdb->update( 
						$table, 
						array( 
							'completion_date' => '',
						), 
						array( 
							'id' => $id
						) 
					);	
				}
			}
		} else {
			if($course_id != 0 && $course_version != 0 && $user_id > 0 && $status != '') {
				$data = array(
					'course_id' => $course_id,
					'course_version' => $course_version,
					'customer_id' => $user_id,
					'customer_status' => $status,
				);
				$format = array('%d','%d','%d','%s');
				$wpdb->insert($table,$data,$format);
				$id = $wpdb->insert_id;
			} else {
				$id = 0;
			}
		}
		return $id;
	}

	public function delete_log_entry($entry_id) {
		global $wpdb;
		if(flms_is_module_active('course_credits')) {
			$wpdb->delete( FLMS_REPORTING_TABLE, array( 'entry_id' => $entry_id ) );
		}
		$wpdb->delete( FLMS_ACTIVITY_TABLE, array( 'id' => $entry_id ) );
		
	}
	
	public function unenroll_user($user_id, $course_id, $course_version) {
		$entry_id = $this->update_user_activity_log($user_id, $course_id, $course_version, 'pre-enrollment'); 
		$this->reset_course_progress($user_id, $course_id, $course_version);
		$this->delete_log_entry($entry_id);
		$course_identifier = "$course_id-$course_version";
		$response = array(
			'success' => 1,
			'response' => 'User unenrolled from '.get_the_title($course_id)
		);
		return $response;	
	}

	public function get_user_activity($user_id = 0, $course_id = 0, $course_version = 0) {
		if($user_id == 0) {
			global $current_user;
			$user_id = $current_user->ID;
		}
		global $wpdb;
		$table = FLMS_ACTIVITY_TABLE;
		$sql_query = $wpdb->prepare("SELECT * FROM $table WHERE course_id=%d AND course_version=%d AND customer_id=%d ORDER BY id DESC LIMIT 1", $course_id, $course_version, $user_id);
		$results = $wpdb->get_results( $sql_query, ARRAY_A ); 
		if(!empty($results)) {
			return $results[0];
		} else {
			return array(
				'customer_id' => $user_id,
				'course_id' => $course_id,
				'course_version' => $course_version,
				'customer_status' => 'pre-enrolled',
				'steps_completed' => array(),
				'enroll_date' => current_time('mysql'),
				'completion_date' => NULL,
				'last_active' => current_time('mysql'),
			);
		}
	}

	public function reset_course_progress($user_id = 0, $course_id = 0, $version = 0) {
		$course_identifier = "$course_id:$version";
		//clear exam attempts
		$course = new FLMS_Course($course_id);
		global $flms_active_version;
		$flms_active_version = $version;
		$exams = $course->get_course_version_exams();
		foreach($exams as $exam) {
			$exam_identifier = "$exam:$version";

			$meta_key = "flms_{$exam_identifier}_exam_answers";
			delete_user_meta($user_id, $meta_key);

			delete_user_meta($user_id, "flms_current_exam_questions_$exam");

			$meta_key = "flms_{$exam_identifier}_exam_attempts";
			$attempts = get_user_meta($user_id, $meta_key, true);
			if($attempts != '') {
				$attempt_meta_key = "flms_{$exam_identifier}_exam_attempt_{$attempts}";
				$last_attempt = get_user_meta($user_id, $attempt_meta_key, true);
				if($last_attempt == '') {
					$last_attempt_num = $attempts - 1;
					$attempt_meta_key = "flms_{$exam_identifier}_exam_attempt_{$last_attempt_num}";
				}
				delete_user_meta($user_id, $attempt_meta_key);
				delete_user_meta($user_id, $meta_key);
			}

		}
		$lessons = $course->get_lessons();
		foreach($lessons as $lesson_id) {
			$lesson = new FLMS_Lesson($lesson_id);
			global $flms_active_version;
			$flms_active_version = $version;
			$exams = $lesson->get_lesson_version_exams();
			foreach($exams as $exam) {
				$exam_identifier = "$exam:$version";

				$meta_key = "flms_{$course_identifier}_exam_answers";
				delete_user_meta($user_id, $meta_key);

				$meta_key = "flms_{$exam_identifier}_exam_attempts";
				$attempts = get_user_meta($user_id, $meta_key, true);
				if($attempts != '') {
					$attempt_meta_key = "flms_{$exam_identifier}_exam_attempt_{$attempts}";
					$last_attempt = get_user_meta($user_id, $attempt_meta_key, true);
					if($last_attempt == '') {
						$last_attempt_num = $attempts - 1;
						$attempt_meta_key = "flms_{$exam_identifier}_exam_attempt_{$last_attempt_num}";
					}
					delete_user_meta($user_id, $attempt_meta_key);
					delete_user_meta($user_id, $meta_key);
				}
			}
		}

		//reset progress int he activity table
		global $wpdb;
		$table = FLMS_ACTIVITY_TABLE;
		$sql_query = $wpdb->prepare("SELECT id, customer_status FROM $table WHERE course_id=%d AND course_version=%d AND customer_id=%d ORDER BY id DESC LIMIT 1", $course_id, $version, $user_id);
		$results = $wpdb->get_results( $sql_query ); 
		if(!empty($results)) {
			foreach($results as $result) {
				$id = $result->id;
				$status = $result->customer_status;
				if($status == 'enrolled') {
					$wpdb->update( 
						$table, 
						array( 
							'steps_completed' => NULL,
							'completion_date' => NULL,
						), 
						array( 
							'id' => $id
						) 
					);
				}
				/*if($status == 'completed') {
					$wpdb->update( 
						$table, 
						array( 
							'steps_completed' => 0,
							'customer_status' => 'pre-enrollment',
						), 
						array( 
							'id' => $id
						) 
					);
				}*/
			}
		}

		$response = array(
			'success' => 1,
			'response' => 'Customer course progress reset for '.get_the_title($course_id)
		);
		return $response;
	}

	public function reset_user_completed_course($user_id = 0, $course_id = 0, $version = 0) {
		global $wpdb;
		$table = FLMS_ACTIVITY_TABLE;
		$sql_query = $wpdb->prepare("SELECT id FROM $table WHERE customer_status=%s AND course_id=%d AND course_version=%d AND customer_id=%d ORDER BY id DESC LIMIT 1", 'completed', $course_id, $version, $user_id);
		$results = $wpdb->get_results( $sql_query ); 
		if(!empty($results)) {
			foreach($results as $result) {
				$id = $result->id;
				$wpdb->update( 
					$table, 
					array( 
						'customer_status' => 'enrolled',
					), 
					array( 
						'id' => $id
					) 
				);
			}
		}
		$this->reset_course_progress($user_id, $course_id, $version);
		$response = array(
			'success' => 1,
			'response' => 'Customer course progress reset for '.get_the_title($course_id)
		);
		return $response;
	}

	public function remove_user_completed_course($user_id = 0, $course_id = 0, $version = 0) {
		global $wpdb;
		$table = FLMS_ACTIVITY_TABLE;
		$sql_query = $wpdb->prepare("SELECT id FROM $table WHERE customer_status=%s AND course_id=%d AND course_version=%d AND customer_id=%d ORDER BY id DESC LIMIT 1", 'completed', $course_id, $version, $user_id);
		$results = $wpdb->get_results( $sql_query ); 
		if(!empty($results)) {
			foreach($results as $result) {
				$id = $result->id;
				$wpdb->update( 
					$table, 
					array( 
						'customer_status' => 'pre-enrollment',
					), 
					array( 
						'id' => $id
					) 
				);
				$this->reset_course_progress($user_id, $course_id, $version);
				$this->delete_log_entry($id);
			}
		}
		$response = array(
			'success' => 1,
			'response' => 'Customer course completion removed for '.get_the_title($course_id)
		);
		return $response;
	}

	public function update_user_activity($post_id, $user_id = 0, $course_id = 0, $course_version = 0) {
		global $wpdb;
		$table = FLMS_ACTIVITY_TABLE;
		$sql_query = $wpdb->prepare("SELECT id, steps_completed FROM $table WHERE course_id=%d AND course_version=%d AND customer_id=%d ORDER BY id DESC LIMIT 1", $course_id, $course_version, $user_id);
		$results = $wpdb->get_results( $sql_query ); 
		if(!empty($results)) {
			foreach($results as $result) {
				$id = $result->id;
				$steps_completed = maybe_unserialize($result->steps_completed);
				if(!is_array($steps_completed)) {
					$steps_completed = array();
				}
				if(!in_array($post_id, $steps_completed)) {
					$steps_completed[] = $post_id;
					//flms_debug($steps_completed,'steps for enrolled not completed');
					$wpdb->update( 
						$table, 
						array( 
							'steps_completed' => maybe_serialize( $steps_completed ),
							'last_active' => current_time('mysql'),
						), 
						array( 
							'id' => $id
						) 
					);
				}
				
			}
		} else {
			$steps_completed = array($post_id);
			$data = array(
				'course_id' => $course_id,
				'course_version' => $course_version,
				'customer_id' => $user_id,
				'customer_status' => 'enrolled',
				'steps_completed' => maybe_serialize($steps_completed)
			);
			$format = array('%d','%d','%d','%s', '%s');
			$wpdb->insert($table,$data,$format);
			$id = $wpdb->insert_id;
		}
		
		
		$course = new FLMS_Course($course_id);
		global $flms_active_version;
		$flms_active_version = $course_version;
		$course_steps = $course->get_course_steps_order();
		$all_steps = $course->get_all_course_steps();

		//see if all topics and exams in a lesson have been completed and if so, complete the lesson
		$post_type = get_post_type($post_id);
		if($post_type == 'flms-topics' || $post_type == 'flms-exams') {
			if($post_type == 'flms-topics') {
				$parent_id = flms_get_topic_version_parent($post_id);					
			} else if($post_type == 'flms-exams') {
				$parent_id = flms_get_exam_version_parent($post_id);
			}
			if(!in_array($parent_id,$steps_completed)) {
				//print_r($course_steps);
				if (array_key_exists($parent_id, $course_steps)) {
					if(is_array($course_steps[$parent_id])) {
						$step_parent_complete = true;
						foreach($course_steps[$parent_id] as $step_id) {
							if(!in_array($step_id, $steps_completed)) {
								$step_parent_complete = false;
								break;
							}
						}
						if($step_parent_complete) {
							$steps_completed[] = $parent_id;
							$wpdb->update( 
								$table, 
								array( 
									'steps_completed' => maybe_serialize( $steps_completed ),
									'last_active' => current_time('mysql'),
								), 
								array( 
									'id' => $id
								) 
							);
						}
					}
				}
			}
		}

		sort($all_steps);
		sort($steps_completed);			
		//flms_debug(array($all_steps,$steps_completed), "COMPLETE?");
		if ($all_steps == $steps_completed) {
			$wpdb->update( 
				$table, 
				array( 
					'customer_status' => 'completed',
					'completion_date' => current_time('mysql'),
				), 
				array( 
					'id' => $id
				) 
			);
			
			//unenroll from the cours
			//$this->unenroll_user($user_id, $course_id, $course_version);
		}
	
		return true;
	}

	public function complete_course($user_id, $course_id, $course_version) {
		global $wpdb;
		$course = new FLMS_Course($course_id);
		global $flms_active_version;
		$flms_active_version = $course_version;
		$all_steps = $course->get_all_course_steps();

		$table = FLMS_ACTIVITY_TABLE;
		$sql_query = $wpdb->prepare("SELECT id FROM $table WHERE course_id=%d AND course_version=%d AND customer_id=%d ORDER BY id DESC LIMIT 1", $course_id, $course_version, $user_id);
		$results = $wpdb->get_results( $sql_query ); 
		if(!empty($results)) {
			foreach($results as $result) {
				$id = $result->id;
				$wpdb->update( 
					$table, 
					array( 
						'customer_status' => 'completed',
						'steps_completed' => maybe_serialize( $all_steps ),
						'last_active' => current_time('mysql'),
						'completion_date' => current_time('mysql'),
					), 
					array( 
						'id' => $id
					) 
				);
			}
		} else {
			$data = array(
				'course_id' => $course_id,
				'course_version' => $course_version,
				'customer_id' => $user_id,
				'customer_status' => 'completed',
				'steps_completed' => maybe_serialize($all_steps),
				'completion_date' => current_time('mysql'),
			);
			$format = array('%d','%d','%d','%s','%s','%s');
			$wpdb->insert($table,$data,$format);
			$id = $wpdb->insert_id;
		}
		$response = array(
			'success' => 1,
			'response' => 'Customer course progress reset for '.get_the_title($course_id),
			'entry_id' => $id
		);
		return $response;
	}
}
new FLMS_Course_Progress();
