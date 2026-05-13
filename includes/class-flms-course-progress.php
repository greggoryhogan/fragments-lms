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

			$this->log_user_activity($user_id, $course_id, $course_version, 'enrolled');

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

			$this->log_user_activity($user_id, $course_id, $course_version, 'reenrolled');
			
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
			$status = $results[0];
		} else {
			$status = array(
				'id' => false,
				'customer_status' => 'pre-enrollment'
			);
		};
		if(current_user_can('administrator')) {
			if(!is_admin()) {
				$force_acess = apply_filters('flms_admin_user_has_access', true, $course_id, $course_version);
				if($force_acess) {
					if($status['customer_status'] != 'completed') {
						$status['customer_status'] = 'enrolled';
					}
				}
			}
		}
		return $status;
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
				'enroll_date' => current_time('mysql'),
				'last_active' => current_time('mysql'),
			);
			$format = array('%d','%d','%d','%s','%s','%s');
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
					'enroll_date' => current_time('mysql'),
					'last_active' => current_time('mysql'),
				);
				$format = array('%d','%d','%d','%s','%s','%s');
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
			'response' => 'User unenrolled from '.flms_get_the_title($course_id)
		);

		$this->log_user_activity($user_id, $course_id, $course_version, 'unenrolled');

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

			delete_user_meta($user_id, "flms_current_exam_questions_{$exam}_$version");

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
			'response' => 'Customer course progress reset for '.flms_get_the_title($course_id)
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
			'response' => 'Customer course progress reset for '.flms_get_the_title($course_id)
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
			'response' => 'Customer course completion removed for '.flms_get_the_title($course_id)
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
		$all_steps_ct = count($all_steps);
		$completed_steps_ct = count($steps_completed);
		//flms_debug(array($all_steps,$steps_completed), "COMPLETE?");
		if ($all_steps == $steps_completed || $completed_steps_ct >= $all_steps_ct) {
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
			$insert_id = $this->log_user_activity($user_id, $course_id, $course_version, 'completed');
			if($insert_id !== false) {
				$this->save_course_completion_time($insert_id, $course_id, $course_version, $user_id );
			}

			do_action('flms_user_completed_course', $user_id, $course_id, $course_version);

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
		
		$insert_id = $this->log_user_activity($user_id, $course_id, $course_version, 'completed');
		if($insert_id !== false) {
			$this->save_course_completion_time($insert_id, $course_id, $course_version, $user_id );
		}

		do_action('flms_user_completed_course', $user_id, $course_id, $course_version);
		
		$response = array(
			'success' => 1,
			'response' => 'Customer course progress reset for '.flms_get_the_title($course_id),
			'entry_id' => $id
		);
		return $response;
	}

	public function log_user_activity($user_id, $course_id, $course_version, $course_action) {
		$ignore_activity_log_user_ids = apply_filters('flms_ignore_activity_log_users', array());
		if(in_array($user_id, $ignore_activity_log_user_ids)) {
			return false;
		}
		global $wpdb;
		$table = FLMS_USER_ACTIVITY_TABLE;
		$data = array(
			'customer_id' => $user_id,
			'post_id' => $course_id,
			'post_version' => $course_version,
			'post_action' => $course_action,
		);
		$format = array('%d','%s','%s','%s');
		$wpdb->insert($table,$data,$format);
		$id = $wpdb->insert_id;
		return $id;
	}

	public function save_course_completion_time($insert_id, $course_id, $course_version, $user_id) {
		global $wpdb;
		$table = FLMS_USER_ACTIVITY_TABLE;
		$current = $wpdb->get_results("SELECT timestamp FROM $table WHERE id = $insert_id" );
		$previous = $wpdb->get_results("SELECT timestamp FROM $table WHERE id < $insert_id AND customer_id = $user_id AND post_id = '$course_id' AND post_version = '$course_version' AND (post_action = 'enrolled' OR post_action = 'reenrolled') ORDER BY $table.`id` DESC LIMIT 1" );
		if(!empty($previous) && !empty($current)) {
			$current_timestamp = $current[0]->timestamp;
			$previous_timestamp = $previous[0]->timestamp;
			$difference = flms_timestamp_difference_display($previous_timestamp, $current_timestamp);
			if($difference !== false) {
				//saved the difference, we'll move this later but worth saving here
				$completion_times = maybe_unserialize(get_post_meta($course_id, 'flms_course_completion_time', true));
				if(!is_array($completion_times)) {
					$completion_times = array();
				}
				if(!isset($completion_times["$course_id:$course_version"])) {
					$completion_times["$course_id:$course_version"] = array();
				}
				$completion_times["$course_id:$course_version"][] = $difference['seconds'];
				update_post_meta($course_id, 'flms_course_completion_time', maybe_serialize( $completion_times ));
			}
		}
	}

	public function save_exam_completion_time($insert_id, $course_id, $course_version, $user_id) {
		global $wpdb;
		$table = FLMS_USER_ACTIVITY_TABLE;
		$current = $wpdb->get_results("SELECT timestamp FROM $table WHERE id = $insert_id" );
		$previous = $wpdb->get_results("SELECT timestamp FROM $table WHERE id < $insert_id AND customer_id = $user_id AND post_id = '$course_id' AND post_version = '$course_version' AND (post_action = 'started') ORDER BY $table.`id` DESC LIMIT 1" );
		if(!empty($previous) && !empty($current)) {
			$current_timestamp = $current[0]->timestamp;
			$previous_timestamp = $previous[0]->timestamp;
			$difference = flms_timestamp_difference_display($previous_timestamp, $current_timestamp);
			$data = array(
				'current' => $current[0]->timestamp,
				'previous' => $previous[0]->timestamp,
				'diff' => $difference
			);
			if($difference !== false) {
				//saved the difference, we'll move this later but worth saving here
				$completion_times = maybe_unserialize(get_post_meta($course_id, 'flms_exam_completion_time', true));
				if(!is_array($completion_times)) {
					$completion_times = array();
				}
				if(!isset($completion_times["$course_id:$course_version"])) {
					$completion_times["$course_id:$course_version"] = array();
				}
				$completion_times["$course_id:$course_version"][] = $difference['seconds'];
				update_post_meta($course_id, 'flms_exam_completion_time', maybe_serialize( $completion_times ));
			}
		}
	}

	public function display_course_activity_table() {
		?><div class="wrap">
			
			<h1><?= __(FLMS_PLUGIN_NAME .' User Activity', 'flms') ?></h1>
			<div class="page-user-activity">
				<p>Showing activity from the last <strong>7</strong> days.</p>
				<div id="activity-table">
					
						<?php 
						$time_limit = date('Y-m-d 00:00:00',strtotime('6 days ago')); //2022-09-29 19:43:24
						$limit = apply_filters('flms_user_activity_per_page', 15);
						$page = ( isset( $_GET['paged'] ) ) ? $_GET['paged'] : 1;
						$get_user_id = ( isset( $_GET['user-id'] ) ) ? $_GET['user-id'] : '';
						$get_course_id = ( isset( $_GET['course-id'] ) ) ? $_GET['course-id'] : '';
						$get_course_version = ( isset( $_GET['course-version'] ) ) ? $_GET['course-version'] : '';
						
						$offset = $limit * ($page - 1);
						
						global $wpdb;
						$date_format = get_option('date_format');
						$time_format = get_option('time_format');
						$table = FLMS_USER_ACTIVITY_TABLE;
						
						if($get_user_id != '') {
							$total = $wpdb->get_results("SELECT * FROM $table WHERE timestamp >= '$time_limit' AND customer_id = ".$get_user_id." AND post_action != 'started'" );
							$results = $wpdb->get_results("SELECT * FROM $table WHERE timestamp >= '$time_limit' AND customer_id = ".$get_user_id." AND post_action != 'started' ORDER BY $table.`id` DESC LIMIT $limit OFFSET $offset" );
						} else if($get_course_id != '' && $get_course_version != '') {
							$total = $wpdb->get_results("SELECT * FROM $table WHERE timestamp >= '$time_limit' AND post_id IN (" . implode(',', $get_course_id) . ") AND post_action != 'started' AND post_version = $get_course_version" );
							$results = $wpdb->get_results("SELECT * FROM $table WHERE timestamp >= '$time_limit' AND post_id IN (" . implode(',', $get_course_id) . ")  AND post_action != 'started' AND post_version = $get_course_version ORDER BY $table.`id` DESC LIMIT $limit OFFSET $offset" );
						} else {
							$total = $wpdb->get_results("SELECT * FROM $table WHERE timestamp >= '$time_limit' AND post_action != 'started'" );
							$results = $wpdb->get_results("SELECT * FROM $table WHERE timestamp >= '$time_limit' AND post_action != 'started' ORDER BY $table.`id` DESC LIMIT $limit OFFSET $offset" );
						}

						//echo "SELECT * FROM $table WHERE timestamp >= $time_limit ORDER BY $table.`id` DESC LIMIT $limit OFFSET $offset";

						
						if(!empty($results)) {
							echo '<table>';
							echo '<tr><td class="heading">Date</td><td class="heading">User</td><td class="heading">Course</td><td class="heading">Activity</td></tr>';
							foreach ($results as $result){    
								$time = '';
								$action = $result->post_action;
								$user = get_user_by('id', $result->customer_id);
								if($user == false) {
									continue;
								}
								$post_id = $result->post_id;
								if(get_post_type($post_id) == 'flms-courses') {
									if($action == 'enrolled' || $action == 'reenrolled') {
										$action .= ' in';
									}
									if($action == 'unenrolled') {
										$action .= ' from';
									}
									$action_type = 'course';
									if($action == 'completed') {
										$previous = $wpdb->get_results("SELECT timestamp FROM $table WHERE customer_id = $result->customer_id AND post_id = '$result->post_id' AND post_version = '$result->post_version' AND (post_action = 'enrolled' OR post_action = 'reenrolled') ORDER BY $table.`id` DESC LIMIT 1" );
										if(!empty($previous)) {
											$previous_timestamp = $previous[0]->timestamp;
											$difference = flms_timestamp_difference_display($previous_timestamp, $result->timestamp);
											if($difference !== false) {
												$time .= ' ('.$difference['output'].')';
											}
										}
										
									}
									$course_id = $post_id;
								} else if(get_post_type($post_id) == 'flms-lessons') {
									$course_id = get_post_meta($post_id,'flms_course',true);
									$action_type = 'lesson "'.get_the_title($post_id).'"';
								} else {
									$action_type = 'exam "'.get_the_title($post_id).'"';
									$parent_id = flms_get_exam_version_parent($post_id);
									$parent = get_post($parent_id);
									if($parent->post_type == 'flms-lessons') {
										$course_id = get_post_meta($parent_id,'flms_course',true);
									} else {
										$course_id = $parent_id;
									}
									if($action == 'completed' || $action == 'failed') {
										$previous = $wpdb->get_results("SELECT id, timestamp FROM $table WHERE id < $result->id AND customer_id = $result->customer_id AND post_id = '$result->post_id' AND post_version = '$result->post_version' AND (post_action = 'started') ORDER BY $table.`id` DESC LIMIT 1" );
										if(!empty($previous)) {
											$previous_timestamp = $previous[0]->timestamp;
											$difference = flms_timestamp_difference_display($previous_timestamp, $result->timestamp);
											if($difference !== false) {
												$time .= ' ('.$difference['output'].')';
											}
										} 
										
									}
								}
								$course = new FLMS_Course($course_id);
								global $flms_active_version;
								$course_title = '<div class="flms-flex gap-sm">';
								$toggled = '';
								$course = new FLMS_Course($course_id);
								global $flms_active_version;
								$flms_active_version = $result->post_version;
								$steps = $course->get_all_course_steps();
								if(!in_array($result->post_id, $steps)) {
									$steps[] = $result->post_id;
								}
								if(!in_array($course_id, $steps)) {
									$steps[] = $course_id;
								}
								$course_steps = http_build_query(array('course-id' => $steps));
								$filter_link = admin_url('admin.php?page=flms-user-activity&'.$course_steps.'&course-version='.$result->post_version);
								if($get_course_id != '' && $get_course_version != '') {
									$toggled = ' toggled';
									$filter_link = admin_url('admin.php?page=flms-user-activity');
								}
								$course_title .= ' <a href="'.$filter_link.'" class="filter-by-course '.$toggled.'">Filter by course</a>';
								$course_title .= '<span>'.$course->get_course_version_name($result->post_version).'</span>';
								$course_title .= '</div>';
								

								$date = get_date_from_gmt( date( "$date_format, $time_format", strtotime($result->timestamp) ), "$date_format, $time_format" );
								
								$user_info = '<div class="flms-flex gap-sm">';
								$user_filter_link = admin_url('admin.php?page=flms-user-activity&user-id='.$result->customer_id);
								$user_toggled = '';
								if($get_user_id != '') {
									$user_toggled = ' toggled';
									$user_filter_link = admin_url('admin.php?page=flms-user-activity');
								}
								$user_info .= ' <a href="'.$user_filter_link.'" class="filter-by-course '.$user_toggled.'">Filter by course</a>';
								$user_info .= '<div>'.$user->first_name.' '.$user->last_name.' ('.$user->user_email.')</div>';
								$user_info .= '</div>';
								echo '<tr><td data-title="Date">'.$date.'</td><td data-title="User">'.$user_info.'</td><td data-title="Course">'.$course_title.'</td><td class="activity" data-title="Activity">'.$action.' '.$action_type.$time.'</td></tr>';
							}
							echo '</table>';
						} else {
							echo '<em>No activity yet.</em>';
						}
						 ?>
					
				</div>
				<?php 
				if(count($results) < count($total)) {
					echo '<div class="activity-pagination">';
					
						$pages = ceil(count($total) / $limit);
						$big = 999999999; // need an unlikely integer
						$course_links = 'course-id=';
						if( isset( $_GET['course-id'] )) {
							$course_links = http_build_query(array('course-id' => $_GET['course-id']));
						} 
						$big = 999999999; // need an unlikely integer

						echo paginate_links(
							array(
								'base' => admin_url("admin.php?page=flms-user-activity&paged=%#%&$course_links&course-version=$get_course_version"),
								'format' => "?paged=%#%",
								'current' => max(
									1,
									$page
								),
								'total' => $pages //$q is your custom query
							)
						);				  
					echo '</div>';
				} ?>
			</div>
		</div><?php
	}

	public function send_customer_completed_course_email($user_id, $course_id, $course_version) {
		WC()->mailer()->get_emails()['FLMS_Email_Customer_Completed_Course']->trigger( $user_id, $course_id, $course_version );
	}

}
new FLMS_Course_Progress();
