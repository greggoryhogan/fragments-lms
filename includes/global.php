<?php
/**
 * The post types we use in the plugin
 * @return array
 * @since 1.0.0
 */
function flms_get_plugin_post_types() {
    $post_types = array(
		array(
			'name' => 'Course',
			'plural_name' => 'Courses',
			'permalink' => 'course',
			'internal_permalink' => 'flms-courses',
			'rewrite' => '%courses_permalink%',
			'register' => true
		),
		array(
			'name' => 'Lesson',
			'plural_name' => 'Lessons',
			'permalink' => 'lesson',
			'internal_permalink' => 'flms-lessons',
			'parent' => 'flms-courses',
			'rewrite' => '%courses_permalink%/%course%/%version_name%/%lessons_permalink%',
			'register' => true
		),
		array(
			'name' => 'Topic',
			'plural_name' => 'Topics',
			'permalink' => 'topic',
			'internal_permalink' => 'flms-topics',
			'rewrite' => '%courses_permalink%/%course%/%version_name%/%lessons_permalink%/%lesson%/%topics_permalink%',
			'register' => true
		),
		array(
			'name' => 'Exam',
			'plural_name' => 'Exams',
			'permalink' => 'exam',
			'internal_permalink' => 'flms-exams',
			'rewrite' => '%courses_permalink%/%course%/%version_name%/%lessons_permalink%/%lesson%/%topics_permalink%/%topic%/%exams_permalink%',
			'register' => true
		)
	);
	if(flms_is_module_active('course_certificates')) {
		$post_types[] = array(
			'name' => 'Certificate',
			'plural_name' => 'Certificatess',
			'permalink' => 'certificate',
			'internal_permalink' => 'flms-certificates',
			'rewrite' => '%courses_certificate%',
			'register' => false
		);	
	}
	if(flms_is_module_active('groups')) {
		$post_types[] = array(
			'name' => 'Group',
			'plural_name' => 'Groups',
			'permalink' => 'group',
			'internal_permalink' => 'flms-groups',
			'rewrite' => '%group_permalink%',
			'register' => false
		);	
	}
	return $post_types;
}

function flms_get_plugin_post_type_internal_permalinks() {
	$permalinks = array();
	$post_types = flms_get_plugin_post_types();
	foreach($post_types as $post_type) {
		$permalinks[] = $post_type['internal_permalink'];
	}
	return $permalinks;
}

function flms_get_post_type_label($post_type, $plural = false) {
	global $flms_settings;
	$post_types = flms_get_plugin_post_types();
	$id = array_search($post_type, array_column($post_types, 'internal_permalink')); 
	if($id !== false) {
		if(!$plural) {
			$name = $post_types[$id]['name'];
			$lower = strtolower($name);
			if(isset($flms_settings['labels']["{$lower}_singular"])) {
				$name = $flms_settings['labels']["{$lower}_singular"];
			}
		} else {
			$name = $post_types[$id]['plural_name'];
			$key = $post_types[$id]['name'];
			$lower = strtolower($key);
			if(isset($flms_settings['labels']["{$lower}_plural"])) {
				$name = $flms_settings['labels']["{$lower}_plural"];
			}
		}
		return $name;
	} else {
		//Likely invalid or using 'any'
		return 'Course, Lesson or Topic';
	}
}
/**
 * Check if we're using gutenberg
 */
function flms_is_gutenberg_editor() {
	if(!is_admin()) {
		return false;
	}
	if( function_exists( 'is_gutenberg_page' ) && is_gutenberg_page() ) { 
		//gutenberg plugin fallback
		return true;
	}   
	$current_screen = get_current_screen();
	if(!$current_screen) {
		return false;
	}
	if ( method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor() ) {
		//Gutenberg content in wp5+
		return true;
	}
	return false;
}

/**
 * Check if a module is active
 */
function flms_is_module_active($module) {
	global $flms_settings;
	if(isset($flms_settings["modules"][$module])) {
		$status = $flms_settings["modules"][$module];
		if($status == 'active') {
			return true;
		}
	}
	return false;
}

/**
 * Get an output of the lessons for a course from a lesson array
 */
function flms_get_course_lessons_list($course_data) {
	global $flms_user_has_access, $flms_settings, $flms_active_version, $flms_course_id;
	$lesson_html = '';
	if(isset($course_data["course_lessons"])) {
		$lessons = $course_data["course_lessons"];
		if(!empty($lessons)) {
			$course_name = 'Course';
			if(isset($flms_settings['labels']["course_singular"])) {
				$course_name = $flms_settings['labels']["course_singular"];
			}
			$lesson_name = 'Lessons';
			if(isset($flms_settings['labels']["lesson_singular"])) {
				$lesson_name = $flms_settings['labels']["lesson_singular"];
			}
			$lessons_name = 'Lessons';
			if(isset($flms_settings['labels']["lesson_plural"])) {
				$lessons_name = $flms_settings['labels']["lesson_plural"];
			}
			$lesson_html = '<div class="flms-course-summary">';
				$lesson_html .= "<h2>$course_name $lessons_name</h2>";
				$lesson_html .= '<div class="flms-course-lessons">';
					foreach($lessons as $lesson_id) {
						$has_topics = false;
						$has_exams = false;
						$sample = false;
						$sample_meta_values = get_post_meta($lesson_id,'flms_is_sample_lesson');
						$sample_key = "$flms_course_id:$flms_active_version";
						if(is_array($sample_meta_values)) {
							if(in_array($sample_key,$sample_meta_values)) {
								$sample = true;
							}
						}
						$completed = flms_user_completed_course($flms_course_id, $flms_active_version);
						$lesson_html .= '<div class="flms-course-lesson-item flms-course-content-list-item flms-background-border';
						if(!$flms_user_has_access && !$sample && !$completed) {
							$lesson_html .= ' flms-no-access';
						}
						$lesson_html .= '">';
							$lesson_html .= '<div class="lesson-header">';
								$lesson_html .= '<div class="lesson-actions">';	
									$lesson_html .= '<div class="flms-step-header">';
										if($flms_user_has_access || $completed) {
											$lesson_html .= flms_step_complete_checkbox($lesson_id, $completed);
										}
										if($flms_user_has_access || $sample || $completed) {
											$lesson_html .= '<a href="'.get_permalink($lesson_id).'" class="flms-lesson-link flms-primary">'.get_the_title($lesson_id).'</a>';
										} else {
											$lesson_html .= '<span class="flms-lesson-link flms-primary">'.get_the_title($lesson_id).'</span>';
										}
									$lesson_html .= '</div>';
									
									$lesson_html .= '<div class="lesson-meta flms-secondary">';
										$lesson = new FLMS_Lesson($lesson_id);
										$lesson_count = count($lesson->get_lesson_version_topics());
										if($lesson_count > 0) {
											$has_topics = true;
											$lesson_html .= '<span>'.$lesson_count .' ';
											if($lesson_count == 1) {
												$lesson_html .= flms_get_post_type_label('flms-topics');
											} else {
												$lesson_html .= flms_get_post_type_label('flms-exams', true);
											}
											$lesson_html .= '</span>';	
										}
										//count lesson exams
										$exam_count = count($lesson->get_lesson_version_exams());
										$lesson_topics = $lesson->get_lesson_version_topics();
										if(!empty($lesson_topics)) {
											foreach($lesson_topics as $topic_id) {
												$topic = new FLMS_Topic($topic_id);
												$exam_count += count($topic->get_topic_version_exams());
											}
										}
										if($exam_count > 0) {
											$has_exams = true;
											$lesson_html .= '<span>'.$exam_count .' ';
											if($exam_count == 1) {
												$lesson_html .= flms_get_post_type_label('flms-exams');
											} else {
												$lesson_html .= flms_get_post_type_label('flms-exams', true);
											}
											$lesson_html .= '</span>';
										}
										if(!$flms_user_has_access && $sample && !$completed) {
											$lesson_html .= '<span class="sample-lesson flms-primary flms-secondary-bg">Sample '.$lesson_name.'</span>';
										}
									$lesson_html .= '</div>';
								$lesson_html .= '</div>';
								if($has_topics || $has_exams) {
									$lesson_html .= '<div class="lesson-toggle flms-primary-bg"></div>';	
								}

								
							$lesson_html .= '</div>';
							$lesson_html .= '<div class="lesson-content to-toggle flms-secondary-border">';
								$lesson_html .= flms_get_lesson_topics_html($lesson_id,true);
							$lesson_html .= '</div>';
						$lesson_html .= '</div>';
					}
				$lesson_html .= '</div>';
			$lesson_html .= '</div>';
		}
	}
	
	return $lesson_html;
}

function flms_get_lesson_topics_list($lessons) {
	global $flms_user_has_access, $flms_course_id, $flms_active_version;
	$lesson_html = '';
	if(!empty($lessons)) {
		$completed = flms_user_completed_course($flms_course_id, $flms_active_version);
		$lesson_html = '<div class="flms-course-summary">';
			$lesson_html .= '<h2>Lesson Topics</h2>';
			$lesson_html .= '<div class="flms-course-lessons">';
				foreach($lessons as $lesson_id) {
					$lesson_html .= '<div class="flms-course-lesson-item flms-course-content-list-item flms-background-border">';
						$lesson_html .= '<div class="lesson-header">';
							$lesson_html .= '<div class="lesson-actions">';	
								$lesson_html .= '<div class="flms-step-header">';
									if($flms_user_has_access || $completed) {
										$lesson_html .= flms_step_complete_checkbox($lesson_id);
									}
									if($flms_user_has_access || $completed) {
										$lesson_html .= '<a href="'.get_permalink($lesson_id).'" class="flms-lesson-link flms-primary">';
									}
									$lesson_html .= get_the_title($lesson_id);
									if($flms_user_has_access || $completed) {
										$lesson_html .= '</a>';
									}
								$lesson_html .= '</div>';
							$lesson_html .= '</div>';
						$lesson_html .= '</div>';
					$lesson_html .= '</div>';
				}
			$lesson_html .= '</div>';
		$lesson_html .= '</div>';
	}
	
	return $lesson_html;
}


function flms_get_lesson_topics_html($lesson_id, $show_heading = true) {
	global $flms_course_id, $flms_active_version;
	$lesson_html = '';
	$new_lesson = new FLMS_Lesson($lesson_id);
	global $flms_user_has_access;
	$lesson_topics = $new_lesson->get_lesson_version_topics();
	//print_r($lesson_topics);
	if(!empty($lesson_topics)) {
		$completed = flms_user_completed_course($flms_course_id, $flms_active_version);
		$lesson_is_sample = false;
		$sample_meta_values = get_post_meta($lesson_id,'flms_is_sample_lesson');
		$sample_key = "$flms_course_id:$flms_active_version";
		if(is_array($sample_meta_values)) {
			if(in_array($sample_key,$sample_meta_values)) {
				$lesson_is_sample = true;
			}
		}
		if($show_heading) {
			$lesson_html .= '<div class="flms-background-bg flms-primary highlight">'.flms_get_post_type_label('flms-lessons').' '.flms_get_post_type_label('flms-topics', true).'</div>';
		}
		$lesson_html .= '<ul class="flms-topics-list flms-list">';
		//$lesson_html .= print_r($lesson_topics,true);
		foreach($lesson_topics as $topic_id) {
			if($topic_id > 0) {
				$topic = new FLMS_Topic($topic_id);
				$lesson_html .= '<li>';
				$lesson_html .= '<div class="flms-step-header">';
					if($flms_user_has_access || $completed) {
						$lesson_html .= flms_step_complete_checkbox($topic_id, $completed);
					}
					if($flms_user_has_access || $lesson_is_sample || $completed) {
						$lesson_html .= '<a href="'.get_permalink($topic_id).'" class="flms-primary">';
						
					}
					$lesson_html .= get_the_title($topic_id);
					if($flms_user_has_access || $lesson_is_sample || $completed) {
						$lesson_html .= '</a>';
					}
				$lesson_html .= '</div>';
				/*$topic_exams = $topic->get_topic_version_exams();
				if(!empty($topic_exams)) {
					$lesson_html .= '<ul class="flms-exams-list flms-list">';
					$lesson_html .= '<li class="flms-font-strong">'.flms_get_post_type_label('flms-topics').' '.flms_get_post_type_label('flms-exams', true).'</li>';
					foreach($topic_exams as $exam_id) {
						if($exam_id > 0) {
							$lesson_html .= '<li><a href="'.get_permalink($exam_id).'" class="flms-primary">'.get_the_title($exam_id).'</a></li>';
						}
					}
					$lesson_html .= '</ul>';
				}*/
				$lesson_html .= '</li>';
			}
		}
		$lesson_html .= '</ul>';
	}
	$lesson_exams = $new_lesson->get_lesson_version_exams();
	if(!empty($lesson_exams)) {
		$completed = flms_user_completed_course($flms_course_id, $flms_active_version);
		if($show_heading) {
			$lesson_html .= '<div class="flms-background-bg flms-primary highlight">'.flms_get_post_type_label('flms-lessons').' '.flms_get_post_type_label('flms-exams', true).'</div>';
		}
		$lesson_html .= '<ul class="flms-topics-list flms-list">';
		foreach($lesson_exams as $exam_id) {
			if($exam_id > 0) {
				$lesson_html .= '<div class="flms-step-header">';
					if($flms_user_has_access || $completed) {
						$lesson_html .= flms_step_complete_checkbox($exam_id, $completed);
					}
					if($flms_user_has_access || $completed) {
						$lesson_html .= '<a href="'.get_permalink($exam_id).'" class="flms-primary">';
					}
					$lesson_html .= get_the_title($exam_id);
					if($flms_user_has_access || $completed) {
						$lesson_html .= '</a>';
					}
					
				$lesson_html .= '</div>';
			}
		}
		$lesson_html .= '</ul>';
	}
	return $lesson_html;
}

