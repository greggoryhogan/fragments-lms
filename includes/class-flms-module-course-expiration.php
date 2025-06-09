<?php
class FLMS_Module_Course_expiration {
    public function __construct() {
		//add_action('init',array($this,'test_int'));
	}
    public function get_expiration_settings_fields() {
        /*
         array(
                'label' => 'Expiration access',
                'key' => 'course_expiration_access',
                'type' => 'select',
                'description' => 'Define what happens when a user tries to access and expired course. <br><strong>Access actions:</strong> Completing the course, lessons, topics or exams.<br><strong>No action</strong> will disable course expiration functionality.',
                'options' => array(
                    'disable-all' => 'Enrollment and course actions are disabled',
                    'disable-enrollment' => 'Enrollment disabled, access actions continue',
                    'no-action' => 'No action',
                ),
                'default' => 'disable-all',
            ),
            */
        $fields = array(
            array(
                'label' => 'Expiration course action',
                'key' => 'course_expiration_action',
                'type' => 'select',
                'description' => 'Define what happens when a course expires. <br><strong>Access actions:</strong> Completing the course, lessons, topics or exams.<br><strong>No action</strong> will disable course expiration functionality.',
                'options' => array(
                    'archive-course' => 'Automatically expire course version',
                    'no-action' => 'No action',
                ),
                'default' => 'archive-course',
            ),
            array(
                'label' => 'Expiration user action',
                'key' => 'user_expiration_action',
                'type' => 'select',
                'description' => 'Define what happens when a course expires. <br><strong>Access actions:</strong> Completing the course, lessons, topics or exams.<br><strong>No action</strong> will disable course expiration functionality.',
                'options' => array(
                    'expire' => 'Move access to expired',
                    'unenroll' => 'Unenroll user',
                    'no-action' => 'No action',
                ),
                'default' => 'archive-course',
            ),
            array(
                'label' => 'Schedule',
                'key' => 'course_expiration_schedule',
                'type' => 'select',
                'description' => 'Define how often the server should check to see if a course is expired',
                'options' => array(
                    'flms_5_minutes' => 'Every 5 minutes',
                    'flms_10_minutes' => 'Every 10 minutes',
                    'flms_15_minutes' => 'Every 15 minutes',
                    'flms_30_minutes' => 'Every 30 minutes',
                    'hourly' => 'Every hour',
                    'twicedaily' => 'Twice daily',
                    'daily' => 'Daily',
                ),
                'default' => 'hourly',
            ),
            array(
                'label' => 'Reminders',
                'key' => 'course_expiration_reminder',
                'type' => 'select',
                'description' => 'Define if a reminder should be sent to users who have not completed the course',
                'options' => array(
                    'no-reminder' => 'No reminder',
                    'week-before' => 'Remind customers a week before',
                    'day-before' => 'Remind customers the day before',
                    'day-of' => 'Remind customers the day of',
                ),
                'default' => 'no-reminder',
            ),
            array(
                'label' => 'Enrolled User Expiration Text',
                'key' => 'enrolled_user_course_expiration_text',
                'type' => 'text',
                'description' => 'The notice to show on a course with an expiration.<br><strong>Placeholders:</strong><br>%expiration_date%<br>%expiration_time%<br>%expiration_timezone%',
                'default' => "This course expires on %expiration_date% at %expiration_time% %expiration_timezone%. Please complete the course before it expires."
            ),
            array(
                'label' => 'Unenrolled User Expiration Text',
                'key' => 'unenrolled_user_course_expiration_text',
                'type' => 'text',
                'description' => 'The notice to show on a course with an expiration.<br><strong>Placeholders:</strong><br>%expiration_date%<br>%expiration_time%<br>%expiration_timezone%',
                'default' => "This course expires on %expiration_date% at %expiration_time% %expiration_timezone%. Please purchase and complete the course before it expires."
            ),
            array(
                'label' => 'Expired Course Text',
                'key' => 'expired_user_course_expiration_text',
                'type' => 'text',
                'description' => 'The notice to show when after a course has expired.<br><strong>Placeholders:</strong><br>%expiration_date%<br>%expiration_time%<br>%expiration_timezone%',
                'default' => "This course expired on %expiration_date% at %expiration_time% %expiration_timezone%."
            ),
        );
        /*
        array(
            'label' => 'Expiration schedule',
            'key' => 'course_expiration_schedule',
            'type' => 'select',
            'description' => 'Define how often the server should check to see if a course is expired',
            'options' => array(
                'flms_5_minutes' => 'Every 5 minutes',
                'flms_10_minutes' => 'Every 10 minutes',
                'flms_15_minutes' => 'Every 15 minutes',
                'flms_30_minutes' => 'Every 30 minutes',
                'hourly' => 'Every hour',
                'twicedaily' => 'Twice daily',
                'daily' => 'Daily',
            ),
            'default' => 'hourly',
        ),
        */
        return $fields;
    }

