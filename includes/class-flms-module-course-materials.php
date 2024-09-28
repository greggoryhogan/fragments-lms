<?php

class FLMS_Module_Course_Materials {

    public $class_name = 'FLMS_Module_Course_Materials';

    public function __construct() {
    
	}
    
    public function get_course_materials_course_settings() {
        global $flms_settings, $flms_course_id, $flms_active_version, $flms_course_version_content;
        $return = '';
        $return .= $this->get_course_material_form();
        $return .= '<div id="course-materials-list">';
        $materials = array();
        if(isset($flms_course_version_content["$flms_active_version"]['course_materials'])) {
            $materials = $flms_course_version_content["$flms_active_version"]['course_materials'];
        }
        $has_materials = false;
        if(!empty($materials)) {
            $has_materials = true;
            foreach($materials as $material) {
                $return .= $this->get_course_material_form($material);
            }
        } 
        $return .= '<div class="no-course-materials';
        if(!$has_materials) {
            $return .= ' no-materials';
        }
        $return .= '"></div>';
        $return .= '</div>';
       
        return $return;
    }

    public function get_course_material_form($params = array()) {
        wp_enqueue_media();
        wp_enqueue_script( 'flms-course-materials');
        global $post, $flms_settings;
		$course_name = 'Course';
		if(isset($flms_settings['labels']["course_singular"])) {
			$course_name = $flms_settings['labels']["course_singular"];
		}
        $form = '';
        if(empty($params)) {
            $form = '<div id="course-materials-form">';
        }
        if(!empty($params)) {
            $form .= '<div class="course-material-item-container">';
            $form .= '<div class="flms-handle"></div>';
        }
        $form .= '<div class="course-material-item">';
            $form .= '<div class="settings-field col-2">';
                $form .= '<div class="setting-field-label">Title</div>';
                $form .= '<p class="description"></p>';
                $default = '';
                if(isset($params['title'])) {
                    $default = $params['title'];
                }
                $form .= '<input type="text" placeholder="Title" name="material-title" class="material-title" value="'.$default.'" data-field="title" />';
            $form .= '</div>';
            $form .= '<div class="settings-field col-2">';
                $form .= '<div class="setting-field-label">Enrollment Status</div>';
                $form .= '<p class="description"></p>';
                $form .= '<select class="material-availability" data-field="status">';
                $default = 'any';
                if(isset($params['status'])) {
                    $default = $params['status'];
                }
                $options = array(
                    'any' => 'Any Enrollment Status',
                    'pre-enrollment' => 'Pre Enrollment',
                    'post-enrollment' => 'Post Enrollment',
                    'post-completion' => $course_name.' Completed'
                );
                foreach($options as $k => $v) {
                    $form .= '<option name="course-material-availability" value="'.$k.'"';
                    if($default == $k) {
                        $form .= ' selected="selected"';
                    }
                    $form .= '>'.$v.'</option>';
                }
                $form .= '</select>';
            $form .= '</div>';
            $form .= '<div class="settings-field">';
                $form .= '<div class="setting-field-label">Media File</div>';
                $form .= '<p class="description"></p>';
                $form .= '<div class="flms-flex gap-sm">';
                    $default = '';
                    if(isset($params['file'])) {
                        $default = $params['file'];
                    }
                    $form .= '<input class="course-material-media-url" type="text" placeholder="Path to your media file" value="'.$default.'" data-field="file" />';
                    $form .= '<button class="add-course-material-media" class="button button-secondary">Add Media</button>';
                $form .= '</div>';
            $form .= '</div>';
            if(!empty($params)) {
                $form .= '</div>';
            }
        if(empty($params)) {
            $form .= '</div>';
        }
        if(empty($params)) {
            $form .= '<button id="insert-course-material" class="button button-primary">Add '.$course_name.' Material</button>';
        } else {
            $form .= '<div class="flms-remove"></div>';
            $index = $params['index'];
            $form .= '<input type="hidden" name="flms_course_materials['.$index.'][title]" value="'.$params['title'].'" data-field="title"  />';
            $form .= '<input type="hidden" name="flms_course_materials['.$index.'][status]" value="'.$params['status'].'" data-field="status"  />';
            $form .= '<input type="hidden" name="flms_course_materials['.$index.'][file]" value="'.$params['file'].'" data-field="file" />';
        }
        $form .= '</div>';
        return $form;
    }

    public function replace_tmp_fields($form_fields, $name, $status, $license, $fee_type, $fee, $description) {
        $new_fields = array();
		
		foreach($form_fields as $form_field) {
            $form_field['key'] = str_replace('tmp-course-credit-','',$form_field['key']);
			if($form_field['key'] == "name") {
				//$form_field['type'] = 'hidden';
				$form_field['default'] = $name;
			} else if($form_field['key'] == "status") {
				$form_field['default'] = $status;
			} else if($form_field['key'] == "license-required") {
				$form_field['default'] = $license;
			} else if($form_field['key'] == "reporting-fee-status") {
				$form_field['default'] = $fee_type;
			} else if($form_field['key'] == "reporting-fee") {
				$form_field['default'] = $fee;
			} else if($form_field['key'] == "reporting-fee-description") {
				$form_field['default'] = $description;
			}
			$new_fields[] = $form_field;
		}
		
        return $new_fields;
    }

