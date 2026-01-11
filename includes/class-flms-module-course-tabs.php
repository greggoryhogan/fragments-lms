<?php

class FLMS_Module_Course_Tabs {

    public $class_name = 'FLMS_Module_Course_Tabs';

    public static $captured_tabs = array();

    public function __construct() {

	}
    
    public function get_course_tab_fields($exclude_dynamic = false, $sort = false, $exclude_content_preview = false) {
        global $flms_settings;

        if(!$exclude_dynamic) {
            $default_fields = array(
                array(
                    'label' => 'Create Course Tab',
                    'key' => 'create-course-tabs-field',
                    'type' => 'dynamic',
                    'class' => $this->class_name,
                    'function' => 'create_course_tab',
                ) 
            );
        } else {
            $default_fields = array();
        }

        $custom_tabs = $this->get_default_tab_fields('The default content for enrolled or unenrolled users. This tab cannot be deleted.');
        $fields = array(
            array(
                'label' => 'Course Content / Preview',
                'key' => 'course-content',
                'type' => 'group',
                'sortable' => 'handle',
                'group_fields' => $this->replace_tmp_fields($custom_tabs, 'Course Content', 'active'),
            )
        );

        if(isset($flms_settings['course_tabs'])) {
            $existing_fields = $flms_settings['course_tabs'];
            unset($existing_fields['course-content']);
            $custom_tabs = $this->get_custom_tab_fields();
            //print_r($custom_credit_fields);
            foreach($existing_fields as $k => $v) {
                //if(isset($v["$k-custom"])) {
                    $name = $v["name"];
                    $status = $v["status"];
                    $form_fields = $this->replace_tmp_fields($custom_tabs, $name, $status);
                    
                    //print_r($form_fields);
                    //print_r($form_fields);
                    $fields[] = array(
                        'label' => $name,
                        'key' => $k,
                        'type' => 'group',
                        'sortable' => 'handle',
                        'group_fields' => $form_fields
                    );
                //}

            }
        } else {
            $custom_tabs = $this->get_default_tab_fields('The default content for enrolled or unenrolled users. This tab cannot be removed.');
            $fields = array(
                array(
                    'label' => 'Course Content / Preview',
                    'key' => 'course-content',
                    'type' => 'group',
                    'sortable' => 'handle',
                    'group_fields' => $this->replace_tmp_fields($custom_tabs, 'Course Content', 'active'),
                )
            );
        }
        
        if($exclude_content_preview) {
            unset($fields[0]); //course content / preview
        }

        if($sort) {
            if(isset($flms_settings['course_tabs'])) {
                $sorted_fields = array();
                foreach($flms_settings['course_tabs'] as $k => $tab) {
                    //echo '<pre>'.$k.':<br>'.print_r($credit,true).'</pre>';
                    foreach($fields as $field) {
                        if($field['key'] == $k) {
                            $sorted_fields[] = $field;
                            break;
                        }
                    }
                }
                $fields = $sorted_fields;
            }
        };

        $fields = array_merge($default_fields,$fields);

        return $fields;
    }