    public function get_white_label_field($field) {
        global $flms_settings;
        $return = '';
        if(isset($flms_settings['course_expiration'])) {
            $white_label_fields = $flms_settings['course_expiration'];
            if(isset($white_label_fields["$field"])) {
                $return = $white_label_fields["$field"];
            } 
        }
        return $return;
    }

    public function course_expiration_cron_schedules($schedules) {
        $schedules['flms_5_minutes'] = array(
            'interval' => 300,
            'display'  => esc_html__( 'Every Five Minutes' ), );
        $schedules['flms_10_minutes'] = array(
            'interval' => 600,
            'display'  => esc_html__( 'Every 10 Minutes' ), );
        $schedules['flms_15_minutes'] = array(
            'interval' => 900,
            'display'  => esc_html__( 'Every 15 Minutes' ), );
        $schedules['flms_30_minutes'] = array(
            'interval' => 1800,
            'display'  => esc_html__( 'Every 30 Minutes' ), );

        return $schedules;
    }

    public function flms_course_expiration_notice($course_id = 0, $course_version = 0, $alert = true) {
        global $post, $flms_course_id, $flms_active_version;
        $notice = $this->get_course_expiration_text($flms_course_id, $flms_active_version);
        if($notice != '') {
            echo flms_alert($notice);
        }
    }

    public function get_course_expiration_text($course_id = 0, $course_version = 0) {
        global $flms_course_id, $flms_active_version, $flms_settings;
        if($course_id == 0) {
            $course_id = $flms_course_id;
        }
        if($course_version == 0) {
            $course_version = $flms_active_version;
        }
        $notice = '';
        if($this->course_has_expiration($course_id, $course_version)) {
            $flms_course_version_content = get_post_meta($course_id,'flms_version_content',true);	
            $date = date(get_option('date_format'),strtotime($flms_course_version_content[$course_version]['course_expiration']['expiration_date']));
            $time = date(get_option('time_format'), strtotime($flms_course_version_content[$course_version]['course_expiration']['expiration_time']));
            $course_is_expired = $this->is_course_expired($course_id, $course_version);
            if($course_is_expired) {
                if(isset($flms_settings['course_expiration']['expired_user_course_expiration_text'])) {
                    $notice = $flms_settings['course_expiration']['expired_user_course_expiration_text'];
                }
            } else {
                //expired_user_course_expiration_text
                $flms_user_has_access = flms_user_has_access($course_id, $course_version, true);
                if($flms_user_has_access) {
                    if(isset($flms_settings['course_expiration']['enrolled_user_course_expiration_text'])) {
                        $notice = $flms_settings['course_expiration']['enrolled_user_course_expiration_text'];
                    }
                } else {
                    if(isset($flms_settings['course_expiration']['unenrolled_user_course_expiration_text'])) {
                        $notice = $flms_settings['course_expiration']['unenrolled_user_course_expiration_text'];
                    }
                }
            }
            $notice = str_replace('%expiration_date%', $date, $notice);
            $notice = str_replace('%expiration_time%', $time, $notice);
            $notice = str_replace('%expiration_timezone%', $this->getabbreviatedtimezone(), $notice);
        }
        
		//echo '<pre>'.print_r($flms_course_version_content,true).'</pre>';
        return $notice;
    }

    public function get_course_expiration_date($course_id = 0, $course_version = 0) {
        global $flms_course_id, $flms_active_version, $flms_settings;
        if($course_id == 0) {
            $course_id = $flms_course_id;
        }
        if($course_version == 0) {
            $course_version = $flms_active_version;
        }
        $notice = '';
        if($this->course_has_expiration($course_id, $course_version)) {
            $flms_course_version_content = get_post_meta($course_id,'flms_version_content',true);	
            $date = date(get_option('date_format'),strtotime($flms_course_version_content[$course_version]['course_expiration']['expiration_date']));
            $time = date(get_option('time_format'), strtotime($flms_course_version_content[$course_version]['course_expiration']['expiration_time']));
            return $date .' '.$time;
        }
        
		//echo '<pre>'.print_r($flms_course_version_content,true).'</pre>';
        return $notice;
    }

    private function getabbreviatedtimezone(){
        $date = new DateTime(null, new DateTimeZone(wp_timezone_string()));
        return $date->format('T');
    }