function flms_step_complete_checkbox($lesson_id, $completed = false) {
	global $flms_course_id, $flms_active_version, $current_user;
	$flms_user_activity = flms_get_user_activity($current_user->ID, $flms_course_id, $flms_active_version);
	$steps_completed = maybe_unserialize($flms_user_activity['steps_completed']);
	//print_r($steps_completed);
	if(!is_array($steps_completed)) {
		$steps_completed = array();
	}
	$complete = 'incomplete';
	$bg = 'flms-primary flms-background-bg flms-primary-border';
	if(in_array($lesson_id, $steps_completed) || $completed) {
		$complete = 'complete';
		$bg = 'flms-background flms-primary-bg flms-primary-border';

	}
	$checkbox = '<div class="step-checkbox '.$bg.'">';
	$checkbox .= '<div class="is-'.$complete.'"></div>';
	$checkbox .= '</div>';
	return $checkbox;
}

function flms_get_lesson_content($lesson_id,$content) {
	global $wp;
	$version = '';
	if(isset($wp->query_vars['course-version'])) {
		$version = $wp->query_vars['course-version'];
	}
	$lessons = array();
	$versions = get_post_meta($lesson_id,'flms_version_content',true);	
	//echo $lesson_id .'<br>';
	//echo '<pre>'.print_r($versions,true).'</pre>';
	if(is_array($versions)) {
		if($version == '') {
			//get first (latest) version in the array
			$index = array_key_last($versions);
			$latest = $versions["$index"];
			if(isset($latest["$content"])) {
				$lessons = $latest["$content"];
			}
		} else {
			//echo count($versions).'<br>';
			$course_id = get_post_meta($lesson_id,'flms_course',true);
			$index = get_version_index_from_slug($course_id,$version);
			if(isset($versions["$index"]["$content"])) {
				$lessons = $versions["$index"]["$content"];
			}
		}
	}
	return $lessons;
}

function get_version_index_from_slug($course_id,$slug = null) {
	global $wp;
	$versions = get_post_meta($course_id,'flms_version_content',true);
	if(!is_array($versions)) {
		return false;
	}
	if($slug == null) {
		return array_key_last($versions);
	}
	$index = 0;
	if(is_array($versions)) {
		foreach($versions as $k => $v) {
			if(isset($v['version_permalink'])) {
				if($v['version_permalink'] == $slug) {
					return $k;
					break;
				}
			}
		}
	}
	if($index = 0) {
		array_reverse($versions,true);
		return array_key_last($versions);
	}
	return false;
}
function flms_get_associated_exams($course_data, $wrap = false) {
	global $flms_user_has_access, $flms_course_id, $flms_active_version;
	
	if(!isset($course_data["post_exams"])) {
		return;
	}
	$lessons = $course_data["post_exams"];
	$lesson_html = '';
	if(!empty($lessons)) {
		if($wrap) {
			$lesson_html .= '<div class="flms-course-summary">';
		}
		$lesson_html .= '<div class="flms-course-exams flms-course-content-section">';
			$lesson_html .= '<h2>'.flms_get_post_type_label('flms-exams', true).'</h2>';
			$lesson_html .= '<div class="flms-list">';
				foreach($lessons as $lesson_id) {
					$completed = flms_user_completed_course($flms_course_id, $flms_active_version);
					$lesson_html .= '<div class="flms-course-lesson-item flms-course-exam flms-background-border';
					if(!$flms_user_has_access && !$completed) {
						$lesson_html .= ' flms-no-access';
					}
					$lesson_html .= '">';
						$lesson_html .= '<div class="flms-step-header">';
							if($flms_user_has_access || $completed) {
								$lesson_html .= flms_step_complete_checkbox($lesson_id, $completed);
							}
							if($flms_user_has_access || $completed) {
								$lesson_html .= '<a href="'.get_permalink($lesson_id).'" class="flms-primary">';
							} 
							$lesson_html .= get_the_title($lesson_id);
							if($flms_user_has_access) {
								$lesson_html .= '</a>';
							}
						$lesson_html .= '</div>';
						//$lesson_html .= '<div><a href="'.get_permalink($lesson_id).'" class="flms-lesson-link flms-primary">'.get_the_title($lesson_id).'</a></div>';
						//$lesson_html .= flms_get_lesson_topics_html($lesson_id,false);
					$lesson_html .= '</div>';
				}
			$lesson_html .= '</div>';
		$lesson_html .= '</div>';
		if($wrap) {
			$lesson_html .= '</div>';
		}
	}
	
	return $lesson_html;
}

function flms_print_exams($exams, $wrap = false) {
	global $flms_user_has_access;
	$lesson_html = '';
	if(!empty($exams)) {
		if($wrap) {
			$lesson_html .= '<div class="flms-course-summary">';
		}
		$lesson_html .= '<div class="flms-course-exams">';
			$lesson_html .= '<h2>'.flms_get_post_type_label('flms-exams', true).'</h2>';
			$lesson_html .= '<div class="flms-list flms-course-exam flms-background-border">';
				foreach($exams as $lesson_id) {
					
					if($lesson_id > 0) {
						$lesson_html .= '<div>';
							$lesson_html .= '<div class="flms-step-header">';
								if($flms_user_has_access) {
									$lesson_html .= flms_step_complete_checkbox($lesson_id);
								}
								$lesson_html .= '<a href="'.get_permalink($lesson_id).'" class="flms-primary">'.get_the_title($lesson_id).'</a>';
							$lesson_html .= '</div>';
						$lesson_html .= '</div>';
					}
				
				}
			$lesson_html .= '</div>';
		$lesson_html .= '</div>';
		if($wrap) {
			$lesson_html .= '</div>';
		}
	}
	return $lesson_html;
}

/**
 * Wrapper for get_permalink to add query args for versions
 */
function flms_get_permalink($post_id, $version = null) {
	global $wp;
	if(isset($wp->query_vars['course-version'])) {
		$version = $wp->query_vars['course-version'];
	}
	if($version !== null) {
		return add_query_arg ('course-version', $version, get_permalink($post_id)) ;
	} else {
		return get_permalink($post_id);
	}
}

function flms_get_title() {
	global $post;
	$title = get_the_title($post->ID);
	return apply_filters('flms_page_title',$title);
}

if ( ! function_exists( 'flms_page_title' ) ) {

	/**
	 * Page Title function.
	 *
	 * @param  bool $echo Should echo title.
	 * @return string
	 */
	function flms_page_title( $echo = true ) {

		global $post; 

		$page_title   = get_the_title( $post->ID );

		$page_title = apply_filters( 'flms_page_title', $page_title );

		if ( $echo ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $page_title;
		} else {
			return $page_title;
		}
	}
}


function flms_get_version_title($post_id,$version_slug) {
	$post_type = get_post_type($post_id);
	if($post_type == 'flms-topics') {
		$lesson_id = flms_get_topic_version_parent($post_id);					
		$course_id = get_post_meta($lesson_id,'flms_course',true);
	} else if($post_type == 'flms-lessons') {
		$course_id = get_post_meta($post_id,'flms_course',true);
	} else if($post_type == 'flms-exams') {
		$parent_id = flms_get_exam_version_parent($post_id);
		if($parent_id > 0) {
			$parent = get_post($parent_id);
			if($parent->post_type == 'flms-topics') {
				$lesson_id = flms_get_topic_version_parent($parent_id);
				if($lesson_id != '') {
					$course_id = get_post_meta($lesson_id,'flms_course',true);
				}
			} else if($parent->post_type == 'flms-lessons') {
				$lesson_id = $parent_id;
				if($lesson_id != '') {
					$course_id = get_post_meta($lesson_id,'flms_course',true);
				}
			} else if($parent->post_type == 'flms-courses') {
				$course_id = $parent->ID;
				
			} 
		} else {
			$course_id = 0;
		}
	} else {
		$course_id = $post_id;
	}
	if($course_id > 0) {
		$versions = get_post_meta($course_id,'flms_version_content',true);
		if(is_array($versions)) {
			
			$key = array_search($version_slug, array_map(function($data) {
				if(isset($data['version_permalink'])) {
					return $data['version_permalink'];
				} else {
					return '';
				}
			}, $versions));
			
			if($key !== false) {
				if(isset($versions["{$key}"]["version_name"])) {
					return $versions["{$key}"]["version_name"];
				} else {
					return '';
				}
			}
		}
	} else {
		return '';
	}
	
}

function flms_get_course_active_version_data($course_id, $field) {
	global $wp;
	if(isset($wp->query_vars['course-version'])) {
		$active_version = $wp->query_vars['course-version'];
	} else {
		$active_version = get_post_meta($course_id,'flms_course_active_version',true);
	}
	$version_data = '';
	if($active_version) {
		$versions = get_post_meta($course_id,'flms_version_content',true);
		if(is_array($versions)) {
			array_reverse($versions,true);
			$version_name = array_key_last($versions);
		} else {
			$version_name = 1;
		}
		if($active_version != $version_name) {
			if(isset( $versions["{$active_version}"])) {
				$version_data = $versions["{$active_version}"]["{$field}"];
				//add trailing slash when appending version permalinks
				if($field == 'version_permalink') {
					$version_data .= '/';
				}
			}
			 
		} 
	}  
	return $version_data;
}

function flms_get_post_type($post_id) {
	$post_type = get_post_type($post_id);
	return flms_get_post_type_label($post_type);
}

function flms_get_exam_version_parent($exam_id) {
	
	global $wp, $post; 
	if(empty($wp->query_vars['course-version'])) {
		
		$existing_parents = get_post_meta( $exam_id, 'flms_exam_parent_ids', true );
		
		if(!is_array($existing_parents)) {
			return false;
		}
		foreach($existing_parents as $parent) {
			if(is_array($parent)) {
				return;
			}
			$exam_data = explode(':',$parent);
			return $exam_data[0];
		}
		/*$key_values = array_column($existing_parents, 'version'); 
		array_multisort($key_values, SORT_DESC, $existing_parents);
		if(isset($existing_parents[0])) {
			return $existing_parents[0]['post_id'];
		} else {
			return 0;
		}*/
	}
	
	$course_id = '';

	if ($post->post_type == 'flms-courses') {
		$course_id = $post->ID;
	} else if ($post->post_type == 'flms-lessons') {
		$course_id = get_post_meta($post->ID,'flms_course',true);
	} else if ($post->post_type == 'flms-topics') {
		$lesson_id = flms_get_topic_version_parent($post->ID);
		if($lesson_id != '') {
			$course_id = get_post_meta($lesson_id,'flms_course',true);
		}
	} else {
		if(isset($wp->query_vars['flms-topics'])) {
			$pt_dbl_check = 'flms-topics';
			$pt_name = $wp->query_vars['flms-topics'];
		} else if(isset($wp->query_vars['flms-lessons'])) {
			$pt_dbl_check = 'flms-lessons';
			$pt_name = $wp->query_vars['flms-lessons'];
		} else if(isset($wp->query_vars['flms-courses'])) {
			$pt_dbl_check = 'flms-courses';
			$pt_name = $wp->query_vars['flms-courses'];
		}
		$args = array(
			'post_type'  => $pt_dbl_check,
			'name' => $pt_name
		);
		$postslist = get_posts( $args );
		if(!empty($postslist)) {
			
			foreach($postslist as $posty) {
				if(get_post_type($posty->ID) == 'flms-courses') {
					$course_id = $posty->ID;
				} else if(get_post_type($posty->ID) == 'flms-lessons') {
					$course_id = get_post_meta($posty->ID,'flms_course',true);
				} else { 
					//topic
					$lesson_id = flms_get_topic_version_parent($post->ID);
					$course_id = get_post_meta($lesson_id,'flms_course',true);
				}
				
				break;
			}
		}
	}
	if($course_id == '') {
		return 0;
	}

	$existing_parents = get_post_meta( $exam_id, 'flms_exam_parent_ids', true );
	if($existing_parents == '') {
		//no parents specified
		return false;
	}
	$version = $wp->query_vars['course-version'];
	$index = get_version_index_from_slug($course_id,$version);
	foreach ($existing_parents as $k => $v) {
		$exam_data = explode(':',$v);
		if ($exam_data[1] == $index) {
			return $exam_data[0];
			break;
		}
	}

	//default
	return false;
}

function flms_get_topic_version_parent($topic_id) {
	global $wp; 
	$existing_parents = get_post_meta( $topic_id, 'flms_topic_parent_ids', true );
	
	if(!is_array($existing_parents)) {
		return false;
	}
	if(empty($wp->query_vars['course-version'])) {
		$lesson_ids = get_post_meta($topic_id,'flms_topic_parent_ids',true);
		if(!empty($lesson_ids)) {
			$lessonct = 0;
			foreach($lesson_ids as $lesson) {
				$lesson_data = explode(':',$lesson);
				$lesson_id = $lesson_data[0];
				$lesson_version = $lesson_data[1];

				$lesson = new FLMS_Lesson($lesson_id);
				$lesson_version = $lesson_version;
				$course_id = $lesson->get_course_id();
				if($course_id > 0) {
					//$course_id = get_post_meta($lesson_id,'flms_course',true);
					$course_manager = new FLMS_Course($course_id);
					$index = $course_manager->get_active_version();
					if($index == $lesson_version) {
						return $lesson_id;
					}
				}
			}
		}
	} else {	
		$pt_dbl_check = '';
		if(isset($wp->query_vars['flms-courses'])) {
			$pt_dbl_check = 'flms-courses';
			$pt_name = $wp->query_vars['flms-courses'];
			$args = array(
				'post_type'  => $pt_dbl_check,
				'name' => $pt_name
			);
			$postslist = get_posts( $args );
			if(!empty($postslist)) {
				foreach($postslist as $posty) {
					$course_id = $posty->ID;
					break;
				}
			}
			$version = $wp->query_vars['course-version'];
			$index = get_version_index_from_slug($course_id,$version);
			foreach ($existing_parents as $k => $v) {
				$lesson_data = explode(':',$v);
				$lesson_id = $lesson_data[0];
				$lesson_version = $lesson_data[1];
				if ($lesson_version == $index) {
					return $lesson_id;
					break;
				}
			}
		}
	}

	return false;
}

function flms_is_flms_post_type($post) {
	if(!isset($post)) {
		return false;
	}
	$post_types = array(
		'flms-courses',
		'flms-lessons',
		'flms-topics',
		'flms-exams',
	);
	if(in_array($post->post_type,$post_types)) {
		return true;
	}
	return false;
}

