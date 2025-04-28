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
class FLMS_Lesson {

	public int $lesson_id;
	public int $flms_active_version;

	/**
	 * The Constructor.
	 */
	public function __construct(int $post_id) {
		global $flms_course_version_content, $flms_lesson_id, $flms_lesson_version_content;
		$flms_lesson_id = absint($post_id);
		$flms_lesson_version_content = get_post_meta($flms_lesson_id,'flms_version_content',true);	
		//echo '<pre>'.print_r($flms_lesson_version_content,true).'</pre>';
		global $flms_course_id, $flms_active_version, $flms_lesson_id, $flms_course_version_content, $wp, $flms_version_index, $flms_latest_version;
		//$lesson_post = get_post($flms_lesson_id);
		$flms_course_id = flms_get_course_id($flms_lesson_id);
		/*$course = new FLMS_Course($flms_course_id);
		if(!is_array($flms_lesson_version_content)) {
			$flms_lesson_version_content = array();
		} else {
			krsort($flms_lesson_version_content);
		}*/
	}

	public function lesson_is_sample() {
		global $flms_course_id, $flms_active_version, $flms_lesson_id;
		$sample_meta_values = get_post_meta($flms_lesson_id,'flms_is_sample_lesson');
		if(is_array($sample_meta_values)) {
			$sample_meta_key = "$flms_course_id:$flms_active_version";
			if(in_array($sample_meta_key,$sample_meta_values)) {
				return true;
			}
		}
		return false;
	}

	public function get_lesson_version_content() {
		global $flms_lesson_id, $flms_lesson_version_content, $flms_active_version;
		if(isset($flms_lesson_version_content["$flms_active_version"]["post_content"])) {
			$content = $flms_lesson_version_content["$flms_active_version"]["post_content"];
		} else {
			
			$content = '';
		}
		return $content;
	}

	public function get_lesson_version_topics() {
		global $flms_lesson_version_content, $flms_active_version;
		$topics = array();
		if(isset($flms_lesson_version_content["$flms_active_version"]["lesson_topics"])) {
			$topics = $flms_lesson_version_content["$flms_active_version"]["lesson_topics"];
		}
		return $topics;
	}

	public function print_lesson_topics() {
		return flms_get_lesson_topics_list($this->get_lesson_version_topics());
	}

	public function get_lesson_version_exams() {
		global $flms_lesson_version_content, $flms_active_version;
		$exams = array();
		if(isset($flms_lesson_version_content["$flms_active_version"]["post_exams"])) {
			$exams = $flms_lesson_version_content["$flms_active_version"]["post_exams"];
		}
		return $exams;
	}

	public function get_course_id() {
		global $flms_course_id;
		if($flms_course_id !== '') {
			return $flms_course_id;
		} else {
			return 0;
		}
	}

	

}
