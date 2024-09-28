<?php

class FLMS_Module_Course_Numbers {

    public $class_name = 'FLMS_Module_Course_Numbers';

    public function __construct() {
        add_action( 'init', array($this,'register_shortcodes') );
	}
    
    public function get_shortcodes() {
		$shortcodes = array(
			'course-number' =>  array(
                'description' => 'Display a course number field',
                'atts' => array(
                    'field' => "A credit type, defaults to 'global'",
                    'before' => 'Text before the field',	
                    'after' => 'Text after the field',	
                )
            )
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
    
    public function flms_course_number_shortcode($atts) {
        global $post, $flms_active_version, $flms_settings;
        $default_atts = array(
            'before' => '',
            'after' => '',
            'field' => '',
        );
        $atts = shortcode_atts( $default_atts, $atts, 'flms-taxonomy' );
        $return = '';
        $type = 'global';
        if($atts['field'] != '' && $atts['field'] != 'global') {
            $type = '';
            $sanitized_att_name = preg_replace('/[^\w-]/', '', strtolower(trim(strip_tags(html_entity_decode($atts['field'])))));
            if(isset($flms_settings['course_credits'])) {
                //return '<pre>'.print_r($flms_settings['course_credits'],true).'</pre>';
                foreach($flms_settings['course_credits'] as $k => $v) {
                    if($atts['field'] == $v['name'] || $atts['field'] == $k || $sanitized_att_name == $k) {
                        $type = $k;
                        break;
                    }
                }
            }
            if($type == '') {
                return '';
            }
        } 
        $post_id = $post->ID;
        $course_number = $this->get_course_number($post_id, $flms_active_version, $type);
        if($course_number != '') {
            return $atts['before'] . $course_number . $atts['after'];
        }
        return ;
    }

    public function update_course_numbers($post_id, $active_version, $data) {
        $course_numbers = array();
        if(isset($data['global_course_number'])) {
            $course_numbers['global'] = sanitize_text_field( $data['global_course_number'] );
        } else {
            $course_numbers['global'] = '';
        }
        if(flms_is_module_active('course_credits')) {
            $course_credits = new FLMS_Module_Course_Credits();
            $course_credit_fields = $course_credits->get_course_credit_fields();
            foreach($course_credit_fields as $field) {
                if(isset($data["$field-course-number"])) {
                    $course_numbers[$field] = sanitize_text_field( $data["$field-course-number"] );
                } else {
                    $course_numbers[$field] = '';
                }
            }
        }
        //flms_debug($course_numbers);
        $course = new FLMS_Course($post_id);
        global $flms_active_version;
        $flms_active_version = $active_version;
        $course->update_course_version_field('course_numbers', $course_numbers);
    }

    public function get_course_number($post_id, $active_version = 'inherit', $type = 'global') {
        $course = new FLMS_Course($post_id);
        if($active_version == 'inherit') {
            global $flms_active_version;
            $active_version = $flms_active_version;
        }
        global $flms_course_version_content;
        if(isset($flms_course_version_content[$active_version]['course_numbers'][$type])) {
            return $flms_course_version_content[$active_version]['course_numbers'][$type];
        }
        return '';
    }

}
new FLMS_Module_Course_Numbers();