function flms_get_course_id($post_id) {
	$post_type = get_post_type($post_id);
	switch ($post_type) {
		case 'flms-courses':
			return $post_id;
			break;
		case 'flms-lessons':
			return get_post_meta($post_id, 'flms_course', true);
			break;
		case 'flms-topics':
			$lesson_id = flms_get_topic_version_parent($post_id);
			return get_post_meta($lesson_id, 'flms_course', true);
			break;
		case 'flms-exams':
			$parent_id = flms_get_exam_version_parent($post_id);
			if($parent_id > 0) {
				//$parent_post = get_post($parent_id);
				return flms_get_course_id($parent_id);
			} else {
				return 0;
			}
			break;
		default:
			return 0;
			break;
	}		
}

function flms_user_has_access($post_id, $course_version = 1, $hard_query = false) {
	global $flms_user_has_access, $flms_active_version;
	if($course_version == 1) {
		$course_version = $flms_active_version;
	}
	if(current_user_can('administrator')) {
		$flms_user_has_access = true;
		return $flms_user_has_access;
	}
	if($flms_user_has_access != '' && !$hard_query) {
		return $flms_user_has_access;
	}
	//$flms_post = get_post($post_id);
	$course_id = flms_get_course_id($post_id);
	$user_id = get_current_user_id();
	if($user_id == 0) {
		return false;
	}
	
	$user_access = flms_get_user_course_status($user_id, $course_id, $course_version);
	if($user_access['customer_status'] == 'enrolled') {
		$flms_user_has_access = true;
		return $flms_user_has_access;
	}
	$completed = flms_user_completed_course($course_id, $course_version);
	if($completed) {
		$flms_user_has_access = true;
		return $flms_user_has_access;
	}
	$flms_user_has_access = false;
	return $flms_user_has_access;
}

function flms_enroll_course_button($post_id, $course_version, $version_index) {
	$return = '';
	
	$versions = get_post_meta($post_id,'flms_version_content',true);
	$default_label = flms_get_label('enroll_label');
	if(isset($course_version['course_settings']['course_access'])) {
		$return .= '<div class="course-enrollment-actions flms-course-content-section">';
			if($course_version['course_settings']['course_access'] == 'open') {
				if(get_current_user_id() > 0) {
					$return .= '<div><button id="flms-enroll" class="button button-primary">';
					if(flms_user_completed_course($post_id,$version_index)) {
						$return .= apply_filters('flms_enroll_again_text', 'Enroll Again');
					} else {
						$return .= apply_filters('flms_enroll_text', $default_label);
					}
					$return .= '</button></div>';
					$return .= '<div id="enroll-response" class="description"></div>';
				} else {
					$permalink = get_permalink($post_id);
					$lcenroll = strtolower($default_label);
					echo flms_login_link("Log in to $lcenroll", $permalink);
				}
			} else {
				//needs to be purchased
				if(flms_is_module_active('woocommerce')) {
					//$course_version
					global $flms_latest_version;
					if($flms_latest_version == $version_index) {
						$product_id = get_post_meta($post_id, 'flms_woocommerce_product_id', true);
						if($product_id != '') {
							global $product;
							$product = wc_get_product($product_id);
							$type = $product->get_type();
							wp_enqueue_script('flms-woocommerce-variations-toggle');
							$return .= '<div class="woocommerce">';
								$return .= '<div class="product course-add-product">';
									$type = $product->get_type();
									wp_enqueue_script('flms-woocommerce-variations-toggle');
									ob_start();
									//notice if course available from a group
									if(flms_is_module_active('groups')) {
										$groups = new FLMS_Module_Groups();
										echo $groups->course_available_through_group($post_id,$version_index);
									}
									woocommerce_template_single_price();
									if($type == 'variable') {
										woocommerce_variable_add_to_cart();
									} else {
										woocommerce_simple_add_to_cart();
									}
									//do_action( 'woocommerce_' . $product->get_type() . '_add_to_cart' );
									global $product;
									$return .= ob_get_clean();
								$return .= '</div>';
							$return .= '</div>';
						} else {
							$noproducts = '<p class="no-purchase-option">There are no options to purchase this course.</p>';
							if(current_user_can('manage_woocommerce')) {
								$noproducts .= ' <a href="'.admin_url('edit.php?post_type=product').'" class="btn">Assign course to product</a> <a href="'.admin_url('post.php?post='.$post_id.'&action=edit').'" class="btn">Update course access type</a>';
							} else if(current_user_can('edit_others_posts')) {
								$noproducts .= ' <a href="'.admin_url('post.php?post='.$post_id.'&action=edit').'" class="btn">Update course access type</a>';
							} 
							
							$return .= apply_filters('flms_no_purchase_option', $noproducts, array($post_id,$course_version));
						}
					} else {
						//$woo = new FLMS_Module_Woocommerce();
						//echo $woo->purchase_course_options($post_id, $version_index);
						$args = array(
							'post_type' => array('product','product_variation'),
							'meta_query' => array(
								array(
									'key'     => 'flms_woocommerce_course_id',
									'value'   => array("$post_id:$version_index"),
									'compare' => 'IN'
								),
							),
							
						);
						//flms_woocommerce_product_id
						$products = get_posts($args);
						
						if(!empty($products)) {
							foreach ($products as $index => $product_array) {
								$product = wc_get_product($product_array->ID);
								$type = $product->get_type();
								if($type == 'simple') {
									$product_id = $product_array->ID;
								} else {
									$product_id = $product->get_parent_id();
								}
								$taken = get_post_meta($product_id, 'flms_woocommerce_product_id', true);
								if($taken != '') {
									unset($products[$index]);
								}
							}
						}
						if(empty($products)) {
							$noproducts = '<p class="no-purchase-option">There are no options to purchase this course.</p>';
							if(current_user_can('manage_woocommerce')) {
								$noproducts .= ' <a href="'.admin_url('edit.php?post_type=product').'" class="btn">Assign course to product</a> <a href="'.admin_url('post.php?post='.$post_id.'&action=edit').'" class="btn">Update course access type</a>';
							} else if(current_user_can('edit_others_posts')) {
								$noproducts .= ' <a href="'.admin_url('post.php?post='.$post_id.'&action=edit').'" class="btn">Update course access type</a>';
							} 
							
							$return .= apply_filters('flms_no_purchase_option', $noproducts, array($post_id,$course_version));
						}
						$product_options = array();
						foreach ($products as $product_array) {
							$product = wc_get_product($product_array->ID);
							$type = $product->get_type();
							if($type == 'simple') {
								$product_id = $product_array->ID;
							} else {
								$product_id = $product->get_parent_id();
							}
							if(!in_array($product_id,$product_options)) {
								$product_options[] = $product_id;
							}
						}
						if(!empty($product_options)) {
							if(count($product_options) > 1) {
								$return = '<p>There are multiple purchase options for this course:</p>';
								$return .= '<ul>';
								foreach($product_options as $product_option) {
									$return .= '<li><a href="'.get_permalink($product_option).'">'.get_the_title($product_option).'</a></li>';
								}
								$return .= '</ul>';
							} else {
								//$return = '<div><a href="'.get_permalink($product_options[0]).'" class="button button-primary">Purchase</a></div>';
								global $product;
								$return .= '<div class="woocommerce">';
								$return .= '<div class="product course-add-product">';
								$product = wc_get_product($product_options[0]);
								$type = $product->get_type();
								wp_enqueue_script('flms-woocommerce-variations-toggle');
								ob_start();
								woocommerce_template_single_price();
								if($type == 'variable') {
									woocommerce_variable_add_to_cart();
								} else {
									woocommerce_simple_add_to_cart();
								}
								//do_action( 'woocommerce_' . $product->get_type() . '_add_to_cart' );
								global $product;
								$return .= ob_get_clean();
								$return .= '</div>';
								$return .= '</div>';
								
								
							}
						} 
						
					}
				}
			}
		$return .= '</div>';
		return $return;
	} 
	return $return;
}

/**
 * Helper function to insert array by key
 */
function flms_array_insert_after( array $array, $key, array $new ) {
	$keys = array_keys( $array );
	$index = array_search( $key, $keys );
	$pos = false === $index ? count( $array ) : $index + 1;

	return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
}

function flms_get_user_active_courses($user_id = 0) {
	if($user_id == 0) {
		global $current_user;
		$user_id = $current_user->ID;
	}
	global $wpdb;
	$table = FLMS_ACTIVITY_TABLE;
	$sql_query = $wpdb->prepare("SELECT * FROM $table WHERE customer_status=%s AND customer_id=%d ORDER BY id DESC", 'enrolled', $user_id);
	$results = $wpdb->get_results( $sql_query, ARRAY_A ); 
	if(!empty($results)) {
		return $results;
	} else {
		return array();
	}
	return $active_courses;
}

function flms_get_user_completed_courses($user_id = 0) {
	if($user_id == 0) {
		global $current_user;
		$user_id = $current_user->ID;
	}
	global $wpdb;
	$table = FLMS_ACTIVITY_TABLE;
	$sql_query = $wpdb->prepare("SELECT * FROM $table WHERE customer_status=%s AND customer_id=%d ORDER BY id DESC", 'completed', $user_id);
	$results = $wpdb->get_results( $sql_query, ARRAY_A ); 
	if(!empty($results)) {
		return $results;
	} else {
		return array();
	}
	return $completed_courses;
}

/*function flms_user_completed_course($course_id, $course_version) {
	$completed_courses = flms_get_user_completed_courses();
	$completed = false;
	foreach($completed_courses as $active_course) {
		$current_course_id = $active_course['course_id'];
		$current_course_version = $active_course['course_version'];
		if($current_course_id == $course_id && $current_course_version == $course_version) {
			$completed = true;
			break;
		}
	}
	return $completed;
}*/

function flms_get_user_completed_course_list($user_id, $active_courses, $echo = false) {
	global $flms_settings;
	if(flms_is_module_active('course_numbers')) {
		$extra_class = 'columns-5';
	} else {
		$extra_class = 'columns-4';
	}
	$date_format = get_option('date_format');
	$course_label = 'Course';
	if(isset($flms_settings['labels']["course_singular"])) {
		$course_label = $flms_settings['labels']["course_singular"];
	}
	$list = '<div class="my-courses-list course-list-item '.$extra_class.'">';
		//$list .= '<div class="course-list-item headings flms-font-bold '.$extra_class.'">';
			$list .= '<div class="course-name flms-font-bold flms-desktop-only">'.$course_label.' Name</div>';
			//$list .= '<div class="course-meta">';
			if(flms_is_module_active('course_numbers')) {
				$list .= '<div class="flms-font-bold flms-desktop-only">'.$course_label.' Number</div>';
			}
			$list .= '<div class="flms-font-bold flms-desktop-only">Enrolled</div>';
			$list .= '<div class="flms-font-bold flms-desktop-only">Completed</div>';
			$list .= '<div class="flms-font-bold flms-desktop-only actions text-right">Actions</div>';
			//$list .= '</div>';
		//$list .= '</div>';
		foreach($active_courses as $active_course) {
			//$data = explode(':',$active_course['course']);
			$course_id = $active_course['course_id'];
			$course_version = $active_course['course_version'];
			$course_data = get_post_meta($course_id,'flms_version_content',true);
			if(!is_array($course_data)) {
				$course_data = array();
			}
			krsort($course_data);
			if(array_key_first($course_data) == $course_version) {
				//viewing the latest version
				/*if(isset($course_data["$course_version"]['version_name'])) {
					$title = $course_data["$course_version"]['version_name'];
				} else {
					$title = get_the_title($course_id);
				}*/
				$title = get_the_title($course_id);
				$permalink = get_permalink( $course_id);
			} else {
				if(isset($course_data["$course_version"]['version_name'])) {
					$title = get_the_title($course_id) .' ('.$course_data["$course_version"]['version_name'].')';
				} else {
					$title = get_the_title($course_id);
				}
				$permalink = flms_get_course_version_permalink($course_id, $course_version);
				
			}
			
			//$list .= '<div class="course-list-item '.$extra_class.'">';
				$list .= '<div class="course-name" data-label="'.$course_label.':"><a href="'.$permalink.'" title="View '.$title.'">'.$title.'</a></div>';
				//$list .= '<div class="course-meta">';
					if(flms_is_module_active('course_numbers')) {
						$course_numbers = new FLMS_Module_Course_Numbers();
						$list .= '<div data-label="'.$course_label.' Number:">'.$course_numbers->get_course_number($course_id, $course_version).'</div>';
					}
					$list .= '<div data-label="Enrolled:">';
					if(isset($active_course['enroll_date'])) {
						$enrolled = $active_course['enroll_date'];
						$list .= date($date_format, strtotime($enrolled));
					} 
					$list .= '</div>';
					$date_completed = $active_course['completion_date'];
					$list .= '<div data-label="Completed:">'. date($date_format, strtotime($date_completed)).'</div>';
					$list .= '<div class="actions text-right" data-label="Actions:">';
						if(is_admin()) {
							$list .= '<a href="#" class="profile-reset-completed-course" data-course="'.$course_id.'" data-version="'.$course_version.'" data-user="'.$user_id.'">Reset course progress</a>';
						}
						if(flms_is_module_active('course_certificates')) {
							$course_certificates = new FLMS_Module_Course_Certificates();
							$label = $course_certificates->get_certificate_label();
							$rewrite = $course_certificates->get_certificate_permalink();
							$list .= '<a href="/'.$rewrite.'/'.$course_id.'/'.$course_version.'/'.$user_id.'" target="_blank" class="view-certificate">View '.$label.'</a>';
						}
					$list .= '</div>';
					//}
				//$list.= '</div>';
			//$list.= '</div>';
		}
	$list .= '</div>';
	if($echo) {
		echo $list;
	} else {
		return $list;
	}
}

