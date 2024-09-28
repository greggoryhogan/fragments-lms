<?php
class FLMS_Module_ACF {
    public function __construct() {
        global $acf_version_field, $flms_active_version, $post, $acf_version_fields;
        $acf_version_fields = '';
        /*if(isset($post)) {
            $post_id = $post->ID;
            $identifier = "$post_id:$flms_active_version";
            $acf_version_fields = get_post_meta($post_id,"$identifier-acf-fields",true);
        }*/
		add_filter('acf/load_value', array($this,'load_acf_field'), 10, 3);
        add_filter('acf/save_post', array($this,'save_version_fields'), 10, 1);
        //add_filter('acf/pre_save_post',array($this,'set_post_post_id'),99);
        //add_filter('acf/validate_post_id',array($this,'set_post_post_id'),99);
	}

    public function set_post_post_id($post_id) {
        global $post, $flms_active_version, $acf_version_fields;
        $identifier = 7;
        return $identifier;
    }

    public function save_version_fields($post_id) { //$version, $post_data
        $acf_version_fields = array();
        global $post, $flms_active_version;
        if($flms_active_version == '') {
            return;
        }
        $identifier = "{$post_id}_{$flms_active_version}";
        if(isset($_POST['acf'])) {
            $acf_version_fields = $_POST['acf'];
            flms_debug($acf_version_fields);
            /*$acf_version_fields = $this->flatten($post_data['acf']);
            // Loop over values.
            foreach ( $post_data as $key => $value ) {

                // Get field.
                $field = acf_get_field( $key );

                // Update value.
                if ( $field ) {
                    
                    // Update meta.
                    $return = acf_update_metadata( $post_id, $identifier.':'.$field['name'], $value );

                    // Update reference.
                    acf_update_metadata( $post_id, $identifier.':'.$field['name'], $field['key'], true );

                    // Delete stored data.
	                acf_flush_value_cache( $post_id, $identifier.':'.$field['name'] );

                }
            }*/
        }
        
        
        
        $fields = get_fields();
        $field_values = array();
        foreach( $fields as $name => $value ) {
            $field_values[$name] = $value;
        }

        $values = array_merge($acf_version_fields, $field_values);
        //flms_debug($acf_version_fields,'saved');
        update_post_meta($post_id,"flms_acf_fields_$identifier",$acf_version_fields);
    }

    public function load_acf_field($value, $post_id, $field) {
        if($value != '') {
            //echo '<pre>'.print_r($field,true).'</pre>';
        }
        global $post, $flms_active_version, $acf_version_fields;
        //if($acf_version_fields == '') {
            $identifier = "{$post_id}_{$flms_active_version}";
            $acf_version_fields = maybe_unserialize(get_post_meta($post_id,"flms_acf_fields_$identifier",true));
            //echo $identifier;
            //echo '<pre>'.print_r($acf_version_fields,true).'</pre>';
        //}
        $key = $field['key'];
        if(isset($acf_version_fields[$key])){
            if(is_array($acf_version_fields[$key])) {
                //echo $key.' - '.print_r($acf_version_fields[$key],true).'<br>';
            } else {
                //echo $key.' - '.$acf_version_fields[$key].'<br>';
            }
            $value = $acf_version_fields[$key];
            //print_r($value);
        } else {
            //echo 'Missing: '.$key.'<br>';
        }

        return $value;
    }

    public function custom_acf_get_field_name_by_key( $key ) {

        $field = acf_maybe_get_field( $key );
        if($field == false) {
            return '';
        }
        //$field = get_field_object($key);
        if(isset($field['type'])) {
            if($field['type'] == 'clone') {
                return '';
            }
        }
        return $field['name'];
    
    }
    
    public function flatten($array, $prefix = '') {
        
        $result = array();
        foreach($array as $key => $value) {
            $daname = $prefix.$key;
            $names = explode('_',$daname);
            if(is_array($names)) {
                foreach($names as $name) {
                    $process_name = 'field_'.str_replace('_','',$name);
                    $keyname = $this->custom_acf_get_field_name_by_key($process_name);
                    $daname = str_replace($process_name, $keyname, $daname);
                    
                }
            }
            $daname = str_replace('row-','',$daname);
            $daname = str_replace('__','_',$daname);
            if(is_array($value)) {
                //$result[$daname] = $value;
                $result = $result + $this->flatten($value, $prefix . $key . '_');
            }
            else {
                //$result[$prefix . $key] = $value;
                $result[$daname] = $value;
            }
        }
        
        return $result;
    }
}
new FLMS_Module_ACF();