    public function course_has_expiration($course_id = 0, $course_version = 0) {
        global $flms_course_id, $flms_active_version, $flms_settings;
        if($course_id == 0) {
            $course_id = $flms_course_id;
        }
        if($course_version == 0) {
            $course_version = $flms_active_version;
        }
        $flms_course_version_content = get_post_meta($course_id,'flms_version_content',true);	
        if(isset($flms_course_version_content[$course_version]['course_expiration'])) {
            if($flms_course_version_content[$course_version]['course_expiration'] != '') {
                if(isset($content['course_expiration']['version_expires'])) {
				    if($content['course_expiration']['version_expires'] != '') {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public function is_course_expired($course_id = 0, $course_version = 0) {
        global $flms_course_id, $flms_active_version;
        if($course_id == 0) {
            $course_id = $flms_course_id;
        }
        if($course_version == 0) {
            $course_version = $flms_active_version;
        }
        if(!$this->course_has_expiration($course_id, $course_version)) {
            return false;
        }
        $flms_course_version_content = get_post_meta($course_id,'flms_version_content',true);	
        $date = date(get_option('date_format'),strtotime($flms_course_version_content[$course_version]['course_expiration']['expiration_date']));
        $time = date(get_option('time_format'), strtotime($flms_course_version_content[$course_version]['course_expiration']['expiration_time']));

        //echo $flms_course_version_content[$course_version]['course_expiration']['expiration_date'];
        $expiration_time = strtotime("$date $time");
        $current_datetime = current_datetime();
        $current_time = strtotime($current_datetime->format('Y-m-d H:i:s'));

        if($current_time > $expiration_time) {
            $this->expire_course($course_id, $course_version);
            return true;
        }
        return false;
    }

    //convert course to draft so it can no longer be enrolled
    public function expire_course($course_id, $course_version) {
        global $flms_settings;
        $action = 'archive-course';
        if(isset($flms_settings['course_expiration']['course_expiration_action'])) {
            $action = $flms_settings['course_expiration']['course_expiration_action'];
        }
        if($action == 'archive-course') {
            $flms_course_version_content = get_post_meta($course_id,'flms_version_content',true);
            $flms_course_version_content[$course_version]['version_status'] = 'archived';
            update_post_meta($course_id, 'flms_version_content', $flms_course_version_content);
            $this->check_course_for_expiration_flags($course_id, $course_version);
            $action = 'expire';
            if(isset($flms_settings['course_expiration']['user_expiration_action'])) {
                $action = $flms_settings['course_expiration']['user_expiration_action'];
            }
            if($action == 'expire') {
                $this->archive_expired_users($course_id, $course_version);
            } else if($action == 'unenroll') {
                $this->unenroll_expired_users($course_id, $course_version);
            }
        }
        
        //Update course metadata
        $course_manager = new FLMS_Course_Manager();
        $course_manager->update_course_query_metadata($course_id);
    }

    public function check_course_for_expiration_flags($course_id, $course_version) {
        $course_versioned_content = get_post_meta($course_id,'flms_version_content',true);
        $expirations = array();
		foreach($course_versioned_content as $active_version => $content) {
			if(isset($content['course_expiration']['version_expires'])) {
				if($content['course_expiration']['version_expires'] != '') {
					if(isset($content['course_expiration']['expiration_date'])) {
						$expirations[] = $content['course_expiration']['expiration_date'];
					}
				}
			}
		}
		delete_post_meta($course_id, 'flms_course_has_expiration_date');
		if(!empty($expirations)) {
			foreach($expirations as $expiration) {	
				add_post_meta($course_id, 'flms_course_has_expiration_date', $expiration );
			}
		}
    }

    public function unenroll_expired_users($course_id, $course_version) {
        global $wpdb;
        $table = FLMS_ACTIVITY_TABLE;
        $sql_query = $wpdb->prepare("SELECT customer_id FROM $table WHERE customer_status=%s AND course_id=%d AND course_version=%d ORDER BY id DESC", 'enrolled', $course_id, $course_version);
        $results = $wpdb->get_results( $sql_query, ARRAY_A ); 
        if(!empty($results)) {
            $course_progress = new FLMS_Course_Progress();
            foreach($results as $result) {
                $user_id = $result['customer_id'];
                $course_progress->unenroll_user($user_id, $course_id, $course_version);
            }
        } 
    }

    public function archive_expired_users($course_id, $course_version) {
        global $wpdb;
        $table = FLMS_ACTIVITY_TABLE;
        $sql_query = $wpdb->prepare("SELECT customer_id FROM $table WHERE customer_status=%s AND course_id=%d AND course_version=%d ORDER BY id DESC", 'enrolled', $course_id, $course_version);
        $results = $wpdb->get_results( $sql_query, ARRAY_A ); 
        if(!empty($results)) {
            $course_progress = new FLMS_Course_Progress();
            foreach($results as $result) {
                $user_id = $result['customer_id'];
                $course_progress->update_user_activity_log($user_id, $course_id, $course_version, 'expired', false);
            }
        } 
    }

    public function expire_courses_cron() {
        $args = array(
            'post_type' => 'flms-courses',
            'posts_per_page' => -1,
            'meta_query' => array(
                'key' => 'flms_course_has_expiration_date',
                'compare' => 'EXISTS' //'meta_compare' => 'NOT IN'
            ),
        );
        $course_query = new WP_Query( $args );
        if($course_query->have_posts()) {
            while($course_query->have_posts()) {
                $course_query->the_post();
                $course_id = get_the_ID();
                $course_versioned_content = get_post_meta($course_id,'flms_version_content',true);
                foreach($course_versioned_content as $active_version => $content) {
                    if($this->is_course_expired($course_id, $active_version)) {
                        $this->expire_course($course_id, $active_version);
                    }
                }
            }
        }
        wp_reset_postdata();
    }


}
new FLMS_Module_Course_Expiration();