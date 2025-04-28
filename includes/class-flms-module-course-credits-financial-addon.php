<?php
class FLMS_Module_Course_Credits_Financial_Addon {
    public function __construct() {
		//add_action('init',array($this,'test_int'));
	}
    public function get_financial_fields() {
        $prefix = '$';
        if(function_exists('get_woocommerce_currency_symbol')) {
            $prefix = get_woocommerce_currency_symbol();
        }
        $types = array(
            'cpa' => 'CPA',
            'cfp' => 'CFP',
            'eaotrp' => 'EA / OTRP',
            'erpa' => 'ERPA',
            'cdfa' => 'CDFA',
        );
        $fields = array();
        global $flms_settings;
        $license_label = $flms_settings['labels']['license_singular'];
        $course_credit_fields = array();
        foreach($types as $k => $v) {
            $course_credit_settings_fields = array(
                'label' => $v,
                'key' => $k,
                'type' => 'group',
                'sortable' => 'handle',
                'group_fields' => array(
                    array(
                        'label' => 'Name',
                        'key' => "name",
                        'type' => 'hidden',
                        'default' => $v,
                    ),
                    array(
                        'label' => 'Status',
                        'key' => "status",
                        'type' => 'radio',
                        'options' => array(
                            'active' => 'Active',
                            'inactive' => 'Inactive'
                        ),
                        'default' => 'active',
                    ),
                    array(
                        'label' => "$license_label Required",
                        'key' => "license-required",
                        'type' => 'select',
                        'options' => array(
                            'required' => 'Required',
                            'optional' => 'Optional',
                            'none' => "No $license_label"
                        ),
                        'default' => 'required',
                        'description' => 'Requiring a license adds this field to the user&rsquo;s profile and checks for the license during reporting. <br>Optional licenses show the field in the profile but does not require it for reporting.',
                    ),
                )
            );
            
            if(flms_is_module_active('woocommerce')) {
                //echo '<pre>'.print_r($fields, true).'</pre>';
                $woo_fields = array(
                    array(
                        'label' => 'Reporting Fee',
                        'key' => "reporting-fee-status",
                        'description' => 'Requires an active ecommerce module.',
                        'type' => 'select',
                        'options' => array(
                            'none' => 'None',
                            'flat-fee' => 'Flat Fee',
                            'per-credit' => 'Per Credit'
                        ),
                        'default' => 'none',
                        'conditional_toggle' => array(
                            'field' => "#course_credits-$k .reporting-fee, #course_credits-$k .reporting-fee-description",
                            'action' => 'hide',
                            'value' => 'none'
                        ),
                    ),
                    array(
                        'label' => 'Fee',
                        'key' => "reporting-fee",
                        'type' => 'currency',
                        'prefix' => $prefix,
                        'default' => '0',
                    ),
                    array(
                        'label' => 'Description',
                        'key' => "reporting-fee-description",
                        'type' => 'text',
                        'default' => '',
                        'description' => 'A short description of what this fee entails'
                    ),
                );
                $course_credit_settings_fields['group_fields'] = array_merge($course_credit_settings_fields['group_fields'], $woo_fields);
            }
            $fields[] = array_merge($course_credit_fields,$course_credit_settings_fields,$course_credit_settings_fields);
        }
        //$fields[] = $course_credit_fields;
        return $fields;
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
}
//new FLMS_Module_Course_Credits_Financial_Addon();