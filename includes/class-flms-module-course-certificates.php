<?php

class FLMS_Module_Course_Certificates {

    public $class_name = 'FLMS_Module_Course_Certificates';

    public function __construct() {
        add_action( 'add_meta_boxes', array($this,'flms_register_meta_boxes') );
	}
    
    public function register_cpt() {
        //Register Certificates cpt
		$labels = array(
			"name" => __( "Certificates", "" ),
			"singular_name" => __( "Certificate", "flms" ),
			'all_items' => __( "Certificates", "flms" ),
			'edit_item' => __( "Edit Certificate", "flms" ),
			'update_item' => __( "Update Certificate", "flms" ),
			'add_new' => __( "Add New Certificate", "flms" ),
			'add_new_item' => __( "Add New Certificate", "flms" ),
			'new_item_name' => __( "New Certificate", "flms" ),
			'menu_name' => __( "Certificates", "flms" ),
			'back_to_items' => __( "&laquo; All Certificates", "flms" ),
			'not_found' => __( "No Certificates found.", "flms" ),
			'not_found_in_trash' => __( "No Certificates found in trash.", "flms" ),
		);
		$args = array(
			"label" => __( "Certificates", "flms" ),
			"labels" => $labels,
			"description" => "",
			"public" => false,
			"publicly_queryable" => true,
			"show_ui" => true,
			"show_in_rest" => true,
			"rest_base" => "",
			"has_archive" => true,
			'show_in_nav_menus' => false,
			"show_in_menu" => false,
			"exclude_from_search" => true,
			"capability_type" => "page",
			"map_meta_cap" => true,
			"query_var" => true,
			'hierarchical' => false,
			"rewrite" => false,
			"supports" => array('title','editor','custom_fields'),
			'capabilities' => array(
				'edit_post'          => 'edit_post' ,
				'read_post'          => 'read_post' ,
				'delete_post'        => 'delete_post',
				'edit_posts'         => 'edit_posts' ,
				'edit_others_posts'  => 'edit_others_posts',
				'delete_posts'       => 'delete_posts',
				'publish_posts'      =>'publish_posts',
				'read_private_posts' => 'read_private_posts'
			),
			//"taxonomies" => array( "supplier" ),
		);
		register_post_type( "flms-certificates", $args );
        remove_post_type_support( 'flms-certificates', 'thumbnail' );
    }

    public function register_rewrite_rule() {
        global $flms_settings;
		// Handle URLs with 'version' for child pages
		$certificate_permalink = $this->get_certificate_permalink();
       
        add_rewrite_rule(
			"^{$certificate_permalink}/([^/]+)/([^/]+)/([^/]+)/?$",
			'index.php?post_type=flms-certificates&certificate-course=$matches[1]&certificate-course-version=$matches[2]&certificate-user=$matches[3]',
			'top'
		);
        flush_rewrite_rules();
    }

    public function register_query_vars($query_vars) {
        $query_vars[] = 'certificate-course';
        $query_vars[] = 'certificate-course-version';
        $query_vars[] = 'certificate-user';
        //echo '<pre>'.print_r($query_vars,true).'</pre>';
        return $query_vars;
    }

