<?php

class FLMS_Module_Course_Metadata {

    public $class_name = 'FLMS_Module_Course_Metadata';

    public function __construct() {
        //add_action( 'init', array($this, 'register_shortcodes') );
	}
    
    public function get_shortcodes() {
		$shortcodes = array(
			'metadata' => array(
				'description' => 'Display custom course metadata',
				'atts' => array(
					'field' => 'Slug for the desired metadata',
					'before' => 'Text before the metadata',	
                    'after' => 'Text after the metadata',	
				)
			),
		);
		return $shortcodes;
	}

    public function register_shortcodes() {
        $shortcodes = $this->get_shortcodes();
		foreach($shortcodes as $shortcode => $value) {
            $replace = str_replace('-','_',$shortcode);
            $shortcode_callback = 'flms_'.$replace.'_shortcode';
			add_shortcode( "flms-$shortcode", array($this, $shortcode_callback) );
            $prefix = get_flms_whitelabel_prefix();
            if($prefix != '') {
                add_shortcode( "$prefix-$shortcode", array($this, $shortcode_callback) );
            }
		}
    }
    
    public function flms_metadata_shortcode($atts) {
        global $post;
        $default_atts = array(
            'field' => '',
            'before' => '',
            'after' => '',
        );
        $atts = shortcode_atts( $default_atts, $atts, 'flms-metadata' );
        $return = '';
        if($atts['field'] != '') {
            global $flms_settings, $flms_course_id, $flms_active_version, $flms_course_version_content;
            if(isset($flms_course_version_content["$flms_active_version"]['course_metadata'][$atts['field']])) {
                $field = $flms_course_version_content["$flms_active_version"]['course_metadata'][$atts['field']];
                if($field != '') {
                    if($atts['before'] != '') {
                        $return .= $atts['before'];
                    }
                    $return .= $field;
                    if($atts['after'] != '') {
                        $return .= $atts['after'];
                    }
                }
            } 
        }
        return $return;
    }

    public function flms_course_metaboxes() {
        $return = '<div class="flms-course-metadata">';
        $return .= $this->flms_get_course_metafields();
        $return .= '</div>';
        return $return;
    }

    public function flms_get_course_metafields($location = 'design', $echo = true, $show_label = true, $heading_wrap = 'h3') {
        global $flms_settings, $flms_course_id, $flms_active_version, $flms_course_version_content;
        $return = '';
        $metadata = $this->get_course_metadata_fields(true, true);
        $display = '';
        if($location == 'design') {
            $display = 'display-block';
        }
        if(!empty($metadata)) {
            //echo '<pre>'.print_r($metadata,true).'</pre>';
            foreach($metadata as $metadata) {
                $label = $metadata['label'];
                $return .= '<input type="text" name="flms-'.$metadata['key'].'" placeholder="'.$label.'" />';
            }
        }
        
        if($echo) {
            echo $return;
        } else {
            return $return;
        }
        
    }