function flms_get_user_active_course_list($user_id, $completed_courses, $echo = false) {
	global $flms_settings;
	$show_course_progress = apply_filters('flms_show_course_progress', true);
	$columns = 3;
	$extra_class = '';
	if($show_course_progress) {
		$columns++;
	} else {
		$extra_class .= ' span-last-col';
	}
	if(flms_is_module_active('course_numbers')) {
		$columns++;
	}
	$extra_class .= ' columns-'.$columns;
	$date_format = get_option('date_format');
	$course_label = 'Course';
	if(isset($flms_settings['labels']["course_singular"])) {
		$course_label = $flms_settings['labels']["course_singular"];
	}
	$course_materials_module = new FLMS_Module_Course_Materials();
	$list = '<div class="my-courses-list course-list-item '.$extra_class.'">';
		$list .= '<div class="course-name flms-font-bold flms-desktop-only">'.$course_label.' Name</div>';
		//$list .= '<div class="course-meta">';
		if(flms_is_module_active('course_numbers')) {
			$list .= '<div class="flms-font-bold flms-desktop-only">'.$course_label.' Number</div>';
		}
		$list .= '<div class="flms-font-bold flms-desktop-only">Enrolled</div>';
		if($show_course_progress) {
			$list .= '<div class="flms-font-bold flms-desktop-only">Progress</div>';
		}
		if(is_admin()) {
			$list .= '<div class="actions flms-font-bold flms-desktop-only">Actions</div>';
		} else {
			$label = "Course Materials";
			if(isset($flms_settings['labels']['course_materials'])) {
				$label = $flms_settings['labels']['course_materials'];
			}
			$list .= '<div class="flms-font-bold flms-desktop-only';
			if($show_course_progress) {
				$list .= ' actions';
			}
			$list .= '">'.$label.'</div>';
		}
			//$list .= '</div>';
		//$list .= '</div>';
		foreach($completed_courses as $active_course) {
			$course_id = $active_course['course_id'];
			$course_version = $active_course['course_version'];
			$course = new FLMS_Course($course_id);
			global $flms_active_version;
			$flms_active_version = $course_version;
			$flms_user_activity = flms_get_user_activity($user_id, $course_id, $course_version);
			$steps_completed = maybe_unserialize($active_course['steps_completed']);
			$title = $course->get_course_version_name($course_version);
			$permalink = flms_get_course_version_permalink($course_id, $course_version);
			if(is_admin()) {
				$permalink = get_edit_post_link($course_id).'&set-course-version='.$course_version;
			}

			
			//$list .= '<div class="course-list-item '.$extra_class.'">';
				$list .= '<div class="course-name" data-label="'.$course_label.':"><a href="'.$permalink.'" title="View '.$title.'">'.$title.'</a></div>';
				//$list .= '<div class="course-meta">';
					if(flms_is_module_active('course_numbers')) {
						$course_numbers = new FLMS_Module_Course_Numbers();
						$course_number = $course_numbers->get_course_number($course_id, $course_version);
						if($course_number == '' && is_admin()) {
							$course_number = 'N/A';
						}
						$list .= '<div data-label="'.$course_label.' Number:">'.$course_number.'</div>';
					}
					$list .= '<div data-label="Enrolled:">';
					$identifier = str_replace(':','-',$active_course);
					$list .= date($date_format, strtotime($active_course['enroll_date']));
					$list .= '</div>';
					$completed = 0;
					if(!is_array($steps_completed)) {
						$completed = 0;
					} else {
						$completed = count($steps_completed);
					}
					$course = new FLMS_Course($course_id);
					global $flms_active_version;
					$flms_active_version = $course_version;
					$steps = $course->get_all_course_steps();
					$steps_count = count($steps);
					if($steps_count == 0) {
						$percent = '0%';
					} else {
						$percent = absint(100 * (absint($completed) / absint($steps_count))).'%';
					}
					if($show_course_progress) {
						$list .= '<div data-label="Progress:">';
						$list .= "$percent ($completed of $steps_count steps)";
						$list .= '</div>';
					}
					$list .= '<div class="actions';
					if(!$show_course_progress) {
						if(!is_admin()) {
							$list .= ' text-left';
						}
					}
					$list .= '" data-label="Actions:">';
						if(is_admin()) {
							if($completed > 0) {
								//$list .= '<button class="profile-reset-user-progress button button-primary" data-course="'.$course_id.'" data-version="'.$course_version.'" data-user="'.$user_id.'">Reset User Progress</button>';	
								$list .= '<a href="#" class="profile-reset-user-progress" data-course="'.$course_id.'" data-version="'.$course_version.'" data-user="'.$user_id.'">Reset User Progress</a>';	
							}
							//$list .= '<button class="profile-unenroll-user button button-primary" data-course="'.$course_id.'" data-version="'.$course_version.'" data-user="'.$user_id.'">Unenroll User</button>';
							$list .= '<a href="#" class="profile-unenroll-user" data-course="'.$course_id.'" data-version="'.$course_version.'" data-user="'.$user_id.'">Unenroll User</a>';
							$list .= '<a href="#" class="profile-complete-course" data-course="'.$course_id.'" data-version="'.$course_version.'" data-user="'.$user_id.'">Complete course</a>';
							$exams = $course->get_course_version_exams();
							if(is_array($exams)) {
								//$list .= print_r($exams,true);
								if(!empty($exams)) {
									foreach($exams as $exam) {
										$exam_identifier = "$exam:$course_version";
										$meta_key = "flms_{$exam_identifier}_exam_attempts";
										$attempts = get_user_meta($user_id, $meta_key, true);
										$meta_key = "flms_{$exam_identifier}_exam_attempt_{$attempts}";
										$attempts = get_user_meta($user_id, $meta_key, true);
										if($attempts != '') {
											$edit_text = 'Edit exam responses';
											if(count($exams) > 1) {
												$edit_text .= ' for &ldquo;'.get_the_title($exam).'&rdquo';
											}
											$list .= '<a href="'.admin_url('admin.php?page=flms-exam-editor&exam_id='.$exam.'&exam_version='.$course_version.'&user_id='.$user_id).'" target="_blank">'.$edit_text.'</a>';
										}
									}
								}
							}
							//$list .= print_r($exams,true);
						} else {
							//$list .= '<a href="#">Course Materials?</a>';
							$list .= implode('<br>',$course_materials_module->display_course_materials(array("$course_id:$course_version")));
						}
					$list .= '</div>';
					//}
				//$list.= '</div>';
			//$list.= '</div>';
		}
	$list .= '</div>';
	if($echo) {
		echo $list;
	} else {
		return $list;
	}
}

function get_course_lesson_count($version_data) {
	if(isset($version_data["course_lessons"])) {
		return count($version_data["course_lessons"]);
	} else {
		return 0;
	}
}

function flms_get_course_select_box() {
	$args = array(
		'post_type' => 'flms-courses',
		'numberposts' => -1,
		'order'       => 'ASC',
  		'orderby'     => 'title'
	);
	$results = array();
	$courses = get_posts( $args );
	if ($courses) {
		foreach ( $courses as $course ) {
			$course_id = $course->ID;
			$versions = get_post_meta($course_id,'flms_version_content',true);
			if(is_array($versions)) {
				krsort($versions);
				foreach($versions as $k => $v) {
					//$results[get_the_ID()] = get_the_title();
					$course_identifier = "$course_id:$k";
					if(isset($v['version_name'])) {
						$version_name = $v['version_name'];
					} else {
						$version_name = "Version $k";
					}
					$status = 'draft';
					if(isset($v['version_status'])) {
						$status = $v['version_status'];
					}
					if($status == 'draft') {
						$version_name .= ', Draft';
					}
					$results[$course_identifier] = get_the_title($course_id) ." ($version_name)";
				}
			}
		}
		wp_reset_postdata(); 	
	}
	return $results;

	wp_reset_query();
}

function flms_get_certificate_select_box() {
	$args = array(
		'post_type' => 'flms-certificates',
		'numberposts' => -1,
		'order'       => 'ASC',
  		'orderby'     => 'title'
	);
	$results = array();
	$certificates = get_posts( $args );
	if ($certificates) {
		foreach ( $certificates as $certificate ) {
			$results[$certificate->ID] = get_the_title($certificate);
		}
		wp_reset_postdata(); 	
	}
	return $results;

	wp_reset_query();
}

function flms_get_label($label) {
	if($label == '') {
		return;
	}
	global $flms_settings;
	if(isset($flms_settings['labels'][$label])) {
		return $flms_settings['labels'][$label];
	} else {
		//check if custom credit type
		foreach($flms_settings['course_credits'] as $k => $v) {
			if($label == $k) {
				return $v["name"];
			}
		}
	}
	return $label;
}

function flms_print_field_input($field, $field_category, $parent_key = '') {
	global $flms_settings;
	$field_value = '';
	if($parent_key != '') {
		$parent = "[$parent_key]";	
		$id = $field_category.'-'.$parent_key;
		if(isset( $flms_settings[$field_category][$parent_key][$field['key']] )) {
			$field_value = $flms_settings[$field_category][$parent_key][$field['key']];
			//echo '<pre>'.print_r($field,true).'</pre>';
			//$field_value = $field['default'];
		} else if(isset( $field['default'] )) {
			$field_value = $field['default'];
		}
	} else {
		$parent = '';
		$id = $field_category.'-'.$field['key'];
		if(isset( $flms_settings[$field_category][$field['key']] )) {
			$field_value = $flms_settings[$field_category][$field['key']];
		} else if(isset( $field['default'] )) {
			$field_value = $field['default'];
		} 
	}
	//see if there are conditional fields to process
	$conditional_toggle = '';
	if(isset($field['conditional_toggle'])) {
		//print_r($field);
		$conditional_field = $field['conditional_toggle']['field'];
		$conditional_value = $field['conditional_toggle']['value'];
		$conditional_action = $field['conditional_toggle']['action'];
		//if($flms_settings[$field_category][$conditional_field] == $conditional_value)
		$conditional_toggle = ' data-conditional-toggle="true" data-conditional-field="'.$conditional_field.'" data-condition="'.$conditional_action.'" data-conditional-toggle-val="'.$conditional_value.'"';
	}
	$class = '';
	if(isset($field['class'])) {
		$class = $field['class'];
	}
	$module_status = '';
	if(strpos($id, 'modules') !== false) {
		$module_status = $field_value;
	}
	?>
	<div class="settings-field <?php echo $field['key']; ?> flms-field-<?php echo $field['type']; ?> <?php echo $class; ?> <?php echo $module_status; ?>" id="<?php echo $id; ?>" <?php echo $conditional_toggle; ?> data-label="<?php echo $field['label']; ?>">
		<div class="setting-field-label">
			<?php 
			if($field['type'] == 'delete') {
				?><button class="delete-field button button-primary" data-group="<?php echo $field_category.'-'.$parent_key; ?>">Delete Field</button><?php 
			} else if($field['type'] == 'section_heading' || $field['type'] == 'group') {
				echo '<h3>'.$field['label'].'</h3>'; 
			} else if($field['type'] == 'flms-message') {
				echo '<p class="section-message">'.$field['label'].'</p>'; 
			} else {
				echo $field['label']; 
			} ?>
			<?php if(isset($field['description'])) {
				if($field['description'] != '') {
					if($field_category != 'modules' && $field_category != 'advanced' && $field['type'] != 'section_heading' && $field['type'] != 'flms-message' && $field['type'] != 'flms-button') {
						echo '<div class="flms-tooltip" data-tooltip="'.$field['description'].'"></div>';
					}
				}
			} ?>
		</div>
		<?php if($field_category == 'modules' || $field_category == 'advanced' || $field['type'] == 'section_heading' || $field['type'] == 'flms-message' || $field['type'] == 'flms-button') {
			if(isset($field['description'])) {
				if($field['description'] != '') {
					echo "<div class='desc'><p>{$field['description']}</p></div>";
				}
			}
		} ?>
		<div class="flms-field <?php echo $field['type']; ?>">
			<?php 
			if($parent_key != '') {
				$field_name = "flms_settings[$field_category][$parent_key][{$field['key']}]";
			} else {
				$field_name = "flms_settings[$field_category][{$field['key']}]";
			}
			switch($field['type']) {
				case 'text':
					?>
					<input autocomplete="false" type="text" name="<?php echo $field_name; ?>" value="<?php echo $field_value; ?>" <?php if(isset($field['placeholder'])) { echo 'placeholder="'.$field['placeholder'].'"'; } ?> <?php if(isset($field['flag_check'])) { echo 'data-flag="'.$field['flag_check'].'"'; } ?>><?php 
					break;
				case 'textarea':
					?>
					<textarea name="<?php echo $field_name; ?>"><?php echo $field_value; ?></textarea><?php 
					break;
				case 'radio':
					$count = 0;
					foreach($field['options'] as $optionname => $optionlabel) { 
						$count++; ?>
						<input type="radio" id="<?php echo $field_name; ?>-<?php echo $count; ?>" name="<?php echo $field_name; ?>" value="<?php echo $optionname; ?>" <?php if($field_value == $optionname) echo ' checked'; ?> <?php if(isset($field['flag_check'])) { echo 'data-flag="'.$field['flag_check'].'"'; } ?>>
						<label for="<?php echo $field_name; ?>-<?php echo $count; ?>"><?php echo $optionlabel; ?></label><?php 
					}
					break;
				case 'checkbox':
					$checked = '';
					if($field_value != '') {
						$checked = ' checked="checked"';
					}
					?>
					<label>
						<input type="checkbox" name="<?php echo $field_name; ?>" <?php echo $checked; ?> <?php if(isset($field['flag_check'])) { echo 'data-flag="'.$field['flag_check'].'"'; } ?> /><?php
						echo $field['checkbox_label']; ?>
					</label><?php 
					break;
				case 'flms-button':
					?>
					<button id="<?php echo $field['key']; ?>" class="button button-secondary"><?php echo $field['button_label']; ?></button>
					<?php 
					break;
				case 'number':
					if(isset($field['prefix'])) {
						echo $field['prefix'];
					}
					?>
					<input type="number" name="<?php echo $field_name; ?>" min="0" value="<?php echo $field_value; ?>" <?php if(isset($field['flag_check'])) { echo 'data-flag="'.$field['flag_check'].'"'; } ?>><?php 
					if(isset($field['suffix'])) {
						echo $field['suffix'];
					}
					break;
				case 'currency':
					if(isset($field['prefix'])) { 
						echo '<span class="currency-label">'.$field['prefix'].'</span>'; 
					} ?>
					<input type="number" class="currency-input <?php if(isset($field['prefix'])) { echo 'has-prefix'; } ?>" step=".01" name="<?php echo $field_name; ?>" min="0" value="<?php echo number_format(floatval($field_value), 2); ?>" <?php if(isset($field['flag_check'])) { echo 'data-flag="'.$field['flag_check'].'"'; } ?>><?php 
					break;
				case 'select':
					?>
					<select name="<?php echo $field_name; ?>" <?php if(isset($field['flag_check'])) { echo 'data-flag="'.$field['flag_check'].'"'; } ?>><?php 
					foreach($field['options'] as $optionname => $optionlabel) { echo $optionlabel; ?>
						<option value="<?php echo $optionname; ?>" <?php if($field_value == $optionname) echo ' selected'; ?>><?php echo $optionlabel; ?></option><?php 
					}
					?>
					</select><?php 
					break;
				case 'color_picker':
					?>
					<input type="text" class="flms-color-picker" name="<?php echo $field_name; ?>" value="<?php echo $field_value; ?>" <?php if(isset($field['flag_check'])) { echo 'data-flag="'.$field['flag_check'].'"'; } ?>><?php 
					break;
				case 'icon_select':
					foreach($field['options'] as $optionname => $optionlabel) {  ?>
						<div class="icon-select">
							<input id="<?php echo $field_category.'-'.$field['key'].'-'.$optionname;?>" type="radio" name="flms_settings[<?php echo $field_category ;?>][<?php echo $field['key'];?>]" value="<?php echo $optionname; ?>" <?php if($field_value == $optionname) echo ' checked'; ?> <?php if(isset($field['flag_check'])) { echo 'data-flag="'.$field['flag_check'].'"'; } ?>>
							<label for="<?php echo $field_category.'-'.$field['key'].'-'.$optionname;?>" class="dashicon-<?php echo $optionname; ?>">
								<div class="icon dashicons-<?php echo $optionname; ?>"></div>
							</label>
						</div><?php 
					}
					break;
				case 'group':
					if(isset($field['sortable'])) {
						echo '<div class="sortable-group">';
							echo '<div>';
					}
					foreach($field['group_fields'] as $group_field) {
						//echo '<pre>'.print_r($group_field,true).'</pre>';
					//	echo $k .' '.$v.'<br>';
						$parent_key = $field['key'];
						flms_print_field_input($group_field, $field_category, $parent_key);
					}
					if(isset($field['sortable'])) {
							echo '</div>';
							echo '<div class="'.$field['sortable'].'"></div>';
						echo '</div>';
					}
					break;
				case 'dynamic':
					$class = new $field['class']();
					$function = $field['function'];
					echo $class->$function();
					echo '<div id=""></div>';
					break;
				case 'hidden':
					?>
					<input type="hidden" name="<?php echo $field_name; ?>" value="<?php echo $field_value; ?>" <?php if(isset($field['flag_check'])) { echo 'data-flag="'.$field['flag_check'].'"'; } ?>><?php 
					break;
				case 'section_heading':
					
					break;
				case 'message':
					
					break;
				case 'delete':
					?>
					<?php
					break;
				case 'debug':
					echo '<pre>'.print_r($flms_settings,true).'</pre>';
					break;
				case 'attribute_select':
					if(function_exists('wc_get_attribute_taxonomies')) {
						$attributes = wc_get_attribute_taxonomies();
						if(is_array($attributes)) {
							if(empty($attributes)) {
								echo '<a href="'.admin_url('edit.php?post_type=product&page=product_attributes').'">Create some attributes</a> before using this option.';
							} else {
								foreach($attributes as $attribute) {
									echo '<div>';
										echo '<div class="flms-label">'.$attribute->attribute_label.'</div>';
										$taxonomy = 'pa_'.$attribute->attribute_name;
										$terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => 0));
										if(!empty($terms)) {
											foreach($terms as $term) {
												$checked = '';
												if(isset($field_value[$taxonomy])) {
													if(in_array($term->term_id,$field_value[$taxonomy])) {
														$checked = ' checked="checked"';
													}
												}
												echo '<label><input type="checkbox" name="'.$field_name.'['.$taxonomy.'][]" value="'.$term->term_id.'" '.$checked.' />'.$term->name.'</label>';
											}
										}
									echo '</div>';
								}
							}
						}
					} else {
						echo 'Active Woocommerce to enable attribute selector.';
					}
					break;
			} ?>
			<div class="flag-notice"></div>
		</div>
	</div>
	<?php
}

