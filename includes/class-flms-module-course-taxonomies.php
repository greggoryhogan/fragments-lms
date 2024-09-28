<?php

class FLMS_Module_Course_Taxonomies {

    public $class_name = 'FLMS_Module_Course_Taxonomies';

    private $zci_placeholder;

    public function __construct() {
        //add_action( 'init', array($this, 'register_shortcodes') );
        $this->zci_placeholder = FLMS_PLUGIN_URL.'assets/img/admin/placeholder.png';
	}
    
    public function get_shortcodes() {
		$shortcodes = array(
			'taxonomy' => array(
				'description' => 'Display custom course taxonomy data',
				'atts' => array(
					'slug' => 'Slug for the desired taxonomy',
					'before' => 'Text before the taxonomy',	
                    'after' => 'Text after the taxonomy',	
                    'link' => "Link to the taxonomy's archive, true or false",	
                    'sep' => 'Separator when multiple fields are returned for the taxonomy',	
                    'display' => 'Whether multiple taxonomies should be on their own line. Default "block", use "inline" to keep all on one line',	

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
    
    public function flms_taxonomy_shortcode($atts) {
        global $post;
        $default_atts = array(
            'slug' => '',
            'name' => '',
            'before' => '',
            'after' => '',
            'link' => 'false',
            'sep' => ', ',
            'display' => 'block'
        );
        $atts = shortcode_atts( $default_atts, $atts, 'flms-taxonomy' );
        $return = '';
        if($atts['slug'] != '') {
            $terms = get_the_terms($post->ID, $atts['slug']);
            if(!is_wp_error( $terms )) {
                if($terms != false) {
                    if(!empty($terms)) {
                        $term_count = 0;
                        $return .= '<span class="flms-taxonomy-display';
                        if($atts['display'] == 'block') {
                            $return .= ' display-block';
                        }
                        $return .= '">';
                        if($atts['before'] != '') {
                            $return .= $atts['before'];
                        }
                        foreach($terms as $term) {
                            if($term_count > 0) {
                                $return .= $atts['sep'];
                            }
                            if($atts['link'] == 'true') {
                                $link = get_term_link($term, $atts['slug']);
                                $return .= '<a href="'.$link.'" title="View '.$term->name.'">';
                            }
                            $return .= apply_filters( 'flms_content', $term->name);
                            if($atts['link'] == 'true') {
                                $return .= '</a>';
                            }
                            $term_count++;

                        }
                        if($atts['after'] != '') {
                            $return .= $atts['after'];
                        }
                        $return .= '</span>';
                    }
                } else {
                    return '';
                }
            } 
        }
        return $return;
    }

    public function flms_course_taxonomies() {
        echo '<div class="flms-course-taxonomies flms-course-content-section">';
        $this->flms_get_course_taxonomies();
        echo '</div>';
    }

    public function flms_get_course_taxonomies($location = 'design', $echo = true, $show_label = true, $heading_wrap = 'h3') {
        global $flms_settings, $flms_course_id, $flms_active_version, $flms_course_version_content;
        $return = '';
        $taxonomies = $this->get_course_taxonomy_fields();
        $display = '';
        if($location == 'design') {
            $display = 'display-block';
        }
        if(!empty($taxonomies)) {
            //echo '<pre>'.print_r($taxonomies,true).'</pre>';
            foreach($taxonomies as $taxonomy) {
                $slug = $taxonomy['slug'];
                if(isset($flms_settings[$location]["{$slug}_course_display"])) {
                    if($flms_settings[$location]["{$slug}_course_display"] == 'show') {
                        $singular = $taxonomy['name-singular'];
                        $plural = $taxonomy['name-plural'];
                        $plural_lower = strtolower($plural);
                        $terms = get_the_terms($flms_course_id, $slug);
                        if(!is_wp_error( $terms )) {
                            if($terms != false) {
                                if(!empty($terms)) {
                                    $term_count = 0;
                                    $return .= '<span class="flms-taxonomy-display '.$display.'">';
                                    $return .= "$singular: ";
                                    foreach($terms as $term) {
                                        if($term_count > 0) {
                                            $return .= ', ';
                                        }
                                        if(isset($flms_settings[$location]["{$slug}_link_to_archive"])) {
                                            if($flms_settings[$location]["{$slug}_link_to_archive"] == 'show') {
                                                $link = get_term_link($term, $slug);
                                                $return .= '<a href="'.$link.'" title="View '.$term->name.'">';
                                            }
                                        }
                                        $return .= apply_filters( 'flms_content', $term->name);
                                        if(isset($flms_settings[$location]["{$slug}_link_to_archive"])) {
                                            if($flms_settings[$location]["{$slug}_link_to_archive"] == 'show') {
                                                $return .= '</a>';
                                            }
                                        }
                                        $term_count++;

                                    }
                                    $return .= '</span> ';
                                }
                            }
                        }
                    }
                }
            }
        }
        
        if($echo) {
            echo $return;
        } else {
            return $return;
        }
        
    }

    public function get_course_taxonomies_array() {
        global $flms_settings, $flms_course_id, $flms_active_version, $flms_course_version_content;
        $return = array();
        $taxonomies = $this->get_course_taxonomy_fields();
        if(!empty($taxonomies)) {
            //echo '<pre>'.print_r($taxonomies,true).'</pre>';
            foreach($taxonomies as $taxonomy) {
                $slug = $taxonomy['slug'];
                $terms = get_the_terms($flms_course_id, $slug);
                if(!is_wp_error( $terms )) {
                    if($terms != false) {
                        if(!empty($terms)) {
                            $return[$slug] = array();
                            foreach($terms as $term) {
                                $return[$slug][] = $term->name;
                            }
                        }
                    }
                }
            }
        }
        return $return;
    }

    public function get_course_taxonomies_fields($exclude_dynamic = false, $sort = false) {
        global $flms_settings;

        if(!$exclude_dynamic) {
            $default_fields = array(
                array(
                    'label' => 'Create Course Taxonomy',
                    'key' => 'create-course-taxonomies-field',
                    'type' => 'dynamic',
                    'class' => $this->class_name,
                    'function' => 'create_course_taxonomy',
                ) 
            );
        } else {
            $default_fields = array();
        }

        $fields = array();
        if(isset($flms_settings['course_taxonomies'])) {
            $existing_fields = $flms_settings['course_taxonomies'];
            $custom_credit_fields = $this->get_custom_taxonomy_fields();
            //print_r($custom_credit_fields);
            foreach($existing_fields as $k => $v) {
                //if(isset($v["$k-custom"])) {
                    $credit_name = $k;
                    
                    $singular_name = $v["name-singular"];
                    $plural_name = $v["name-plural"];
                    $slug = $v["slug"];
                    $hierarchal = $v["hierarchal"];
                    $filter_status = $v["filter-status"];
                    $status = $v["status"];
                    $form_fields = $this->replace_tmp_fields($custom_credit_fields, $singular_name, $plural_name, $slug, $hierarchal, $filter_status, $status);
                    
                    //print_r($form_fields);
                    //print_r($form_fields);
                    $fields[] = array(
                        'label' => $plural_name,
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
            if(isset($flms_settings['course_taxonomies'])) {
                $sorted_fields = array();
                foreach($flms_settings['course_taxonomies'] as $k => $credit) {
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

    public function register_taxonomies() {
        $taxonomies = $this->get_course_taxonomy_fields(true);
        if(!empty($taxonomies)) {
            //echo '<pre>'.print_r($taxonomies,true).'</pre>';
            foreach($taxonomies as $taxonomy) {
                $singular = $taxonomy['name-singular'];
                $plural = $taxonomy['name-plural'];
                $plural_lower = strtolower($plural);
                $slug = $taxonomy['slug'];
                $hierarchal = $taxonomy['hierarchal'];
                $hierarchy = false;
                if($hierarchal == 'true') {
                    $hierarchy = true;
                }
                
                $labels = array(
                    "name" => __( $plural, "" ),
                    "singular_name" => __( $singular, "" ),
                    "search_items" => "Search $plural",
                    "popular_items" => "Popular $plural",
                    "all_items" => "All $plural",
                    "parent_item" => "Parent $singular",
                    "edit_item" => "Edit $singular",
                    "view_item" => "View $singular",
                    "update_item" => "Update $singular",
                    "add_new_item" => "Add new $singular",
                    "new_item_name" => "New $singular Name",
                    "not_found" => "No $plural Found",
                    "choose_from_most_used" => "Choose from the most used $plural_lower",
                    'back_to_items' => __( "&larr; Go to $plural", 'flms' ),
                );
            
                $args = array(
                    "label" => __( $plural, "" ),
                    "labels" => $labels,
                    "public" => true,
                    "label" => $plural,
                    "hierarchical" => $hierarchy,
                    "show_ui" => true,
                    "show_in_menu" => FLMS_PLUGIN_SLUG,
                    "show_in_nav_menus" => false,
                    "query_var" => true,
                    "rewrite" => array( 'slug' => $slug),
                    "show_admin_column" => 0,
                    "show_in_rest" => false,
                    "show_in_quick_edit" => true,
                    
                );
                register_taxonomy( $slug, array( "flms-courses" ), $args );

                //add featured image support
                add_action($slug.'_add_form_fields', array($this, 'add_taxonomy_field'));
                add_action($slug.'_edit_form_fields', array($this, 'edit_taxonomy_field'));
                add_filter('manage_edit-'.$slug.'_columns', array($this, 'taxonomy_columns'));
                add_filter('manage_'.$slug.'_custom_column', array($this, 'taxonomy_column'), 10, 3 );

                //save image
                add_action('edit_term', array($this, 'save_taxonomy_image'));
                add_action('create_term', array($this, 'save_taxonomy_image'));

                // If tax is deleted
                add_action("delete_{$slug}", function($tt_id) {
                    delete_term_meta($tt_id, 'flms_taxonomy_image');
                });
            }
        }
    }

    public function register_taxonomy_menu_pages() {
        $taxonomies = $this->get_course_taxonomy_fields(true);
        if(!empty($taxonomies)) {
            //echo '<pre>'.print_r($taxonomies,true).'</pre>';
            foreach($taxonomies as $taxonomy) {
                $singular = $taxonomy['name-singular'];
                $plural = $taxonomy['name-plural'];
                $slug = $taxonomy['slug'];
                $hierarchal = $taxonomy['hierarchal'];
                add_submenu_page(FLMS_PLUGIN_SLUG,$plural,$plural,'edit_posts', 'edit-tags.php?taxonomy='.$slug);
            }
        }
    }

    public function get_taxonomy_slugs() {
        $slugs = array();
        $taxonomies = $this->get_course_taxonomy_fields(true);
        if(!empty($taxonomies)) {
            foreach($taxonomies as $taxonomy) {
                $slug = $taxonomy['slug'];
                $slugs[] = $slug;
            }
        }
        return $slugs;
    }

    public function replace_tmp_fields($form_fields, $singular_name, $plural_name, $slug, $hierarchal, $filter_status, $status) {
        $new_fields = array();
		
		foreach($form_fields as $form_field) {
            $form_field['key'] = str_replace('tmp-course-taxonomy-', '', $form_field['key']);
			if($form_field['key'] == "name-singular") {
				$form_field['default'] = $singular_name;
			} else if($form_field['key'] == "name-plural") {
				$form_field['default'] = $plural_name;
			} else if($form_field['key'] == "slug") {
				$form_field['default'] = $slug;
			} else if($form_field['key'] == "hierarchal") {
				$form_field['default'] = $hierarchal;
			} else if($form_field['key'] == "filter-status") {
				$form_field['default'] = $filter_status;
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

    public function create_course_taxonomy() {
        echo $this->create_course_taxonomy_form();
    }

    public function create_course_taxonomy_form() {
        $settings_class = new FLMS_Settings();
        $form = '<div id="create-course-credit-form">';
            ob_start();
            $form_field_category = 'tmp_create_course_taxonomy';
            $form_fields = $this->get_custom_taxonomy_fields();
            foreach($form_fields as $form_field) {
                flms_print_field_input($form_field, $form_field_category);
            }
            $form .= ob_get_clean();
            $form .= '<button class="button button-primary" id="create-course-taxonomy-field">Create Taxonomy</button>';
        $form .= '</div>';
        return $form;
    }

    public function get_custom_taxonomy_fields() {
        $prefix = '$';
        if(function_exists('get_woocommerce_currency_symbol')) {
            $prefix = get_woocommerce_currency_symbol();
        }
        global $flms_settings;
        $form_fields = array(
            array(
                'label' => 'Plural Name',
                'key' => 'tmp-course-taxonomy-name-plural',
                'type' => 'text',
                'default' => '',
                'description' => '',
                'placeholder' => 'My Taxonomies',
            ),
            array(
                'label' => 'Singular Name',
                'key' => 'tmp-course-taxonomy-name-singular',
                'type' => 'text',
                'default' => '',
                'description' => '',
                'placeholder' => 'My Taxonomy',
            ),
            array(
                'label' => 'Slug',
                'key' => 'tmp-course-taxonomy-slug',
                'type' => 'text',
                'default' => '',
                'description' => '<strong>Warning:</strong> Changing the taxonomy slug will delete any existing terms',
                'placeholder' => 'my-taxonomy',
            ),
            array(
                'label' => "Hierarchal",
                'key' => "tmp-course-taxonomy-hierarchal",
                'type' => 'select',
                'options' => array(
                    'true' => 'True',
                    'false' => 'False',
                ),
                'default' => 'false',
            ),
            array(
                'label' => 'Show as course filter',
                'key' => "tmp-course-taxonomy-filter-status",
                'type' => 'radio',
                'options' => array(
                    'show' => 'Show',
                    'hide' => 'Hide'
                ),
                'default' => 'show',
            ),
            array(
                'label' => 'Status',
                'key' => "tmp-course-taxonomy-status",
                'type' => 'radio',
                'options' => array(
                    'active' => 'Active',
                    'inactive' => 'Inactive'
                ),
                'default' => 'active',
            ),
            array(
                'label' => 'Delete',
                'key' => "tmp-course-taxonomy-delete",
                'type' => 'delete',
                'default' => 'Delete Taxonomy',
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

     
    public function get_course_credit_course_settings() {
        global $flms_settings, $flms_course_id, $flms_active_version, $flms_course_version_content;
        $course_credit_fields = $this->get_course_credit_fields();
        $return = '';
		foreach($course_credit_fields as $field) {
            if(isset($flms_settings['labels'][$field])) {
                $label = $flms_settings['labels'][$field];
            } else if(isset($flms_settings['course_credits'][$field]["$field-name"])) {
                $label = $flms_settings['course_credits'][$field]["$field-name"];  
            } else {
                //print_r($field);
                $label = $field;
                //$label = $field["$field.'-name"];
            }
            $default = 0;
            if(isset($flms_course_version_content["$flms_active_version"]['course_credits'][$field])) {
                $default = $flms_course_version_content["$flms_active_version"]['course_credits'][$field];
            }
            $return .= '<div class="settings-field">';
				$return .= '<label>'.$label.'</label>';
				$return .= '<input type="number" step="any" min="0" name="'.$field.'-credits" value="'.$default.'" />';
            $return .= '</div>';
        }
        return $return;
    }

    
    public function get_course_taxonomy_fields($active_only = true) {
        global $flms_settings, $flms_course_id, $flms_active_version, $flms_course_version_content;
        $course_credit_fields = array();
        if(!empty($flms_settings['course_taxonomies'])) {
            foreach($flms_settings['course_taxonomies'] as $k => $v) {
                if(!$active_only) {
                    $course_credit_fields[$k] = $v;
                } else {
                    if($v["status"] == 'active') {
                        $course_credit_fields[$k] = $v;
                    }
                }
            }
        }
        return $course_credit_fields;
    }

    public function get_taxonomy_options() {
        global $flms_settings;
        $fields = array();
        $taxonomies = $this->get_course_taxonomy_fields(true);
        if(!empty($taxonomies)) {
            $default_fields = array(
                array(
                    'label' => "Course Taxonomies",
                    'key' => 'course_credits_heading',
                    'type' => 'section_heading',
                ),
            );
            //echo '<pre>'.print_r($taxonomies,true).'</pre>';
            $tax_fields = array();
            foreach($taxonomies as $taxonomy) {
                $singular = $taxonomy['name-singular'];
                $plural = $taxonomy['name-plural'];
                $plural_lower = strtolower($plural);
                $slug = $taxonomy['slug'];
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
                $tax_fields[] = array(
                    'label' => "Link to $singular archive",
                    'key' => "{$slug}_link_to_archive",
                    'type' => 'radio',
                    'options' => array(
                        'show' => 'Show',
                        'hide' => 'Hide'
                    ),
                    'default' => 'link',
                );
            }
            $fields = array_merge($default_fields, $tax_fields);
        }
        return $fields;
    }

    // add image field in add form
    function add_taxonomy_field() {
        if (get_bloginfo('version') >= 3.5) {
            wp_enqueue_media();
            wp_enqueue_script( 'flms-media-uploader' );
        }
        
        echo '<div class="form-field">
            <label for="zci_taxonomy_image">' . __('Image', 'categories-images') . '</label>
            <div id="flms-media-uploaded-image"></div>
            <div class="flms-flex">
            <div><button id="flms-upload-media" class="button button-secondary align-self-start" data-media-uploader-target="#zci_taxonomy_image">Upload</button></div>
            <div class="flex-1"><input type="text" name="zci_taxonomy_image" id="zci_taxonomy_image" class="flms-update-media-upload-preview-image" value="" /></div>
            </div>
        </div>';

        if(flms_is_module_active('course_taxonomy_royalties')) {
            $types = array(
                'none' => 'None',
                'flat_fee' => 'Flat fee',
                'percent' => 'Percentage'
            );
            echo '<div class="form-field">
                <label for="royalty-type">Royalty type</label>
                <div class="royalty-type">';

               
                $options = $this->get_royalty_reporting_types();
            
                echo '<select name="royalty-type" class="select-toggle">';
                    foreach($options as $k => $v) {
                        echo '<option value="'.$k.'"';
                        if(isset($v['toggle'])) {
                            echo ' data-select-toggle="'.$v['toggle'].'"';
                        }
                        echo '>'.$v['label'].'</option>';
                    }
                echo '</select>';
            

                echo '</div>
                <p class="description">The dollar amount to attribute when reporting on this term.</p>
            </div>';
            $currency = '$';
            if(function_exists('get_woocommerce_currency_symbol')) {
                $currency = get_woocommerce_currency_symbol();
            }
            echo '<div class="form-field toggle-div select-toggle-div" id="royalty_amount">
                <label for="royalty-amount">Royalty amount</label>
                <div class="royalty-amount">
                <input type="number" name="royalty-amount" min="0" value="0" />
                <span>'.$currency.'</span>
                </div>
                <p class="description">The dollar amount to attribute when reporting on this term.</p>
            </div>';
            echo '<div class="form-field toggle-div select-toggle-div" id="royalty_percentage">
                <label for="royalty-percentage">Royalty percentage</label>
                <div class="royalty-percentage">
                <input type="number" name="royalty-percentage" min="0" max="100" value="0" />
                <span>%</span>
                </div>
                <p class="description">The percent amount to attribute when reporting on this term.</p>
            </div>';
        }
    }

    public function get_royalty_reporting_types() {
        $options = array(
            'none' => array('label' => 'None', 'toggle' => ''),
            'percentage' => array('label' => 'Percentage', 'toggle' => '#royalty_percentage'),
            'flat_fee' => array('label' => 'Flat fee', 'toggle' => '#royalty_amount'),
            'per_course' => array('label' => 'Defined per course', 'toggle' => '')
        );
        return apply_filters('flms_royalty_reporting_types', $options);
    }
    // add image field in edit form
    function edit_taxonomy_field($taxonomy) {
        if (get_bloginfo('version') >= 3.5) {
            wp_enqueue_media();
            wp_enqueue_script( 'flms-media-uploader' );
        }
        $flms_thumbnail = get_term_meta($taxonomy->term_id, 'flms_taxonomy_image', true);
        echo '<tr class="form-field">
            <th scope="row" valign="top"><label for="zci_taxonomy_image">' . __('Image', 'categories-images') . '</label></th>
            <td>
            <div id="flms-media-uploaded-image">';
            if($flms_thumbnail != '') {
                echo '<img src="'.$flms_thumbnail.'" />';
            }
            echo '</div>
            <div class="flms-flex">
            <div><button id="flms-upload-media" class="button button-secondary align-self-start" data-media-uploader-target="#zci_taxonomy_image">Upload</button></div>
            <div class="flex-1"><input type="text" name="zci_taxonomy_image" id="zci_taxonomy_image" value="'.$flms_thumbnail.'" class="flms-update-media-upload-preview-image" /></div>
            </div>
            </td>
        </tr>';

        if(flms_is_module_active('course_taxonomy_royalties')) {
            $type = get_term_meta($taxonomy->term_id, 'flms_royalty_type', true);
            $options = $this->get_royalty_reporting_types();
            echo '<tr class="form-field">
            <th scope="row" valign="top"><label for="royalty-amount">Royalty type</label></th>
            <td>
            <div class="royalty-type">';
                echo '<select name="royalty-type" class="select-toggle">';
                    foreach($options as $k => $v) {
                        echo '<option value="'.$k.'"';
                        if($type == $k) {
                            echo ' selected';
                        }
                        if(isset($v['toggle'])) {
                            echo ' data-select-toggle="'.$v['toggle'].'"';
                        }
                        echo '>'.$v['label'].'</option>';
                    }
                echo '</select>';
            echo '</div>
            <p class="description">The calculation type when reporting on this '.$taxonomy->taxonomy.'.</p>
            </td>
        </tr>';
            
            $amount = get_term_meta($taxonomy->term_id, 'flms_royalty_amount', true);
            $currency = '$';
            if(function_exists('get_woocommerce_currency_symbol')) {
                $currency = get_woocommerce_currency_symbol();
            }
            echo '<tr class="form-field toggle-div select-toggle-div';
            if($type == 'flat_fee') {
                echo ' is-active';
            }
            echo '" id="royalty_amount">
                <th scope="row" valign="top"><label for="royalty-amount">Royalty amount</label></th>
                <td>
                <div class="royalty-amount">
                <input type="number" name="royalty-amount" min="0" value="'.$amount.'" />
                <span>'.$currency.'</span>
                </div>
                <p class="description">The amount to attribute when reporting on this '.$taxonomy->taxonomy.'.</p>
                </td>
            </tr>';
            $percentage = get_term_meta($taxonomy->term_id, 'flms_royalty_percentage', true);
            echo '<tr class="form-field toggle-div select-toggle-div';
            if($type == 'percentage') {
                echo ' is-active';
            }
            echo '" id="royalty_percentage">
                <th scope="row" valign="top"><label for="royalty-percentage">Royalty percentage</label></th>
                <td>
                <div class="royalty-percentage">
                <input type="number" name="royalty-percentage" min="0" max="100" value="'.$percentage.'" />
                <span>%</span>
                </div>
                <p class="description">The percent amount to attribute when reporting on this '.$taxonomy->taxonomy.'.</p>
                </td>
            </tr>';
           
        }
    }

    /**
     * flms_thumbnailnail column added to category admin.
     *
     * @access public
     * @param mixed $columns
     * @return void
     */
    function taxonomy_columns( $columns ) {
        $new_columns = array();
        if(isset($columns['cb'])) {
            $new_columns['cb'] = $columns['cb'];
        }
        $new_columns['flms_thumbnail'] = __('Image', 'categories-images');
        $columns['flms_post_count'] = __('Count', 'post-count');
        if(isset($columns['cb'])) {
            unset( $columns['cb'] );
        }
        if(isset($columns['posts'])) {
            unset( $columns['posts'] );
        }

        return array_merge( $new_columns, $columns );
    }

    function taxonomy_column( $columns, $column, $id ) {
        if ( $column == 'flms_thumbnail' ) {
                $flms_thumbnail = get_term_meta($id,'flms_taxonomy_image',true);
            if($flms_thumbnail != '') {
                $columns = '<span><img src="' . $flms_thumbnail . '" alt="' . __('flms_thumbnailnail', 'categories-images') . '" class="wp-post-image" /></span>';
            }
        } else if ( $column == 'flms_post_count' ) {
            $term = get_term($id);
            $my_posts = get_posts(array(
                'post_type' => 'flms-courses', //post type
                'numberposts' => -1,
                'tax_query' => array(
                  array(
                    'taxonomy' => $term->taxonomy, //taxonomy name
                    'field' => 'id', //field to get
                    'terms' => $id, //term id
                  )
                )
              ));
              //https://bhfe.wpenginepowered.com/wp-admin/edit.php?subject=ethics-cfp-cpa-irs
            $columns = '<a href="'.admin_url('edit.php?'.$term->taxonomy.'='.$term->slug).'&post_type=flms-courses">'.count($my_posts).'</a>'; 
        }
        
        return $columns;
    }

    function save_taxonomy_image($term_id) {
        if(isset($_POST['zci_taxonomy_image'])) {
            update_term_meta($term_id,'flms_taxonomy_image',$_POST['zci_taxonomy_image']);
        } else {
            delete_term_meta($term_id,'flms_taxonomy_image');
        }

        if(isset($_POST['royalty-type'])) {
            update_term_meta($term_id,'flms_royalty_type', sanitize_text_field($_POST['royalty-type']));
        } else {
            delete_term_meta($term_id,'flms_royalty_type');
        }
        if(isset($_POST['royalty-amount'])) {
            update_term_meta($term_id,'flms_royalty_amount',absint($_POST['royalty-amount']));
        } else {
            delete_term_meta($term_id,'flms_royalty_amount');
        }
        if(isset($_POST['royalty-percentage'])) {
            update_term_meta($term_id,'flms_royalty_percentage',absint($_POST['royalty-percentage']));
        } else {
            delete_term_meta($term_id,'flms_royalty_percentage');
        }

        
    }

    public function flms_course_royalties_metabox() {
        global $post;
        $this->get_royalty_terms($post->ID);
    }

    public function get_royalty_terms($post_id = 0) {
        $result = '';
        if($post_id > 0) {
            $options = array(
                'none' => array('label' => 'None', 'toggle' => ''),
                'percentage' => array('label' => 'Percentage', 'toggle' => '#royalty_percentage'),
                'flat_fee' => array('label' => 'Flat fee', 'toggle' => '#royalty_amount'),
            );
            $taxonomies = get_object_taxonomies( 'flms-courses' );
            if(!empty($taxonomies)) {
                foreach($taxonomies as $taxonomy) {
                    $terms = get_the_terms($post_id, $taxonomy);
                    if(!empty($terms)) {
                        foreach($terms as $term) {
                            $default_term_type = get_term_meta($term->term_id, 'flms_royalty_type', true);
                            $link = '<a href="'.get_edit_term_link($term,$taxonomy).'" target="_blank">edit</a>';
                            $result .= '<label>'.$term->name.' '.$link.'</label>';
                            if($default_term_type == 'per_course') {
                                //$result .= print_r($term, true);
                                $type = get_post_meta($post_id, 'flms_royalty_type_'.$term->term_id, true);
                                $result .= '<p class="description">Royalty type</p>';
                                $result .= '<select name="royalty-type-'.$term->term_id.'" class="select-toggle flms-full-width">';
                                    foreach($options as $k => $v) {
                                        $result .= '<option value="'.$k.'"';
                                        if(isset($v['toggle'])) {
                                            $result .= ' data-select-toggle="'.$v['toggle'].'"';
                                        }
                                        if($k == $type) {
                                            $result .= ' selected';
                                        }
                                        $result .= '>'.$v['label'].'</option>';
                                    }
                                $result .= '</select>';
                                $currency = '$';
                                if(function_exists('get_woocommerce_currency_symbol')) {
                                    $currency = get_woocommerce_currency_symbol();
                                }
                                $value = get_post_meta($post_id, 'flms_royalty_amount_'.$term->term_id, true);
                                $result .= '<div class="form-field toggle-div select-toggle-div';
                                if($type == 'flat_fee') {
                                    $result .= ' is-active';
                                }
                                $result .= '" id="royalty_amount">
                                    <label for="royalty-amount">Royalty amount</label>
                                    <div class="royalty-amount">
                                    <input type="number" name="royalty-amount-'.$term->term_id.'" min="0" value="'.$value.'" />
                                    <span>'.$currency.'</span>
                                    </div>
                                    <p class="description">The dollar amount to attribute when reporting on this term.</p>
                                </div>';
                                $result .= '<div class="form-field toggle-div select-toggle-div';
                                if($type == 'percentage') {
                                    $result .= ' is-active';
                                }
                                $value = get_post_meta($post_id, 'flms_royalty_percentage_'.$term->term_id, true);
                                $result .= '" id="royalty_percentage">
                                    <label for="royalty-percentage">Royalty percentage</label>
                                    <div class="royalty-percentage">
                                    <input type="number" name="royalty-percentage-'.$term->term_id.'" min="0" max="100" value="'.$value.'" />
                                    <span>%</span>
                                    </div>
                                    <p class="description">The percent amount to attribute when reporting on this term.</p>
                                </div>';
                                $result .= '<div class="flms-spacer"></div>';
                            } else {
                                $result .= '<p class="description">';
                                if($default_term_type == 'none' || $default_term_type == '') {
                                    $result .= 'No reporting fee.';
                                } else {
                                    if($default_term_type == 'flat_fee') {
                                        $currency = '$';
                                        if(function_exists('get_woocommerce_currency_symbol')) {
                                            $currency = get_woocommerce_currency_symbol();
                                        }
                                        $amount = absint(get_term_meta($term->term_id, 'flms_royalty_amount', true));
                                        $result .= 'Flat fee of '.$currency.$amount;
                                        
                        
                                    } else if($default_term_type == 'percentage') {
                                        $percentage = absint(get_term_meta($term->term_id, 'flms_royalty_percentage', true));
                                        $result .= $percentage.'%';
                                    }
                                    $result .= ' defined globally.';
                                }
                                $result .= '</p>';
                            }
                        }
                    }
                }
            }
        }
        echo $result;
        //wp_die();
    }
    //add_action( "wp_ajax_nopriv_get_royalty_terms", 'get_royalty_terms' );
    //add_action( "wp_ajax_get_royalty_terms", 'get_royalty_terms' );

    function save_royalty_amounts($post_id, $data) {
        $taxonomies = get_object_taxonomies( 'flms-courses' );
        
        if(!empty($taxonomies)) {
            foreach($taxonomies as $taxonomy) {
                $terms = get_the_terms($post_id, $taxonomy);
                if(!empty($terms)) {
                    foreach($terms as $term) {
                        if(isset($data['royalty-type-'.$term->term_id])) {
                            $value = sanitize_text_field($data['royalty-type-'.$term->term_id]);
                            update_post_meta($post_id, 'flms_royalty_type_'.$term->term_id, $value);
                        } else {
                            delete_post_meta($post_id, 'flms_royalty_type_'.$term->term_id);
                        }
                        if(isset($data['royalty-amount-'.$term->term_id])) {
                            $value = sanitize_text_field($data['royalty-amount-'.$term->term_id]);
                            update_post_meta($post_id, 'flms_royalty_amount_'.$term->term_id, $value);
                        } else {
                            delete_post_meta($post_id, 'flms_royalty_amount_'.$term->term_id);
                        }
                        if(isset($data['royalty-percentage-'.$term->term_id])) {
                            $value = sanitize_text_field($data['royalty-percentage-'.$term->term_id]);
                            update_post_meta($post_id, 'flms_royalty_percentage_'.$term->term_id, $value);
                        } else {
                            delete_post_meta($post_id, 'flms_royalty_percentage_'.$term->term_id);
                        }
                    }
                }
            }
        }
    }

}
new FLMS_Module_Course_Taxonomies();