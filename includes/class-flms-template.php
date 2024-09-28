<?php
/**
 * Fragment LMS Setup.
 *
 * @package FLMS\Classes
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Autoloader class.
 */
class FLMS_Template {

	static $admin_color_scheme = [];

	/**
	 * The Constructor.
	 */
	public function __construct() {
		add_filter( 'single_template', array($this, 'load_flms_templates' ) );
		add_filter( 'archive_template', array($this, 'load_flms_archive_templates' ) );
		add_action('flms_after_heading', array($this, 'flms_course_notices'), 5);
		add_action('flms_before_my_courses',array($this, 'flms_course_notices'), 5);
		add_action('flms_before_my_groups',array($this, 'flms_course_notices'), 5);
		add_action('flms_after_heading', array($this,'flms_breadcrumbs'),10);
		add_action('flms_main_content', array($this, 'flms_main_content'), 20);
		add_action('template_redirect', array($this,'flms_access_redirect'));
		add_action('before_flms_course_content', array($this, 'flms_enroll_actions'), 5);
		if(flms_is_module_active('course_certificates')) {
			add_action('before_flms_course_content', array($this, 'flms_show_course_certificate'), 7);
		}
		if(flms_is_module_active('course_taxonomies')) {
			$course_taxonomies = new FLMS_Module_Course_Taxonomies();
			add_action('before_flms_course_content', array($course_taxonomies, 'flms_course_taxonomies'), 5);
		}
		if(flms_is_module_active('course_materials')) {
			$course_materials = new FLMS_Module_Course_Materials();
			add_action('before_flms_course_content', array($course_materials, 'flms_course_materials'), 18);
		}
		if(flms_is_module_active('course_credits')) {
			$course_credits = new FLMS_Module_Course_Credits();
			add_action('before_flms_course_content', array($course_credits, 'flms_course_credits'), 10);
		}
		add_action('after_flms_main_content', 'flms_course_navigation', 10);
		add_action('before_flms_course_content', array($this, 'flms_course_percentage'), 10);
		add_action('after_flms_lesson_content', array($this,'output_lesson_video'), 30);

		add_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
		add_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );

		if(flms_is_module_active('groups')) {
			$groups = new FLMS_Module_Groups();
			add_action('flms_no_group_access', array($groups, 'flms_no_group_access'), 10);
			add_action('flms_groups_main_content', array($groups, 'flms_groups_admin_content'), 10);
			add_action('flms_groups_main_content', array($groups, 'flms_groups_member_content'), 20);
		}
	}

	public function flms_enroll_actions() {
		global $flms_user_has_access;
		global $flms_settings, $flms_course_id, $flms_active_version, $flms_course_version_content, $post;
		if($flms_course_id != $post->ID) {
			return;
		}
		if(!$flms_user_has_access) {
			//if(get_current_user_id() > 0) {
				echo flms_enroll_course_button($flms_course_id,$flms_course_version_content["$flms_active_version"],$flms_active_version);
			//} else {
				//$permalink = get_permalink($flms_course_id);
				//echo flms_login_link('Log in to enroll', $permalink);	
			//}
		} else {
			$completed = flms_user_completed_course($flms_course_id, $flms_active_version);
			$course_label = flms_get_label('course_singular');
			$course_label_lc = strtolower($course_label);
			if($completed) {
				$message = 'Congratulations, you have completed this '.$course_label_lc.'. <a href="/#repurchase" data-toggle-trigger="#purchase-again">Purchase again?</a>';
			} else {
				$message = 'You are currently enrolled in this '.$course_label_lc.'. <a href="/#repurchase" data-toggle-trigger="#purchase-again">Purchase again?</a>';
			}
			echo flms_alert($message, false);
			
			echo '<div id="purchase-again" class="toggle-div">';
				echo flms_enroll_course_button($flms_course_id,$flms_course_version_content["$flms_active_version"],$flms_active_version);
			echo '</div>';
		}
	}

	public function flms_purchase_course_actions() {
		global $flms_user_has_access;
		global $flms_settings, $flms_course_id, $flms_active_version, $flms_course_version_content, $post;
		if($flms_course_id != $post->ID) {
			return;
		}
		if(flms_is_module_active('woocommerce')) {
			$woo = new FLMS_Module_Woocommerce();
			echo $woo->purchase_course_options($flms_course_id,$flms_active_version);
		}
	}

	public function output_lesson_video() {
		global $post;
		global $flms_lesson_version_content, $flms_active_version;
		$video_settings = flms_get_video_settings_default_fields();
		if(isset($flms_lesson_version_content["$flms_active_version"]['video_settings'])) {
			$video_settings = $flms_lesson_version_content["$flms_active_version"]['video_settings'];
		}
		/*$enabled = 0;
		if(isset($video_settings['force_full_video'])) {
			$enabled = $video_settings['force_full_video'];
		}
		if($enabled == 1) {*/
			$type = 'default';

			$video = '';
			if(isset($video_settings['video_url'])) {
				$video = $video_settings['video_url'];
			}
			$controls = 0;
			if(isset($video_settings['controls'])) {
				$controls = $video_settings['controls'];
			}

			$full_watch = 0;
			if(isset($video_settings['force_full_video'])) {
				$full_watch = $video_settings['force_full_video'];
			}
			global $flms_user_progress;
			if(is_array($flms_user_progress)) {
				if(in_array($post->ID, $flms_user_progress)) {
					$full_watch = 0;
				}
			}
			
			if($full_watch == 1) {
				$controls = 0;
			}
			
			$aspect_ratio = 'widescreen';
			if(isset($video_settings['video_ratio'])) {
				$aspect_ratio = $video_settings['video_ratio'];
			}
			if($video != '') {
				wp_enqueue_script('flms-video');
				if (preg_match('#<iframe(.*?)></iframe>#is', $video, $matches) == 1) {
					//found an iframe
					$iframe_atts = $matches[1];
					$url = '';
					if(preg_match('/src="([^"]+)"/', $video, $match) == 1) {
						$url = $match[1];
					}
					if($url != '') {
						$youtube = array(
							'youtube',
							'you.tube'
						);
						if(strposa($url,$youtube)) {
							//$video = str_replace($url, $url.'&controls=0&disablekb=1&autoplay=1&mute=1',$video);
							$type = 'youtube';
							$video = str_replace($url, $url.'&disablekb=1&enablejsapi=1', $iframe_atts);
							$iframe_id = 'flms-youtube-embed';
						} else if(strpos($url, 'vimeo')) {
							//$video = $iframe_atts;
							$type = 'vimeo';
							$video = str_replace($url, $url.'&controls='.$controls, $iframe_atts);
							$iframe_id = 'flms-vimeo-embed';
						} else {
							$type = 'local';
							$video = str_replace($url, $url, $iframe_atts);
							//$video = $url;
							$iframe_id = 'flms-local-embed';
						}
						$fullscreen = '';
						if($full_watch == 1) {
							$fullscreen = 'donotallowfullscreen';
						}
						echo '<div id="flms-content-video" class="flms-video '.$aspect_ratio.'">';
							echo '<iframe type="text/html" id="'.$iframe_id.'" '.$video.' '.$fullscreen.' allow=""></iframe>';
							if(($type != 'youtube' || $type != 'local') && $controls == 0) {
								echo $this->flms_video_playpause();
							}
						echo '</div>';
					}
				} else {
					//it's a url
					
					?>
					<div class="flms-video <?php echo $aspect_ratio; ?>">
					<video id="flms-default-embed" <?php if($full_watch == 1) echo 'disablePictureInPicture'; ?> <?php if($controls == 1) echo 'controls'; ?>>
						<source src="<?php echo $video; ?>#t=1">
						Your browser does not support the video tag.
					</video>
					<?php 
					if($controls == 0) {
						echo $this->flms_video_playpause(); 
					} ?>
				</div><?php 
				}
				
				
				
			}
		//}
	}

	public function flms_video_playpause() {
		$controls = '<div class="flms-play-pause">';
			$controls .= '<div class="action play-pause-btn play-pause-btn--pause"><svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24"><path d="M 360 -320 h 80 v -320 h -80 v 320 Z m 160 0 h 80 v -320 h -80 Z Z Z m 0 -320 Z"></path></svg></div>';
			$controls .= '<div class="action play-pause-btn play-pause-btn--play is-active"><svg xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 -960 960 960" width="24"><path d="m 380 -300 l 280 -180 l -280 -180 Z Z m 0 -80 Z Z"></path></svg></div>';
		$controls .= '</div>';
		$controls .= '<div id="seekbar">';
			$controls .= '<div class="bg"></div>';
			$controls .= '<span></span>';
			$controls .= '<div class="timefeedback">0:00</div>';
		$controls .= '</div>';
		return $controls;
	}

	public function flms_access_redirect() {
		global $post, $wp;
		
		if(!$post) {
			return;
		}
		global $flms_course_id;
		$post_id = $post->ID;
		if($post->post_type == 'flms-exams' && isset($wp->query_vars['print-exam-id'])) {
			//id defined through the url
			$post_id = $wp->query_vars['print-exam-id'];
		}
		$flms_course_id = flms_get_course_id($post_id);
		if($flms_course_id == '') {
			return;
		}
		

		$course = new FLMS_Course($flms_course_id);
		$active_version = $course->get_active_version();
		global $flms_user_has_access;
		$flms_user_has_access = flms_user_has_access($post_id, $active_version);
		//if(current_user_can('administrator')) {
			//$flms_user_has_access = true;
		//}

		//see if course is linear and previous content completed
		global $flms_course_version_content;
		$current_progression = 'freeform';
		if(isset($flms_course_version_content["$active_version"]['course_settings']['course_progression'])) {
			$current_progression = $flms_course_version_content["$active_version"]['course_settings']['course_progression'];
		}
		if($flms_user_has_access && $current_progression == 'linear' && ($post->post_type == 'flms-lessons' || $post->post_type == 'flms-topics' || $post->post_type == 'flms-exams')) {
			$course_steps = $course->get_course_steps_order();
			$keys = array_keys($course_steps);
			$course_progress = new FLMS_Course_Progress();
			$flms_user_activity = $course_progress->get_user_activity(get_current_user_id(), $flms_course_id, $active_version);
			$steps_completed = maybe_unserialize($flms_user_activity['steps_completed']);
			switch($post->post_type) {
				case 'flms-courses': 
		
					break;
				case 'flms-lessons': 
					//prev
					$prev_index = array_search($post_id, $keys) - 1;
					if($prev_index >= 0) {
						if (array_key_exists($keys[$prev_index], $course_steps)) {
							$prev_key = $keys[$prev_index];
							$last_item_in_previous_lesson = count($course_steps[$prev_key]) - 1;
							if (array_key_exists($last_item_in_previous_lesson, $course_steps[$prev_key])) {
								$prev = $course_steps[$prev_key][$last_item_in_previous_lesson];
								if(!flms_is_step_complete($steps_completed, $prev)) {
									wp_safe_redirect(get_permalink($prev).'?access=linear');
								}
							} else {
								if(!flms_is_step_complete($steps_completed, $prev_key)) {
									wp_safe_redirect(get_permalink($prev_key).'?access=linear');
								}
								
							}
						}
					} 
					break;
				case 'flms-topics':
					$lesson_id = flms_get_topic_version_parent($post_id);	
					if (array_key_exists($lesson_id, $course_steps)) {
						$index = array_search($post_id, $course_steps[$lesson_id]);
						//$key = array_search('green', $array);
						if($index !== false && $index > 0 ) {
							//previous step in lesson
							$prev = $course_steps[$lesson_id][$index-1];
							if(!flms_is_step_complete($steps_completed, $prev)) {
								wp_safe_redirect(get_permalink($prev).'?access=linear');
							}
						} else {
							if(!flms_is_step_complete($steps_completed, $lesson_id) && $index > 0) {
								//echo $index;
								wp_safe_redirect(get_permalink($lesson_id).'?access=linear');
							}
						}
					}
					break;
				case 'flms-exams':
					if (array_key_exists($post_id, $course_steps)) {
						$prev_index = array_search($post_id, $keys) - 1;
						if($prev_index >= 0) {
							if (array_key_exists($keys[$prev_index], $course_steps)) {
								$prev_key = $keys[$prev_index];
								$last_item_in_previous_lesson = count($course_steps[$prev_key]) - 1;
								if (array_key_exists($last_item_in_previous_lesson, $course_steps[$prev_key])) {
									$prev = $course_steps[$prev_key][$last_item_in_previous_lesson];
									if(!flms_is_step_complete($steps_completed, $prev)) {
										wp_safe_redirect(get_permalink($prev).'?access=linear');
									}
								} else if (array_key_exists($keys[$prev_index], $course_steps)) {
									$prev = $keys[$prev_index];
									if(!flms_is_step_complete($steps_completed, $prev)) {
										wp_safe_redirect(get_permalink($prev).'?access=linear');
									}
								}
							}
						}
					} else {
						//lesson exam
						$parent_id = flms_get_exam_version_parent($post_id);
						if (array_key_exists($parent_id, $course_steps)) {
							$index = array_search($post_id, $course_steps[$parent_id]);
							//$key = array_search('green', $array);
							if($index !== false && $index > 0 ) {
								//previous step in lesson
								$prev = $course_steps[$parent_id][$index-1];
								if(!flms_is_step_complete($steps_completed, $prev)) {
									wp_safe_redirect(get_permalink($prev).'?access=linear');
								}
							} else {
								if(!flms_is_step_complete($steps_completed, $parent_id)) {
									wp_safe_redirect(get_permalink($parent_id).'?access=linear');
								}
							}
						}
					}
					break;
			}
		}
		if(!$flms_user_has_access && ($post->post_type == 'flms-lessons' || $post->post_type == 'flms-topics' || $post->post_type == 'flms-exams')) {
			global $flms_course_version_content, $flms_active_version, $flms_latest_version;

			//see if course if completed
			$completed = flms_user_completed_course($flms_course_id, $flms_active_version);
			
			$version_permalink = '';
			if($flms_latest_version != $flms_active_version) {
				$version_permalink = $flms_course_version_content[$flms_active_version]['version_permalink'];
			} 
			
			if($post->post_type == 'flms-lessons') {
				$lesson = new FLMS_Lesson($post_id);
				$sample = $lesson->lesson_is_sample();
				if(!$sample && !$completed) {
					wp_safe_redirect(trailingslashit( get_permalink($flms_course_id) ).$version_permalink.'?access=invalid');
				}
			} else if($post->post_type == 'flms-topics') {
				$topic = new FLMS_Topic($post_id);
				$sample = $topic->lesson_is_sample();
				if(!$sample && !$completed) {
					wp_safe_redirect(trailingslashit( get_permalink($flms_course_id) ).$version_permalink.'?access=invalid');
				}
			} else {
				if(!$completed) {
					wp_safe_redirect(trailingslashit( get_permalink($flms_course_id) ).$version_permalink.'?access=invalid');
				}
			}
		}
	}
	/**
	 * Load master template for flms
	 */
	public function load_flms_templates($template) {
		global $wp;
		
		global $post;
		$post_types = flms_get_plugin_post_type_internal_permalinks();
		$directory = trailingslashit(FLMS_ABSPATH) .'template/';

		if ( in_array($post->post_type, $post_types)) {
			//global $flms_active_version, $flms_course_version_content, $flms_course_id;
			//$flms_course_id = flms_get_course_id($post);
			//$flms_course_version_content = get_post_meta($flms_course_id,'flms_version_content',true);	

			$tpl_file = str_replace('flms-','',$post->post_type);
			if(!locate_template( array( "flms/$tpl_file/single-$tpl_file.php" ) ) !== $template) {
				return $directory . "$tpl_file/single-$tpl_file.php";
			}
		}

		
		return $template;
	}

	/**
	 * Load archive template for flms
	 */
	public function load_flms_archive_templates($template) {
		global $post, $wp;
		$directory = FLMS_ABSPATH .'/template/';
		if(isset($post->post_type)) {
			if($post->post_type == 'flms-exams' && isset($wp->query_vars['print-exam-id'])) {
				if(!locate_template( array( "flms/exams/print-exam.php" ) ) !== $template) {
					return $directory . "exams/print-exam.php";
				}
			}
			if($post->post_type == 'flms-certificates') {
				if(flms_is_module_active('course_certificates')) {
					if(!locate_template( array( "flms/certificates/single-certificate.php" ) ) !== $template) {
						return $directory . "certificates/single-certificate.php";
					}
				}
			}
			if($post->post_type == 'flms-groups') {
				if(flms_is_module_active('groups')) {
					if(!locate_template( array( "flms/groups/archive-groups.php" ) ) !== $template) {
						return $directory . "groups/archive-groups.php";
					}
				}
			}
		}

		return $template;
	}

	public function flms_breadcrumbs( $echo = true ) {
		global $wp;
		if ( apply_filters( 'flms_show_breadcrumbs', true ) ) {
			$breadcrumbs = '<div class="flms-breadcrumbs flms-course-content-section">';
				global $post;
				if($post->post_type == 'flms-courses') {
					$course_id = $post->ID;
				} else if($post->post_type == 'flms-lessons') {
					$course_id = get_post_meta($post->ID,'flms_course',true);
				} else if($post->post_type == 'flms-topics') {
					$lesson_id = flms_get_topic_version_parent($post->ID);
					$course_id = get_post_meta($lesson_id,'flms_course',true);
				}
				$breadcrumbs .= $this->get_breadcrumbs_pt_output($post->ID,$post->post_type);
			$breadcrumbs .= '</div>';
			echo $breadcrumbs;
		}
		
	}

	public function get_breadcrumbs_pt_output($post_id,$post_type) {
		global $wp;
		$breacrumbs = '';
		$sep = ' > ';
		switch ($post_type) {
			case 'flms-topics':
				//get lessons
				$lesson_id = flms_get_topic_version_parent($post_id);
				$course_id = get_post_meta($lesson_id, 'flms_course', true);
				$breacrumbs .= '<a href="'.get_permalink($course_id).'" title="'.get_the_title($course_id).'">'.get_the_title($course_id).'</a>';
				$breacrumbs .= $sep;
				if(isset($wp->query_vars['course-version'])) {
					if($wp->query_vars['course-version'] != '') {
						$title = flms_get_version_title($post_id,$wp->query_vars['course-version']);
						$breacrumbs .= '<a href="'.trailingslashit( get_permalink($course_id)).'" title="'.get_the_title($course_id).' '.$title.'">'.$title.'</a>';
						$breacrumbs .= $sep;
					}
				}
				$breacrumbs .= '<a href="'.get_permalink($lesson_id).'" title="'.get_the_title($lesson_id).'">'.get_the_title($lesson_id).'</a>';
				$breacrumbs .= $sep;
				$breacrumbs .= flms_page_title(false);
				break;
			case 'flms-lessons':
				$course_id = get_post_meta($post_id, 'flms_course', true);
				$breacrumbs .= '<a href="'.get_permalink($course_id).'" title="'.get_the_title($course_id).'">'.get_the_title($course_id).'</a>';
				$breacrumbs .= $sep;
				if(isset($wp->query_vars['course-version'])) {
					if($wp->query_vars['course-version'] != '') {
						$title = flms_get_version_title($post_id,$wp->query_vars['course-version']);
						$breacrumbs .= '<a href="'.trailingslashit( get_permalink($course_id)).'" title="'.get_the_title($course_id).' '.$title.'">'.$title.'</a>';
						$breacrumbs .= $sep;
					}
				}
				$breacrumbs .= flms_page_title(false);
				break;
			case 'flms-courses':
				if(isset($wp->query_vars['course-version'])) {
					if($wp->query_vars['course-version'] != '') {
						$course_id = get_the_ID();
						$breacrumbs .= '<a href="'.get_permalink($course_id).'" title="'.get_the_title($course_id).'">'.get_the_title($course_id).'</a>';
						$breacrumbs .= $sep;
						$breacrumbs .= flms_get_version_title($post_id,$wp->query_vars['course-version']);
						
					}
				}
				break;
			case 'flms-exams':
				$parent_id = flms_get_exam_version_parent($post_id);
				if($parent_id > 0) {
					$parent = get_post($parent_id);
					switch ($parent->post_type) {
						case 'flms-topics':
							//get lessons
							$lesson_id = flms_get_topic_version_parent($parent_id);
							$course_id = get_post_meta($lesson_id, 'flms_course', true);
							$breacrumbs .= '<a href="'.get_permalink($course_id).'" title="'.get_the_title($course_id).'">'.get_the_title($course_id).'</a>';
							$breacrumbs .= $sep;
							if(isset($wp->query_vars['course-version'])) {
								if($wp->query_vars['course-version'] != '') {
									$title = flms_get_version_title($post_id,$wp->query_vars['course-version']);
									$breacrumbs .= '<a href="'.trailingslashit( get_permalink($course_id)).$wp->query_vars['course-version'].'" title="'.get_the_title($course_id).' '.$title.'">'.$title.'</a>';
									$breacrumbs .= $sep;
								}
							}
							$breacrumbs .= '<a href="'.get_permalink($lesson_id).'" title="'.get_the_title($lesson_id).'">'.get_the_title($lesson_id).'</a>';
							$breacrumbs .= $sep;
							$breacrumbs .= '<a href="'.get_permalink($parent_id).'" title="'.get_the_title($parent_id).'">'.get_the_title($parent_id).'</a>';
							$breacrumbs .= $sep;
							$breacrumbs .= flms_page_title(false);
							break;
						case 'flms-lessons':
							$course_id = get_post_meta($parent_id, 'flms_course', true);
							$breacrumbs .= '<a href="'.get_permalink($course_id).'" title="'.get_the_title($course_id).'">'.get_the_title($course_id).'</a>';
							$breacrumbs .= $sep;
							if(isset($wp->query_vars['course-version'])) {
								if($wp->query_vars['course-version'] != '') {
									$title = flms_get_version_title($post_id,$wp->query_vars['course-version']);
									$breacrumbs .= '<a href="'.trailingslashit( get_permalink($course_id)).$wp->query_vars['course-version'].'" title="'.get_the_title($course_id).' '.$title.'">'.$title.'</a>';
									$breacrumbs .= $sep;
								}
							}
							$breacrumbs .= '<a href="'.get_permalink($parent_id).'" title="'.get_the_title($parent_id).'">'.get_the_title($parent_id).'</a>';
							$breacrumbs .= $sep;
							$breacrumbs .= flms_page_title(false);
							break;
						case 'flms-courses':
							$course_id = $parent_id;
							$breacrumbs .= '<a href="'.get_permalink($course_id).'" title="'.get_the_title($course_id).'">'.get_the_title($course_id).'</a>';
							$breacrumbs .= $sep;
							if(isset($wp->query_vars['course-version'])) {
								if($wp->query_vars['course-version'] != '') {
									$title = flms_get_version_title($post_id,$wp->query_vars['course-version']);
									$breacrumbs .= '<a href="'.trailingslashit( get_permalink($course_id)).$wp->query_vars['course-version'].'" title="'.get_the_title($course_id).' '.$title.'">'.$title.'</a>';
									$breacrumbs .= $sep;
								}
							}
							$breacrumbs .= flms_page_title(false);
							break;
					}
				}
				break;
			case 'flms-groups':
				if(function_exists('wc_get_account_endpoint_url')) {
					$groups_tab_slug = get_option('flms_my_groups_endpoint');
					$endpoint = wc_get_account_endpoint_url( $groups_tab_slug );
					$label = 'My Groups';
					if(isset($flms_settings['woocommerce']['my_groups_tab_name'])) {
						$label = $flms_settings['woocommerce']['my_groups_tab_name'];
					}
					$breacrumbs .= '<a href="'.$endpoint.'" title="'.$label.'">'.$label.'</a>';
					$breacrumbs .= $sep;
					$breacrumbs .= flms_page_title(false);
				}
				
				
				break;
		}
		return $breacrumbs;
	}

	public function flms_course_notices() {
		global $post, $flms_course_id, $flms_active_version, $flms_user_has_access, $flms_settings;
		$course_label = flms_get_label('course_singular');
		$course_label_lc = strtolower($course_label);
		
		if (isset($_GET['access'])) {
			if($_GET['access'] == 'invalid') {
				$message = 'You do not have access to that '.$course_label_lc.' content';
				echo flms_alert($message, true);
			}
			if($_GET['access'] == 'linear') {
				global $post;
				$post_type = get_post_type( $post );
				$message = 'This '.strtolower(flms_get_post_type_label($post_type)).' must be completed before continuing.';
				echo flms_alert($message, false);
			}
		}

		if (isset($_GET['user-unenrolled'])) {
			$course_data = explode('-',$_GET['user-unenrolled']);
			$course_id = absint($course_data[0]);
			$course_version = 1;
			if(isset($course_data[1])) {
				$course_version = absint($course_data[1]);
			}
			$message = 'you have been unenrolled from &ldquo;'.get_the_title($course_id).'.&rdquo;';
			if(isset($_GET['reason'])) {
				$reason = sanitize_text_field($_GET['reason']);
				if($reason == 'exam-result') {
					$before = 'Because you did not pass the exam, ';
					$message = $before . $message;
				}
			}
			$course = new FLMS_Course($course_id);
			$link = $course->get_course_version_permalink($course_version);
			//$link = flms_get_permalink($course_id, $course_version);
			$message .= ' <a href="'.$link.'" title="'.get_the_title($course_id).'" class="button button-primary">View '.$course_label.'</a>';
			echo flms_alert($message, true);
		}

		if (isset($_GET['progress-reset'])) {
			$message = "your $course_label_lc progress has been reset.";
			if(isset($_GET['reason'])) {
				$reason = sanitize_text_field($_GET['reason']);
				if($reason == 'exam-result') {
					$exam_label = flms_get_label('exam_singular');
					$before = 'Because you did not pass the '.strtolower($exam_label).', ';
					$message = $before . $message;
				}
			}
			echo flms_alert($message, true);
		}

		if (isset($_GET['update-error'])) {
			if(sanitize_text_field($_GET['update-error']) == 'invalid-owner-email') {
				$message = 'There is no user with the specified email.';
				echo flms_alert($message, true);
			}
		}

		if (isset($_GET['display-error'])) {
			if(sanitize_text_field($_GET['display-error']) == 'no-course-certificate') {
				$message = 'There is no certificate associated with this course.';
				echo flms_alert($message, true);
			} else if(sanitize_text_field($_GET['display-error']) == 'licenses-unavailable') {
				$certificate_label = strtolower($flms_settings['labels']['certificate_singular']);
            	$license_label = strtolower($flms_settings['labels']['license_singular']);
				$licenses_label = strtolower($flms_settings['labels']['license_plural']);
				global $flms_settings;
        		$credits_location = $flms_settings['woocommerce']['my_licensess_account_location'];
				if($credits_location == 'tab') {
					$license_tab_slug = get_option('flms_my_licenses_endpoint');
					$link = '<a href="'.trailingslashit(get_bloginfo('url')).'my-account/'.$license_tab_slug.'" title="'.$licenses_label.'">'.$licenses_label.'</a>';
				} else {
					$link = '<a href="'.trailingslashit(get_bloginfo('url')).'my-account/edit-account" title="Edit profile">account details</a>';
				}
				$message = "Required $license_label information for this $certificate_label could not be found. Please enter your $license_label information in the $link section of your account.";
				echo flms_alert($message, true);
			} else if(sanitize_text_field($_GET['display-error']) == 'invalid-user-id') {
				$message = 'This certificate is associated with an unknown user.';
				echo flms_alert($message, true);
			}
			
		}

		if (isset($_GET['update-success'])) {
			if(sanitize_text_field($_GET['update-success']) == 'new-group-owner') {
				$message = 'New owner assigned successfully.';
				echo flms_alert($message, true);
			}
		}
		if (isset($_GET['group-deleted'])) {
			$message = 'Group deleted successfully.';
			echo flms_alert($message, true);
		}
		if (isset($_GET['left-group'])) {
			$group_id = absint($_GET['left-group']);
			if($group_id > 0) {
				$title = get_the_title($group_id);
				$message = 'You have left the group "'.$title.'."';
			} else {
				$message = 'You have left the group.';
			}
			
			echo flms_alert($message, true);
		}
		
	}

	public function flms_main_content() {
		do_action('before_flms_main_content');
		global $post, $flms_settings, $current_user;
		if(!flms_is_flms_post_type($post)) {
			return '';
		}
		$course_id = flms_get_course_id($post->ID);
		if($course_id == '') {
			return '';
		}
		
		//see if content is restricted by a closed book exam
		$has_restrictions = get_user_meta($current_user->ID, 'flms_content_restricted_by_exam');
		if(!empty($has_restrictions)) {
			foreach($has_restrictions as $restriction) {
				$restriction_data = json_decode( $restriction, true );
				//only restrict for the current course
				if($restriction_data['course'] == $course_id) {
					global $flms_active_version;
					if($flms_active_version == $restriction_data['version']) {
						if($post->ID != $restriction_data['exam'] && $post->ID != $course_id) {
							$course_label = strtolower(flms_get_label('course_singular'));
							$exam_label = strtolower(flms_get_label('exam_singular'));
							$exam_name = get_the_title($restriction_data['exam']);
							$exam_id = $restriction_data['exam'];
							$message = sprintf('This content cannot be accessed until you complete the "%s" %s.', $exam_name, $exam_label);
							$message = apply_filters('flms_restricted_content_message', $message, $exam_id, $course_id);
							echo '<div class="flms-restricted-notice flms-alert flms-primary flms-secondary-bg flms-secondary-border flms-flex">'.$message.'</div>';

							$meta_value = serialize(
								array(
									'course' => $course_id,
									'exam' => $restriction_data['exam'],
									'version' => $flms_active_version
								)
							);
							//delete_user_meta( $current_user->ID, 'flms_content_restricted_by_exam', $meta_value );

							return;
						}
					}
				}
			}
		}
		global $flms_user_has_access;
		if($post->post_type == 'flms-courses') {
			global $flms_course_version_content, $flms_active_version;
			$course = new FLMS_Course($course_id);
			global $flms_course_id;
			//$content = $course->get_course_version_content();
			//echo '<pre>'.print_r($flms_course_version_content,true).'</pre>';
			//$course_steps = $course->get_course_steps_order();
			//echo '<pre>'.print_r($course_steps,true).'</pre>';
			//$all_course_steps = $course->get_all_course_steps();
			//echo '<pre>'.print_r($all_course_steps,true).'</pre>';
			do_action('before_flms_course_content');
			echo '<div class="flms-course-content-section">';
			if($flms_user_has_access) {
				$content = '';
				if(isset($flms_course_version_content["$flms_active_version"]['post_content'])) {
					$content = $flms_course_version_content["$flms_active_version"]['post_content'];
				}
				echo apply_filters('the_content',$content);
			} else {
				//preview content
				$content = '';
				//print_r($flms_course_version_content);
				if(isset($flms_course_version_content["$flms_active_version"]['course_preview'])) {
					$content = $flms_course_version_content["$flms_active_version"]['course_preview'];
				}
				if($content == '') {
					//use the global post content
					if(isset($flms_course_version_content["$flms_active_version"]['post_content'])) {
						$content = $flms_course_version_content["$flms_active_version"]['post_content'];
					}
				}
				echo apply_filters('the_content',$content);
			}
			echo '</div>';
			do_action('after_flms_course_content');
			do_action('before_flms_course_lessons');
			echo flms_get_course_lessons_list($flms_course_version_content["$flms_active_version"]);
			do_action('after_flms_course_lessons');
			do_action('before_flms_course_exams');
			echo flms_get_associated_exams($flms_course_version_content["$flms_active_version"]);
			do_action('after_flms_course_exams');
		} else if($post->post_type == 'flms-lessons') {
			do_action('before_flms_lesson_content');
			global $flms_lesson_version_content, $flms_active_version;
			//echo '<pre>'.print_r($flms_lesson_version_content,true).'</pre>';
			$lesson = new FLMS_Lesson($post->ID);
			$content = $lesson->get_lesson_version_content();
			echo apply_filters('the_content',$content);
			do_action('after_flms_lesson_content');
			do_action('before_flms_lesson_topics');
			echo $lesson->print_lesson_topics();
			do_action('after_flms_lesson_topics');
			do_action('before_flms_lesson_exams');
			//echo $lesson->get_lesson_version_exams();
			echo flms_print_exams($lesson->get_lesson_version_exams());
			do_action('after_flms_lesson_exams');
		} else if($post->post_type == 'flms-topics') {
			do_action('before_flms_topic_content');
			$topic = new FLMS_Topic($post->ID);
			$content = $topic->get_topic_version_content();
			echo apply_filters('the_content',$content);
			do_action('after_flms_topic_content');
			do_action('before_flms_topic_exams');
			echo flms_print_exams($topic->get_topic_version_exams());
			do_action('after_flms_topic_exams');
		} else if($post->post_type == 'flms-exams') {
			do_action('before_flms_exam_content');
			$exam = new FLMS_Exam($post->ID);
			$content = $exam->get_exam_version_content();
			echo apply_filters('the_content',$content);
			do_action('after_flms_exam_content');
			do_action('after_flms_content');
		}
		do_action('after_flms_main_content');
	}

	public function flms_course_percentage() {
		global $flms_course_id, $flms_active_version;
		$course = new FLMS_Course($flms_course_id);
		global $flms_user_has_access, $current_user;
		$flms_user_activity = flms_get_user_activity($current_user->ID, $flms_course_id, $flms_active_version);
		$steps_completed = maybe_unserialize($flms_user_activity['steps_completed']);
		if(!$flms_user_has_access) {
			return;
		}
		global $current_user;
		if(!is_array($steps_completed)) {
			$completed = 0;
		} else {
			$completed = count($steps_completed);
		}
		$steps = $course->get_all_course_steps();
		$steps_count = count($steps);
		if($steps_count == 0) {
			return '';
		}
		$content = '';
		$content .= "<p>$completed of $steps_count steps complete</p>";
		$percent = absint(100 * (absint($completed) / absint($steps_count))).'%';
		
		$content .= '<div class="course-progress-bar">';
			$content .= '<div class="bar flms-background-bg">';
				$content .= '<div class="percentage flms-primary-bg" style="width: '.$percent.';"></div>';
			$content .= '</div>';
		$content .= '</div>';
		echo $content;
	}	

	public function flms_show_course_certificate() {
		global $flms_course_id, $flms_active_version, $current_user;
		$course = new FLMS_Course($flms_course_id);
		$completed = flms_user_completed_course($flms_course_id, $flms_active_version);
		if($completed) {
			$course_certificates = new FLMS_Module_Course_Certificates();
			$label = $course_certificates->get_certificate_label();
			$rewrite = $course_certificates->get_certificate_permalink();
			$link = '/'.$rewrite.'/'.$flms_course_id.'/'.$flms_active_version.'/'.$current_user->ID;
			$label = 'View '.$label;
			echo '<p><button class="button button-primary flms-button-has-link" data-flms-button-link="'.$link.'" data-name="'.$label.'">'.$label.'</button></p>';
		
		}
	}
}
new FLMS_Template();