    public function get_shortcodes() {
		$shortcodes = array(
			'certificate-owner' => array(
				'description' => 'Display first and/or last name of the user',
				'atts' => array(
                    'first' => 'Display user first name, defaults to true',
                    'last' => 'Display user last name, defaults to true',
					'before' => 'Text before the taxonomy',	
                    'after' => 'Text after the taxonomy',	
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

    public function flms_certificate_owner_shortcode($atts) {
        global $post;
        $default_atts = array(
            'before' => '',
            'after' => '',
            'first' => 'true',
            'last' => 'true',
        );
        $atts = shortcode_atts( $default_atts, $atts, 'flms-taxonomy' );
        global $wp;
        if(isset($wp->query_vars)) {
            $query_vars = $wp->query_vars;
            if(isset($query_vars['certificate-user'])) {
                $user_id = $query_vars['certificate-user'];
                if($user_id) {
                    $user = get_user_by( 'id', $user_id);
                    if($user !== false) {
                        $fname = $user->first_name;
                        $lname = $user->last_name;
                        $return = $atts['before'];
                        if($atts['first'] != 'false') {
                            $return .= $fname;
                        }
                        if($atts['first'] != 'false' && $atts['last'] != 'false') {
                            $return .= ' ';
                        }
                        if($atts['last'] != 'false') {
                            $return .= $lname;
                        }
                        $return .= $atts['after'];
                        
                    }
                }
            }
        }
        return $return;
    }

    public function get_course_certificate_labels() {
        $fields = array(
            array(
                'label' => 'Certificate (Singular)',
                'key' => 'certificate_singular',
                'type' => 'text',
                'default' => 'Certificate',
            ),
            array(
                'label' => 'Certificate (Plural)',
                'key' => 'certificate_plural',
                'type' => 'text',
                'default' => 'Certificates',
            ),
        );
        return $fields;
    }

    public function get_certificate_label($singular = true, $lowercase = false) {
        global $flms_settings;
        if($singular) {
            $label = 'Certificate';
            if(isset($flms_settings['labels']['certificate_singular'])) {
                $label = $flms_settings['labels']['certificate_singular'];
            }
        } else {
            $label = 'Certificates';
            if(isset($flms_settings['labels']['certificate_plural'])) {
                $label = $flms_settings['labels']['certificate_plural'];
            }
        }
        if($lowercase) {
            $label = strtolower($label);
        }
        return $label;
    }

    public function get_certificate_permalink() {
        $rewrite = 'certificate';
        global $flms_settings;
        if(isset($flms_settings['custom_post_types']['certificate_permalink'])) {
            $rewrite = $flms_settings['custom_post_types']['certificate_permalink'];
        }
        return $rewrite;
    }

    public function flms_register_meta_boxes() {
		add_meta_box( 'flms-certificate-settings', __( 'Certificate Settings', 'textdomain' ), array($this,'flms_certificates_metabox'), 'flms-certificates', 'normal', 'high' );
        add_meta_box( 'flms-certificate-preview', __( 'Certificate Preview', 'textdomain' ), array($this,'flms_certificates_preview_metabox'), 'flms-certificates', 'side', 'default' );
	}

    public function flms_certificates_metabox() {
        global $flms_settings;
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script(
			'flms-admin-certificates',
			FLMS_PLUGIN_URL . 'assets/js/admin-certificates.js',
			array('jquery', 'wp-color-picker'),
			false,
			true
		);
        wp_localize_script( 'flms-admin-certificates', 'flms_admin_certificates', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'certificate_link' => get_bloginfo('url').'/'.$this->get_certificate_permalink()
        ));
        global $post;
        $certificate_settings = get_post_meta($post->ID,'flms_certificate_settings', true);
        echo '<div class="flms-tabs certificate-tabs theme-color">';
            echo '<div class="tab is-active" data-tab="#options">Settings</div>';
            echo '<div class="tab" data-tab="#shortcodes">Shortcode Reference</div>';
		echo '</div>';

        echo '<div class="flms-tab-section is-active" id="options">';
            /*echo '<div>';
                echo '<label>Heading Color</label>';
                echo '<p class="description"></p>';
                $default = $flms_settings['design']['primary_color'];
                if(isset($certificate_settings['heading_color'])) {
                    $default = $certificate_settings['heading_color'];
                }
                echo '<input type="text" placeholder="'.$default.'" class="flms-color-picker" value="'.$default.'" name="certificate-heading-color" />';
            echo '</div>';
            echo '<div>';
                echo '<label>Text Color</label>';
                echo '<p class="description"></p>';
                $default = $flms_settings['design']['secondary_color'];
                if(isset($certificate_settings['text_color'])) {
                    $default = $certificate_settings['text_color'];
                }
                echo '<input type="text" placeholder="'.$default.'" class="flms-color-picker" value="'.$default.'" name="certificate-text-color" />';
            echo '</div>';*/
            echo '<div>';
                echo '<label class="heading">Themeing</label>';
                echo '<label>Background Color</label>';
                echo '<p class="description"></p>';
                $default = $flms_settings['design']['background_color'];
                if(isset($certificate_settings['background_color'])) {
                    $default = $certificate_settings['background_color'];
                }
                echo '<input type="text" placeholder="'.$default.'" class="flms-color-picker background-color" value="'.$default.'" name="certificate-background-color" />';
                echo '<br>';
                echo '<label>Font Family</label>';
                echo '<p class="description"></p>';
                $default = 'times';
                if(isset($certificate_settings['font-family'])) {
                    $default = $certificate_settings['font-family'];
                }
                $options = array(
                    'times' => 'Times-Roman',
                    //'timesb' => 'Times-Bold',
                    //'timesi' => 'Times-Italic',
                    //'timesbi' => 'Times-BoldItalic',
                    'helvetica' => 'Helvetica',
                    //'helveticab' => 'Helvetica-Bold',
                    //'helveticai' => 'Helvetica-Oblique',
                    //'helveticabi' => 'Helvetica-BoldOblique',
                    'courier' => 'Courier',
                    //'courierb' => 'Courier-Bold',
                    //'courieri' => 'Courier-Oblique',
                    //'courierbi' => 'Courier-BoldOblique'
                );
                echo '<select name="certificate-font-family">';
                foreach($options as $k => $v) {
                    echo '<option value="'.$k.'"';
                    if($k == $default) {
                        echo ' selected';
                    }
                    echo '>'.$v.'</option>';
                }
                echo '</select>';
            echo '</div>';

            if(flms_is_module_active('course_credits')) {
                if(!wp_script_is( 'select2', 'enqueued' )) {
                    wp_enqueue_style( 'select2');
                    wp_enqueue_script( 'select2');
                }
                wp_enqueue_script( 'flms-certificates');
                //echo '<div class="span-2"><label>Only allow user to access this certificate when:</label></div>';
                    $course_credits = new FLMS_Module_Course_Credits();
                    $credit_fields = $course_credits->get_course_credit_fields();
                    $options = array();
                    foreach($credit_fields as $credit_field) {
                        $label = $course_credits->get_credit_label($credit_field);
                        $options["$credit_field"] = $label;
                    }
                    $saved_values = array();
                    if(isset($certificate_settings['credit_restrictions'])) {
                        $saved_values = $certificate_settings['credit_restrictions'];
                    }
                    echo '<div>';
                        echo '<label class="heading">Restrictions</label>';
                        echo '<label>User must have course credit field</label>';
                        echo '<p class="description"></p>';
                        echo '<select name="certificate-display-credit-type[]" id="flms-course-certificates" class="flms-full-width" multiple="multiple">';
                        foreach($options as $k => $v) {
                            echo '<option value="'.$k.'"';
                            if(in_array($k, $saved_values)) {
                            	echo ' selected';
                            }
                            echo '>'.$v.'</option>';
                        }
                        echo '</select>';
                    echo '</div>';
                
                
            }
		echo '</div>';
        echo '<div class="flms-tab-section" id="shortcodes">';
            $shortcodes = new FLMS_Shortcodes();
            $shortcodes->display_shortcode_references();
        echo '</div>';
        
		
		echo '<input type="hidden" name="flms-post-type" value="flms-certificates" />';
		
	}

    public function flms_certificates_preview_metabox() {
        $courses = flms_get_course_select_box();
        echo '<label>Course</label>';
        echo '<p class="description">Select the course for previewing</p>';
        $select = '<select id="flms-certificate-preview-course">';
            $select .= '<option value="0">Select a Course</option>';
            foreach($courses as $k => $v) {
                $select .= '<option value="'.$k.'">'.$v.'</option>';
            }
        $select .= '</select>';
        echo $select;
        echo '<div class="flms-spacer"></div>';
        echo '<div id="certificate-users-response"></div>';
        echo '<button id="preview-certificate" class="button button-primary" style="display:none;">Preview Certificate</button>';
    }

    public function save_settings($post_id, $data) {
        $settings = array();
        if(isset($data['certificate-background-color'])) {
            $settings['background_color'] = sanitize_text_field($data['certificate-background-color']);
        }
        if(isset($data['certificate-font-family'])) {
            $settings['font-family'] = sanitize_text_field($data['certificate-font-family']);
        }
        if(isset($data['certificate-display-credit-type']) && is_array($data['certificate-display-credit-type'])) {
            $saved_credits = array();
            foreach($data['certificate-display-credit-type'] as $credit_type) {
                $saved_credits[] = sanitize_text_field($credit_type);
            }
            $settings['credit_restrictions'] = $saved_credits;
        }
        update_post_meta($post_id, 'flms_certificate_settings', $settings);
    }
    
}
new FLMS_Module_Course_Certificates();