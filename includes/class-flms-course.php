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
class FLMS_Course {

	public int $course_id;
	public int $flms_active_version;

	/**
	 * The Constructor.
	 */
	public function __construct(int $post_id) {
		global $flms_course_id, $flms_active_version, $flms_course_version_content, $wp, $flms_version_index, $flms_latest_version, $flms_course_steps, $flms_user_activity;
		$this->course_id = absint($post_id);
		$flms_course_id = $this->course_id;
		
		$flms_course_version_content = get_post_meta($flms_course_id,'flms_version_content',true);	
		if(!is_array($flms_course_version_content)) {
			$flms_course_version_content = array();
		} else {
			krsort($flms_course_version_content);
		}
		//echo '<pre>'.print_r($flms_course_version_content,true).'</pre>';
		$flms_latest_version = '';
		foreach($flms_course_version_content as $k => $v) {
			if(isset($v['version_status'])) {
				$status_value = $v['version_status'];
				if($status_value == 'publish') {
					$flms_latest_version = $k;
					break;
				}
			}
		}
		if($flms_latest_version == '') {
			$flms_latest_version = array_key_first($flms_course_version_content);
		}
		if(is_admin()) {
			$flms_active_version = get_post_meta($flms_course_id,'flms_course_active_version',true);
		} else {
			$flms_active_version = flms_get_current_course_version();
		}

	}


	public function update_course_steps() {
		global $flms_course_id, $flms_active_version, $flms_course_version_content;
		$course_steps = array();
		//$course_steps[$this->course_id] = array();
		$lessons = $this->get_lessons();
		foreach($lessons as $lesson_id) {
			$lesson = new FLMS_Lesson($lesson_id);
			$topics = $lesson->get_lesson_version_topics();
			//$course_steps['lessons'][$lesson_id]['topics'] = $topics;
			$exams = $lesson->get_lesson_version_exams();
			$steps = array_merge($topics, $exams);
			$course_steps[$lesson_id] = $steps;
		}
		$exams = $this->get_course_version_exams();
		foreach($exams as $exam) {
			$course_steps[$exam] = array();
		}
		$flms_course_version_content["$flms_active_version"]["course_steps"] = $course_steps;
		update_post_meta($flms_course_id, 'flms_version_content', $flms_course_version_content);
		return $course_steps;
	}

	public function get_course_steps_order() {
		global $flms_course_id, $flms_active_version, $flms_course_version_content;
		if(!isset($flms_course_version_content["$flms_active_version"]["course_steps"])) {
			$course_steps = $this->update_course_steps();
		} else {
			$course_steps = $flms_course_version_content["$flms_active_version"]["course_steps"];
		}
		return $course_steps;
	}

	public function get_all_course_steps() {
		$steps = $this->get_course_steps_order();
		$course_steps = array();
		foreach($steps as $k => $v) {
			$course_steps[] = $k;
			foreach($v as $i => $j) {
				$course_steps[] = $j;
			}
		}
		return $course_steps;
	}

	public function array_flatten($array) {
		$return = array();
		foreach ($array as $key => $value) {
			if (is_array($value)){
				$return = array_merge($return, $this->array_flatten($value));
			} else {
				$return[$key] = $value;
			}
		}
	
		return $return;
	}
	
	public function get_id() {
		return $this->course_id;
	}

	public function get_course_name() {
		$course_data = get_post($this->course_id);
		return $course_data->post_name;
	}

	public function get_lessons() {
		global $flms_course_version_content, $flms_active_version;
		if(isset($flms_course_version_content[$flms_active_version]["course_lessons"])) {
			$lessons = $flms_course_version_content[$flms_active_version]["course_lessons"];
		} else {
			$lessons = array();
		}
		return $lessons;
	}

	public function get_versions() {
		global $flms_course_version_content;
		return $flms_course_version_content;
	}

	public function get_active_version() {
		global $flms_active_version;
		return $flms_active_version;
	}