function flms_course_navigation() {
	global $flms_course_id, $flms_active_version, $flms_course_version_content, $post, $flms_user_has_access, $current_user;
	
	$course = new FLMS_Course($flms_course_id);
	$course_steps = $course->get_course_steps_order();
	//echo '<pre>'.print_r($course_steps,true).'</pre>';
	//$all_course_steps = $course->get_all_course_steps();
	//echo '<pre>'.print_r($all_course_steps,true).'</pre>';
	$post_id = $post->ID;
	$post_type = get_post_type($post_id);
	$keys = array_keys($course_steps);
	
	$course_id = flms_get_course_id($post->ID);
	$course_identifier = "$course_id:$flms_active_version";
	$flms_user_activity = flms_get_user_activity($current_user->ID, $course_id, $flms_active_version);
	$steps_completed = maybe_unserialize($flms_user_activity['steps_completed']);
	
	echo '<div class="flms-course-navigation">';
	switch($post_type) {
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
						echo '<a href="'.get_permalink($prev).'" class="button button-primary">&laquo;&nbsp;'.get_the_title($prev).'</a>';
					} else {
						echo '<a href="'.get_permalink($prev_key).'" class="button button-primary">&laquo;&nbsp;'.get_the_title($prev_key).'</a>';
					}
				}
			} else {
				echo '<a href="'.get_permalink($course_id).'" class="button button-primary">&laquo;&nbsp;'.get_the_title($course_id).'</a>';
			}

			//next
			$next = 0;
			if (array_key_exists($post_id, $course_steps)) {
				if(isset($course_steps[$post_id][0])) {
					//first topic in lesson
					$next = $course_steps[$post_id][0];
				} else {
					//no topics in lesson
					$next_index = array_search($post_id, $keys) + 1;
					if($next_index < count($course_steps)) {
						//next step in lesson
						$next = $keys[$next_index];
					}
				}
			}
			
			if(flms_is_step_complete($steps_completed, $post_id)) {
				flms_lesson_is_complete($post_id);
				//show next
				echo '<a href="'.get_permalink($next).'" class="button button-primary">'.get_the_title($next).'&nbsp;&raquo;</a>';
			} else {
				//needs completing
				$lesson_steps = $course_steps[$post_id];
				if(!empty($lesson_steps) || !$flms_user_has_access) {
					//link to sub content, this lesson can't be completed yet
					echo '<a href="'.get_permalink($next).'" class="button button-primary">'.get_the_title($next).'&nbsp;&raquo;</a>'; 
				} else {
					//complete lesson
					flms_complete_lesson_button($post_id, $next);
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
					echo '<a href="'.get_permalink($prev).'" class="button button-primary">&laquo;&nbsp;'.get_the_title($prev).'</a>';
				} else {
					//no previous step in lesson
					//see if prior lesson has topics
					echo '<a href="'.get_permalink($lesson_id).'" class="button button-primary">&laquo;&nbsp;'.get_the_title($lesson_id).'</a>';
				}
				
				$next = 0;
				if($index !== false && $index < count($course_steps[$lesson_id])-1) {
					//next step in lesson
					$next = $course_steps[$lesson_id][$index+1];
				}
				if(isset($course_steps[$post_id][0])) {
					$next = $course_steps[$post_id][0];
				}
				if($next == 0) {
					//end of current lesson, find next lesson
					$next_index = array_search($lesson_id, $keys) + 1;
					if($next_index < count($course_steps)) {
						//next step in lesson
						$next = $keys[$next_index];
					}
				}
				if($next == 0) {
					$next = $course_id;
				}
				//print_r($course_steps);
				if(flms_is_step_complete($steps_completed, $post_id)) {
					flms_lesson_is_complete($post_id);
					//link to sub content, this lesson can't be completed yet
					echo '<a href="'.get_permalink($next).'" class="button button-primary">'.get_the_title($next).'&nbsp;&raquo;</a>'; 
				} else {
					//complete lesson
					flms_complete_lesson_button($post_id, $next);
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
							echo '<a href="'.get_permalink($prev).'" class="button button-primary">&laquo;&nbsp;'.get_the_title($prev).'</a>';
						} else if (array_key_exists($keys[$prev_index], $course_steps)) {
							$prev = $keys[$prev_index];
							echo '<a href="'.get_permalink($prev).'" class="button button-primary">&laquo;&nbsp;'.get_the_title($prev).'&nbsp;&raquo;</a>';
						}
					}
				} else {
					echo '<div></div>'; //placeholder to get button to float right
				}

				if(flms_is_step_complete($steps_completed, $post_id)) {
					flms_lesson_is_complete($post_id);
				} 
				
				//course exam
				$next = 0;
				$next_index = array_search($post_id, $keys) + 1;
				if($next_index < count($course_steps)) {
					if (array_key_exists($keys[$next_index], $course_steps)) {
						$next = $keys[$next_index];
						//echo '<a href="'.get_permalink($next_key).'" class="button button-primary">'.get_the_title($next_key).'&nbsp;&raquo;</a>';
					}
				} else {
					$next = $course_id;
					//last step in course
					//echo '<a href="'.get_permalink($course_id).'" class="button button-primary">'.get_the_title($course_id).'&nbsp;&raquo;</a>';
				}
				//for testing
				//flms_complete_lesson_button($post_id, $next);
				
				echo '<a href="'.get_permalink($next).'" class="button button-primary">'.get_the_title($next).'&nbsp;&raquo;</a>';
			} else {
				//lesson exam
				$parent_id = flms_get_exam_version_parent($post_id);
				if (array_key_exists($parent_id, $course_steps)) {
					$index = array_search($post_id, $course_steps[$parent_id]);
					//$key = array_search('green', $array);
					if($index !== false && $index > 0 ) {
						//previous step in lesson
						$prev = $course_steps[$parent_id][$index-1];
						echo '<a href="'.get_permalink($prev).'" class="button button-primary">&laquo;&nbsp;'.get_the_title($prev).'</a>';
					} else {
						//no previous step in lesson
						echo '<a href="'.get_permalink($parent_id).'" class="button button-primary">&laquo;&nbsp;'.get_the_title($parent_id).'</a>';
					}

					if(flms_is_step_complete($steps_completed, $post_id)) {
						flms_lesson_is_complete($post_id);
					}

					$next = 0;
					if($index !== false && $index < count($course_steps[$parent_id])-1) {
						//next step in lesson
						$next = $course_steps[$parent_id][$index+1];
						//echo '<a href="'.get_permalink($next).'" class="button button-primary">'.get_the_title($next).'&nbsp;&raquo;</a>';
					} else {
						$next_index = array_search($parent_id, $keys) + 1;
						if($next_index < count($course_steps)) {
							if (array_key_exists($keys[$next_index], $course_steps)) {
								$next = $keys[$next_index];
								//echo '<a href="'.get_permalink($next_key).'" class="button button-primary">'.get_the_title($next_key).'&nbsp;&raquo;</a>';
							}
						} else {
							//last step in course
							$next = $course_id;
							//echo '<a href="'.get_permalink($course_id).'" class="button button-primary">'.get_the_title($course_id).'&nbsp;&raquo;</a>';
						}
						/*$next_key = $keys[array_search($parent_id, $course_steps)+1];
						if (array_key_exists($next_key, $course_steps)) {
							echo '<a href="'.get_permalink($next_key).'" class="button button-primary">'.get_the_title($next_key).'&nbsp;&raquo;</a>';
						}*/
					}
					//flms_complete_lesson_button($post_id, $next);
					echo '<a href="'.get_permalink($next).'" class="button button-primary">'.get_the_title($next).'&nbsp;&raquo;</a>';
					
					
				}
			}
			
			break;
	}
	echo'</div>';
	
}

function flms_is_step_complete($user_progress, $post_id) {
	if(!is_array($user_progress)) {
		return false;
	}
	if(in_array($post_id, $user_progress)) {
		return true;
	} else {
		return false;
	}
}

function flms_complete_lesson_button($post_id, $next, $echo = true) {
	global $flms_user_activity, $flms_user_has_access;
	if(flms_is_step_complete($flms_user_activity, $post_id)) {
		$return = flms_lesson_is_complete($post_id);
		if($next > 0) {
			$return .= '<a href="'.get_permalink($next).'" class="button button-primary">'.get_the_title($next).'&nbsp;&raquo;</a>';
		}
	} else {
		//see if there is a video
		global $flms_active_version;
		$current_content = get_post_meta($post_id,'flms_version_content',true);	
		$video_settings = flms_get_video_settings_default_fields();
		if(isset($current_content["$flms_active_version"]['video_settings'])) {
			$video_settings = $current_content["$flms_active_version"]['video_settings'];
		}
		$enabled = 0;
		if(isset($video_settings['force_full_video'])) {
			$enabled = $video_settings['force_full_video'];
		}
		$disabled = '';
		$tooltip = '';
		if($enabled == 1) {
			$disabled = 'disabled="disabled"';
			$tooltip = 'data-flms-tooltip="Complete the video to continue"';
		}
		
		$return = '<button id="flms-complete-step" data-step="'.$post_id.'" data-redirect="'.$next.'" '.$disabled.' '.$tooltip.'>';
			$return .= 'Complete &amp; continue';
		$return .= '</button>'; 
	}
	$return = apply_filters('flms_complete_continue_button', $return, $post_id, $next);
	if($echo) {
		echo $return;
	} else {
		return $return;
	}
}
function flms_lesson_is_complete($post_id, $echo = true) {
	global $flms_active_version;
	$post_type = get_post_type($post_id);
	if($post_type == 'flms-exams') {
		$exam_settings = get_post_meta($post_id, "flms_exam_settings_$flms_active_version", true);
		$label = $exam_settings['exam_label_override'];
	} else {
		$label = flms_get_post_type_label($post_type);
	}
	$label .= ' Complete';
	$label = apply_filters('flms_course_item_complete', $label, $post_id, $flms_active_version);
	$return = '<div class="flms-completed-step button button-primary">'.flms_step_complete_checkbox($post_id).$label.'</div>';
	if($echo) {
		echo $return;
	} else {
		return $return;
	}
}

