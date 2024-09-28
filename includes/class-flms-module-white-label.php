<?php
class FLMS_Module_White_Label {
    public function __construct() {
		//add_action('init',array($this,'test_int'));
	}
    public function get_white_label_fields() {
        $fields = array(
            array(
                'label' => 'Menu Title',
                'key' => 'menu_title',
                'type' => 'text',
                'default' => 'Fragments LMS',
            ),
            array(
                'label' => 'Shortcode Prefix',
                'key' => 'shortcode_prefix',
                'description' => 'Override the default flms prefix with a white label prefix. Lowercase, no spaces or special characters.',
                'type' => 'text',
                'default' => 'flms',
            ),
        );
        $json = file_get_contents(FLMS_ABSPATH . 'assets/dashicons.json'); //https://github.com/WordPress/dashicons/tree/master
        $json_data = json_decode($json,true); 
        ksort( $json_data );
        $dashicons = array();
        $dashicons["default"] = 'Default';
        foreach($json_data as $k => $v) {
            $dashicons["$k"] = ucwords(str_replace('-',' ',$k));
        }
        $fields[] = array(
            'label' => 'Menu Icon',
            'key' => 'menu_icon',
            'type' => 'icon_select',
            'options' => $dashicons,
            'default' => '',
        );
        return $fields;
    }

    public function get_white_label_field($field) {
        global $flms_settings;
        $return = '';
        if(isset($flms_settings['white_label'])) {
            $white_label_fields = $flms_settings['white_label'];
            if(isset($white_label_fields["$field"])) {
                $return = $white_label_fields["$field"];
            } 
        }
        return $return;
    }
}
new FLMS_Module_White_Label();