	public function update_course_version_field($field, $value) {
		global $flms_course_id, $flms_course_version_content, $flms_active_version;
		$flms_course_version_content["$flms_active_version"][$field] = $value;
        update_post_meta($flms_course_id, 'flms_version_content', $flms_course_version_content);
	}

	public function get_course_version_name($course_version) {
		global $flms_course_id, $flms_course_version_content, $flms_latest_version;
		if($flms_latest_version == $course_version) {
			$title = get_the_title($flms_course_id);
		} else {
			if(isset($flms_course_version_content["$course_version"]['version_name'])) {
				$title = get_the_title($flms_course_id) . ' ('.$flms_course_version_content["$course_version"]['version_name'].')';
			} else {
				$title = get_the_title($flms_course_id);
			}
		}
		return $title;
	}

	public function get_course_version_permalink($course_version) {
		global $flms_course_id, $flms_course_version_content, $flms_latest_version;
		if($flms_latest_version == $course_version) {
			$permalink = trailingslashit(get_permalink( $flms_course_id));
		} else {
			if(isset($flms_course_version_content["$course_version"]['version_permalink'])) {
				$permalink = trailingslashit( get_permalink( $flms_course_id)) . $flms_course_version_content["$course_version"]['version_permalink'];
			} else {
				$permalink = trailingslashit(get_permalink($flms_course_id));
			}
		}
		return $permalink;
	}

	public function get_course_version_slug($course_version, $trailing_slash = false) {
		global $flms_course_id, $flms_course_version_content, $flms_latest_version;
		//echo $flms_latest_version.'  '.$course_version.'<br>';
		if($flms_latest_version == $course_version) {
			$permalink = '';
		} else {
			if(isset($flms_course_version_content["$course_version"]['version_permalink'])) {
				$permalink = $flms_course_version_content["$course_version"]['version_permalink'];
				if($trailing_slash) {
					$permalink .= '/';
				}
			} else {
				$permalink = '';
			}
		}
		return $permalink;
	}

	public function get_course_version_content() {
		global $flms_course_id, $flms_course_version_content, $flms_active_version;
		if(isset($flms_course_version_content["$flms_active_version"]["post_content"])) {
			$content = $flms_course_version_content["$flms_active_version"]["post_content"];
		} else {
			$content = '';
		}
		return $content;
	}

	public function get_course_version_exams() {
		global $flms_course_version_content, $flms_active_version;
		$exams = array();
		if(isset($flms_course_version_content["$flms_active_version"]["post_exams"])) {
			$exams = $flms_course_version_content["$flms_active_version"]["post_exams"];
		}
		return $exams;
		
	}

	public function get_course_certificates() {
		global $flms_course_version_content, $flms_active_version;
		$certificates = array();
		if(isset($flms_course_version_content["$flms_active_version"]["course_certificates"])) {
			$certificates = $flms_course_version_content["$flms_active_version"]["course_certificates"];
		}
		/*if(empty($certificates)) {
			$args = array(
				'post_type'      => 'flms-certificates',
				'posts_per_page' => -1,
				'post_status' => array('publish', 'pending', 'draft', 'future', 'private')
			);
			$certificate_posts = get_posts($args);
			if(!empty($certificate_posts)) {
				foreach($certificate_posts as $certificate) {
					$certificates[] = $certificate->ID;
				}
			}
		}*/
		return $certificates;
	}

	public function get_all_exams_from_course() {
		$exams = array();
		$lessons = $this->get_lessons();
		foreach($lessons as $lesson_id) {
			$lesson = new FLMS_Lesson($lesson_id);
			$lesson_exams = $lesson->get_lesson_version_exams();
			$exams = array_merge($exams, $lesson_exams);
		}
		$course_exams = $this->get_course_version_exams();
		$exams = array_merge($exams, $course_exams);
		return $exams;
	}
}