function custom_wpkses_post_tags( $tags, $context ) {

	if ( 'flms-video' === $context ) {
		$tags['iframe'] = array(
			'src'             => true,
			'height'          => true,
			'width'           => true,
			'frameborder'     => true,
			'allowfullscreen' => true,
		);
		if(isset($tags['a'])) {
			unset($tags['a']);
		}
		/*$tags['div'] = array(
			'style' => true,
            'class' => true
		);*/
	}

	return $tags;
}

add_filter( 'wp_kses_allowed_html', 'custom_wpkses_post_tags', 10, 2 );

function strposa(string $haystack, array $needles, int $offset = 0): bool {
    foreach($needles as $needle) {
        if(strpos($haystack, $needle, $offset) !== false) {
            return true; // stop on first true result
        }
    }

    return false;
}

function flms_get_import_export_columns($type) {
	switch ($type) {
		case 'courses':
			$fields = array(
				'ID',
				'Title',
				'Status',
				'Version',
				'Version Name',
				'Version Permalink',
				'Version Status',
				'Post Content',
				'Course Preview',
				'Course Access',
				'Course Progression',
				'Lessons',
				'Exams'
			);
			if(flms_is_module_active('course_numbers')) {
				$fields[] = 'Course Number';
			}
			if(flms_is_module_active('course_credits')) {
				$course_credits = new FLMS_Module_Course_Credits();
				$credit_fields = $course_credits->get_course_credit_fields();
				foreach($credit_fields as $credit_field) {
					$label = $course_credits->get_credit_label($credit_field);
					$fields[] = "$label Credits";
					if(flms_is_module_active('course_numbers')) {
						$fields[] = "$label Course Number";
					}
				}
			}
			if(flms_is_module_active('course_taxonomies')) {
				$course_taxonomies = new FLMS_Module_Course_Taxonomies();
				$tax_fields = $course_taxonomies->get_course_taxonomies_fields(true);
				foreach($tax_fields as $credit_field) {
					$fields[] = $credit_field['label'];
				}
			}
			if(flms_is_module_active('course_materials')) {
				$fields[] = 'Course Materials';
			}
			if(flms_is_module_active('woocommerce')) {
				$fields[] = 'Product Type';
				$fields[] = 'Attributes';
				$fields[] = 'Price(s)';
			}
			break;
		case 'lessons': 
			$fields = array(
				'ID',
				'Title',
				'Course Name',
				'Version',
				'Lesson Order',
				'Sample Lesson',
				'Post Content',
				'Status',
				'Video URL',
				'Aspect Ratio',
				'Video Controls',
				'Full watch required',
				'Autocomplete lesson',
				'Topics',
				'Exams',
			);
			break;
		case 'topics':
			$fields = array(
				'ID',
				'Title',
				'Lesson Name',
				'Version',
				'Topic Order',
				'Post Content',
				'Status',
			);
			break;
		case 'exams': 
			$fields = array(
				'ID',
				'Title',
				'Course Name',
				'Associated Content',
				'Associated Content Post Type',
				'Version',
				'Exam Order',
				'Post Content',
				'Status',
				'Exam Type',
				'Question Select Type',
				'Exam Questions',
				'Exam Question Categories',
				'Questions to Draw',
				'Cumulative Exam Settings',
				'Questions Order',
				'Exam Attempts',
				'Question Per Page',
				'Save/Continue Enabled',
				'Exam Review Enabled',
				'Exam is Graded',
				'Exam is Graded Using',
				'Pass Percentage',
				'Pass Points',
				'Exam Attempts Action',
				'Exam Label',
				'Start Exam Label',
				'Resume Exam Label',
			);
			break;
		case 'questions': 
			$fields = array(
				'ID',
				'Title',
				'Category',
				'Type',
				'Post Content',
				'Status',
				'Options',
				'Answer',
			);
			break;
		case 'user-data': 
			$fields = array(
				'ID',
				'Username',
				'Display Name',
				'First Name',
				'Last Name',
				'Email',
				'Active Courses',
				'Completed Courses'
			);
			if(flms_is_module_active('course_credits')) {
				$course_credits = new FLMS_Module_Course_Credits();
				$credit_fields = $course_credits->get_course_credit_fields();
				foreach($credit_fields as $credit_field) {
					$label = $course_credits->get_credit_label($credit_field);
					//$credit_name = preg_replace('/[^\w-]/', '', trim(html_entity_decode($label)));
					$credit_name = strip_tags(flms_get_label($credit_field));
					$fields[] = $credit_name;
				}
			}
			if(flms_is_module_active('woocommerce')) {
				$billing_address_fields = WC()->countries->get_address_fields('','billing_');
				foreach($billing_address_fields as $index => $field) {
					if($index == 'billing_address_2') {
						$label = 'Address line 2';
					} else {
						$label = $field['label'];
					}
					$fields[] = "Billing $label";
				}
				$shipping_address_fields = WC()->countries->get_address_fields('','shipping_');
				foreach($shipping_address_fields as $index => $field) {
					if($index == 'shipping_address_2') {
						$label = 'Address line 2';
					} else {
						$label = $field['label'];
					}
					$fields[] = "Shipping $label";
				}
			}
			break;
		default:
			$fields = array();
			break;	
	}
	return apply_filters('flms_import_export_fields', $fields, $type);
}

function flms_get_video_settings_default_fields() {
	return array(
		'video_url',
		'video_ratio',
		'controls',
		'force_full_video',
		'autocomplete'
	);
}
/** Show column in excel format, thanks Stack Overflow, https://stackoverflow.com/questions/3302857/algorithm-to-get-the-excel-like-column-name-of-a-number */
function flms_num2alpha($n) {
    for($r = ""; $n >= 0; $n = intval($n / 26) - 1)
        $r = chr($n%26 + 0x41) . $r;
    return $r;
}

/** Detect csv deliminator, thanks Stack Overflow, https://stackoverflow.com/questions/26717462/php-best-approach-to-detect-csv-delimiter */
function flms_detect_csv_elimiter(string $pathToCSVFile): ?string {
    $delimiters = array(
		"\t" => 0,
        ";" => 0,
        "," => 0,
        "|" => 0,
		"^" => 0,
	);

    $handle = fopen($pathToCSVFile, 'r');
    $firstLine = fgets($handle);
    fclose($handle);

    foreach ($delimiters as $delimiterCharacter => $delimiterCount) {
        $foundColumnsWithThisDelimiter = count(str_getcsv($firstLine, $delimiterCharacter));
        if ($foundColumnsWithThisDelimiter > 1) {
            $delimiters[$delimiterCharacter] = $foundColumnsWithThisDelimiter;
        } else {
            unset($delimiters[$delimiterCharacter]);
        }
    }

    if (!empty($delimiters)) {
        return array_search(max($delimiters), $delimiters);
    } else {
        return false;
    }
}

function flms_local_attachment_url($attachment_id) {
	$url = wp_get_attachment_url($attachment_id);
	$path = str_replace(trailingslashit(get_bloginfo('url')), ABSPATH, $url);  
	return $path;
}

function flms_get_post_id($id, $post_type) {
	if(is_numeric($id)) {
		return $id;
	}
	$posts = get_posts(array('post_type' => $post_type, 'title' => $id));
	if(!empty($posts)) {
		foreach($posts as $flms_post) {
			return $flms_post->ID;
		}
	}
	return false;
}

function flms_get_question_category_id($category) {
	$taxonomy = 'flms-question-categories';
	if(is_numeric($category)) {
		$cat  = get_term_by('term_id', $category, $taxonomy);
	} else {
		$cat  = get_term_by('name', $category, $taxonomy);
	}
	if($cat == false){
		//cateogry not exist create it 
		$cat = wp_insert_term($category, $taxonomy);
		if(!is_wp_error($cat)) {
			$cat_id = $cat['term_id'] ;
		} else {
			$cat_id = false;
		}
	} else{ 
		//category already exists, get ID
		$cat_id = $cat->term_id ;
	}
	return $cat_id;
}

function flms_get_cpt_id($cpt_name_or_id, $post_type) {
	if(is_numeric($cpt_name_or_id)) {
		return $cpt_name_or_id;
	}
	$posts = get_posts(array('post_type' => $post_type, 'title' => $cpt_name_or_id));
	if(!empty($posts)) {
		foreach($posts as $flms_post) {
			return $flms_post->ID;
		}
	} else {
		return false;
	}
}

function flms_get_taxonomy_id($tax_name_or_id, $taxonomy) {
	if(is_numeric($tax_name_or_id)) {
		return $tax_name_or_id;
	} else {
		$cat = get_term_by('name', $tax_name_or_id, $taxonomy);
	}
	if($cat == false){
		//cateogry not exist create it 
		$cat = wp_insert_term($tax_name_or_id, $taxonomy);
		$cat_id = $cat['term_id'] ;
	} else{ 
		//category already exists, get ID
		$cat_id = $cat->term_id ;
	}
	return $cat_id;
}

function flms_debug($data, $subject = 'Debug data') {
	if(is_array($data) || is_object($data)) {
		$data = print_r($data, true);
	}
	wp_mail('greggoryhogan@gmail.com',$subject,'Data:<br>'.$data);
}

function flms_get_current_course_version() {
	global $wp, $flms_latest_version, $flms_course_id;
	if(isset($wp->query_vars['course-version'])) {
		if($wp->query_vars['course-version'] != '') {
			$version = $wp->query_vars['course-version'];
			$flms_active_version = get_version_index_from_slug($flms_course_id, $version);
		} else {
			$flms_active_version = $flms_latest_version;	
		}
		
	} else {
		$flms_active_version = $flms_latest_version;
	}
	return $flms_active_version;
}

function flms_login_link($text = 'Log in', $redirect = '') {
	if(flms_is_module_active('woocommerce')) {
		$my_account_page = get_option('woocommerce_myaccount_page_id');
		$permalink = get_permalink( $my_account_page );
		if($redirect != '') {
			$permalink .= '?flms-login-redirect='.$redirect;
		}
	} else {
		$permalink = wp_login_url($redirect);
	}
	$login_atts = apply_filters('flms_login_link', array(
		'permalink' => $permalink,
		'class' => 'button button-primary flms-login-link',
		'text' => $text
	));
	return sprintf('<a href="%s" title="Log in" class="%s">%s</a>', $login_atts['permalink'], $login_atts['class'], $login_atts['text']);
}

function flms_user_completed_course($course_id, $course_version, $user_id = 0) {
	global $wpdb;
	if($user_id == 0) {
		global $current_user;
		$user_id = $current_user->ID;
	}
	global $flms_user_has_access;
	if($flms_user_has_access) {
		//return false;
	}
	$table = FLMS_ACTIVITY_TABLE;
	$sql_query = $wpdb->prepare("SELECT customer_status FROM $table WHERE course_id=%d AND course_version=%d AND customer_id=%d ORDER BY id DESC LIMIT 1", $course_id, $course_version, $user_id);
	$results = $wpdb->get_results( $sql_query, ARRAY_A ); 
	if(!empty($results)) {
		foreach($results as $result) {
			if($result['customer_status'] == 'completed') {
				return true;
			}
		}
	}
	return false;
}