    public function update_course_materials($post_id, $active_version, $data) {
        $course_materials= array();
        if(isset($data['flms_course_materials'])) {
            $index = 0;
            foreach($data['flms_course_materials'] as $k) {
                $title = '';
                if(isset($k['title'])) {
                    $title = $k['title'];
                }
                $status = '';
                if(isset($k['status'])) {
                    $status = $k['status'];
                }
                $file = '';
                if(isset($k['file'])) {
                    $file = $k['file'];
                }
                $course_materials[] = array(
                    'index' => $index,
                    'title' => $title,
                    'status' => $status,
                    'file' => $file
                );
                $index++;
            }
        }
        
        $course = new FLMS_Course($post_id);
        global $flms_active_version;
        $flms_active_version = $active_version;
        $course->update_course_version_field('course_materials', $course_materials);
    }

    public function get_course_materials_settings_options() {
        global $flms_settings;
        $fields = array(
            array(
                'label' => "Course Materials",
                'key' => 'course_materials_heading',
                'type' => 'section_heading',
            ),
            array(
                'label' => "Show Course Materials in courses",
                'key' => "show_course_display",
                'type' => 'radio',
                'options' => array(
                    'show' => 'Show',
                    'hide' => 'Hide'
                ),
                'default' => 'show',
            ),
        );
        return $fields;
    }

    public function get_ecommerce_options() {
        global $flms_settings;
        $fields = array(
            array(
                'label' => "Course Materials",
                'key' => 'course_materials_heading',
                'type' => 'section_heading',
            ),
            array(
                'label' => "Show Course Materials in products",
                'key' => "show_course_display",
                'type' => 'radio',
                'options' => array(
                    'show' => 'Show',
                    'hide' => 'Hide'
                ),
                'default' => 'show',
            ),
        );
        return $fields;
    }

    public function display_course_materials($courses = array()) {
        global $flms_course_id, $flms_active_version;
        $return = '';
        $course_material_output = array();
        if(empty($courses)) {
            $courses = array("$flms_course_id:$flms_active_version");
        }
        
        foreach($courses as $course) {
            $course_data = explode(':',$course);
            $course_id = $course_data[0];
            $course_version = $course_data[1];
            $course_version_content = get_post_meta($course_id,'flms_version_content',true);	
            if(!is_array($course_version_content)) {
                $course_version_content = array();
            }
            $materials = array();
            if(isset($course_version_content["$course_version"]['course_materials'])) {
                $materials = $course_version_content["$course_version"]['course_materials'];
            }
            if(!empty($materials)) {
                global $current_user;
                $user_id = $current_user->ID;
                $user_course_status_data = flms_get_user_course_status($user_id, $course_id, $course_version);
                $user_course_status = $user_course_status_data['customer_status'];
                
                foreach($materials as $material) {
                    $title = $material['title'];
                    $status = $material['status'];
                    $file = $material['file'];
                    if($status == $user_course_status || $status == 'any') {
                        $course_material_output[] = '<a href="'.$file.'" target="_blank" title="'.$title.'">'.$title.'</a>';
                    }
                }
            } 
        }
        return $course_material_output;
    }

    public function flms_course_materials() {
        global $flms_settings;
        $display_materials = false;
        if(isset($flms_settings['design']['show_course_display'])) {
            if($flms_settings['design']['show_course_display'] == 'show') {
                $display_materials = true;
            }
        }
        if(!$display_materials) {
            return '';
        }
        $course_material_output = $this->display_course_materials();
        if(empty($course_material_output)) {
            return '';
        }
        $label = 'Course Materials';
        if(isset($flms_settings['labels']['course_materials'])) {
            $label = $flms_settings['labels']['course_materials'];
        }
        echo '<div class="flms-course-materials flms-course-content-section">';
            echo '<div>'.$label.':</div><div>';
            echo implode('<br>',$course_material_output);
        echo '</div></div>';
        
    }

    public function flms_ecommerce_course_materials($courses) {
        global $flms_settings;
        $display_materials = false;
        if(isset($flms_settings['woocommerce']['show_course_display'])) {
            if($flms_settings['woocommerce']['show_course_display'] == 'show') {
                $display_materials = true;
            }
        }
        if(!$display_materials) {
            return '';
        }
        $course_material_output = $this->display_course_materials($courses);
        if(empty($course_material_output)) {
            return '';
        }
        $label = 'Course Materials';
        if(isset($flms_settings['labels']['course_materials'])) {
            $label = $flms_settings['labels']['course_materials'];
        }
        echo '<div>'.$label.': '.implode(', ',$course_material_output).'</div>';
    }

    public function get_course_material_labels() {
        $fields = array(
            array(
                'label' => 'Course Materials',
                'key' => 'course_materials',
                'type' => 'text',
                'default' => 'Course Materials',
            ),
        );
        return $fields;
    }
}
new FLMS_Module_Course_Materials();