    public function get_course_metadata_fields($exclude_dynamic = false, $sort = false) {
        global $flms_settings;

        if(!$exclude_dynamic) {
            $default_fields = array(
                array(
                    'label' => 'Create Course Meta Field',
                    'key' => 'create-course-metadata-field',
                    'type' => 'dynamic',
                    'class' => $this->class_name,
                    'function' => 'create_course_metadata',
                ) 
            );
        } else {
            $default_fields = array();
        }

        $fields = array();
        if(isset($flms_settings['course_metadata'])) {
            $existing_fields = $flms_settings['course_metadata'];
            //print_r($custom_credit_fields);
            foreach($existing_fields as $k => $v) {
                //if(isset($v["$k-custom"])) {
                    $credit_name = $k;
                    
                    $singular_name = $v["name"];
                    $slug = $v["slug"];
                    $description = '';
                    if(isset($v["description"])) {
                        $description = $v["description"];
                    }
                    $status = $v["status"];
                    $form_fields = $this->replace_tmp_fields($singular_name, $slug, $description, $status);
                    
                    //print_r($form_fields);
                    //print_r($form_fields);
                    $fields[] = array(
                        'label' => $singular_name,
                        'key' => $slug,
                        'type' => 'group',
                        'sortable' => 'handle',
                        'group_fields' => $form_fields
                    );
                //}

            }
        } else {
            $fields = array();  
        }
        //print_r($fields);

        if($sort) {
            if(isset($flms_settings['course_metadata'])) {
                $sorted_fields = array();
                foreach($flms_settings['course_metadata'] as $k => $credit) {
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
        }

        $fields = array_merge($default_fields,$fields);

        return $fields;
    }

    public function get_metadata_slugs() {
        $slugs = array();
        $metadata = $this->get_course_metadata_fields(true);
        if(!empty($metadata)) {
            foreach($metadata as $metadata) {
                $slug = $metadata['slug'];
                $slugs[] = $slug;
            }
        }
        return $slugs;
    }

    public function replace_tmp_fields($name, $slug, $description, $status) {
        $form_fields = $this->get_custom_metadata_fields();
        $new_fields = array();
		
		foreach($form_fields as $form_field) {
            $form_field['key'] = str_replace('tmp-course-metadata-', '', $form_field['key']);
			if($form_field['key'] == "name") {
				$form_field['default'] = $name;
			} else if($form_field['key'] == "slug") {
				$form_field['default'] = $slug;
			} else if($form_field['key'] == "description") {
				$form_field['default'] = $description;
			} else if($form_field['key'] == "status") {
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

    public function create_course_metadata() {
        echo $this->create_course_metadata_form();
    }

    public function create_course_metadata_form() {
        $settings_class = new FLMS_Settings();
        $form = '<div id="create-course-metadata-form">';
            ob_start();
            $form_field_category = 'tmp_create_course_metadata';
            $form_fields = $this->get_custom_metadata_fields();
            foreach($form_fields as $form_field) {
                flms_print_field_input($form_field, $form_field_category);
            }
            $form .= ob_get_clean();
            $form .= '<button class="button button-primary" id="create-course-metadata-field">Create metadata field</button>';
        $form .= '</div>';
        return $form;
    }

    public function get_custom_metadata_fields() {
        global $flms_settings;
        $form_fields = array(
            array(
                'label' => 'Name',
                'key' => 'tmp-course-metadata-name',
                'type' => 'text',
                'default' => '',
                'description' => '',
                'placeholder' => 'My metadata',
            ),
            array(
                'label' => 'Slug',
                'key' => 'tmp-course-metadata-slug',
                'type' => 'text',
                'default' => '',
                'description' => '<strong>Warning:</strong> Changing the metadata slug will remove any existing metadata.',
                'placeholder' => 'my-metadata',
            ),
            array(
                'label' => 'Description',
                'key' => 'tmp-course-metadata-description',
                'type' => 'text',
                'default' => '',
                'description' => '',
                'placeholder' => 'Description of my metadata',
            ),
            array(
                'label' => 'Status',
                'key' => "tmp-course-metadata-status",
                'type' => 'radio',
                'options' => array(
                    'active' => 'Active',
                    'inactive' => 'Inactive'
                ),
                'default' => 'active',
            ),
            array(
                'label' => 'Delete',
                'key' => "tmp-course-metadata-delete",
                'type' => 'delete',
                'default' => 'Delete metadata',
            ),
        );
        return $form_fields;
    }

    public function get_course_credit_field($field) {
        global $flms_settings;
        $return = '';
        if(isset($flms_settings['course_credits'])) {
            $white_label_fields = $flms_settings['course_credits'];
            if(isset($white_label_fields["$field"])) {
                $return = $white_label_fields["$field"];
            } 
        }
        return $return;
    }

     
    public function get_course_metadata_settings($layout) {
        global $flms_settings, $flms_course_id, $flms_active_version, $flms_course_version_content;
        $course_credit_fields = $this->get_course_metadata_settings_fields();
        $return = '';
        if(!empty($course_credit_fields)) {
            foreach($course_credit_fields as $field) {
                if($field["status"] == 'active') {
                    $label = $field["name"];  
                    $key = $field["slug"]; 
                    $description = $field["description"];  
                    $default = '';
                    if(isset($flms_course_version_content["$flms_active_version"]['course_metadata'][$key])) {
                        $default = $flms_course_version_content["$flms_active_version"]['course_metadata'][$key];
                    }
                    $return .= '<div class="settings-field">';
                        $return .= '<div class="setting-field-label">'.$label;
                        if($description != '' && $layout == 'grid') {
                            $return .= ' <div class="flms-tooltip" data-tooltip="'.$description.'"></div>';
                        }
                        $return .= '</div>';
                        if($layout != 'grid') {
                            $return .= '<p class="description">'.$description.'</p>';
                        }
                        $return .= '<input type="text" name="'.$key.'-metadata" placeholder="'.$label.'" value="'.$default.'" />';
                    $return .= '</div>';
                }
            }
        }
        if($return == '') {
            $return = '<em>There are no active course metadata fields to set.</em>';
        }
        return $return;
    }

    public function get_course_metadata_settings_fields() {
        global $flms_settings;
        if(isset($flms_settings['course_metadata'])) {
            if(!empty($flms_settings['course_metadata'])) {
                return $flms_settings['course_metadata'];
            }
        }
        return array();
    }
    
    public function update_course_metadata($post_id, $active_version, $data) {
        $course_credit_fields = $this->get_course_metadata_settings_fields();
        $course_numbers = array();
        foreach($course_credit_fields as $field) {
            if($field["status"] == 'active') {
                $key = $field["slug"];  
                if(isset($data["$key-metadata"])) {
                    $course_numbers[$key] = sanitize_text_field( $data["$key-metadata"] );
                } else {
                    $course_numbers[$key] = '';
                }
            }
        }
        $course = new FLMS_Course($post_id);
        global $flms_active_version;
        $flms_active_version = $active_version;
        $course->update_course_version_field('course_metadata', $course_numbers);
    }

    public function get_metadata_options() {
        global $flms_settings;
        $fields = array();
        $metadata = $this->get_course_metadata_fields(true);
        if(!empty($metadata)) {
            $default_fields = array(
                array(
                    'label' => "Course metadata",
                    'key' => 'course_credits_heading',
                    'type' => 'section_heading',
                ),
            );
            //echo '<pre>'.print_r($metadata,true).'</pre>';
            $tax_fields = array();
            foreach($metadata as $metadata) {
                $plural = $metadata['name'];
                $plural_lower = strtolower($plural);
                $slug = $metadata['slug'];
                $tax_fields[] = array(
                    'label' => "Show $singular in courses",
                    'key' => "{$slug}_course_display",
                    'type' => 'radio',
                    'options' => array(
                        'show' => 'Show',
                        'hide' => 'Hide'
                    ),
                    'default' => 'show',
                );
            }
            $fields = array_merge($default_fields, $tax_fields);
        }
        return $fields;
    }

}
new FLMS_Module_Course_Metadata();