function flms_get_plugin_version() {
	if( ! function_exists( 'get_plugin_data' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    }
    $plugin_data = get_plugin_data( FLMS_PLUGIN_FILE );
	if(isset($plugin_data['Version'])) {
		return $plugin_data['Version'];
	} else {
		return get_bloginfo( 'version' );
	}

}

/**
 * Helper, I can't helpt but mistype this function
 */
if(!function_exists('pritn_r')) {
	function pritn_r($data, $echo = true) {
		print_r($data, $echo);
	}
}

function flms_get_user_activity($user_id = 0, $course_id = 0, $course_version = 0) {
	$user_progress = new FLMS_Course_Progress();
	return $user_progress->get_user_activity($user_id, $course_id, $course_version);
}

function flms_get_user_course_status($user_id, $course_id, $course_version = 1) {
	$user_progress = new FLMS_Course_Progress();
	return $user_progress->get_user_course_status($user_id, $course_id, $course_version);
}

function flms_update_user_activity($post_id, $user_id = 0, $course_id = 0, $version = 0) {
	if($user_id == 0) {
		global $current_user;
		$user_id = $current_user->ID;
	}
	if($user_id == 0) {
		//no one is logged in
		return false;
	}
	if($course_id == 0) {
		global $flms_course_id;
		$course_id = $flms_course_id;
	}
	if($version == 0) {
		global $flms_active_version;
		$version = flms_get_current_course_version();
	}
	$user_progress = new FLMS_Course_Progress();
	return $user_progress->update_user_activity($post_id, $user_id, $course_id, $version);
}

function flms_generate_group_code($post_id, $length = 10) {
	$valid_group_code = false;
	while(!$valid_group_code) {
		$characters = '23456789abcdefghjklmnopqrstuvwxyzABCDEFGHIJKLMNPQRSTUVWXYZ'; //no 0/o, 1/i
		$group_code = '';
		for ($i = 0; $i < $length; $i++) {
			$index = rand(0, strlen($characters) - 1);
			$group_code .= $characters[$index];
		}
		$group_code = strtoupper($group_code);
		$valid_group_code = flms_is_group_code_valid($post_id, $group_code);
	}
	return $group_code;
}

function flms_is_group_code_valid($post_id, $group_code) {
	$sanitized = sanitize_text_field($group_code);
	if($group_code == '' || $group_code != $sanitized) {
		return false;
	}
	$args = array(
		'post_type' => 'flms-groups',
		'fields' => 'ids',
		'meta_query' => array(
			array(
				'key'     => 'flms_group_code',
				'value'   => $sanitized,
			),
		),
	);
	if($post_id > 0) {
		$args['post__not_in'] = array($post_id);
	}
	$group_codes = get_posts( $args );
	if(empty($group_codes)) {
		return true;
	}
	return false;
}

function flms_get_exam_questions($exam_id, $exam_version, $get_all_questions = false) {
	$exam_questions = array();
	$exam_settings = get_post_meta($exam_id, "flms_exam_settings_$exam_version", true);
	if(isset($exam_settings["exam_type"])) {
		$exam_type = $exam_settings["exam_type"];
		if($exam_type == 'cumulative') {
			//get selected questions from exams
			if(isset($exam_settings['cumulative_exam_questions'])) {
				$exam_question_settings = $exam_settings['cumulative_exam_questions'];
				foreach($exam_question_settings as $cumulative_exam_id => $question_to_draw) {
					$questions_to_add = flms_get_exam_questions($cumulative_exam_id, $exam_version, true);
					if($questions_to_add == -1) {
						$exam_questions = array_merge($exam_questions, $questions_to_add);
					} else if($question_to_draw > 0) {
						$new_exam_questions = array();
						$questions_drawn = array();
						$i = 0;
						while($i < $question_to_draw) {
							$index = array_rand($questions_to_add, 1);
							if(!in_array($index, $questions_drawn)) {
								$questions_drawn[] = $index;
								$new_exam_questions[] = $questions_to_add[$index];
								$i++;
							}
						}
						$exam_questions = array_merge($exam_questions, $new_exam_questions);
					} else {
						//skip, they set it as zero
					}
				}
				if(isset($exam_settings['question_order'])) {
					$order = $exam_settings['question_order'];
					if($order == 'random') {
						shuffle($exam_questions);
					}
				}
			} 
		} else if($exam_type == 'category-sample-draw') {
			if(isset($exam_settings["exam_question_categories"])) {
				$categories = $exam_settings["exam_question_categories"];
				$question_to_draw = 0;
				if(isset($exam_settings['exam_question_categories_numbers'])) {
					$question_to_draw_array = $exam_settings['exam_question_categories_numbers'];
				}
				//print_r($categories);
				foreach($categories as $category_id) {
					$exam_question_bank = get_posts(
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
									'terms' => array($category_id),
									'operator' => 'IN',
								)
							)
						)
					);
					if(!empty($exam_question_bank)) {
						if(isset($question_to_draw_array[$category_id])) {
							$question_to_draw = absint($question_to_draw_array[$category_id]);
						}
						$new_exam_questions = array();
						$questions_drawn = array();
						$i = 0;
						while($i < $question_to_draw) {
							$index = array_rand($exam_question_bank, 1);
							if(!in_array($index, $questions_drawn)) {
								$questions_drawn[] = $index;
								$new_exam_questions[] = $exam_question_bank[$index];
								$i++;
							}
						}
						$exam_questions = array_merge($exam_questions,$new_exam_questions);
					}
				}
				
				
			} else {
				$exam_questions = array();
			}
			if(!empty($exam_questions)) {
				if(isset($exam_settings['question_order'])) {
					$order = $exam_settings['question_order'];
					if($order == 'random') {
						shuffle($exam_questions);
					}
				}
			}
		} else {
			if(isset($exam_settings['question_select_type'])) {
				$question_select_type = $exam_settings['question_select_type'];
				if($question_select_type == 'manual') {
					//standard exam
					if(isset($exam_settings["exam_questions"])) {
						$exam_questions = $exam_settings["exam_questions"];
					} else {
						$exam_questions = array();
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
						
					} else {
						$exam_questions = array();
					}
				}
			}
			if($exam_type == 'sample-draw' && !empty($exam_questions) && !$get_all_questions) {
				$question_to_draw = $exam_settings['questions_per_page'];
				if(isset($exam_settings['sample-draw-question-count'])) {
					$question_to_draw = $exam_settings['sample-draw-question-count'];
				}
				$new_exam_questions = array();
				$questions_drawn = array();
				$i = 0;
				while($i < $question_to_draw) {
					$index = array_rand($exam_questions, 1);
					if(!in_array($index, $questions_drawn)) {
						$questions_drawn[] = $index;
						$new_exam_questions[] = $exam_questions[$index];
						$i++;
					}
				}
				$exam_questions = $new_exam_questions;
			}

			if(isset($exam_settings['question_order'])) {
				$order = $exam_settings['question_order'];
				if($order == 'random') {
					shuffle($exam_questions);
				}
			}
		}
	} else {
		$exam_questions = array();
	}
	return $exam_questions;
}

function flms_maybe_insert_course_material($filename) {
	require_once(ABSPATH . "wp-admin" . '/includes/image.php');
    require_once(ABSPATH . "wp-admin" . '/includes/file.php');
    require_once(ABSPATH . "wp-admin" . '/includes/media.php');

	// Get upload dir
	$attachment_id = attachment_url_to_postid($filename);
	if($attachment_id > 0) {
		return $filename;
	}

	$tmp = download_url( $filename );
	if( is_wp_error( $tmp ) ) {
		//flms_debug($tmp->get_error_message(), 'Error downloading external course material');
		return $filename;
	}

	// Set variables for storage
	$file_array = array(
		'name' => basename($filename),
		'tmp_name' => $tmp,
	);

	// do the validation and storage stuff
	$attach_id = media_handle_sideload( $file_array);

	@unlink( $tmp_file );

	// If error storing permanently, unlink
	if ( is_wp_error($attach_id) ) {
		//flms_debug($attach_id->get_error_message(), 'Error storing external course material');
		return $filename;
	}
	
	return wp_get_attachment_url($attach_id);
}

function flms_my_courses_output($course_id, $layout = 'list') {
	//get query data
	global $wpdb, $flms_settings;
	$output = '<div class="flms-course flms-course-output layout-'.$layout.'">';
		//$output .= '<a href="'.get_permalink($course_id).'" class="flms-block-link"></a>';
		$img_width = '100%';
		$show_image = apply_filters("flms_show_{$layout}_featured_image", true);
		if($show_image) {
			if($layout == 'list') {
				if(has_post_thumbnail( $course_id )) {
					$img_width = apply_filters('flms_list_layout_image_width', '150px');
					$output .= '<div class="course-image" style="width: '.$img_width.'">';
						$output .= '<a href="'.get_permalink($course_id).'" class="flms-course-title">'.get_the_post_thumbnail($course_id).'</a>';
					$output .= '</div>';
				}
			} else {
				$output .= '<div class="course-image">';
					$output .= '<a href="'.get_permalink($course_id).'" class="flms-course-title">'.get_the_post_thumbnail($course_id).'</a>';
				$output .= '</div>';
			}
			
			
		}
		//}
		$output .= '<div class="course-info">';	
			$output .= '<a href="'.get_permalink($course_id).'" class="flms-course-title">'.apply_filters( 'flms_page_title',get_the_title($course_id)).'</a>';
			//$output .= '<div class="flms-course-title">'.get_the_title($course_id).'</div>';
			//$output .= print_r($mapped,true);
			if(flms_is_module_active('course_credits')) {
				$table = FLMS_COURSE_QUERY_TABLE;
				$sql_query = $wpdb->prepare("SELECT meta_key, meta_value FROM $table WHERE course_id=%d", $course_id);
				$results = $wpdb->get_results( $sql_query, ARRAY_A ); 
				$mapped = array();
				if(!empty($results)) {
					foreach($results as $result) {
						$mapped[$result['meta_key']] = $result['meta_value'];
					}
				}
				$course_credits = new FLMS_Module_Course_Credits();
				$credits_array = $course_credits->get_course_credits_fields(true,true);
				//$output .=  '<pre>'.print_r($credits_array,true).'</pre>';
				$credits_output = array();
				if(!empty($credits_array)) {
					foreach($credits_array as $credit) {
						$parent = 'none';
						if(isset($flms_settings['course_credits'][$credit['key']]['parent'])) {
							$parent = $flms_settings['course_credits'][$credit['key']]['parent'];
						}
						$key = $credit['key'];
						$label = flms_get_label($key);
						if(isset($mapped[$key])) {
							if($parent == 'none') {
								$credits_output[] = "$label: $mapped[$key]";
							} else {
								$credits_output[] = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$label: $mapped[$key]";
							}
						}
					}
				}
				if(!empty($credits_output)) {
					$label = 'Course Credits';
					if(isset($flms_settings['labels']['credits'])) {
						$label = $flms_settings['labels']['credits'];
					}
					$output .= '<div class="credit-summary"><div class="flms-label">'.$label.':</div><div>'.implode('<br>',$credits_output).'</div></div>';
				}
			}
		$output .= '</div>';
		$output .= '<div class="product-data">';
			if(flms_is_module_active('woocommerce')) {
				$product_id = get_post_meta( $course_id, 'flms_woocommerce_product_id', true );
				if($product_id != '') {
					$sale = false;
					//echo $product_id.'<br>';
					$product = wc_get_product($product_id);
					if($product->is_on_sale() ) {
						$sale = apply_filters( 'woocommerce_sale_flash', '<span class="onsale">' . esc_html__( 'Sale!', 'woocommerce' ) . '</span>', $course_id, $product );
					}
					$type = $product->get_type();
					if($type == 'simple') {
						$output .= $product->get_price_html();
					} else {
						$min_price = $product->get_variation_regular_price( 'min' );
						$min_sale_price = $product->get_variation_sale_price('min');
        				$max_price = $product->get_variation_price( 'max' );
						//$output .= '<div>'.wc_format_price_range( $min_price, $max_price ).'</div>';
						$output .= '<div>'.$sale.$product->get_price_html().'</div>';
						/*$variations = $product->get_children();
						$course_credits_array = array();
						$logged_variations = array();
						foreach($variations as $variation_id) {
							$variation = wc_get_product($variation_id);
							if ($variation->get_status( 'edit' ) == 'publish') {
								//$name = $variation->get_name();
								//$price = $variation->get_price();
								//$output .= $name .' '.$price.'<br>';
								$product_attributes = wc_get_formatted_variation( $variation, true, false, false );
								if(!in_array($product_attributes, $logged_variations)) {
									$logged_variations[] = $product_attributes;
									$output .= '<p class="variation_price">'. $product_attributes . ": ". wc_price( $variation->get_price() ).'</p>';
								}
								
								
							}
							
						}*/
					}
				} else {
					$output .= 'Open Enrollment';
				}
			}
			$course_label = 'View Course';
			if(isset($flms_settings['labels']['course_singular'])) {
				$course_label = 'View '.$flms_settings['labels']['course_singular'];
			}
			$output .= '<a href="'.get_permalink($course_id).'" class="button button-primary">'.$course_label.'</a>';
		$output .= '</div>';
		/*$output .= '<div class="actions">';
			$course_label = 'View Course';
			if(isset($flms_settings['labels']['course_singular'])) {
				$course_label = 'View '.$flms_settings['labels']['course_singular'];
			}
			$output .= '<a href="'.get_permalink($course_id).'" class="button button-primary">'.$course_label.'</a>';
		$output .= '</div>';*/
	$output .= '</div>';
	return $output;
}

