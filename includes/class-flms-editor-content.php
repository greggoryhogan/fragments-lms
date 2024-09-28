<?php
/**
 * Fragment LMS Editor content
 *
 * @package FLMS\Classes
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Editor content class
 */
class FLMS_Editor_Content {

	/**
	 * The Constructor.
	 */
	public function __construct() {
		add_action('enqueue_block_editor_assets', array($this, 'get_current_version_gutenberg_content'));
		add_action('admin_enqueue_scripts', array($this,'flms_admin_assets'));
		add_action('wp_after_insert_post',array($this,'maintain_versions'),10,4);
		add_filter('the_editor_content',array($this,'get_current_version_wysiwyg_content'),10,2);
		add_action( 'enqueue_block_editor_assets', array($this,'alerts_enqueue' ));
		add_action( 'add_meta_boxes', array($this,'flms_register_meta_boxes') );
	}

	/**
	 * Ajax to switch between versions in admin
	 */
	public function flms_admin_assets() {
		global $post;
		if($post == null) {
			return;
		}
		if(!flms_is_flms_post_type($post)) {
			return;
		}
		$course_id = flms_get_course_id($post->ID);
		$no_course = $this->get_no_course_modal($post);
		//Load js for saving version
		wp_enqueue_script(
			'flms-version-manager',
			FLMS_PLUGIN_URL . 'assets/js/version_manager.js',
			array('wp-blocks', 'wp-editor', 'wp-components','jquery'),
			false,
			true
		);
		wp_localize_script( 'flms-version-manager', 'version_manager', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'course_id' => $course_id,
			'no_course_id_notice' => $no_course
		));
	}

	public function get_no_course_modal($post) {
		if($post->post_type == 'flms-lessons') {
			$post_type = 'flms-courses';
		} else if($post->post_type == 'flms-topics') {
			$post_type = 'flms-lessons';
		} else {
			$post_type = 'any';
		}
		$label = strtolower(flms_get_post_type_label($post_type));
		$callback = '<div id="no-course-found"><div class="course-notice">Assign this content to a '.$label.' to continue.';
		$callback .= '<div class="flex">';
		$callback .= '<input type="text" id="associated-content-search" class="autocomplete associate-content" placeholder="Search for a';
		if($post_type == 'flms-exams') {
			$callback .= 'n'; //huh?
		}
		$callback .= ' '.strtolower($label).'" data-type="'.$post_type.'" data-post="'.$post->ID.'" />';	
		$callback .= '<button id="saveassociatedcontent" class="button button-primary">Save</button>';
		$callback .= '</div></div>';
		return $callback;
	}
	/**
	 * Determine whether to save current data as a version when editing
	 */
	public function maintain_versions($post_id,$post,$updated,$post_before) {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}
		if(!flms_is_flms_post_type($post)) {
			return;
		}
		$has_course = false;
		$course_id = flms_get_course_id($post->ID);
		if($course_id > 0 && $course_id != $post->ID) {
			$has_course = true;
		}

		//Ok, we're good to go
		if($has_course) {
			//Add some logic to only process this once every 10 seconds
			//For some reason gutenberg calls wp_after_insert_post 2-4 times during each save
		
			$continue = false;
			$now = time();
			$flms_last_updated = get_post_meta($post_id,'flms_last_updated',true);
			if($flms_last_updated == '') {
				$continue = true;
				update_post_meta($post_id,'flms_last_updated',time());
			} else {
				$diff = $now - $flms_last_updated;
				if($diff > 5) {
					$continue = true;
				} 
			}
			//Check if there is an associated course
			if($continue) {
			
				
				$course = new FLMS_Course($course_id);
				//Get active version, if it doesn't exist (a new post) then create it
				$active_version = $course->get_active_version();
				if($active_version == '') {
					$active_version = 1;
					update_post_meta($course_id,'flms_course_active_version',$active_version);
				}

				//Get all versions of the content. If they don't exist (a new post) create it
				$versioned_content = get_post_meta($post->ID,'flms_version_content',true);
				if(!is_array($versioned_content)) {
					$versioned_content = array();
				}
				
				//Update content for the current version
				if(!isset($versioned_content["{$active_version}"]["version_name"])) {
					$versioned_content["{$active_version}"]["version_name"] = "Version {$active_version}";
				}
				if(!isset($versioned_content["{$active_version}"]["version_permalink"] )) {
					$versioned_content["{$active_version}"]["version_permalink"] = "version-{$active_version}";
				}
				$versioned_content["{$active_version}"]["post_content"] = $post->post_content;

				//update the post
				update_post_meta($post_id,'flms_version_content',$versioned_content);
				//remove original content so we keep a blank canvas
				$origin_version = array(
					'ID'           => $post_id,
					'post_content' => '',
				);
				wp_update_post( $origin_version, false, false );
				
				//Update our timestamp now that the process is done
				update_post_meta($post_id,'flms_last_updated',time());
			}
		}	
	}
	
	/**
	 * Update wysiwyg with current version
	 */
	public function get_current_version_wysiwyg_content($content, $default_editor) {
		global $post;
		if(!flms_is_gutenberg_editor()) {
			if(!flms_is_flms_post_type($post)) {
				return $content;
			}
			if($post->post_type == 'flms-lessons') {
				$course_id = get_post_meta($post->ID,'flms_course',true);
			} else if($post->post_type == 'flms-topics') {
				$lesson_id = flms_get_topic_version_parent($post->ID);
				$course_id = get_post_meta($lesson_id,'flms_course',true);
			} else if($post->post_type == 'flms-exams') {
				$associated_id = flms_get_exam_version_parent($post->ID);
				if($associated_id == 0) {
					$course_id = 0;
				} else {
					$post_type = get_post_type($associated_id);	
					if($post_type == 'flms-lessons') {
						$course_id = get_post_meta($associated_id,'flms_course',true);
					} else if($post_type == 'flms-topics') {
						$lesson_id = flms_get_topic_version_parent($associated_id);
						$course_id = get_post_meta($lesson_id,'flms_course',true);
					} else {
						$course_id = $associated_id;
					}
				}
			} else {
				$course_id = $post->ID;
			}
			if($course_id != '' && $course_id > 0 && $course_id != $post->ID) {
				$active_version = get_post_meta($course_id,'flms_course_active_version',true);
				$versions = get_post_meta($post->ID,'flms_version_content',true);
				if(is_array($versions)) {
					foreach($versions as $k => $v) {
						if($k == $active_version) {
							if(isset($v["post_content"])) {
								$content = $v["post_content"];
							} else {
								$content = "";
							}
							
						}
					}
				}
			} 
		}
		return $content;
	}

	/**
	 * Update gutenberg with current version
	 */
	public function get_current_version_gutenberg_content() {
		global $post;
		
		if(!flms_is_gutenberg_editor()) {
			return;
		}
		if(get_post_type($post->ID) !== 'flms-courses') {
			return;
		}
		$active_version = get_post_meta($post->ID,'flms_course_active_version',true);
		
		wp_enqueue_script(
            'get-course-version',
            FLMS_PLUGIN_URL . 'assets/js/get_course_version.js',
            array('wp-blocks', 'wp-editor', 'wp-components'),
            false,
            true
        );
		wp_localize_script( 'get-course-version', 'get_course_version', array(
			'root' => esc_url_raw( rest_url() ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'course_id' => $post->ID,
			'version' => $active_version,
		) );

    }

	/**
	 * Show notice at the top of gutenberg with which version you are editing
	 */
	public function alerts_enqueue() {
		global $post;
		if(get_post_type($post->ID) !== 'flms-courses' || get_post_type($post->ID) !== 'flms-lessons' || get_post_type($post->ID) !== 'flms-topics') {
			return;
		}
		wp_enqueue_script(
            'admin_notice',
            FLMS_PLUGIN_URL . 'assets/js/admin_notice.js',
            array('wp-blocks', 'wp-editor', 'wp-components'),
            false,
            true
        );
		wp_localize_script( 'admin_notice', 'admin_content', array(
			'content' => $this->show_admin_editor_toolbar()
		));
	}

	/**
	 * Content for the notice
	 */
	public function show_admin_editor_toolbar() {
		global $post;
		$active_version = get_post_meta($post->ID,'flms_course_active_version',true);
		$current = '1';
		if($active_version > 1) {
			$current = $active_version;
		}
		return 'Currently editing: v'.$current; 
	}
	
	/**
	 * Add meta boxes to flms-courses
	 * @since 1.0.0
	 * @return void
	 */
	public function flms_register_meta_boxes() {
		global $post;
		if(!isset($post)) {
			return;
		}
		if($post->post_type == 'flms-lessons') {
			$course_id = get_post_meta($post->ID,'flms_course',true);
		} else if($post->post_type == 'flms-topics') {
			$lesson_id = flms_get_topic_version_parent($post->ID);
			$course_id = get_post_meta($lesson_id,'flms_course',true);
		} else if($post->post_type == 'flms-exams') {
			$associated_id = flms_get_exam_version_parent($post->ID);
			if($associated_id == 0) {
				$course_id = 0;
			} else {
				$post_type = get_post_type($associated_id);	
				if($post_type == 'flms-lessons') {
					$course_id = get_post_meta($associated_id,'flms_course',true);
				} else if($post_type == 'flms-topics') {
					$lesson_id = flms_get_topic_version_parent($associated_id);
					$course_id = get_post_meta($lesson_id,'flms_course',true);
				} else {
					$course_id = $associated_id;
				}
			}
		} else {
			$course_id = $post->ID;
		} 
		if($course_id > 0) {
			$versions = maybe_unserialize(get_post_meta($course_id,'flms_version_content',true));
			if(is_array($versions)) {
				global $flms_settings;
				$course_name = 'Course';
				if(isset($flms_settings['labels']["course_singular"])) {
					$course_name = $flms_settings['labels']["course_singular"];
				}
				add_meta_box( 'flms_course-versions', __( $course_name.' Versions', 'textdomain' ), array($this,'flms_version_metabox'), array('flms-courses','flms-lessons', 'flms-topics', 'flms-exams'), 'side', 'core' );
			}
		}
	}

	/**
	 * Show the versions available in the course and the ability to add new ones
	 */
	public function flms_version_metabox() {
		global $post, $flms_settings;
		$course_id = flms_get_course_id($post->ID);

		if($post->post_type != 'flms-courses') {
			echo '<label>Associated Course:</label>';
			echo '<p class="description"><a href="'.get_permalink($course_id).'">'.get_the_title($course_id).'</a></p>';
			echo '<div class="flms-spacer"></div>';
		}

		$versions = get_post_meta($course_id,'flms_version_content',true);
		$active_version = get_post_meta($course_id,'flms_course_active_version',true);
		//Sort by descending value (krsort because we use v1,v2.. to store the data instead of using numbers)
		krsort($versions);
		$latest = array_key_first($versions);
		$copy_select = '';
		$course_name = 'course';
		if(isset($flms_settings['labels']["course_singular"])) {
			$course_name = strtolower($flms_settings['labels']["course_singular"]);
		}
		if(is_array($versions)) {
			echo '<div class="components-panel__column">';
				echo '<label>Current Version:</label>';
				echo '<p class="description">Select the '.$course_name.' version you would like to edit</p>';
				echo '<select id="switch-to-version" class="flms-full-width">';
				foreach($versions as $k => $v) {
					if(is_numeric($k)) {
						$version_name = 'Version '.$k;
						if(isset($v['version_name'])) {
							$version_name = $v['version_name'];
						}
						$copy_select .= '<option value="'.$k.'"';
							if($active_version == $k) $copy_select .= ' selected';
						$copy_select .= '>'.$version_name;
						$status_value = 'draft';
						if(isset($v['version_status'])) {
							$status_value = $v['version_status'];
						}
						/*if($latest == $k)
							$copy_select .= ' (Latest)';*/
						if($status_value == 'draft') {
							$copy_select .= ' - Draft';
						} else {
							$copy_select .= ' - Published';
						}
						$copy_select .= '</option>';
					}
				}
				echo $copy_select;
				echo '</select>';
				
			echo '</div>';
		}

		//Button to create new versions
		if($post->post_type == 'flms-courses') {
			ksort($versions);
			$new_version_count = array_key_last($versions) + 1;
			//echo '<div class="button button-primary" id="flms-new-version">Create New Version</div>';

			echo '<button class="button button-primary" data-modal-trigger="#create-course-version" id="create-course-version-trigger">Create New Version</button>';

			echo '<div id="create-course-version" class="modal">';
				echo '<input type="hidden" id="version-count" value="'.$new_version_count.'" />';
				echo '<div class="modal-content">';
					echo '<div class="settings-field">';
						echo '<label>Version name</label>';
						echo '<input type="text" id="version-name" value="Version '.$new_version_count.'" />';
					echo '</div>';
					echo '<div class="settings-field">';
						echo '<label>Version permalink</label>';
						echo '<input type="text" id="version-permalink" value="version-'.$new_version_count.'" />';
					echo '</div>';
					echo '<div class="settings-field">';
						echo '<label data-checkbox-toggle="#from-source"><input type="checkbox" id="copy-course-content" value="" checked="checked" /> Copy course content</label>';
					echo '</div>';
					echo '<div class="settings-field toggle-div is-active" id="from-source">';
						echo '<label>From source</label>';
						echo '<select id="copy-version-from">'.$copy_select.'</select>';
					echo '</div>';
					echo '<div class="text-center">';
						echo '<div class="button button-primary" id="flms-new-version">Create New Version</div>';
					echo '</div>';
					
					echo '<div class="modal-footer">';
						echo '<div class="cancel text-center"></div>';
					echo '</div>';
				echo '</div>';
			echo '</div>';
		}
		if(count($versions) > 1) {
			
		//if($post->post_type != 'flms-courses') {
			krsort($versions);
			
			$found_version = false;
			$select = '';
			foreach($versions as $k => $v) {
				if(is_numeric($k)) {
					if($active_version != $k) {
						$found_version = true;
						$version_name = 'Version '.$k;
						if(isset($v['version_name'])) {
							$version_name = $v['version_name'];
						}
						$select .= '<option value="'.$k.'">'.$version_name.'</option>';
					}
				}
			}
			if($found_version) {
				echo '<div class="flms-spacer"></div>';
				echo '<label>Copy version content:</label>';
				echo '<p class="description">Copy content from another '.$course_name.' version to this version.<br><strong>Warning: </strong>This cannot be undone.</p>';
				echo '<select id="copy-version-content-select" class="flms-full-width">';
				echo '<option value="-1" selected>Select a version</option>';
				echo $select;
				echo '</select>';
				echo '<button id="copy-version-content" class="button button-primary" data-active-version="'.$active_version.'">Copy Content</button>';
			}
				
			//}
		}
		
	}

}
new FLMS_Editor_Content();
