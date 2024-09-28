<?php

class FLMS_Module_Course_Credits {

    public $class_name = 'FLMS_Module_Course_Credits';

    public function __construct() {
        
	}

    public function get_shortcodes() {
		$shortcodes = array(
			'course-credit' =>  array(
                'description' => 'Display a course credit field',
                'atts' => array(
                    'field' => 'The field name to display',
                    'before' => 'Text before the field',	
                    'after' => 'Text after the field',	
                )
            ),
            'user-license' => array(
                'description' => "Display a user's license number",
                'atts' => array(
                    'field' => 'The field name to display',
                    'user_id' => 'ID of the user, defaults to current user or displayed certificate user',
                    'before' => 'Text before the field',	
                    'after' => 'Text after the field',	
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

    public function flms_course_credit_shortcode($atts) {
        global $post;
        $default_atts = array(
            'field' => '',
            'before' => '',
            'after' => ''
        );
        $atts = shortcode_atts( $default_atts, $atts, 'flms-course-credit' );
        $return = '';
        if($atts['field'] != '') {
            $sanitized_att_name = preg_replace('/[^\w-]/', '', strtolower(trim(strip_tags(html_entity_decode($atts['field'])))));
            global $flms_active_version;
            $post_id = $post->ID;
            $course_data = "$post_id:$flms_active_version";
            $credits_values_array = $this->flms_get_course_credits_array();
            //return print_r($credits_values_array,true);
            global $flms_settings;
            $field_name_reference = '';

            if(isset($flms_settings['course_credits'])) {
                //return '<pre>'.print_r($flms_settings['course_credits'],true).'</pre>';
                foreach($flms_settings['course_credits'] as $k => $v) {
                    if($atts['field'] == $k || $sanitized_att_name == $k) {
                        $field_name_reference = $k;
                    } else if(isset($v['name'])) {
                        if($v['name'] == $atts['field']) {
                            $field_name_reference = $k;
                            //return $field_name_reference .'  '.$k;
                        }
                    }
                        
                }
            }
            if($field_name_reference == '') {
                if(isset($flms_settings['labels'])) {
                    foreach($flms_settings['labels'] as $k => $v) {
                        if($atts['field'] == $k) {
                            $field_name_reference = $k;
                        } else if($atts['field'] == $v) {
                            $field_name_reference = $k;
                        }
                            
                    }
                }
            }
            if($field_name_reference != '') {
                //return $field_name_reference;
                if(isset($credits_values_array[$field_name_reference])) {
                    return $atts['before'] . $credits_values_array[$field_name_reference] . $atts['after'];
                } 
            } else {
                return 'could not find '.$atts['field'] .' or '.$sanitized_att_name;
            }
        }
        return $return;
    }

    public function flms_user_license_shortcode($atts) {
        global $post;
        $default_atts = array(
            'field' => '',
            'user_id' => 0,
            'before' => '',
            'after' => ''
        );
        $atts = shortcode_atts( $default_atts, $atts, 'flms-course-credit' );
        if($atts['field'] != '') {
            $sanitized_field = preg_replace('/[^\w-]/', '', strtolower(trim(strip_tags(html_entity_decode($atts['field'])))));
            $label = $this->get_credit_label($atts['field']);
            $field = '';
            global $flms_settings;
            if(isset($flms_settings['course_credits'])) {
                if(is_array($flms_settings['course_credits'])) {
                    foreach($flms_settings['course_credits'] as $k => $v) {
                        if($sanitized_field == $k || $sanitized_field == $v['name'] || $label == $k || $atts['field'] == $k || $atts['field'] == $v['name']) {
                            $field = $k;
                        }
                    }
                }
            }
            if($field == '') {
                return '';
            }
            if(absint($atts['user_id']) > 0) {
                $user_id = absint($atts['user_id']);
            } else {
                global $current_user;
                $user_id = $current_user->ID;
            }
            $license = get_user_meta( $user_id, "flms_license-$field", true );
            if($license != '') {
                return $atts['before'] . $license . $atts['after'];
            } else {
                return '';
            }
        }
        return '';
    }
    public function flms_course_credits() {
        $this->flms_get_course_credits();
    }

    public function flms_get_course_credits($echo = true, $show_label = true, $heading_wrap = 'h3') {
        global $flms_settings, $flms_course_id, $flms_active_version, $flms_course_version_content;
        $course_credit_fields = $this->get_course_credit_fields();
        $return = '';
        $has_credits = false;
        $return .= '<div class="flms-course-credits flms-table flms-background-border flms-course-content-section">';
        $return .= '<div class="flms-course-credits flms-table-item heading flms-background-border">';
                $return .= '<div class="col-1-2 flms-background-border">Credit</div><div class="col-auto credit flms-background-border">Amount</div>';
            $return .= '</div>';
		foreach($course_credit_fields as $field) {
            $label = $this->get_credit_label($field);
            $default = 0;
            //print_r($flms_course_version_content);
            if(isset($flms_course_version_content["$flms_active_version"]['course_credits']["$field"])) {
                $default = $flms_course_version_content["$flms_active_version"]['course_credits']["$field"];
            }
            if($default > 0) {
                $parent_class = '';
                if(isset($flms_settings['course_credits']["$field"]['parent'])) {
                    $parent = $flms_settings['course_credits']["$field"]['parent'];
                    if($parent != 'none') {
                        $parent_class = ' has-parent';
                    }
                }    
                $has_credits = true;
                $return .= '<div class="flms-course-credits flms-table-item flms-background-border'.$parent_class.'">';
                    $return .= '<div class="col-1-2 flms-background-border">'.$label.'</div><div class="col-auto credit flms-background-border">'.$default.'</div>';
                $return .= '</div>';
            }
        }
        $return .= '</div>';
        $credits = '';
        if($has_credits) {
            if($show_label) {
                //$credits .= '<'.$heading_wrap.'>'.$flms_settings['labels']['credits'].'</'.$heading_wrap.'>';
            }
            
            $credits .= $return;
        }
        
        if($echo) {
            echo $credits;
        } else {
            return $credits;
        }
        
    }

    public function get_credit_label($field) {
        global $flms_settings;
        if(isset($flms_settings['labels'][$field])) {
            $label = $flms_settings['labels'][$field];
        } else if(isset($flms_settings['course_credits'][$field]["name"])) {
            $label = $flms_settings['course_credits'][$field]["name"];
        } else {
            $label = $field;
        }
        return $label;
    }

    public function flms_get_course_credits_array($course_version_content = null, $course_version = null) {
        global $flms_settings, $flms_course_id;
        if($course_version_content == null) {
            global $flms_course_version_content;
            $course_version_content = $flms_course_version_content;
        } 
        if($course_version == null) {
            global $flms_active_version;
            $course_version = $flms_active_version;
        }
        $course_credit_fields = $this->get_course_credit_fields();
        $return = array();
        foreach($course_credit_fields as $field) {
            /*if(isset($flms_settings['labels'][$field])) {
                $label = $flms_settings['labels'][$field];
            } else if(isset($flms_settings['course_credits'][$field]["name"])) {
                $label = $flms_settings['course_credits'][$field]["name"];  
            } else {
                //print_r($field);
                $label = $field;
                //$label = $field["$field.'-name"];
            }*/
            if(isset($course_version_content["$course_version"]['course_credits']["$field"])) {
                if($course_version_content["$course_version"]['course_credits']["$field"] > 0) {
                    $return[$field] = $course_version_content["$course_version"]['course_credits']["$field"];
                }
                
            }
        }
        return $return;
    }
    
    public function get_ecommerce_options() {
        global $flms_settings;
        $label = 'course credits';
        if(isset($flms_settings['labels']['credits'])) {
            $label = strtolower($flms_settings['labels']['credits']);
        }
        $credits_label = 'course credits';
        /*if(isset($flms_settings['woocommerce']['my_credits_tab_name'])) {
            $label = strtolower($flms_settings['woocommerce']['my_credits_tab_name']);
        }*/
        $credits_name = 'Course Credits';
		if(isset($flms_settings['labels']["course_credits"])) {
			$credits_name = strtolower($flms_settings['labels']["course_credits"]);
		}
        $licenses_label = 'Licenses';
        if(isset($flms_settings['labels']['license_plural'])) {
            $licenses_label = $flms_settings['labels']['license_plural'];
        }
        $lowercase_license = strtolower($licenses_label);
        $fields = array(
            array(
                'label' => "Course Credits",
                'key' => 'course_credits_heading',
                'type' => 'section_heading',
            ),
            array(
                'label' => 'Show '.$credits_label.' account tab',
                'key' => 'my_credits_tab',
                'type' => 'radio',
                'options' => array(
                    'show' => 'Show',
                    'hide' => 'Hide'
                ),
                'default' => 'show',
                'flag_check' => '',
                'description' => ''
            ),
            array(
                'label' => "Course Credits tab name",
                'key' => 'my_credits_tab_name',
                'type' => 'text',
                'default' => 'My '.$credits_name,
            ),
            array(
                'label' => $licenses_label.' account location',
                'key' => 'my_licensess_account_location',
                'type' => 'radio',
                'options' => array(
                    'edi-account' => 'Account details',
                    'tab' => 'Tab',
                ),
                'default' => 'edit-account',
                'flag_check' => '',
                'description' => ''
            ),
            array(
                'label' => "$licenses_label tab name",
                'key' => 'my_licenses_tab_name',
                'type' => 'text',
                'default' => 'My '.$licenses_label,
                'description' => "Used when $lowercase_license account location is set to tab",
            ),
            array(
                'label' => "Account details course credits $lowercase_license heading",
                'key' => 'account_details_tab_heading',
                'type' => 'text',
                'default' => 'Course credit licenses',
                'description' => "Used when $lowercase_license account location is set to Account Details. A separator heading above the licenses when editing a profile."
            ),
            array(
                'label' => "Account details license explanation",
                'key' => 'account_details_tab_explanation',
                'type' => 'text',
                'default' => 'Please enter your licenses for each credit field.',
                'description' => 'Text above the licenses when editing a profile.'
            ),
            array(
                'label' => 'Show missing '.$credits_label.' licenses notice in account',
                'key' => 'missing_licenses_notice',
                'type' => 'radio',
                'options' => array(
                    'show' => 'Show',
                    'hide' => 'Hide'
                ),
                'default' => 'show',
                'flag_check' => '',
                'description' => ''
            ),
            array(
                'label' => 'Show '.$label.' in shop',
                'key' => 'shop_course_credits',
                'type' => 'radio',
                'options' => array(
                    'show' => 'Show',
                    'hide' => 'Hide'
                ),
                'default' => 'show',
                'flag_check' => '',
                'description' => ''
            ),
            array(
                'label' => 'Show '.$label.' on single products',
                'key' => 'product_course_credits',
                'type' => 'radio',
                'options' => array(
                    'show' => 'Show',
                    'hide' => 'Hide'
                ),
                'default' => 'show',
                'flag_check' => '',
                'description' => ''
            ),
            array(
                'label' => 'Show '.$label.' in cart items',
                'key' => 'cart_course_credits',
                'type' => 'radio',
                'options' => array(
                    'show' => 'Show',
                    'hide' => 'Hide'
                ),
                'default' => 'show',
                'flag_check' => '',
                'description' => ''
            ),
            array(
                'label' => 'Show '.$label.' cart summary',
                'key' => 'cart_course_credits_summary',
                'type' => 'radio',
                'options' => array(
                    'show' => 'Show',
                    'hide' => 'Hide'
                ),
                'default' => 'show',
                'flag_check' => '',
                'description' => ''
            ),
            array(
                'label' => 'Show '.$label.' in checkout',
                'key' => 'checkout_course_credits',
                'type' => 'radio',
                'options' => array(
                    'show' => 'Show',
                    'hide' => 'Hide'
                ),
                'default' => 'show',
                'flag_check' => '',
                'description' => ''
            ),
        );
        return $fields;
    }

    public function get_course_credit_labels() {
        $fields = array(
            array(
                'label' => 'Course Credits',
                'key' => 'credits',
                'type' => 'text',
                'default' => 'Course Credits',
            ),
            array(
                'label' => 'License (Singular)',
                'key' => 'license_singular',
                'type' => 'text',
                'default' => 'License',
            ),
            array(
                'label' => 'License (Plural)',
                'key' => 'license_plural',
                'type' => 'text',
                'default' => 'Licenses',
            ),
            array(
                'label' => 'Cart Credits Summary',
                'key' => 'credits_summary',
                'type' => 'text',
                'default' => 'Credits Summary',
            ),
            array(
                'label' => 'Reporting Fee',
                'key' => 'reporting_fee',
                'type' => 'text',
                'default' => 'Reporting Fee',
            ),
            array(
                'label' => 'Select Credit Reporting',
                'key' => 'select_credit_reporting',
                'type' => 'text',
                'default' => 'Select Credit Reporting',
            )
        );
        $available_fields = $this->get_course_credits_fields(true, true, true);
        if(!empty($available_fields)) {
            foreach($available_fields as $field) {
                $fields[] = array(
                    'label' => $field['label'],
                    'key' => $field['key'],
                    'type' => 'text',
                    'default' => $field['label'],
                );      
            }
        }
        return $fields;
    }
    
    public function get_course_credits_fields($exclude_dynamic = false, $sort = false, $exclude_custom = false) {
        global $flms_settings;

        if(!$exclude_dynamic) {
            $default_fields = array(
                array(
                    'label' => 'Create Course Credit Field',
                    'key' => 'create-course-credit-field',
                    'type' => 'dynamic',
                    'class' => $this->class_name,
                    'function' => 'create_course_credit_type',
                ) 
            );
        } else {
            $default_fields = array();
        }

        $fields = array();
        if(isset($flms_settings['course_credits']) && !$exclude_custom) {
            $existing_fields = $flms_settings['course_credits'];
            $custom_credit_fields = $this->get_custom_credit_fields();
            foreach($existing_fields as $k => $v) {
                if(isset($v["custom"])) {
                    $credit_name = $k;
                    //echo '<pre>'.print_r($v,true).'</pre>';
                    $name = $v["name"];
                    $status = $v["status"];
                    $license = $v["license-required"];
                    $fee_type = $v["reporting-fee-status"];
                    $fee = $v["reporting-fee"];
                    $description = $v["reporting-fee-description"];
                    $parent = $v["parent"];
                    $form_fields = $this->replace_tmp_fields($credit_name, $custom_credit_fields, $name, $status, $license, $fee_type, $fee, $description, $parent);
                    //print_r($form_fields);
                    //print_r($form_fields);
                    $fields[] = array(
                        'label' => $v["name"],
                        'key' => $k,
                        'type' => 'group',
                        'sortable' => 'handle',
                        'group_fields' => $form_fields
                    );
                }

            }
        } else {
            $fields = array();  
        }
        
        //$fields = array();
        if(flms_is_module_active('course_credits_financial')) {
            $financial_addon = new FLMS_Module_Course_Credits_Financial_Addon();
            $financial_fields = $financial_addon->get_financial_fields();
            $fields = array_merge($fields,$financial_fields);
        }
        
        /*if(isset($flms_settings['course_credits'])) {
            $sorted_fields = array();
            foreach($fields as $k => $credit) {
                //echo '<pre>'.$k.':<br>'.print_r($credit,true).'</pre>';
                foreach($fields as $field) {
                    if($field['key'] == $k) {
                        $sorted_fields[] = $field;
                        break;
                    }
                }
            }
            //$fields = array_merge($default_fields,$sorted_fields);
            //print_r($fields);
        }*/

        if($sort) {
            if(isset($flms_settings['course_credits'])) {
                $sorted_fields = array();
                foreach($flms_settings['course_credits'] as $k => $credit) {
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

    public function replace_tmp_fields($credit_name, $form_fields, $name, $status, $license, $fee_type, $fee, $description, $parent) {
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
			} else if($form_field['key'] == "parent") {
				$form_field['default'] = $parent;
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

    public function create_course_credit_type() {
        echo $this->create_course_credit_type_form();
    }

    public function create_course_credit_type_form() {
        $settings_class = new FLMS_Settings();
        $form = '<div id="create-course-credit-form">';
            ob_start();
            $form_field_category = 'tmp_create_course_credits';
            $form_fields = $this->get_custom_credit_fields();
            foreach($form_fields as $form_field) {
                flms_print_field_input($form_field, $form_field_category);
            }
            $form .= ob_get_clean();
            $form .= '<button class="button button-primary" id="create-course-credit-field">Create Credit Field</button>';
        $form .= '</div>';
        return $form;
    }

    public function get_custom_credit_fields() {
        $prefix = '$';
        if(function_exists('get_woocommerce_currency_symbol')) {
            $prefix = get_woocommerce_currency_symbol();
        }
        global $flms_settings;
        $license_label = $flms_settings['labels']['license_singular'];
        $form_fields = array(
            array(
                'label' => 'Name',
                'key' => 'tmp-course-credit-name',
                'type' => 'text',
                'default' => '',
                'description' => ''
            ),
            array(
                'label' => 'Status',
                'key' => "tmp-course-credit-status",
                'type' => 'radio',
                'options' => array(
                    'active' => 'Active',
                    'inactive' => 'Inactive'
                ),
                'default' => 'active',
            ),
            array(
                'label' => "$license_label Required",
                'key' => "tmp-course-credit-license-required",
                'type' => 'select',
                'options' => array(
                    'required' => 'Required',
                    'optional' => 'Optional',
                    'none' => "No $license_label"
                ),
                'default' => 'required',
                'description' => 'Requiring a license adds this field to the user&rsquo;s profile and checks for the license during reporting. <br>Optional licenses show the field in the profile but does not require it for reporting.',
            ),
            array(
                'label' => 'Reporting Fee',
                'key' => "tmp-course-credit-reporting-fee-status",
                'description' => 'Requires an active ecommerce module.',
                'type' => 'select',
                'options' => array(
                    'none' => 'None',
                    'flat-fee' => 'Flat Fee',
                    'per-credit' => 'Per Credit'
                ),
                'default' => 'none',
                'conditional_toggle' => array(
                    'field' => ".tmp-course-credit-reporting-fee, .tmp-course-credit-reporting-fee-description",
                    'action' => 'hide',
                    'value' => 'none'
                ),
            ),
            array(
                'label' => 'Fee',
                'key' => "tmp-course-credit-reporting-fee",
                'type' => 'currency',
                'prefix' => $prefix,
                'default' => '0',
            ),
            array(
                'label' => 'Description',
                'key' => "tmp-course-credit-reporting-fee-description",
                'type' => 'text',
                'default' => '',
                'description' => 'A short description of what this fee entails'
            ),
            array(
                'label' => 'Parent',
                'key' => "tmp-course-credit-parent",
                'type' => 'select',
                'options' => $this->get_course_credit_dropdown(),
                'default' => '',
                'description' => 'Setting a parent allows you to show and report on credits for this field, but the license is inherited from the parent'
            ),
            array(
                'label' => 'Custom',
                'key' => "tmp-course-credit-custom",
                'type' => 'hidden',
                'default' => '1',
                'description' => ''
            ),
            array(
                'label' => 'Custom Fee',
                'key' => "tmp-course-credit-custom",
                'type' => 'delete',
                'default' => 'Delete Credit Type',
            ),
        );
        return $form_fields;
    }

    public function get_course_credit_dropdown($active_only = true) {
        global $flms_settings, $flms_course_id, $flms_active_version, $flms_course_version_content;
        $course_credit_fields = array();
        $course_credit_fields['none'] = 'None';
        if(!empty($flms_settings['course_credits'])) {
            foreach($flms_settings['course_credits'] as $k => $v) {
                if(!$active_only) {
                    $course_credit_fields[] = $k;
                } else {
                    if($v["status"] == 'active') {
                        $course_credit_fields[$k] = $v["name"];
                        //$course_credit_fields[$k] = print_r($v,true);
                    }
                }
            }
        }
        return $course_credit_fields;
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

     
    public function get_course_credit_course_settings() {
        global $flms_settings, $flms_course_id, $flms_active_version, $flms_course_version_content;
        $course_credit_fields = $this->get_course_credit_fields();
        $return = '';
        $has_course_numbers = flms_is_module_active('course_numbers');
        $extra_classes = '';
        if($has_course_numbers) {
            $extra_classes .= ' col-3';
        }
        $return .= '<div class="settings-field '.$extra_classes.'">';
            $return .= '<label class="heading">Credit Type</label>';
            $return .= '<label class="heading">Credit Amount</label>';
            if($has_course_numbers) {
                $return .= '<label class="heading">Course Number</label>';
            }
        $return .= '</div>';
		foreach($course_credit_fields as $field) {
            if(isset($flms_settings['course_credits'][$field]["name"])) {
                $label = $flms_settings['course_credits'][$field]["name"];  
            } else if(isset($flms_settings['labels'][$field])) {
                $label = $flms_settings['labels'][$field];
            } else {
                //print_r($field);
                $label = $field;
                //$label = $field["$field.'-name"];
            }
            $default = 0;
            if(isset($flms_course_version_content["$flms_active_version"]['course_credits'][$field])) {
                $default = $flms_course_version_content["$flms_active_version"]['course_credits'][$field];
            }
            $return .= '<div class="settings-field '.$extra_classes.'">';
				$return .= '<label>'.$label.'</label>';
				$return .= '<input type="number" step="any" min="0" name="'.$field.'-credits" value="'.$default.'" />';
                if($has_course_numbers) {
                    $label .= ' course number';
                    $label = apply_filters('flms_credit_report_header', $label, $field);
                    $default = '';
                    if(isset($flms_course_version_content["$flms_active_version"]['course_numbers'][$field])) {
                        $default = $flms_course_version_content["$flms_active_version"]['course_numbers'][$field];
                    }
                    $return .= '<input type="text" placeholder="'.$label.'" name="'.$field.'-course-number" value="'.$default.'" />';
                }
            $return .= '</div>';
        }
        return $return;
    }

    
    public function get_course_credit_fields($active_only = true) {
        global $flms_settings, $flms_course_id, $flms_active_version, $flms_course_version_content;
        $course_credit_fields = array();
        if(!empty($flms_settings['course_credits'])) {
            foreach($flms_settings['course_credits'] as $k => $v) {
                if(!$active_only) {
                    $course_credit_fields[] = $k;
                } else {
                    if($v["status"] == 'active') {
                        $course_credit_fields[] = $k;
                    }
                }
            }
        }
        return $course_credit_fields;
    }

    public function update_course_credits($post_id, $active_version, $postdata) {
        $active_fields = $this->get_course_credit_fields();
        $credits = array();
        foreach($active_fields as $field) {
            $name = "$field-credits";
            if(isset($postdata[$name])) {
                $credits[$field] = floatval($postdata[$name]);
            }
        }
        $course = new FLMS_Course($post_id);
        global $flms_active_version;
        $flms_active_version = $active_version;
        $course->update_course_version_field('course_credits', $credits);
    }

    public function get_course_credits($course_data, $credits = array()) {
        if(is_array($course_data)) {
            $course_id = $course_data['course_id'];
            $course_version = $course_data['course_version'];
        } else {
            $course_info = explode(':',$course_data);
            $course_id = $course_info[0];
            $course_version = $course_info[1];
        }
        $course = new FLMS_Course($course_id);
        global $flms_active_version;
        $flms_active_version = $course_version;
        $new_credits = $this->flms_get_course_credits_array();
        foreach($new_credits as $k => $v) {
            if(!isset($credits[$k])) {
                $credits[$k] = 0;
            }
            $credits[$k] += $v;
        }
        return $credits;
    }

    public function course_credit_summary($credits) {
        $credit_summary = '<div class="credit-summary flms-table flms-background-border">';
        $credit_summary .= '<div class="credit-item flms-table-item heading flms-background-border">';
        $type = flms_get_label('license_singular');
        $credit_summary .= '<div class="type col-1-4 flms-background-border">'.$type.'</div><div class="value 3-4">Total</div>';
        $credit_summary .= '</div>';
        foreach($credits as $k => $v) {
            $credit_summary .= '<div class="credit-item flms-table-item flms-background-border">';
            $credit_summary .= '<div class="type col-1-4 flms-background-border">'.flms_get_label($k).'</div><div class="value 3-4">'.$v.'</div>';
            $credit_summary .= '</div>';
        }
        $credit_summary .= '</div>';
        return $credit_summary;
    }

    public function print_shop_course_credits($credits) {
        $return = '';
        foreach($credits as $k => $v) {
            $return .= '<div class="flms-course-credit">';
                $return .= '<div>'.flms_get_label($k).'</div><div>'.$v.'</div>';
            $return .= '</div>';
        }
        return $return;
    }

    public function get_missing_credit_licenses($courses) {
        global $flms_settings, $current_user;
        $user_id = $current_user->ID;
        $missing_credits = array();
        if(empty($courses)) {
            return false;
        }
        $credits = array();
        foreach($courses as $active_course) {
            $credits = $this->get_course_credits($active_course, $credits);
        }
        //check if user needs licesnes entered
        foreach($credits as $field => $value) {
            $display = '';
            if(isset($flms_settings['course_credits'][$field]["license-required"])) {
                $display = $flms_settings['course_credits'][$field]["license-required"];
            }
            $user_has_license = get_user_meta( $user_id, "flms_has-license-$field", true);
            if($user_has_license == '' && ($display == 'required' || $display == 'optional')) {
                $label = $this->get_credit_label($field);
                $missing_credits[] = $label;
            } elseif($display == 'required') {
                //check for license
                $value = get_user_meta( $user_id, "flms_license-$field", true);
                if($value == '') {
                    $label = $this->get_credit_label($field);
                    $missing_credits[] = $label;
                }
            }
            
        }
        if(!empty($missing_credits)) {
            return $missing_credits;
        }
        return false;
        
    }

    public function output_missing_credits_message($message, $licenses) {
        //
    }

    public function get_user_license_fields($user, $location = '', $output = 'div') {
        global $flms_settings;
        $course_credit_fields = $this->get_course_credit_fields(); 
        $has_credits = false;
        $field_data = '';
        $form_row_extra_classes = '';
        if($location == 'woocommerce') {
            $form_row_extra_classes = 'woocommerce-form-row';
        }
        if($output == 'table') {
            $field_data .= '<table class="form-table course-credits">';
        }
        foreach($course_credit_fields as $field) {
            $display = '';
            if(isset($flms_settings['course_credits'][$field]["license-required"])) {
                $display = $flms_settings['course_credits'][$field]["license-required"];
            }
            $parent = 'none';
            if(isset($flms_settings['course_credits'][$field]["parent"])) {
                $parent = $flms_settings['course_credits'][$field]["parent"];
            }
            if($display != 'none' && $parent == 'none') {
                $has_credits = true;
                if(isset($flms_settings['labels'][$field])) {
                    $label = $flms_settings['labels'][$field];
                } else if(isset($flms_settings['course_credits'][$field]["name"])) {
                    $label = $flms_settings['course_credits'][$field]["name"];  
                } else {
                    //print_r($field);
                    $label = $field;
                    //$label = $field["$field.'-name"];
                }
                if($output == 'table') {
                    $field_data .= '<tr class="flms-credit-field flms-credit-field-'.$field.'">';
                    $field_data .= '<th>';
                } else {
                    $field_data .= '<div class="form-row flms-flex mobile-flex '.$form_row_extra_classes.' flms-credit-field flms-credit-field-'.$field.'">';
                }
                
                $field_data .=  '<label for="'.$field.'" class="flms-label-full-width full-flex">'.$label.'</label>';
                if($output == 'table') {
                    $field_data .= '</th><td>';
                }

                $has_value = get_user_meta( $user->ID, "flms_has-license-$field", true);
                //print_r(get_user_meta( $user->ID));
                if(isset($_POST["has-$field"])) {
                    $has_value = sanitize_text_field( $_POST["has-$field"] );
                }
                $input_status = 'disabled';
                $checked = '';
                if($has_value == 'on') {
                    $input_status = '';
                    $checked = 'checked="checked"';
                }
                $field_data .= '<input type="checkbox" name="has-'.$field.'" '.$checked.' class="flms-conditional-checkbox flms-checkbox checkbox-large" />';
                $value = get_user_meta( $user->ID, "flms_license-$field", true);
                if(isset($_POST[$field])) {
                    $value = sanitize_text_field( $_POST[$field] );
                }
                $license_label = apply_filters('flms_license_placeholder', $flms_settings['labels']['license_singular'], $field, $display);
                if(apply_filters('flms_show_'.$field.'_license_input', true)) {
                    $field_data .=  '<input type="text" class="needs-checkbox-checked regular-text woocommerce-Input woocommerce-Input--text input-text" name="'.$field.'" id="'.$field.'" value="'.$value.'" placeholder="'.$license_label.'" '.$input_status.' />';
                    if($display == 'required') {
                        $field_data .= apply_filters('flms_after_required_license_field', '');
                    }
                }
                $addition_data = '';
                $field_data .= apply_filters('flms_additional_license_data', $addition_data, $field, $user->ID, $checked);
                if(apply_filters('flms_show_'.$field.'_license_input', true)) {
                    if($has_value == 'on' && $value == '' && $display == 'required') {
                        $required = apply_filters('flms_required_license_text', 'A '.strtolower($license_label).' number is required for '.$label.'.', $label);
                        $field_data .= '<div class="spacer-left-1 required-license-error flms-error full-flex">'.$required.'</div>';
                    }
                }
                if($output == 'table') {
                    $field_data .= '</td>';
                    $field_data .= '</tr>';
                } else {
                    $field_data .= '</div>';
                }
            }
        }
        if($output == 'table') {
            $field_data .= '</table>';
        }
        $field_data .= '<input type="hidden" name="flms-user-licenses-form" value="1" />';
        return array(
            'has_credits' => $has_credits,
            'field_data' => $field_data
        );
    }

    public function init_profile_fields() {
        add_action( 'show_user_profile', array($this, 'course_credit_profile_fields') );
		add_action( 'edit_user_profile', array($this, 'course_credit_profile_fields') );
        add_action( 'personal_options_update', array($this,'save_extra_user_profile_fields') );
        add_action( 'edit_user_profile_update', array($this,'save_extra_user_profile_fields') );
        add_action( 'woocommerce_save_account_details', array($this,'save_extra_user_profile_fields') );
        
    }

    /**
     * Add fields to woo user profile in admin
     */
    public function course_credit_profile_fields($user) {
        global $flms_settings;
        $course_credits = new FLMS_Module_Course_Credits();
        $user_data = $course_credits->get_user_license_fields($user, 'profile-editor', 'table'); 
        $has_credits = false;
        if(isset($user_data['has_credits'])) {
            $has_credits = $user_data['has_credits'];
        }
        $field_data = '';
        if(isset($user_data['field_data'])) {
            $field_data = $user_data['field_data'];
        }
        if($has_credits) {
            $label = $flms_settings['labels']['credits'];
            _e("<h3>User $label Information</h3>", 'flms');
            echo $field_data;
        }
        
    }

    public function save_extra_user_profile_fields($user_id) {
        $this->save_user_license_fields($user_id, $_POST);
    }

    public function save_user_license_fields($user_id, $data) {
        if(!isset( $data['flms-user-licenses-form'] )) {
            return;
        }
        $course_credit_fields = $this->get_course_credit_fields(); 
        do_action('flms_before_save_user_profile_fields',$user_id, $data);
        foreach($course_credit_fields as $field) {
            if ( isset( $data[$field] ) ) {
                update_user_meta( $user_id, "flms_license-$field", sanitize_text_field( $data[$field] ) );
            }
            if ( isset( $data["has-$field"] ) ) {
                update_user_meta( $user_id, "flms_has-license-$field", 'on' );
            } else {
                delete_user_meta( $user_id, "flms_has-license-$field" );
            }
        }
        do_action('flms_after_save_user_profile_fields',$user_id, $data);

    }

}
new FLMS_Module_Course_Credits();