    public function get_design_options() {
        global $flms_settings;
        $fields = array(
            array(
                'label' => "Course Tabs",
                'key' => 'course_tabs_heading',
                'type' => 'section_heading',
            ),
            array(
                'label' => "Show course tabs",
                'key' => "show_course_tabs",
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

    public function replace_tmp_fields($form_fields, $name, $status) {
        $new_fields = array();
		
		foreach($form_fields as $form_field) {
            $form_field['key'] = str_replace('tmp-course-tab-', '', $form_field['key']);
			if($form_field['key'] == "name") {
				$form_field['default'] = $name;
			}  else if($form_field['key'] == "status") {
				$form_field['default'] = $status;
			}
			$new_fields[] = $form_field;
		}
		
        return $new_fields;
    }

    public function sortByOrder($a, $b) {
        if ($a['order'] > $b['order']) {
            return 1;
        } elseif ($a['order'] < $b['order']) {
            return -1;
        }
        return 0;
    }

    public function create_course_tab() {
        echo $this->create_course_tab_form();
    }

    public function create_course_tab_form() {
        $settings_class = new FLMS_Settings();
        $form = '<div id="create-course-credit-form">';
            ob_start();
            $form_field_category = 'tmp_create_course_tab';
            $form_fields = $this->get_custom_tab_fields();
            foreach($form_fields as $form_field) {
                flms_print_field_input($form_field, $form_field_category);
            }
            $form .= ob_get_clean();
            $form .= '<button class="button button-primary" id="create-course-tab-field">Create Tab</button>';
        $form .= '</div>';
        return $form;
    }

    public function get_custom_tab_fields() {
        $form_fields = array(
            array(
                'label' => 'Label',
                'key' => 'tmp-course-tab-name',
                'type' => 'text',
                'default' => '',
                'description' => '',
                'placeholder' => 'My Tab',
            ),
            array(
                'label' => 'Status',
                'key' => "tmp-course-tab-status",
                'type' => 'radio',
                'options' => array(
                    'active' => 'Active',
                    'inactive' => 'Inactive'
                ),
                'default' => 'active',
            ),
            array(
                'label' => 'Delete',
                'key' => "tmp-course-tab-delete",
                'type' => 'delete',
                'default' => 'Delete Tab',
            ),
        );
        return $form_fields;
    }

    public function get_default_tab_fields($message) {
        $form_fields = array(
            array(
                'label' => '',
                'key' => "tmp-course-tab-message",
                'type' => 'flms-message',
                'label' => $message
            ),
            array(
                'label' => 'Label',
                'key' => 'tmp-course-tab-name',
                'type' => 'text',
                'default' => '',
                'description' => '',
                'placeholder' => 'My Tab',
            ),
        );
        return $form_fields;
    }

    public function output_course_tab_wysiwyg($id) {
        global $flms_course_id, $flms_active_version, $flms_course_version_content;
		$return = '<div class="course-.'.$id.'">';
			ob_start();
			$toc = '';
			if(isset($flms_course_version_content[$flms_active_version]['course_tabs'][$id])) {
				$toc = $flms_course_version_content[$flms_active_version]['course_tabs'][$id];
			}
			wp_editor($toc, "$flms_course_id-{$id}");
			$return .= ob_get_clean();
		$return .= '</div>';
		return $return;
    }

    public function save_course_tab_content($post_id, $active_version, $data) {
        $tabs = $this->get_course_tab_fields(true, true, true);
        $course_versioned_content = get_post_meta($post_id,'flms_version_content',true);
		if(!is_array($course_versioned_content)) {
			$course_versioned_content = array();
		}
		
        if(!empty($tabs)) {
            foreach($tabs as $k => $v) {
                if(isset($_POST["$post_id-{$v['key']}"])) {
                    $value = $_POST["$post_id-{$v['key']}"];
                    $tab_field = $v['key'];
                    $course_versioned_content["{$active_version}"]['course_tabs'][$tab_field] = wp_kses_post($value);
                }
            }
        }

        update_post_meta($post_id,'flms_version_content',$course_versioned_content);
    }

    public function course_tab_navigation() {
        if(!is_course_tabs_active()) {
            return;
        }
        global $flms_settings;
        $tab_count = 0;
        echo '<nav class="flms-course-tabs">';
        if(isset($flms_settings['course_tabs'])) {
            if(is_array($flms_settings['course_tabs'])) {
                foreach($flms_settings['course_tabs'] as $key => $array) {
                    if(isset($array['status'])) {
                        $active = $array['status'];
                        if($active == 'active') {
                            self::$captured_tabs[] = $key;
                            echo $this->get_course_version_tab_content($key,$array['name']);
                        }
                    } else {
                        self::$captured_tabs[] = $key;
                        echo $this->get_course_version_tab_content($key,$array['name']);
                        //stop, we're on course content
                        break;
                    }
                }
                
            }
        }
    }
    public function course_additional_tabs() {
        if(!is_course_tabs_active()) {
            return;
        }
        global $flms_active_version;
        if(count(self::$captured_tabs) > 0) {
            if(end(self::$captured_tabs) == 'course-content') {
                echo '</div>'; //close course content tab content
            }
        }
        global $flms_settings;
        if(isset($flms_settings['course_tabs'])) {
            if(is_array($flms_settings['course_tabs'])) {
                foreach($flms_settings['course_tabs'] as $key => $array) {
                    if(in_array($key, self::$captured_tabs)) {
                        continue;
                    }
                    self::$captured_tabs[] = $key;
                    $active = 'active';
                    if(isset($array['status'])) {
                        $active = $array['status'];
                        if($active == 'active') {
                            echo $this->get_course_version_tab_content($key,$array['name']);
                        }
                    }
                    
                }
                
            }
        }
        echo '</nav>';
    }

    public function get_course_version_tab_content($key, $name) {
        $checked = '';
        if(count(self::$captured_tabs) == 1) {
            $checked = ' checked';
        }
        global $flms_course_version_content, $flms_active_version;
        $return = '<input type="radio" id="tab-'.$key.'" name="flms-course-tabs" class="flms-course-tab-toggle tab-'.$key.'"'.$checked.'>';
        $return .= '<label for="tab-'.$key.'" class="flms-course-tab">'.$name.'</label>';
        $return .= '<div class="flms-course-tab-content" id="tab-'.$key.'">';
        if($key == 'course-content') {
            return $return;
        }
        if(isset($flms_course_version_content[$flms_active_version]['course_tabs'])) {
            if(isset($flms_course_version_content[$flms_active_version]['course_tabs'][$key])) {
                $return .= apply_filters('the_content', $flms_course_version_content[$flms_active_version]['course_tabs'][$key]);
            }
        }
        $return .= '</div>';
        return $return;
    }
}
global $course_tabs;
$course_tabs = new FLMS_Module_Course_Tabs();