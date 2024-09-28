<?php

class FLMS_Module_Groups {

    public $class_name = 'FLMS_Module_Groups';

    public function __construct() {
        add_action( 'add_meta_boxes', array($this,'flms_register_meta_boxes') ); 
        add_filter( 'profile_update', array($this, 'user_email_change_updates'), 10, 2 );       
	}

    public function register_cpt() {
        //Register Groups cpt
        global $flms_settings;
		// Handle URLs with 'version' for child pages
		$group_permalink = $flms_settings["custom_post_types"]["group_permalink"];
		$labels = array(
			"name" => __( "Groups", "" ),
			"singular_name" => __( "Group", "flms" ),
			'all_items' => __( "Groups", "flms" ),
			'edit_item' => __( "Edit Group", "flms" ),
			'update_item' => __( "Update Group", "flms" ),
			'add_new' => __( "Add New Group", "flms" ),
			'add_new_item' => __( "Add New Group", "flms" ),
			'new_item_name' => __( "New Group", "flms" ),
			'menu_name' => __( "Groups", "flms" ),
			'back_to_items' => __( "&laquo; All Groups", "flms" ),
			'not_found' => __( "No Groups found.", "flms" ),
			'not_found_in_trash' => __( "No Groups found in trash.", "flms" ),
		);
		$args = array(
			"label" => __( "Groups", "flms" ),
			"labels" => $labels,
			"description" => "",
			"public" => true,
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
			"rewrite" => array( "slug" => $group_permalink, ), //"with_front" => false 
			"supports" => array('title','custom_fields'),
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
		register_post_type( "flms-groups", $args );
        remove_post_type_support( 'flms-groups', 'editor' );
        remove_post_type_support( 'flms-groups', 'thumbnail' );
    }

    public function register_rewrite_rule() {
        /*global $flms_settings;
		// Handle URLs with 'version' for child pages
		$group_permalink = $flms_settings["custom_post_types"]["group_permalink"];
        add_rewrite_rule(
			"^{$group_permalink}/([^/]+)/?$",
			'index.php?post_type=flms-groups&flms-group=$matches[1]',
			'top'
		);
        flush_rewrite_rules();*/
    }

    public function flms_register_meta_boxes() {
		add_meta_box( 'flms_course-manager', __( 'Group Settings', 'textdomain' ), array($this,'flms_groups_metabox'), 'flms-groups', 'normal', 'high' );
	}

    public function register_query_vars($query_vars) {
        $query_vars[] = 'flms-group';
        //echo '<pre>'.print_r($query_vars,true).'</pre>';
        return $query_vars;
    }

    public function get_shortcodes() {
		$shortcodes = array(
			'join-group-form' => array(
				'description' => 'Display the form which allows users to join a group',
				'atts' => array(
					'button_text' => 'Join',
					'placeholder_text' => 'Group Code',	
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

    public function flms_join_group_form_shortcode($atts = array()) {
        global $post;
        $default_atts = array(
            'button-text' => apply_filters('flms_join_group_button_text','Join'),
		    'placeholder' => apply_filters('flms_join_group_placeholder', 'Group Code'),	
            'logged-out-text' => apply_filters('flms_login_required_text', 'You must be logged in to enroll in a group.'),
            'login-text' => apply_filters('flms_log_in_text','Log in'),
        );
        $atts = shortcode_atts( $default_atts, $atts, 'flms-join-group-form' );
        wp_enqueue_script( 'flms-groups' );
        $current_user_id = absint(get_current_user_id());
        if($current_user_id > 0) {
            $group_label = flms_get_label('groups_singular');
            $return = " Enter the $group_label code to join.";
            $return .= '<div class="flms-content-spacer"></div>';
            $return .= '<form id="flms-join-group" class="flms-flex" autocomplete="off">';
                $return .= '<input type="text" id="group_code" placeholder="'.$atts['placeholder'].'" />';
                $return .= '<input type="submit" value="'.$atts['button-text'].'" />';
            $return .= '</form>';
            $return .= '<div id="join_group_feedback" class="flms-form-feedback"></div>';
        } else {
            global $post;
            $redirect = get_permalink($post->ID);
            $login_link = apply_filters('flms_login_link_url', wp_login_url($redirect), $redirect);
            $return = '<div class="flms-content-spacer"></div>';
            $return .= '<p>'.$atts['logged-out-text'].' <a href="'.$login_link.'" title="'.$atts['login-text'].'">'.$atts['login-text'].'</a>';
        }
        return $return;
    }
    
    public function get_user_group_ids($user) {
        $args = array(
            'posts_per_page'   => -1,
            'post_type' => 'flms-groups',
            'orderby'          => 'post_date',
            'order'            => 'ASC',
            'post_status'      => 'publish',
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key'     => 'flms_group_owner',
                    'value'   => $user->ID,
                ),
                array(
                    'key'     => 'flms_group_member',
                    'value'   => $user->ID,
                ),
            ),
        );
        return get_posts( $args );
    }

    public function get_user_groups($user) {
        if(!wp_script_is( 'select2', 'enqueued' )) {
            wp_enqueue_style( 'select2');
            wp_enqueue_script( 'select2');
        }
        wp_enqueue_script( 'flms-groups' );
        $return = '';
        $args = array(
            'posts_per_page'   => -1,
            'post_type' => 'flms-groups',
            'orderby'          => 'post_date',
            'order'            => 'ASC',
            'post_status'      => 'publish',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key'     => 'flms_group_owner',
                    'value'   => $user->ID,
                ),
                array(
                    'key'     => 'flms_group_member',
                    'value'   => $user->ID,
                ),
            ),
        );
        $groups = get_posts( $args );

        if(empty($groups)) {
            $return .='<p id="no-flms-groups">'.apply_filters('flms_no_groups_text', 'You are not a member of any groups.').'</p>';
            //$return .= '<h2>Join a group</h2>';
            //$return .= $this->flms_join_group_form_shortcode();
            $group_visibility = ' flms-d-none';
        } else {
            $group_visibility = '';
        }
        $group_label = flms_get_label('groups_singular');
        //flag for open seats
        $open_seats = get_user_meta($user->ID, 'flms_open_seats', true);
        //echo '<pre>'.print_r($open_seats,true).'</pre>';
        $empty_seats = 0;
        if(is_array($open_seats)) {
            if(!empty($open_seats)) {
                foreach($open_seats as $course => $data) {
                    foreach($data as $order_info) {
                        $empty_seats += absint($order_info['seats']);
                    }
                }
                if($empty_seats > 0) {
                    if($empty_seats == 1) {
                        $label = strtolower(flms_get_label('seats_singular'));
                    } else {
                        $label = strtolower(flms_get_label('seats_plural'));
                    }
                    $return .="<p>You have <strong>$empty_seats</strong> unassigned $label.";
                    if(empty($groups)) {
                        $return .=' Create a '.strtolower($group_label).' to assign them.';
                    }
                    $return .="</p>";
                }
            }
        }
       
        $date_format = get_option('date_format');
        $return .= '<div class="my-groups-list course-list-item columns-4 mb-4 '.$group_visibility.'">';
            $return .='<div class="course-name flms-font-bold flms-desktop-only">'.$group_label.' Name</div>';
            $return .='<div class="flms-font-bold flms-desktop-only">Created</div>';
            $return .='<div class="flms-font-bold flms-desktop-only">Members</div>';
            $return .='<div class="flms-font-bold flms-desktop-only">'.$group_label.' Code</div>';
            //$return .='<div class="actions flms-font-bold flms-desktop-only">Actions</div>';
            if(!empty($groups)) {
                foreach($groups as $group) {
                    $is_manager = false;
                    $group_managers = get_post_meta($group->ID, 'flms_group_manager');
                    if(in_array($user->user_email, $group_managers)) {
                        $is_manager = true;
                    }
                    //$return .='<pre>'.print_r($group,true).'</pre>';
                    $return .='<div data-label="Group name"><a href="'.get_permalink($group->ID).'" title="'.get_the_title($group->ID).'">'.get_the_title($group->ID).'</a></div>';
                    $return .='<div data-label="Created">'.date($date_format,strtotime($group->post_date)).'</div>';
                    $return .='<div data-label="Members">';
                    $group_members = array_unique(get_post_meta($group->ID, 'flms_group_member'));
                    if(empty($group_members)) {
                        $return .= 0;
                    } else {
                        $count = 0;
                        foreach($group_members as $member) {
                            $group_user = get_user_by('id', $member);
                            if($group_user !== false) {
                                $count++;
                            }
                        }
                        $return .= $count;
                    }
                    $return .= '</div>'; //TODO, get group member count
                    $group_owner = get_post_meta($group->ID,'flms_group_owner',true);
                    if($user->ID == $group_owner || $is_manager) {
                        $code = get_post_meta($group->ID, 'flms_group_code', true);
                        $return .= '<div data-label="Group Code" class="group-code"><p class="flms-share-link flms-no-margin group-share-link tooltip-right" data-flms-tooltip="Copy">'.$code.' <span></span></p></div>';
                    } else {
                        $code = apply_filters('flms_group_code_unavailable', 'Unavailable');
                        $return .='<div data-label="Group Code" class="group-code"><em>'.$code.'</em></div>';
                    }
                    
                }
            }
        $return .='</div>';
        
        $return .='<div class="flms-flex">';
            $return .='<button id="create-group" class="button button-primary">Create New '.$group_label.'</button>';
            if($empty_seats > 0) {
                $return .='<button id="assign-seats-toggle" class="button button-secondary">Assign '.flms_get_label('seats_plural').'</button>';
            }
            $return .= '<div id="flms-groups-buttons">';
            ob_start();
            do_action('flms_groups_page_buttons');
            $return .= ob_get_clean();
            $return .= '</div>';
        $return .= '</div>';
        $return .= $this->get_new_group_form(true, false, 0);

        if(is_array($open_seats)) {
            //$return .='<div style="height: 30px;"></div>'; //tmp
            global $flms_settings;
            if(!empty($open_seats)) {
                $return .= '<form id="assign-seats-form" class="flms-d-none">';
                    $return .='<div class="flms-table-layout columns-4 mb-4">';
                        $return .='<div class="course-name flms-font-bold flms-desktop-only">'.flms_get_label('course_singular').'</div>';
                        $return .='<div class="flms-font-bold flms-desktop-only">Reporting Fees</div>';
                        $return .='<div class="flms-font-bold flms-desktop-only">'.flms_get_label('seats_singular').' Assignment</div>';
                        $return .='<div class="flms-font-bold flms-desktop-only">'.$group_label.'</div>';
                        //$return .='<pre>'.print_r($open_seats,true).'</pre>';
                        $index_counter = 0;
                        foreach($open_seats as $course => $data) {
                            $course_data = explode(':',$course);
                            $course_id = $course_data[0];
                            $course_version = $course_data[1];
                            foreach($data as $order_info) {
                                //$return .='<div class="flms-flex">';
                                    $return .='<div>'.get_the_title($course_id).'</div>';
                                    //foreach($order_data as $order_info) {
                                        //$return .='<pre>'.print_r($order_info,true).'</pre>';
                                        $return .='<div class="flms-flex flex-column">';
                                            if(isset($order_info['reporting_fees'])) {
                                                if(!empty($order_info['reporting_fees'])) {
                                                    foreach($order_info['reporting_fees'] as $k => $v) {
                                                        $accepted = 'Declined';
                                                        if($v == 1) {
                                                            $accepted = 'Accepted';
                                                        }
                                                        $credit_type = str_replace('_reporting_fee','',$k);
                                                        if(isset($flms_settings['labels'][$credit_type])) {
                                                            $label = $flms_settings['labels'][$credit_type];
                                                        } else if(isset($flms_settings['course_credits'][$credit_type]["name"])) {
                                                            $label = $flms_settings['course_credits'][$credit_type]["name"];  
                                                        } else {
                                                            $label = $credit_type;
                                                        }
                                                        $return .='<div>'.$label.': '.$accepted.'</div>';
                                                    }
                                                } else {
                                                    $return .= 'N/A';    
                                                }
                                            } else {
                                                $return .= 'N/A';
                                            }
                                        $return .='</div>';
                                        $return .='<div>';
                                            $return .='<select class="select2-no-search" style="width: 65px;" name="seat-assignment-'.$index_counter.'">';
                                                $return .='<option value="0">0</option>';
                                                for($i = 0; $i <= $order_info['seats']; $i++) {
                                                    $return .='<option value="'.$i.'">'.$i.'</option>';
                                                }
                                            $return .='</select>';
                                            $return .=' of '.$order_info['seats'];
                                        $return .='</div>';
                                        $return .='<div>';
                                        if(!empty($groups)) {
                                            $return .='<select class="select2" style="width: 100%;" name="group-assignment-'.$index_counter.'">';
                                            $return .='<option value="0">Select '.$group_label.'</option>';
                                            foreach($groups as $group) {
                                                //$return .='<pre>'.print_r($group,true).'</pre>';
                                                $return .='<option value="'.$group->ID.'">'.get_the_title($group->ID).'</option>';
                                            }
                                            $return .='</select>';
                                        } else {
                                            $return .='No '.flms_get_label('groups_plural').' found';
                                        }
                                        $return .='</div>';
                                    //}
                                //$return .='</div>';
                                $index_counter++;
                            }
                        
                        
                        }
                    $return .='</div>';
                    $return .='<div class="flms-flex">';
                        $return .='<button id="assign-seats" class="button button-primary">Assign '.flms_get_label('seats_plural').'</button>';
                        $return .='<button id="cancel-assign-seats" class="button button-secondary">Cancel</button>';
                    $return .= '</div>';
                $return .= '</form>';
            }
        }
        return $return;
    }

    public function get_new_group_form($hidden = true, $update = false, $post_id = 0) {
        $group_label = flms_get_label('groups_singular');
        if($post_id > 0) {
            $group_name = get_the_title($post_id);
            $post = get_post($post_id);
            $owner = get_post_meta($post_id, 'flms_group_owner', true);
            $managers = get_post_meta($post_id, 'flms_group_manager');
        } else {
            $group_name = '';
            $owner = '';
            $managers = '';
        }
        $html = '<form id="flms-group-form" class="flms-group-form';
        if($hidden) {
            $html .= ' flms-d-none';
        }
        $html .= '">';
            $html .= apply_filters('flms_before_group_code_form', '');
            $html .= '<div class="form-row woocommerce-form-row flms-form-row">';
                $html .= '<label class="flms-label-full-width full-flex">'.$group_label.' Name</label>';
                $html .= '<input type="text" value="'.$group_name.'" id="flms-group-name" class="regular-text woocommerce-Input woocommerce-Input--text input-text" placeholder="'.apply_filters('flms_group_name_placeholder', "Create a Name for your $group_label.", $group_label).'" />';
                $html .= '<div id="name-feedback" class="flms-form-feedback"></div>';
            $html .= '</div>';
            if($update) {
                if($owner != '') {
                    $user = get_user_by('ID', $owner);
                    $owner_email = $user->user_email;
                } else {
                    $owner_email = '';
                }
                $html .= '<div class="form-row woocommerce-form-row flms-form-row">';
                    $html .= '<label class="flms-label-full-width full-flex">Owner email</label>';
                    $html .= '<input type="text" value="'.$owner_email.'" id="flms-group-owner" class="regular-text woocommerce-Input woocommerce-Input--text input-text" placeholder="'.$group_label.' owner email" />';
                    $html .= '<div id="owner-feedback" class="flms-form-feedback"></div>';
                $html .= '</div>';
                $group_managers = implode(', ',$managers);
                $html .= '<div class="form-row woocommerce-form-row flms-form-row">';
                    $html .= '<label class="flms-label-full-width full-flex">Group manager emails</label>';
                    $html .= '<span class="flms-mb-1 flms-d-block""><em>Group managers can add and remove members but not change group settings.</em></span>';
                    $html .= '<input type="text" value="'.$group_managers.'" id="flms-group-managers" name="flms-group-managers" class="regular-text woocommerce-Input woocommerce-Input--text input-text select2tags" placeholder="'.$group_label.' manager email" style="width:100%;" multiple="multiple" />';
                    $html .= '<div id="owner-feedback" class="flms-form-feedback"></div>';
                $html .= '</div>';
            }
            if($post_id > 0) {
                $group_code = get_post_meta($post_id,'flms_group_code',true);
            } else {
                $group_code = flms_generate_group_code(0);
            }
            $html .= '<div class="form-row woocommerce-form-row flms-form-row">';
                $html .= '<label class="flms-label-full-width full-flex">'.$group_label.' Code</label>';
                $html .= '<span class="flms-mb-1 flms-d-block"">The code new users use to join your '.strtolower($group_label).'</span>';
                //$html .= '<input type="text" class="regular-text woocommerce-Input woocommerce-Input--text input-text" placeholder="'.$group_label.' code" value="'.$group_code.'" />';
                $html .= '<div class="flms-flex">';
                    $html .= '<div id="flms-group-code-validator">';
                        $html .= '<input name="flms-group-code" id="flms-group-code" value="'.$group_code.'" type="text" placeholder="'.apply_filters('flms_group_code_placeholder', "Create a Code for your $group_label.", $group_label).'" class="flex-1" />';
                        $html .= '<div class="validity"></div>';
                    $html .= '</div>';
                    $html .= '<button id="check-group-code" class="button button-primary">Validate Code</button>';
                    $html .= '<button id="generate-new-group-code" class="button button-secondary">Generate New Code</button>';
                $html .= '</div>';
                $html .= '<div id="group-code-feedback" class="flms-form-feedback"></div>';
            $html .= '</div>';

            
            if(!$update) {
                $html .= '<div class="flms-flex new-group-actions">';
                    $html .= '<input type="submit" id="create-new-group" value="Create '.$group_label.'" class="btn btn-primary" />';
                    $html .= '<button class="button button-secondary" id="cancel-new-group">Cancel</button>';
                $html .= '</div>';
            } else {
                $html .= '<div class="flms-flex update-group-actions">';
                    $html .= '<input type="submit" id="update-existing-group" value="Update '.$group_label.'" class="btn btn-primary" />';
                    $html .= '<button class="button button-secondary" id="cancel-existing-group">Cancel</button>';
                $html .= '</div>';
            }
            $html .= apply_filters('flms_after_group_code_form', '');
        $html .= '</form>';
        return $html;
    }

    public function get_group_label_options() {
        global $flms_settings;
        $fields = array(
            array(
                'label' => "Groups (Singular)",
                'key' => 'groups_singular',
                'type' => 'text',
                'default' => 'Group'
            ),
            array(
                'label' => "Groups (Plural)",
                'key' => 'groups_plural',
                'type' => 'text',
                'default' => 'Groups'
            ),
            array(
                'label' => "Seats (Singular)",
                'key' => 'seats_singular',
                'type' => 'text',
                'default' => 'Seat'
            ),
            array(
                'label' => "Seats (Plural)",
                'key' => 'seats_plural',
                'type' => 'text',
                'default' => 'Seats'
            ),
        );
        return $fields;
    }
    public function get_ecommerce_options() {
        global $flms_settings;
        $fields = array(
            array(
                'label' => "Groups",
                'key' => 'groups_heading',
                'type' => 'section_heading',
            ),
            array(
                'label' => "My Groups tab name",
                'key' => 'my_groups_tab_name',
                'type' => 'text',
                'default' => 'My Groups',
            ),
            array(
                'label' => "Group Purchase Label",
                'key' => 'group_purchase_label',
                'type' => 'text',
                'default' => 'Group Purchase',
            ),
            array(
                'label' => "Apply discount to bulk group purchases",
                'key' => "groups_bulk_purchase_discount_status",
                'type' => 'radio',
                'options' => array(
                    'none' => 'No Discount',
                    'discounted' => 'Apply Discount'
                ),
                'default' => 'none',
            ),
            array(
                'label' => "Discount Type",
                'key' => "groups_bulk_purchase_discount_type",
                'type' => 'select',
                'options' => array(
                    'percent' => 'Percent',
                    'fixed_per_seat' => 'Fixed per seat',
                    'fixed_from_total' => 'Fixed from total'
                ),
                'default' => 'percent',
            ),
            array(
                'label' => "Discount Amount",
                'key' => 'groups_discount_default_amount',
                'type' => 'number',
                'default' => '10',
            ),
            array(
                'label' => "Minimum seats for discount",
                'key' => 'groups_discount_minimum_seats',
                'type' => 'number',
                'default' => '1',
            ),
            array(
                'label' => "Discount Label",
                'key' => 'group_discount_label',
                'type' => 'text',
                'default' => 'Add 1 or more seats to get a 10% discount'
            ),
            
        );
        return $fields;
    }

    public function flms_groups_metabox() {
        global $flms_settings, $post;
        wp_enqueue_script('jquery-ui-core');
		wp_enqueue_script('jquery-ui-autocomplete');
		wp_enqueue_script('flms-admin-group');
        if(isset($post->ID)) {
            $post_id = $post->ID;
        } else {
            $post_id = 0;
        }
		wp_localize_script('flms-admin-group','flms_admin_group', array(
            'post_id' => $post_id,
			'ajax_url' => admin_url( 'admin-ajax.php' ),
		));

        $metabox_fields = array(
            'settings' => array(
				'label' => "Settings",
				'id' => 'group-settings',
				'description' => '',
				'tooltip' => '',
				'callback' => $this->get_group_settings('standard')
			),
        ); ?>
        <div class="fragment-settings">
			<ul class="tab-selector">
				<?php 
				$tabct = 1;
				foreach($metabox_fields as $field_category => $field_group) { ?>
					<li class="<?php if($tabct == 1) { echo 'is-active'; } ?>">
						<button class="setting-group-button" data-group="<?php echo $field_group['id']; ?>">
							<?php echo $field_group['label']; ?>
						</button>
					</li><?php 
					$tabct++;
				} ?>		
			</ul>
			<div class="tab-content">
				<?php 
				$tabct = 1;
				foreach($metabox_fields as $field_category => $field_group) { ?>
					<div id="<?php echo $field_group['id'] ;?>" class="setting-area-content <?php if($tabct == 1) { echo 'is-active'; } ?> <?php if(isset($field_group['layout'])) { echo 'layout-'.$field_group['layout']; }?>">
						<div class="setting-area-<?php echo $field_group['id'] ;?>">
							<?php if(isset($field_group['description'])) {
								if($field_group['description'] != '') { ?>
									<div class="setting-area-desc"><?php echo $field_group['description']; ?></div>	
								<?php }
							} ?>
							<div class="setting-area-fields">
								<?php echo $field_group['callback']; ?>
							</div>
						</div>
					</div><?php 
					$tabct++;
				} ?>
			</div>
		</div><?php
        
		
		echo '<input type="hidden" name="flms-post-type" value="flms-groups" />';
    }

    public function get_group_settings($layout) {
        global $post;
        if(isset($post->ID)) {
            $post_id = $post->ID;
        } else {
            $post_id = 0;
        }
        $group_id = get_the_ID();
		$return = '<div class="group-settings">';
			$group_owner = get_post_meta($group_id,'flms_group_owner',true);
            if($group_owner != '') {
                global $current_user;
                if($current_user->ID == $group_owner) {
                    $owner = get_user_by('id',$group_owner);
                    $name = $owner->display_name .' (You)';
                } else {
                    $owner = get_user_by('id',$group_owner);
                    $name = $owner->display_name .' ('.$owner->user_email.')';
                }
            } else {
                $name = '';
            }
            $return .= '<div class="settings-field">';
                $return .= '<div class="setting-field-label">';
                    $return .= 'Group Owner';
                    if($layout == 'grid') {
                        $return .= '<div class="flms-tooltip" data-tooltip="Administrator of the group"></div>';
                    }
                $return .= '</div>';
                if($layout != 'grid') {
                    $return .= '<p class="description">Administrator of the group</p>';
                }
                $return .= '<input name="flms-group-owner" id="flms-group-owner" value="'.$name.'" type="text" />';
                $return .= '<input type="hidden" name="flms-group-owner-id" id="flms-group-owner-id" value="'.$group_owner.'" />';
            $return .= '</div>';

            $group_code = get_post_meta($group_id,'flms_group_code',true);
            if($group_code == '') {
                $group_code = flms_generate_group_code($post_id);
            }
            $return .= '<div class="settings-field">';
                $return .= '<div class="setting-field-label">';
                    $return .= 'Access Code';
                    if($layout == 'grid') {
                        $return .= '<div class="flms-tooltip" data-tooltip="The code new users use to join your group"></div>';
                    }
                $return .= '</div>';
                if($layout != 'grid') {
                    $return .= '<p class="description">The code new users use to join your group</p>';
                }
                $return .= '<div class="flms-flex">';
                    $return .= '<div id="flms-group-code-validator">';
                        $return .= '<input name="flms-group-code" id="flms-group-code" value="'.$group_code.'" type="text" />';
                        $return .= '<div class="validity"></div>';
                    $return .= '</div>';
                    $return .= '<button id="check-group-code" class="button button-primary">Check Code</button>';
                    $return .= '<button id="generate-new-group-code" class="button button-secondary">Generate New</button>';
                $return .= '</div>';
                $return .= '<div id="group-code-feedback"></div>';
            $return .= '</div>';

		$return .= '</div>';
		return $return;
    }

    public function save_settings($post_id, $data) {
        $settings = array();
        if(isset($data['flms-group-owner-id'])) {
            $owner = sanitize_text_field($data['flms-group-owner-id']);
            update_post_meta($post_id, 'flms_group_owner', $owner);
        }
        if(isset($data['flms-group-code'])) {
            $group_code = sanitize_text_field($data['flms-group-code']);
            if(flms_is_group_code_valid($post_id, $group_code)) {
                update_post_meta($post_id, 'flms_group_code', $group_code);
            }
        } else {
            $group_code = flms_generate_group_code($post_id);
            update_post_meta($post_id, 'flms_group_code', $group_code);
        }
    }    
    
    public function flms_no_group_access() {
        global $post;
        $current_managers = get_post_meta($post->ID, 'flms_group_manager');
        $group_label = strtolower(flms_get_label('groups_singular'));
        echo apply_filters('flms_no_group_access_notice', "You do not have access to this $group_label.");
        $show_join_form = apply_filters('flms_join_group_form', true);
        if($show_join_form) {
            echo $this->flms_join_group_form_shortcode();
        }
    }

    public function flms_groups_admin_content() {
        global $post;
        $current_user_id = get_current_user_id();
        $current_user = get_user_by('id', $current_user_id);
        $group_owner = get_post_meta($post->ID, 'flms_group_owner', true);
        $managers = get_post_meta($post->ID, 'flms_group_manager');
        if($group_owner != $current_user_id && !in_array($current_user->user_email, $managers)) {
            return;
        }
        $group_members = get_post_meta($post->ID, 'flms_group_member');
        wp_enqueue_script( 'flms-groups' );
        wp_enqueue_style('tagify');
        $group_label = flms_get_label('groups_singular');
        echo '<div class="flms-flex space-between">';
            $show_share_link = apply_filters('flms_group_share_link', true);
            if($show_share_link) {
                $permalink = get_permalink($post->ID);
                $share_text = apply_filters('flms_share_text','Share:');
                echo '<div><p class="flms-share-link group-share-link tooltip-right" data-flms-tooltip="Copy" data-share-text="'.$share_text.'">'.$permalink.' <span></span></p></div>';
            }
            $show_code_link = apply_filters('flms_group_code_link', true);
            if($show_code_link) {
                $code = get_post_meta($post->ID, 'flms_group_code', true);
                $share_text = apply_filters('flms_copy_group_code_text',"$group_label code:");
                echo '<div><p class="flms-share-link group-share-link tooltip-right" data-flms-tooltip="Copy" data-share-text="'.$share_text.'">'.$code.' <span></span></p></div>';
            }
        echo '</div>';

        $manager_ids = array();
        if(!empty($managers)) {
            $invited = get_post_meta($post->ID,'flms_manager_invites');
            $notfound = array();
            $needs_invites = false;
            foreach($managers as $manager) {
                $user = get_user_by('email', $manager);
                if($user === false) {
                    $notice = '<a href="mailto:'.$manager.'">'.$manager.'</a>';
                    if(in_array($manager,$invited)) {
                        $notice .= ' <span class="invite-sent">Invitation sent</span>';
                    } else {
                        $needs_invites = true;
                    }
                    $notfound[] = $notice;
                } else {
                    $manager_ids[] = $user->ID;
                    if(!in_array($user->ID, $group_members)) {
                        add_post_meta($post->ID, 'flms_group_member', $user->ID);
                    }
                    if(!empty($invited)) {
                        delete_post_meta($post->ID,'flms_manager_invites',$user->user_email);
                    }
                }
            }
            
        }
        if($group_owner == $current_user_id) {
            if(!empty($notfound)) {
                echo '<div class="flms-unfound-managers">';
                    echo '<p>Some manager profiles could not be found:</p><ul><li>'.implode('</li><li>',$notfound).'</li></ul>';
                    if($needs_invites) {
                        echo '<button class="button button-primary" id="toggle_invite_managers">Invite Managers</button>';
                    }
                    echo '<div id="invite-managers" class="flms-d-none">';
                        echo '<div class="flms-flex column">';
                            echo '<label>Add a custom message to the manager invitation:</label>';
                            echo '<textarea id="manager-invite-text"></textarea>';
                            echo '<div class="flms-flex">';
                                echo '<button id="send-manager-invitation" class="button button-primary">Send Invitation(s)</button>';
                                echo '<button id="cancel-send-manager-invitation" class="button button-secondary">Cancel</button>';
                            echo '</div>';
                        echo '</div>';
                    echo '</div>';
                echo '</div>';
            }
        

            //check manager members
            

            $edit_label = apply_filters('flms_group_edit_button_text', "Edit $group_label details");
            echo apply_filters('flms_edit_group_button', sprintf('<button id="edit-group-details" class="button button-primary">%s</button>', $edit_label));
            echo $this->get_new_group_form(true, true, $post->ID);

        }
        

        echo '<div class="flms-group-member-data">';
            echo sprintf('<h2>%s Members</h2>', flms_get_label('groups_singular'));
            if(empty($group_members)) {
                echo sprintf('<p><em>This %s has no members.</em></p>',strtolower($group_label));
            } else {
                $group_members = array_unique($group_members);
                $active_members = false;
                //get group courses
                $group_courses = get_post_meta($post->ID, 'group_courses', true);
                $group_course_ids = array();
                if($group_courses != '') {
                    foreach($group_courses as $course_info => $course_settings) {
                        $group_course_ids[] = $course_info;
                    }
                }
                
                    $current_time = strtotime(current_time('mysql'));
                    $member_list = '';
                    foreach($group_members as $group_member) {
                        $user = get_user_by('id', $group_member);
                        if($user !== false) {
                            $active_members = true;
                            $member_list .= '<div class="flms-table-layout course-list-item columns-4 member-row">';
                                $member_list .= '<div data-label="User">'.$user->display_name;
                                    if($group_member == $current_user_id) {
                                        $member_list .= ' (You)';
                                    }
                                $member_list .= '</div>';
                                $last_active = get_user_meta($user->ID,'flms_last_active',true);
                                $member_list .= '<div data-label="Last active">';
                                if($last_active == '') {
                                    $member_list .= 'Never';
                                } else {
                                    $active_timestamp = strtotime($last_active);
                                    $member_list .= sprintf('%s ago', human_time_diff($active_timestamp, $current_time));
                                }
                                $member_list .= '</div>';
                                $member_list .= '<div></div>';
                                
                                
                                if(!in_array($group_member, $manager_ids) && $group_member != $group_owner) {
                                    $member_list .= '<div class="actions" data-label="Actions">';
                                        $member_list .= '<div class="flms-danger-button btn-small italic flms-remove-from-group" data-user="'.$group_member.'">Remove</div>';
                                    $member_list .= '</div>';
                                } else {
                                    $member_list .= '<div class="actions">';
                                        $member_list .= '<div class="flms-danger-button btn-small  no-action">&nbsp;</div>';
                                    $member_list .= '</div>';
                                }
                                
                            $member_list .= '</div>';
                        }
                    }
                    if($active_members) {
                        echo '<div class="flms-table-layout course-list-item columns-4">';
                        echo '<div class="flms-font-bold flms-desktop-only">User</div>';
                        echo '<div class="flms-font-bold flms-desktop-only">Last Active</div>';
                        echo '<div class="flms-font-bold flms-desktop-only"></div>';
                        echo '<div class="flms-font-bold flms-desktop-only actions">Actions</div>';
                        echo '</div>';
                        echo $member_list;
                        
                    } else {
                        echo sprintf('<p><em>This %s has no active members.</em></p>',strtolower($group_label));
                    }
                    
                
                //print_r($group_course_ids);
            }
        echo '</div>';
    }

    public function flms_groups_member_content() {
        global $post;
        if(!wp_script_is( 'select2', 'enqueued' )) {
            wp_enqueue_style( 'select2');
            wp_enqueue_script( 'select2');
        }
        wp_enqueue_script( 'flms-groups' );
        echo '<div id="flms-group-member-content-container">';
        echo $this->get_group_member_content($post->ID);
        echo '</div>';
        
    }

    public function get_group_member_content($post_id) {
        global $flms_settings;
        $group_courses = get_post_meta($post_id, 'group_courses', true);
        $group_owner = get_post_meta($post_id, 'flms_group_owner', true);
        $group_managers = get_post_meta($post_id, 'flms_group_manager');
        $current_user_id = get_current_user_id();
        $current_user = get_user_by('id', $current_user_id);
        $is_manager = false;
        if(in_array($current_user->user_email, $group_managers)) {
            $is_manager = true;
        }
        $return = '';
        if(empty($group_courses)) {
            $course_label = strtolower(flms_get_label('course_plural'));
            $group_label = strtolower(flms_get_label('groups_singular'));
            $return .= apply_filters('flms_no_group_courses', "<p><em>There are no $course_label available to join in this $group_label.</em></p>");
        } else {
            $return .= sprintf('<h2>%s %s</h2>', flms_get_label('groups_singular'), flms_get_label('course_plural'));
            //$return .= '<pre>'.print_r($group_courses, true).'</pre>';
            $course_label = flms_get_label('course_singular');
            if($current_user_id == $group_owner || $is_manager) {
                $columns = 4;
                $notice = '<sup>*</sup>';
            } else {
                $columns = 2;
            }
            $show_course_progress = apply_filters('flms_show_course_progress', true);
            $return .= '<div class="flms-course-list mb-4 flms-groups-list">'; //course-name-first course-list-item columns-'.$columns.'
                /*$return .='<div class="course-name flms-font-bold flms-desktop-only">'.$course_label.'</div>';
                if($current_user_id == $group_owner) {
                    $return .='<div class="flms-font-bold flms-desktop-only">Available Seats '.$notice.'</div>';
                    $return .='<div class="flms-font-bold flms-desktop-only">Reporting Fees '.$notice.'</div>';
                }
                $return .='<div class="flms-font-bold flms-desktop-only actions">Actions</div>';*/
                $course_index = 0;
                foreach($group_courses as $course_info => $course_settings) {
                    $return .= '<div class="flms-course flms-course-output">';
                        $return .= '<div class="course-info">';
                            $course_data = explode(':',$course_info);
                            $course_id = $course_data[0];
                            $course_version = $course_data[1];
                            $course = new FLMS_Course($course_id);
                            global $flms_active_version;
                            $flms_active_version =  $course_version;
                            $seats = 0;
                            if(isset($course_settings['seats'])) {
                                $seats = absint($course_settings['seats']);
                            }
                            if(isset($course_settings['enrolled'])) {
                                $enrolled = count($course_settings['enrolled']);
                                $enrolled_users = $course_settings['enrolled'];
                                //$return .= print_r($enrolled_users,true);
                            } else {
                                $enrolled = 0;
                                $enrolled_users = array();
                            }
                            $return .= '<div data-label="Course:"><a href="'.$course->get_course_version_permalink($course_version).'" class="flms-course-title">'.$course->get_course_version_name($course_version).'</a></div>';
                            if(flms_is_module_active('course_credits')) {
                                $course = new FLMS_Course($course_id);
                                global $flms_course_version_content, $flms_active_version;
                                $flms_active_version = $course_version;
                                $course_credits = new FLMS_Module_Course_Credits();
                                $credits_array = $course_credits->get_course_credits_fields(true,true);
                                $credits_output = array();
                                if(!empty($credits_array)) {
                                    foreach($credits_array as $credit) {
                                        $field = $credit['key'];
                                        $label = $credit['label'];
                                        $default = 0;
                                        if(isset($flms_course_version_content["$flms_active_version"]['course_credits']["$field"])) {
                                            $default = $flms_course_version_content["$flms_active_version"]['course_credits']["$field"];
                                        }
                                        $parent = 'none';
                                        if(isset($flms_settings['course_credits'][$field]['parent'])) {
                                            $parent = $flms_settings['course_credits'][$field]['parent'];
                                        }
                                        if($default > 0) {
                                            
                                            if($parent == 'none') {
                                                $credits_output[] = "$label: $default";
                                            } else {
                                                $credits_output[] = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$label: $default";
                                            }
                                        }
                                    }
                                }
                                if(!empty($credits_output)) {
                                    $label = 'Course Credits';
                                    if(isset($flms_settings['labels']['credits'])) {
                                        $label = $flms_settings['labels']['credits'];
                                    }
                                    $return .= '<div class="flms-flex align-vertical credit-summary"><div class="flms-label group-credit-label flms-desktop-only">'.$label.':</div><div data-label="'.$label.':">'.implode('<br>',$credits_output).'</div></div>';
                                }
                            }
                            

                            if($current_user_id == $group_owner || $is_manager) {
                                //$return .= '</div>';
                                $return .= '<div class="flms-group-owner-info reporting-fees">';
                                    $return .= '<div class="flms-label  flms-desktop-only">Reporting Fees:'.$notice.'</div>';
                                    $return .= '<div data-label="Reporting Fees:*">';
                                        if(empty($course_settings['reporting_fees'])) {
                                            $return .= 'N/A';
                                        } else {
                                            $return .= '<div class="flms-flex flex-column">';
                                                //$return .= print_r($course_settings['reporting_fees'], true);
                                                foreach($course_settings['reporting_fees'] as $fee_type => $fee_data) {
                                                    if(!empty($fee_data)) {
                                                        
                                                        $credit_type = str_replace('_reporting_fee','',$fee_type);
                                                        if(isset($flms_settings['labels'][$credit_type])) {
                                                            $label = $flms_settings['labels'][$credit_type];
                                                        } else if(isset($flms_settings['course_credits'][$credit_type]["name"])) {
                                                            $label = $flms_settings['course_credits'][$credit_type]["name"];  
                                                        } else {
                                                            $label = $credit_type;
                                                        }
                                                        
                                                        $acceptedfees = '';
                                                        foreach($fee_data as $fee_type => $value) {
                                                            if($fee_type == 'accepted') {
                                                                if($value > 0) {
                                                                    
                                                                    $acceptedfees .= '<div>'.$value .' '.ucwords($fee_type).'</div>';
                                                                }
                                                            }
                                                        }
                                                        if($acceptedfees != '') {
                                                            $return .= '<div class="flms-flex align-vertical mobile-flex">';
                                                                $return .= '<div>'.$label.':</div>';
                                                                $return .= '<div class="flms-flex gap-sm">';
                                                                $return .=  $acceptedfees;
                                                                $return .= '</div>';
                                                            $return .= '</div>';
                                                        } else {
                                                            $return .= 'N/A';
                                                        }
                                                            
                                                    }
                                                }
                                            $return .= '</div>';
                                        }
                                    $return .= '</div>';
                                $return .= '</div>';
                                $return .= '<div class="flms-group-owner-info">';
                                    $return .= '<div class="flms-label flms-desktop-only">Available Seats:'.$notice.'</div><div data-label="Available Seats:*">'.$seats . ' <span class="flms-mobile-only">available</span></div>';
                                $return .= '</div>';
                                $return .= '<div class="flms-group-owner-info">';
                                    $return .= '<div class="flms-label flms-desktop-only">Enrolled:'.$notice.'</div><div data-label="Enrolled:*">'.$enrolled . ' <span class="flms-mobile-only">enrolled</span></div>';
                                $return .= '</div>';

                                $return .= '<div class="actions product-data group-data flms-mobile-only" data-label="Status:">';
                                    //if(!flms_user_has_access($course_id, $course_version, true)) {
                                    if(!in_array($current_user_id, $enrolled_users)) {
                                        if($seats > 0) {
                                            $return .= '<button class="button button-primary group-course-enroll" data-course-index="'.$course_index.'">'.flms_get_label('enroll_label').'</button>';
                                        } else {
                                            $return .= '<em>No '.strtolower(flms_get_label('seats_plural')).' available</em>';
                                        }
                                    } else {
                                        $return .= '<em>Enrolled</em>';
                                    }
                                $return .= '</div>';
                                
                                if($enrolled > 0) {
                                    $return .= '<div class="flms-group-owner-info">';
                                        $return .= '<div data-label="Actions:" class="span-columns"><a href="#course-progress-'.$course_index.'" title="View user progress" class="button button-secondary toggle-group-users-progress" data-toggle-text="Hide user progress">View user progress</a></div><div></div>';
                                    $return .= '</div>';
                                }
                            } else {
                                $return .= '<div class="actions product-data group-data flms-mobile-only" data-label="Status:">';
                                    //if(!flms_user_has_access($course_id, $course_version, true)) {
                                    if(!in_array($current_user_id, $enrolled_users)) {
                                        if($seats > 0) {
                                            $return .= '<button class="button button-primary group-course-enroll" data-course-index="'.$course_index.'">'.flms_get_label('enroll_label').'</button>';
                                        } else {
                                            $return .= '<em>No '.strtolower(flms_get_label('seats_plural')).' available</em>';
                                        }
                                    } else {
                                        $return .= '<em>Enrolled</em>';
                                    }
                                $return .= '</div>';
                            }
                        $return .= '</div>';
                        $return .= '<div class="actions product-data group-data flms-desktop-only" data-label="Status:">';
                            //if(!flms_user_has_access($course_id, $course_version, true)) {
                            if(!in_array($current_user_id, $enrolled_users)) {
                                if($seats > 0) {
                                    $return .= '<button class="button button-primary group-course-enroll" data-course-index="'.$course_index.'">'.flms_get_label('enroll_label').'</button>';
                                } else {
                                    $return .= '<em>No '.strtolower(flms_get_label('seats_plural')).' available</em>';
                                }
                            } else {
                                $return .= '<em>Enrolled</em>';
                            }
                        $return .= '</div>';
                        if(($current_user_id == $group_owner || $is_manager) && $enrolled > 0) {
                            $return .= '<div id="course-progress-'.$course_index.'" class="course-progress flms-d-none">';
                                //$return .= '<h3>User Progress</h3>';
                                if($show_course_progress) {
                                    $columns = 3;
                                } else {
                                    $columns = 2;
                                }
                                $return .= '<div class="my-courses-list course-list-item columns-'.$columns.'">';
                                    $return .= '<div class="flms-font-bold flms-desktop-only">Name</div>';
                                    $return .= '<div class="flms-font-bold flms-desktop-only">Status</div>';
                                    if($show_course_progress) {
                                        $return .= '<div class="flms-font-bold flms-desktop-only">Progress</div>';
                                    }
                                    //$return .= '<div class="flms-font-bold flms-desktop-only actions">Actions</div>';
                                    foreach($enrolled_users as $enrolled_user_id) {
                                        $user = get_user_by('ID', $enrolled_user_id);
                                        $return .= '<div data-label="Name">'.$user->display_name;
                                        if($enrolled_user_id == $current_user_id) {
                                            $return .= ' (You)';
                                        }
                                        $return .= '</div>';
                                        $flms_user_activity = flms_get_user_activity($enrolled_user_id, $course_id, $course_version);
                                        $status = '';
                                        if(isset($flms_user_activity['customer_status'])) {
                                            $status = sanitize_text_field($flms_user_activity['customer_status']);
                                        }
                                        $return .= '<div data-label="Status:">'.ucwords($status).'</div>';

                                        if($show_course_progress) {
                                            $completed = 0;
                                            if(isset($flms_user_activity['steps_completed'])) {
                                                $completed = count(maybe_unserialize($flms_user_activity['steps_completed']));
                                            }
                                        // $return .= print_r($flms_user_activity,true).'<br>';
                                            $steps = $course->get_all_course_steps();
                                            $steps_count = count($steps);
                                            if($steps_count == 0) {
                                                $percent = '0%';
                                            } else {
                                                $percent = absint(100 * ($completed / absint($steps_count))).'%';
                                            }
                                            $return .= '<div data-label="Progress">'.$percent.' ('.$completed .' of '.$steps_count.' steps)</div>';
                                        }

                                        //$return .= '<div class="actions"><a class="remove-user-from-group-course" href="/#remove-users">Unenroll</a></div>';
                                    }
                                $return .= '</div>';
                            $return .= '</div>';
                        }
                    $return .= '</div>';

                    $course_index++; //iterate index
                }
            $return .= '</div>';
            if($current_user_id == $group_owner || $is_manager) {
                $return .= "<p class='flms-asterisk-notice align-right'>$notice Data available to group owners only</p>";
            }
        }
        
        if($group_owner == $current_user_id) {
            $return .= '<div class="flms-asterisk-notice align-right"><div id="flms-delete-group" class="flms-danger-button">Delete group</button></div>';
        } else {
            $return .= '<div class="flms-asterisk-notice align-right"><div id="flms-leave-group" class="flms-danger-button">Leave group</button></div>';
        }
        return $return;
    }

    public function user_email_change_updates( $user_id, $old_user_data ) { 
        $old_user_email = $old_user_data->data->user_email;
    
        $user = get_userdata( $user_id );
        $new_user_email = $user->user_email;
    
        if ( $new_user_email !== $old_user_email ) {
            $args = array(
				'post_type' => 'flms-groups',
				'posts_per_page' => -1,
				'post_status' => 'any',
				'meta_query' => array(
					array(
						'key' => 'flms_group_manager',
						'value' => $old_user_email,
						'compare' => '=',
					),
				),
			);
			$query = new WP_Query($args);
			if ($query->have_posts()) {
				while ($query->have_posts()) {
					$query->the_post();
					$group_id = get_the_ID();
                    delete_post_meta($group_id,'flms_group_manager',$old_user_email);
                    add_post_meta($group_id,'flms_group_manager',$new_user_email);
				}
			}
			wp_reset_postdata();
        }
    } 

    public function course_available_through_group($course_id, $course_version) {
        global $current_user;
        $groups = $this->get_user_group_ids($current_user);
        $available = false;
        $course_identifier = "$course_id:$course_version";
        foreach($groups as $group) {
            $group_courses = get_post_meta($group, 'group_courses', true);
            if(is_array($group_courses)) {
                foreach($group_courses as $k => $v) {
                    if($k == $course_identifier) {
                        if(isset($v['seats'])) {
                            if($v['seats'] > 0) {
                                if(isset($v['enrolled'])) {
                                    $enrolled = $v['enrolled'];
                                } else {
                                    $enrolled = array();
                                }
                                if(!in_array($current_user->ID, $enrolled)) {
                                    $link = '<a href="'.get_permalink($group).'" title="'.get_the_title($group).'">'.get_the_title($group).'</a>';
                                    $course_label = strtolower(flms_get_label('course_singular'));
                                    $message = apply_filters('flms_course_available_from_group_text', "This $course_label is available for free as part of your $link membership.", $group);
                                    return flms_alert($message);
                                }
                            }
                        }
                    }
                }
            }
            //print_r($group_courses);
        }
        return;
    }
}
new FLMS_Module_Groups();