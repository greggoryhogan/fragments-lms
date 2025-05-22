<?php
class FLMS_Module_Course_expiration {
    public function __construct() {
		//add_action('init',array($this,'test_int'));
        //add_filter( 'cron_schedules', 'course_expiration_cron_schedules' );
	}
    public function get_expiration_settings_fields() {
        $fields = array(
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
            array(
                'label' => 'Expiration action',
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
                'description' => 'The notice to show on a course with an expiration. <br>%expiration_date% will show the expiration date<br>%expiration_time% will show th expiration time.',
                'default' => "This course expires on %expiration_date% at %expiration_time%. Please complete the course before it expires."
            ),
            array(
                'label' => 'Unenrolled User Expiration Text',
                'key' => 'unenrolled_user_course_expiration_text',
                'type' => 'text',
                'description' => 'The notice to show on a course with an expiration. <br>%expiration_date% will show the expiration date<br>%expiration_time% will show th expiration time.',
                'default' => "This course expires on %expiration_date% at %expiration_time%. Please purchase the course before it expires."
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

    public function flms_course_expiration_notice() {
        global $flms_settings, $flms_course_id, $flms_active_version, $flms_course_version_content, $post, $flms_latest_version;
		if($flms_course_id != $post->ID) {
			return;
		}
        if(isset($flms_course_version_content[$flms_active_version]['course_expiration'])) {
            if($flms_course_version_content[$flms_active_version]['course_expiration'] != '') {
                $date = date(get_option('date_format'),strtotime($flms_course_version_content[$flms_active_version]['course_expiration']['expiration_date']));
                $time = date(get_option('time_format'), strtotime($flms_course_version_content[$flms_active_version]['course_expiration']['expiration_time']));
            }
        }
		$flms_user_has_access = flms_user_has_access($flms_course_id, $flms_active_version, true);
        $notice = '';
        //echo '<pre>'.print_r($flms_course_version_content,true).'</pre>';
        if($flms_user_has_access) {
            if(isset($flms_settings['course_expiration']['enrolled_user_course_expiration_text'])) {
                $notice = $flms_settings['course_expiration']['enrolled_user_course_expiration_text'];
            }
        } else {
            if(isset($flms_settings['course_expiration']['unenrolled_user_course_expiration_text'])) {
                $notice = $flms_settings['course_expiration']['unenrolled_user_course_expiration_text'];
            }
        }
        $notice = str_replace('%expiration_date%', $date, $notice);
        $notice = str_replace('%expiration_time%', $time, $notice);
        echo flms_alert($notice);
    }
}
new FLMS_Module_Course_Expiration();