function flms_course_filters($filters = array()) {
	global $flms_settings, $wpdb;
	$button_class = '';
	$button_text = apply_filters('flms_toggle_filters_text','Show available filters');
	$button_toggle_text = apply_filters('flms_toggled_filters_text','Hide filters');
	$force_height = '';
	if(isset($_GET['course-term'])) {
		$button_class = 'flms-is-active';
		$button_toggle_text = apply_filters('flms_toggle_filters_text','Show available filters');
		$button_text = apply_filters('flms_toggled_filters_text','Hide filters');
		$force_height = 'flms-auto-height';
	}
	$return = '<button class="button button-primary flms-course-filter-toggle '.$button_class.'" id="flms-toggle-course-filters" data-toggle-text="'.$button_toggle_text.'"><span class="text">'.$button_text.'</span><span class="chevron"></span></button>';
	$return .= '<form id="flms-course-filters" class="flms-course-filters flms-flex no-wrap flex-column align-left '.$force_height.'" action="'.get_bloginfo('url').'/courses">';
		$return .= '<div class="flms-filter-section full-flex">';
			
			$return .= '<div class="flms-filter-options flms-flex flex-column full-flex">';
				$return .= '<div class="flms-label flex-1">Key words</div>';
				$course_term = '';
				if(isset($filters['course-term'])) {
					$course_term = stripslashes(sanitize_text_field($filters['course-term']));
				}
				$return .= '<input type="text" name="course-term" value="'.$course_term.'" class="flex-1" />';
			$return .= '</div>';
		$return .= '</div>';
		$show_course_credit_filters = apply_filters('flms_course_credits_filters', true);
		if(flms_is_module_active('course_credits') && $show_course_credit_filters) {
			$course_credits = new FLMS_Module_Course_Credits();
			$credits_array = $course_credits->get_course_credits_fields(true,true);
			$credits_output = array();
			if(!empty($credits_array)) {
				$return .= '<div class="flms-flex full-flex course-credits-filters">';
					$return .= '<div class="flms-filter-section flex-1 flms-flex flex-column">';
						$label = 'Licenses';
						if(isset($flms_settings['labels']['license_plural'])) {
							$label = $flms_settings['labels']['license_plural'];
						}
						$return .= '<div class="flms-label flex-1">'.$label.'</div>';
						$return .= '<div class="flms-filter-options flms-flex flex-1">';
						$credit_keys = array();
						$credit_filters = array();
						if(isset($filters['credit_type'])) {
							$credit_filters = $filters['credit_type'];
						}
						foreach($credits_array as $credit) {
							if(apply_filters('flms_show_license_as_filter', true, $credit['key'])) {
								$key = $credit['key'];
								$credit_keys[] = $key;
								$label = $credit['label'];
								if(isset($flms_settings['labels'][$credit['key']])) {
									$label = $flms_settings['labels'][$credit['key']];
								}
								$checked = '';
								if(in_array($key, $credit_filters)) {
									$checked = 'checked="checked"';
								}
								$return .= '<div><label><input type="checkbox" name="credit_type[]" value="'.$key.'" '.$checked.' />'.$label.'</label></div>';
							}
						}
						$return .= '</div>';
					$return .= '</div>';
				

					$table = FLMS_COURSE_QUERY_TABLE;
					$sql_query = $wpdb->prepare("SELECT meta_value FROM $table WHERE meta_key=%s or meta_key=%s", 'min_credits', 'max_credits'); // IN (" . implode(',', $credit_keys) . ")"
					$results = $wpdb->get_col( $sql_query ); 
					if(!empty($results)) {
						$min = trim(min($results));
						$max = trim(max($results));
						$min_value = $min;
						if(isset($filters['min_credits'])) {
							$min_value = absint($filters['min_credits']);
						}
						$max_value = $max;
						if(isset($filters['max_credits'])) {
							$max_value = absint($filters['max_credits']);
						}
						$label = 'Credits';
						if(isset($flms_settings['labels']['credits'])) {
							$label = $flms_settings['labels']['credits'];
						}

						$return .= '<input type="hidden" id="min_credits" name="min_credits" value="'.$min_value.'" data-reset="'.$min.'" />';
						$return .= '<input type="hidden" id="max_credits" name="max_credits" value="'.$max_value.'" data-reset="'.$max.'" />';

						$return .= '<div class="flms-filter-section flex-1 flms-flex flex-column">';
							$return .= '<div class="flms-label flex-1">'.$label.'</div>';
							$return .= '<div class="flms-filter-options flms-flex flex-1">';

								//desktop slider
								$return .= '<div class="desktop-credit-select flms-double-slider">';
									$return .= '<div class="values"><span id="range1">'.$min.'</span><span> &dash; </span><span id="range2">'.$max.'</span></div>';
									$return .= '<div class="flms-slider-track">';
										$return .= '<input type="range" min="'.$min.'" max="'.$max.'" value="'.$min_value.'" id="min-credits-slider">';
										$return .= '<input type="range" min="'.$min.'" max="'.$max.'" value="'.$max_value.'" id="max-credits-slider">';
									$return .= '</div>';
								$return .= '</div>';
								//mobile select
								$return .= '<div class="mobile-credit-select">';
									$return .= '<div class="flms-flex mobile-flex">';
										
										$return .= '<select id="min_credits_select">';
											for($i = $min; $i <= $max; $i++) {
												$return .= '<option value="'.$i.'"';
												if($i == $min_value) {
													$return .= ' selected';
												}
												$return .= '>'.$i.'</option>';
											}
										$return .= '</select>';
										$return .= '<div>-</div>';
										
										$return .= '<select id="max_credits_select">';
											for($i = $min; $i <= $max; $i++) {
												$return .= '<option value="'.$i.'"';
												if($i == $max_value) {
													$return .= ' selected';
												}
												$return .= '>'.$i.'</option>';
											}
										$return .= '</select>';
										
									$return .= '</div>';
								$return .= '</div>';
							$return .= '</div>';

						$return .= '</div>';
					}
				$return .= '</div>';
			}

			/*$return .= '<div class="flms-flex full-flex course-credits-filters">';
				$return .= '<div class="flms-filter-section flex-1">';
					$return .= '<div class="flms-label">Order By</div>';
					$return .= '<div class="flms-filter-options flms-flex">';
						$orderby = array(
							'menu_order' => 'Default',
							'title' => 'Title',
							'date' => 'Recently Updated'
						);
						$return .= '<select id="max_credits_select">';
							for($i = $min; $i <= $max; $i++) {
								$return .= '<option value="'.$i.'"';
								if($i == $max_value) {
									$return .= ' selected';
								}
								$return .= '>'.$i.'</option>';
							}
						$return .= '</select>';
					$return .= '</div>';
				$return .= '</div>';*/
			
			
		}
		if(flms_is_module_active('course_taxonomies')) {
			//$course_taxonomies = new FLMS_Module_Course_Taxonomies();
			if(isset($flms_settings['course_taxonomies'])) {
				$return .= '<div class="flms-flex full-flex course-credits-filters">';
					foreach($flms_settings['course_taxonomies'] as $taxonomy_name => $options) {
						if($options['filter-status'] == 'show') {
							$terms = get_terms( array( 
								'taxonomy' => $taxonomy_name,
							) );
							if(!empty($terms)) {
								$tax_filter = '';
								if(isset($_GET[$taxonomy_name])) {
									$tax_filter = $_GET[$taxonomy_name];
								}
								$return .= '<div class="flms-filter-section flex-1 flms-flex flex-column">';
									$return .= '<div class="flms-label flex-1">'.$options['name-singular'].'</div>';
									$return .= '<div class="flms-filter-options flms-flex flex-1">';
								
										$return .= '<select name="'.$taxonomy_name.'" class="select2" style="width: 100%">';
											$return .= '<option value="0">All</option>';
											if($options['hierarchal'] == 'true') {
												foreach($terms as $term) {
													$checked = '';
													if($term->term_id == $tax_filter) {
														$checked = ' selected="selected"';
													}
													$return .= '<option value="'.$term->term_id.'" '.$checked.'>'.$term->name.'</option>';
													if( $term->parent == 0 ) {
														$sub = '';
														foreach( $terms as $subcategory ) {
															if($subcategory->parent == $term->term_id) {
																$sub .= '<option value="'. esc_attr( $subcategory->term_id ) .'" '.$checked.'>
																'. esc_html( $subcategory->name ) .'</option>';
															}
														}
														if($sub != '') {
															$return .= '<optgroup>';
																$return .= $sub;
															$return .= '</optgroup>';
														}
														
													} 
												}
											} else {
												foreach($terms as $term) {
													$checked = '';
													if($term->term_id == $tax_filter) {
														$checked = ' selected="selected"';
													}
													$return .= '<option value="'.$term->term_id.'" '.$checked.'>'.$term->name.'</option>';
												}
											}
										$return .= '</select>';
									$return .= '</div>';
								$return .= '</div>';
							}
								
						}
					}
				$return .= '</div>';
			}

		}

		$return .= apply_filters('after_flms_course_filters', '');

		$return .= '<div class="flms-flex">';
			$return .= '<input type="submit" class="button button-primary" value="'.apply_filters('flms_course_search_submit_text','Filter').'" />';
			$return .= '<input type="reset" class="button button-secondary" value="'.apply_filters('flms_course_search_reset_text','Reset').'" />';
		$return .= '</div>';

		$return .= apply_filters('after_flms_course_filters_form', '');

	$return .= '</form>';
	return $return;
}

function flms_get_course_version_permalink($course_id, $course_version) {
	$course = new FLMS_Course($course_id);
	global $flms_active_version;
	$flms_active_version = $course_version;
	$link = $course->get_course_version_permalink($course_version);
	return $link;
}

function flms_user_can_access_group() {
	global $post;
	$current_user_id = get_current_user_id();
	$current_user = get_user_by('id', $current_user_id);
	$group_owner = get_post_meta($post->ID, 'flms_group_owner', true);
	$group_members = get_post_meta($post->ID, 'flms_group_member');
	$group_managers = get_post_meta($post->ID, 'flms_group_manager');
	$is_manager = false;
	if($current_user !== false) {
		if(in_array($current_user->user_email, $group_managers)) {
			$is_manager = true;
		}
	}
	if($current_user_id == $group_owner || in_array($current_user_id, $group_members) || $is_manager || current_user_can('administrator')) {
		return true;
	}
	return false;
}

function flms_notification($to, $subject, $message) {
	global $flms_settings;
	$headers = array('Content-Type: text/html; charset=UTF-8');
	$footer = '<p>Sincerely,<br>'.get_bloginfo('name').'</p>';
	if(isset($flms_settings['design']['email_footer'])) {
		$footer = nl2br(sanitize_textarea_field($flms_settings['design']['email_footer']));
	}
	$message .= '<p>'.$footer.'</p>';
	if ( class_exists( 'woocommerce' ) ) {
		if( ! class_exists( 'WC_Emails' ) ) { 
			include_once WC_ABSPATH . 'includes/class-wc-emails.php'; 
		}
		$mailer  = WC()->mailer();
		$message = $mailer->wrap_message( $subject, $message );
		$mailer->send( $to, $subject, $message, $headers );
	} else {
		wp_mail($to, $subject, $message, $headers);
	}
}

function flms_alert($message, $dismissable = false) {
	$return = '<p class="flms-alert flms-primary flms-secondary-bg flms-secondary-border flms-flex">';
	$return .= '<span class="alert-text">'.ucfirst($message).'</span>';
	if($dismissable) {
		$return .= '<span class="dismissable"></span>';
	}
	$return .= '</p>';
	return $return;
}

function flms_get_group_discount($cart_item) {
	global $flms_settings;
	//print_r($cart_item['data']);
	$price = $cart_item['data']->get_price();
	$seats = 1;
	if(isset($cart_item['group_seats'])) {
		$seats = absint($cart_item['group_seats']);
		if($seats == 0) {
			$seats = 1;
		}
	}
	$new_price = $seats * $price;
	$min_seats = isset( $flms_settings['woocommerce']['groups_discount_minimum_seats'] ) ? absint( $flms_settings['woocommerce']['groups_discount_minimum_seats'] ) : 1;
	$discount_amount = isset( $flms_settings['woocommerce']['groups_discount_default_amount'] ) ? absint( $flms_settings['woocommerce']['groups_discount_default_amount'] ) : 0;
	$discount_type = isset( $flms_settings['woocommerce']['groups_bulk_purchase_discount_type'] ) ? sanitize_text_field( $flms_settings['woocommerce']['groups_bulk_purchase_discount_type'] ) : 'percent';
	if($seats >= $min_seats) {
		switch($discount_type) {
			case 'percent':
				$new_price = $new_price * ((100 - $discount_amount) / 100);
				break;
			case 'fixed_per_seat':
				$new_price = $seats * ($price - $discount_amount);
				break;
			case 'fixed_from_total':
				$new_price = $new_price - $discount_amount;
				break;
		}
	}
	$cart_item['data']->set_price( $new_price );
	do_action('flms_group_purchase_price_adjustments');
	return $cart_item;
}

function flms_seconds_to_time($seconds) {
    if (!is_numeric($seconds))
        throw new Exception("Invalid Parameter Type!");


    $ret = "";

    $hours = (string )floor($seconds / 3600);
    $secs = (string )$seconds % 60;
    $mins = (string )floor(($seconds - ($hours * 3600)) / 60);

    /*if (strlen($hours) == 1)
        $hours = "0" . $hours;
    if (strlen($secs) == 1)
        $secs = "0" . $secs;
    if (strlen($mins) == 1)
        $mins = "0" . $mins;
	*/
	$text_string = '';
	$hour_text = '';
	if($hours > 0) {
		if($hours == 1) {
			$hour_text = "$hours hour";
		} else {
			$hour_text = "$hours hours";
		}
		$text_string .= $hour_text;
	}
	$minutes_text = '';
    if($mins > 0) {
		if($mins == 1) {
			$minutes_text = "$mins minute";
		} else {
			$minutes_text = "$mins minutes";
		}
		if($text_string != '') {
			if($secs > 0) {
				$text_string .= ', ';
			} else {
				$text_string .= ' and ';
			}
		}
		$text_string .= $minutes_text;
	}

	$secs_text = '';
    if($secs > 0) {
		if($secs == 1) {
			$secs_text = "$secs second";
		} else {
			$secs_text = "$secs seconds";
		}
		if($text_string != '') {
			$text_string .= ' and ';
		}
		$text_string .= $secs_text;
	}
	return $text_string;


    return $ret;
}

function flms_track_exam_time($user_id, $exam_identifier, $update_last_timestamp = 0) {
	//$elapsed_key= "flms_{$exam_identifier}_exam_elapsed_time";
	//delete_user_meta($user_id, $elapsed_key);
	//delete_user_meta($user_id, "flms_{$exam_identifier}_exam_last_active");
	//delete_user_meta($user_id, "flms_{$exam_identifier}_exam_time_remaining");

	$exam_data = explode(':',$exam_identifier);
	$exam_id = $exam_data[0];
	$course_version = $exam_data[1];
	$exam_settings = get_post_meta($exam_id, "flms_exam_settings_$course_version", true);
	//track time of user exam
	$time_limit = 0;
	if(isset($exam_settings['time_limit'])) {
		$time_limit = absint($exam_settings['time_limit']);
	}
	if($time_limit > 0) {
		$time = strtotime(current_time('mysql'));
		$last_active_meta_key = "flms_{$exam_identifier}_exam_last_active";
		$remaining_meta_key = "flms_{$exam_identifier}_exam_time_remaining";
		$elapsed_meta_key = "flms_{$exam_identifier}_exam_elapsed_time";
		$elapsed_time = get_user_meta($user_id, $elapsed_meta_key, true);
		if($elapsed_time == '') {
			$elapsed_time = 0;
		}
		//get time since last started
		$last_active = get_user_meta($user_id, $last_active_meta_key, true);
		//reset time since last activity
		if($last_active == '') {
			//user hasnt started the exam yet
			update_user_meta($user_id, $remaining_meta_key, $time_limit);
			//update_user_meta($user_id, $last_active_meta_key, $time);
			$last_active = $time;
		} 
		if($update_last_timestamp == 1) {
			//update last activity
			$tmins = 0;
		} else {
			$tmins = $time - $last_active;
		}
		$elapsed_time += $tmins;	
		$remaining_time = $time_limit - $elapsed_time;
		update_user_meta($user_id, $elapsed_meta_key, $elapsed_time);
		update_user_meta($user_id, $remaining_meta_key, $remaining_time);
		update_user_meta($user_id, $last_active_meta_key, strtotime(current_time('mysql')));
	}
}

function flms_get_exam_timer_html() {
	$timer = '<div id="exam-timer" class="flms_time_counter_display">';
		$timer .= '<div id="timer-days" class="inactive"><span class="days"></span><div class="smalltext">Days</div></div>';
		$timer .= '<div id="timer-hours" class="inactive"><span class="hours"></span><div class="smalltext">Hours</div></div>';
		$timer .= '<div id="timer-minutes" class="inactive"><span class="minutes"></span><div class="smalltext">Minutes</div></div>';
		$timer .= '<div><span class="seconds"></span><div class="smalltext">Seconds</div></div>';
		$timer .= '<div class="remaining">Remaining</div>';
	$timer .= '</div>';
	return $timer;
}

function get_flms_search_form($show_search_value = false, $placeholder = 'Search') {
	$target = apply_filters('flms_course_list_page', get_bloginfo('url'));
	$value = '';
	if(isset($_GET['course-term']) && $show_search_value) {
		$value = stripslashes(sanitize_text_field($_GET['course-term']));
	}
	return '<form method="get" action="'.$target.'"><input name="course-term" type="text" placeholder="'.$placeholder.'" value="'.$value.'" /></form>';
}

function get_flms_whitelabel_prefix() {
	if(flms_is_module_active('white_label')) {
		global $flms_settings;
		if(isset($flms_settings['white_label'])) {
			if(isset($flms_settings['white_label']['shortcode_prefix'])) {
				if($flms_settings['white_label']['shortcode_prefix'] != '') {
					$prefix = sanitize_title( $flms_settings['white_label']['shortcode_prefix'] );
					return $prefix;
				}
			}
		}

	}
	return '';
}