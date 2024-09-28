<?php
class FLMS_Module_Woocommerce {

    public function __construct() {
        global $flms_settings;
        

	}

    public function flms_init_woo_actions_and_filters() {
        add_action( 'woocommerce_product_options_general_product_data', array($this,'flms_simple_product_course_correlation') );
        add_action('woocommerce_process_product_meta', array($this, 'save_simple_product_correlation'));
        add_action( 'woocommerce_product_after_variable_attributes', array($this,'flms_variations_course_correlation'), 10, 3 );
        add_action( 'woocommerce_save_product_variation', array($this,'flms_variations_save_course_correlation'), 10, 2 );
        //add_filter( 'woocommerce_available_variation', array($this,'flms_variations_get_course_correlation_data') );

        add_filter( 'query_vars', array($this, 'wp_query_vars'), 0 );
        add_filter( 'woocommerce_get_query_vars', array($this, 'woocommerce_query_vars') );
        
        add_action( 'init', array($this, 'my_account_my_courses_endpoint') );
        add_filter( 'woocommerce_account_menu_items', array($this, 'my_account_my_courses_link') );
        
        $tab_slug = get_option('flms_my_courses_endpoint');
        add_action( "woocommerce_account_{$tab_slug}_endpoint", array($this, 'my_courses_tab_content') );
        $tab_slug = get_option('flms_my_credits_endpoint');
        add_action( "woocommerce_account_{$tab_slug}_endpoint", array($this, 'my_credits_tab_content') );
        $tab_slug = get_option('flms_my_licenses_endpoint');
        add_action( "woocommerce_account_{$tab_slug}_endpoint", array($this, 'my_licenses_tab_content') );

        if(flms_is_module_active('groups')) {
            $tab_slug = get_option('flms_my_groups_endpoint');
            add_action( "woocommerce_account_{$tab_slug}_endpoint", array($this, 'my_groups_tab_content') );
        }

        add_filter('woocommerce_settings_pages',array($this,'my_account_endpoint_slug'));
        add_action( 'wp_enqueue_scripts', array($this,'woocommerce_frontend_assets') );
        add_action( 'admin_enqueue_scripts', array($this,'woocommerce_admin_assets') );
        add_filter( 'woocommerce_checkout_registration_required',  array($this,'filter_woocommerce_checkout_registration_required'), 10, 1 );

        //order actions
        add_action('woocommerce_payment_complete', array($this,'enroll_customer_courses'));
        //add_action( 'woocommerce_order_status_changed', array($this,'enroll_on_status_change'), 10, 4 );
        
        add_action( 'woocommerce_order_refunded', array( $this, 'remove_course_access_on_refund' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'remove_course_access' ), 10, 1 );
		add_action( 'woocommerce_order_status_failed', array( $this, 'remove_course_access' ), 10, 1 );
        

        //frontend product display
        //add_action('woocommerce_after_shop_loop_item_title', array($this, 'flms_shop_metadata'), 6);
        //add_action( 'woocommerce_before_add_to_cart_button',  array($this,'flms_simple_course_metadata'), 8 );
        add_filter( 'woocommerce_available_variation',  array($this,'flms_variation_course_metadata'), 10, 3 );
        add_filter( 'woocommerce_cart_item_name',  array($this,'vp_cart_display_commodity_code'), 10, 3 );
        //add_filter( 'woocommerce_checkout_cart_item_quantity', array($this,'vp_checkout_display_commodity_code'), 10, 3 );
        add_filter( 'woocommerce_checkout_create_order_line_item',  array($this,'vp_order_item_save_commodity_code'), 10, 4 );
        add_action( 'woocommerce_order_item_meta_start',  array($this,'vp_order_item_display_commodity_code'), 10, 4 );

        //sell individually
        add_filter('woocommerce_is_sold_individually',array($this,'force_sell_individually'), 10, 2);
        
        //force account login
        add_filter('woocommerce_checkout_registration_required',array($this, 'force_account_login_on_checkout'));

        if(flms_is_module_active('course_credits')) {
            global $flms_course_credit_types;
            $course_credits = new FLMS_Module_Course_Credits();
            $flms_course_credit_types = $course_credits->get_course_credit_fields();

            $show_cart_credits = true;
            if(isset($flms_settings['woocommerce']['cart_course_credits_summary'])) {
                if($flms_settings['woocommerce']['cart_course_credits_summary'] == 'hide') {
                    $show_cart_credits = false;
                }
            }
            if($show_cart_credits) {
                add_action('woocommerce_cart_collaterals', array($this,'flms_cart_credit_totals'), 6);
                add_action('woocommerce_cart_collaterals', array($this,'flms_close_cart_totals'), 11);
            }

            //Credit reporting fee option
            //add_action( 'woocommerce_available_variation', array($this,'flms_reporting_fee_product_addon'), 11, 3 );
            add_filter( 'woocommerce_add_to_cart_validation', array($this,'flms_reporting_fee_product_addon_validation'), 10, 3 );
            add_filter( 'woocommerce_add_cart_item_data', array($this,'flms_reporting_fee_product_addon_cart_item_data'), 10, 2 );
            add_action( 'woocommerce_new_order_item', array($this,'flms_reporting_fee_product_addon_order_item_meta'), 10, 2 );
            //add_filter( 'woocommerce_order_item_product', array($this,'flms_reporting_fee_product_addon_display_order'), 10, 2 );
            //add_filter( 'woocommerce_email_order_meta_fields', array($this,'flms_reporting_fee_product_addon_display_emails') );

            add_action('woocommerce_cart_calculate_fees',array($this,'add_reporting_fees_to_cart'));

            //change add to cart button
            add_filter( 'woocommerce_is_purchasable', array($this,'purchasable_filter_for_course_credits'), 10, 2 );

            //extra profile information in my account
            add_action( 'woocommerce_edit_account_form', array($this, 'woocommerce_add_account_details') );
            //save my account data, handled through action in tab content
            //add_action('woocommerce_save_account_details', array($this, 'woocommerce_save_account_details'));
            //add start tab to my account
            add_action('woocommerce_edit_account_form_start', array($this, 'woocommerce_start_account_accordion'));
            //add end tab to my account
            add_action('woocommerce_edit_account_form_end', array($this, 'woocommerce_end_account_accordion'));

            //add_action('woocommerce_add_order_item_meta',array($this, 'woocommerce_display_accept_decline_flms_reporting_fee_admin'),1,2);
            add_action( 'woocommerce_before_save_order_items',  array($this,'action_before_save_order_item_callback'), 10, 2 );
            add_action('woocommerce_before_order_itemmeta', array($this, 'woocommerce_display_accept_decline_flms_reporting_fee_admin'), 10, 3);
            add_action('woocommerce_order_after_calculate_totals', array($this, 'woocommerce_accept_decline_flms_reporting_fee_admin'), 10, 2);
            
            add_filter('woocommerce_hidden_order_itemmeta',array($this,'hide_woo_meta_fields'));

            add_filter('woocommerce_order_item_get_formatted_meta_data',array($this,'hide_credits_meta_fields'), 10, 2);

            //disable order again since fields need to be accepted or declined
            add_filter('woocommerce_valid_order_statuses_for_order_again', array($this, 'remove_order_again_button'));
        }

        if(flms_is_module_active('groups')) {
            add_action( 'woocommerce_before_calculate_totals', array($this, 'group_purchase_price_adjustments'), 10, 1);
        }

        add_action('woocommerce_product_meta_end', array($this, 'flms_extra_woo_product_meta'));

        if(flms_is_module_active('course_materials')) {
            add_action('woocommerce_product_meta_start', array($this, 'flms_woo_course_materials'));
        }

        add_filter( 'woocommerce_add_to_cart_redirect', array($this, 'flms_cart_redirect'), 10, 2 );
        //add_action('woocommerce_product_additional_information', array($this, 'flms_woo_additional_information'), 50);

        //hide the woocommerce products generated from the plugin
        add_action( 'pre_get_posts', array( $this, 'get_products_related_to_courses'), 99 );
        add_filter('wp_count_posts', array($this,'update_products_count'), 10, 3);

        //add_action('woocommerce_after_single_variation', array($this, 'accept_reporting_fee'), 9); //woocommerce_available_variation
        add_action( 'template_redirect', array($this,'product_redirection_to_home'), 100 );
        add_action('flms_before_content',array($this,'woo_notices'));
        add_filter( 'post_type_link', array($this, 'update_product_links'),999,3);
        add_filter( 'wp_robots', array($this, 'block_course_products_from_robots'));
        add_filter( 'woocommerce_return_to_shop_redirect', array($this,'flms_change_return_shop_url') );

        add_filter('woocommerce_order_item_display_meta_key', array($this, 'update_reporting_fee_checkout_display'), 10, 3);

       // add_filter('woocommerce_shortcode_products_query_results', array($this, 'woocommerce_shortcode_products_query_results'), 10, 2);
       add_filter( 'woocommerce_cart_item_thumbnail', array($this, 'woo_thumbnail_override'), 10, 3 );

       
    }

    public function woo_thumbnail_override($product_image, $cart_item, $cart_item_key) {
        $product_id = $cart_item['product_id'];
        $course_id = get_post_meta($product_id, 'flms_woocommerce_product_id', true);
        if($course_id != '') {
            if(has_post_thumbnail( $course_id )) {
                return get_the_post_thumbnail( $course_id );
            }
        }
        return $product_image;
    }

    public function woocommerce_shortcode_products_query_results($results, $query) {
        print_r($query);
    }

    public function group_purchase_price_adjustments($cart) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) )
        return;
        
        // Avoiding hook repetition (when using price calculations for example | optional)
        if ( did_action( 'flms_group_purchase_price_adjustments' ) >= 1 ) {
            return;
        }

        global $flms_settings;
        // Loop through cart items
        foreach ( $cart->get_cart() as $cart_item ) {
            //print_r($cart_item['data']);
            $price = $cart_item['data']->get_price();
            $seats = 1;
            if(isset($cart_item['group_seats'])) {
                $seats = absint($cart_item['group_seats']);
                if($seats == 0) {
                    $seats = 1;
                }
            }
            $new_price = $seats * $price;
            $min_seats = isset( $flms_settings['woocommerce']['groups_discount_minimum_seats'] ) ? absint( $flms_settings['woocommerce']['groups_discount_minimum_seats'] ) : 1;
			$discount_amount = isset( $flms_settings['woocommerce']['groups_discount_default_amount'] ) ? absint( $flms_settings['woocommerce']['groups_discount_default_amount'] ) : 0;
			$discount_type = isset( $flms_settings['woocommerce']['groups_bulk_purchase_discount_type'] ) ? sanitize_text_field( $flms_settings['woocommerce']['groups_bulk_purchase_discount_type'] ) : 'percent';
            if($seats >= $min_seats) {
                switch($discount_type) {
                    case 'percent':
                        $new_price = $new_price * ((100 - $discount_amount) / 100);
                        break;
                    case 'fixed_per_seat':
                        $new_price = $seats * ($price - $discount_amount);
                        break;
                    case 'fixed_from_total':
                        $new_price = $new_price - $discount_amount;
                        break;
                }
            }
            $cart_item['data']->set_price( apply_filters('flms_cart_item_price', $new_price, $cart ) );
        }
        do_action('flms_group_purchase_price_adjustments');
        //remove_action( 'woocommerce_before_calculate_totals', array($this, 'group_purchase_price_adjustments'), 999, 1);
    }
    public function block_course_products_from_robots($robots) {
        if (is_singular( 'product' ) ) {
            global $post;
            $course_id = get_post_meta($post->ID, 'flms_woocommerce_product_id', true);
            if(is_numeric($course_id)) {
                if($post->ID != $course_id) {
                    $robots['noindex']  = true;
                    $robots['nofollow'] = true;
                }
            }
            
        }
        return $robots;
    }

    public function update_product_links( $url, $post, $sample) {
        if($post->post_type != 'product') {
            return $url;
        }
        $course_id = get_post_meta($post->ID, 'flms_woocommerce_product_id', true);
        if(is_numeric($course_id)) {
            if($post->ID != $course_id) {
                return get_permalink($course_id);
            }
        }
        return $url;

    }


    public function flms_change_return_shop_url($link) {

        return trailingslashit(get_bloginfo('url')).'courses';
        //return $link;
    
    }
    
    public function woo_notices() {
        echo '<div class="woocommerce-notices-wrapper">';
            wc_print_notices();
        echo '</div>';
    }

    public function product_redirection_to_home() {
        //if ( ! is_product() ) return; // Only for single product pages.
        global $post;
        if(isset($post->ID)) {
            if(get_post_type($post->ID) != 'product') {
                return;
            }
            $course_id = get_post_meta($post->ID, 'flms_woocommerce_product_id', true);
            if(is_numeric($course_id)) {
                if($post->ID != $course_id) {
                    wp_safe_redirect( get_permalink($course_id) ); // redirect home.
                    exit();
                }
            }
        }
        return;
    }

    /*public function accept_reporting_fee() {
        if(flms_is_module_active('course_credits')) {
            global $product;
            $course_id = get_post_meta($product->get_id(), 'flms_woocommerce_product_id', true);
            $value = array("$course_id:1");
            echo $this->display_accepts_reporting_fee($value);
        }
    }*/

    public function flms_cart_redirect($url, $adding_to_cart) {
        if(isset($_REQUEST['add-to-cart'])) {
            $product_id = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_REQUEST['add-to-cart'] ) );
            $course_id = get_post_meta($product_id, 'flms_woocommerce_product_id', true);
            if($course_id != '') {
               $url = get_permalink( $course_id );
            }
        }
        return $url;
    }

    public function woocommerce_admin_assets() {
        wp_enqueue_script(
            'flms-admin-woocommerce',
            FLMS_PLUGIN_URL . 'assets/js/woocommerce.js',
            array('jquery','selectWoo'),
            false,
            true
        );
        /*wp_localize_script( 'flms-admin-course-manager', 'flms_admin_course_manager', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'settings' => $flms_settings,
            'lesson_list_html' => $this->get_lesson_list_html(),
        ));*/
    }

    public function woocommerce_frontend_assets() {
        global $flms_settings;
        wp_enqueue_script(
            'flms-woocommerce',
            FLMS_PLUGIN_URL . 'assets/js/frontend/woocommerce.js',
            array('jquery','wc-blocks-checkout'),
            false,
            true
        );
        wp_localize_script( 'flms-woocommerce', 'flms_woocommerce', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'settings' => $flms_settings,
        ));
    }
    
    public function get_woocommerce_module_fields() {
        global $flms_settings;
        $course_name = 'Courses';
		if(isset($flms_settings['labels']["course_plural"])) {
			$course_name = strtolower($flms_settings['labels']["course_plural"]);
		}
        $lower = strtolower($course_name);
        $courses_label = 'my courses';
        if(isset($flms_settings['woocommerce']['my_courses_tab_name'])) {
            $label = strtolower($flms_settings['woocommerce']['my_courses_tab_name']);
        }
        $fields = array(
            array(
                'label' => "Course Content",
                'key' => 'course_content_heading',
                'type' => 'section_heading',
            ),
            array(
                'label' => "My $course_name tab name",
                'key' => 'my_courses_tab_name',
                'type' => 'text',
                'default' => 'My '.$course_name,
            ),
            array(
                'label' => 'Show '.$courses_label.' account tab',
                'key' => 'my_courses_tab',
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
                'label' => 'Additional profile fields',
                'key' => 'profile_display',
                'type' => 'radio',
                'options' => array(
                    'default' => 'Default',
                    'accordion' => 'Accordion'
                ),
                'default' => 'default',
                'flag_check' => '',
                'description' => 'Layout for additional profile fields declared.'
            ),
            array(
                'label' => 'Product label',
                'key' => 'associated_courses_label',
                'type' => 'text',
                'default' => 'Associated '.$lower.':',
                'description' => 'The label that appears on the product, cart and checkout pages which explains which '.$course_name.' are enrolled with a product purchase. An empty value hides the label.'
            ),
            array(
                'label' => "Product Default Options",
                'key' => 'product_options_heading',
                'type' => 'section_heading',
            ),
            array(
                'label' => 'Product Type',
                'key' => 'default_product_type',
                'type' => 'select',
                'options' => array(
                    'simple' => 'Simple',
                    'variable' => 'Variable'
                ),
                'default' => 'simple',
                'flag_check' => '',
                'description' => 'Select the default product type to use when selling a course. This can be changed on a course by course basis.'
            ),
            array(
                'label' => 'Default Variation Attributes',
                'key' => 'default_variation_attributes',
                'type' => 'attribute_select',
                'default' => '',
                'description' => 'Select the default attributes to use when selling a course. This can be changed on a course by course basis.'
            ),
        );
        if(flms_is_module_active('course_credits')) {
            $course_credits = new FLMS_Module_Course_Credits();
            $course_credit_fields = $course_credits->get_ecommerce_options();
            $fields = array_merge($fields, $course_credit_fields);
        }
        if(flms_is_module_active('course_taxonomies')) {
            $course_taxonomies = new FLMS_Module_Course_Taxonomies();
            $course_tax_fields = $course_taxonomies->get_taxonomy_options();
            $fields = array_merge($fields, $course_tax_fields);
        }
        if(flms_is_module_active('course_materials')) {
            $course_materials = new FLMS_Module_Course_Materials();
            $course_tax_fields = $course_materials->get_ecommerce_options();
            $fields = array_merge($fields, $course_tax_fields);
        }
        if(flms_is_module_active('groups')) {
            $groups = new FLMS_Module_Groups();
            $course_tax_fields = $groups->get_ecommerce_options();
            $fields = array_merge($fields, $course_tax_fields);
        }
        return $fields;
    }

    public function flms_cart_credit_totals() {
        global $flms_settings;
        $title = 'Credits Summary';
        if(isset($flms_settings['labels']['credits_summary'])) {
            $title = $flms_settings['labels']['credits_summary'];
        }
        echo '<div class="col2-set">';
            echo '<div class="col-1">';
                echo '<h2>'.$title.'</h2>';
                echo '<div id="flms-cart-credits-summary">';
                    echo $this->flms_cart_credit_table();
                echo '</div>';
            echo '</div>';
        //echo '</div>'; //this is closed in flms_close_cart_totals
    }

    public function flms_cart_credit_table() {
        global $flms_settings;
        $cart_credits = $this->get_cart_credits();

        if(!empty($cart_credits)) { 
            $credits = '<table class="shop_table shop_table_responsive">';
                $credits .= '<tbody>';
                    foreach($cart_credits as $k => $v) {
                        $label = flms_get_label($k);
                        $credits .= '<tr><th>'.$label.'</th><td data-title="'.$label.'">'.$v.'</td></tr>';
                    }
                $credits .= '</tbody>';
            $credits .= '</table>';
        } else {
            $credits = 'No course credits in cart.';
        }
        return $credits;
    }

    public function flms_close_cart_totals() {
        echo '</div>';
    }

    public function get_cart_credits() {
        // Loop through cart items
        $credits = array();
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            //print_r($cart_item);
            $product = wc_get_product($cart_item['product_id']);
            $group_seats = 1;
            if(isset($cart_item['group_seats'])) {
                $group_seats = $cart_item['group_seats'];
            }
            $type = $product->get_type();
            if($type == 'simple') {
                $product_id = $cart_item['product_id'];
                $courses = get_post_meta( $product_id, 'flms_woocommerce_simple_course_ids', true );
            } else {
                $product_id = $cart_item['variation_id'];
                $courses = get_post_meta( $product_id, 'flms_woocommerce_variable_course_ids', true );
            }
            if($courses != '') {
                foreach($courses as $course_data) {
                    $course_info = explode(':',$course_data);
                    $course_id = $course_info[0];
                    $course_version = $course_info[1];
                    $course = new FLMS_Course($course_id);
                    global $flms_active_version;
                    $flms_active_version = $course_version;
                    $course_credits = new FLMS_Module_Course_Credits();
                    $new_credits = $course_credits->flms_get_course_credits_array();
                    foreach($new_credits as $k => $v) {
                        if(!isset($credits[$k])) {
                            $credits[$k] = 0;
                        }
                        $credits[$k] += $v * $group_seats;
                    }
                    //$credits = array_merge($credits,$new_credits);
                }
            }
        }
        return $credits;
    }

    public function get_woocommerce_module_field($field) {
        global $flms_settings;
        $return = '';
        if(isset($flms_settings['woocommerce'])) {
            $white_label_fields = $flms_settings['woocommerce'];
            if(isset($white_label_fields["$field"])) {
                $return = $white_label_fields["$field"];
            } else {
                /*$all = $this->get_woocommerce_module_fields();
                $key = array_search($field, array_map(function($data) {
                    if(isset($data['key'])) {
                        return 'YEAT';
                    } 
                }, $all));*/
                
                
            }
        }
        return $return;
    }

    public function my_account_my_courses_endpoint() {
        $tab_slug = get_option('flms_my_courses_endpoint');
        add_rewrite_endpoint( $tab_slug, EP_ROOT | EP_PAGES );
        $credits_tab_slug = get_option('flms_my_credits_endpoint');
        add_rewrite_endpoint( $credits_tab_slug, EP_ROOT | EP_PAGES );
        $license_tab_slug = get_option('flms_my_licenses_endpoint');
        add_rewrite_endpoint( $license_tab_slug, EP_ROOT | EP_PAGES );
        if(flms_is_module_active('groups')) {
            $groups_tab_slug = get_option('flms_my_groups_endpoint');
            add_rewrite_endpoint( $groups_tab_slug, EP_ROOT | EP_PAGES );
        }
    }

    public function woocommerce_query_vars($vars) {
        $tab_slug = get_option('flms_my_courses_endpoint');
        $vars[$tab_slug] = $tab_slug;
        $credits_tab_slug = get_option('flms_my_credits_endpoint');
        $vars[$credits_tab_slug] = $credits_tab_slug;
        $license_tab_slug = get_option('flms_my_licenses_endpoint');
        $vars[$license_tab_slug] = $license_tab_slug;
        if(flms_is_module_active('groups')) {
            $groups_tab_slug = get_option('flms_my_groups_endpoint');
            $vars[$groups_tab_slug] = $groups_tab_slug;
        }
        
        return $vars;
    }
        
    public function wp_query_vars( $vars ) {
        $tab_slug = get_option('flms_my_courses_endpoint');
        $vars[] = $tab_slug;
        $credits_tab_slug = get_option('flms_my_credits_endpoint');
        $vars[] = $credits_tab_slug;
        $license_tab_slug = get_option('flms_my_licenses_endpoint');
        $vars[] = $license_tab_slug;
        if(flms_is_module_active('groups')) {
            $groups_tab_slug = get_option('flms_my_groups_endpoint');
            $vars[] = $groups_tab_slug;
        }
        
        return $vars;
    }
      
    public function my_account_my_courses_link( $items ) {
        $tab_name = $this->get_woocommerce_module_field('my_courses_tab_name');
        $tab_slug = get_option('flms_my_courses_endpoint');
        $lms_tab = array(
            $tab_slug => $tab_name
        );
        //$lms_tab[$tab_slug] = $tab_name;
        $items = flms_array_insert_after($items, 'dashboard', $lms_tab);

        global $flms_settings;
        $display_credits_tab = $flms_settings['woocommerce']['my_credits_tab'];
        if($display_credits_tab != 'hide') {
            $credits_tab_name = $this->get_woocommerce_module_field('my_credits_tab_name');
            $credits_tab_slug = get_option('flms_my_credits_endpoint');
            $lms_tab = array(
                $credits_tab_slug => $credits_tab_name
            );
            //$lms_tab[$tab_slug] = $tab_name;
            $items = flms_array_insert_after($items, $tab_slug, $lms_tab);
            $tab_slug = $credits_tab_slug;
        }
        $licenses_location = $flms_settings['woocommerce']['my_licensess_account_location'];
        if($licenses_location == 'tab') {
            $credits_tab_name = $this->get_woocommerce_module_field('my_licenses_tab_name');
            $credits_tab_slug = get_option('flms_my_licenses_endpoint');
            $lms_tab = array(
                $credits_tab_slug => $credits_tab_name
            );
            //$lms_tab[$tab_slug] = $tab_name;
            $items = flms_array_insert_after($items, $tab_slug, $lms_tab);
            $tab_slug = $credits_tab_slug;
        }

        if(flms_is_module_active('groups')) {
            $groups_tab_name = $this->get_woocommerce_module_field('my_groups_tab_name');
            $groups_tab_slug = get_option('flms_my_groups_endpoint');
            $lms_tab = array(
                $groups_tab_slug => $groups_tab_name
            );
            //$lms_tab[$tab_slug] = $tab_name;
            $items = flms_array_insert_after($items, $tab_slug, $lms_tab);
            $tab_slug = $credits_tab_slug;
        }
        
        return $items;
    }
      
    public function my_courses_tab_content() {
        global $flms_settings, $current_user;
	    $user_id = $current_user->ID;
        $active_courses = flms_get_user_active_courses();
        
        do_action('flms_before_my_courses');
        $course_name = 'My Courses';
        if(isset($flms_settings['labels']["course_plural"])) {
            $course_name = $flms_settings['labels']["course_plural"];
        }
        $this->my_account_missing_licenses_notice($active_courses, 'my-courses');

        if(flms_is_module_active('groups')) {
            $open_seats = get_user_meta($user_id, 'flms_open_seats', true);
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
                        $message = "You have <strong>$empty_seats</strong> unassigned $label.";
                        
                        $groups_tab_slug = get_option('flms_my_groups_endpoint');
					    $endpoint = wc_get_account_endpoint_url( $groups_tab_slug );
                        $link = ' <a href="'.$endpoint.'" title="'.$label.'">Assign '.$label.'</a>';
                        $message .= $link;
                        //$message .="</p>";
                        $message = apply_filters('flms_wc_available_seats_message', $message);
                        wc_print_notice( $message, 'notice');
                    }
                }
            }
        }

        echo "<h2>Active $course_name</h2>";
        $no_courses = apply_filters('flms_no_active_courses','<p>You do not have any active '.strtolower($course_name).'.</p>');
        if(!is_array($active_courses)) {
            echo $no_courses;
        } else if(empty($active_courses)) {
            echo $no_courses;
        } else {
            echo flms_get_user_active_course_list($user_id, $active_courses);
        }

        $completed_courses = flms_get_user_completed_courses();
        //print_r($completed_courses);
        
        if(!is_array($completed_courses)) {
            //echo '<p>You have not completed any '.strtolower($course_name).'.</p>';
        } else if(empty($completed_courses)) {
            //echo '<p>You have not completed any '.strtolower($course_name).'.</p>';
        } else {
            echo '<h2 class="mt-4">Completed '.$course_name.'</h2>';
            echo flms_get_user_completed_course_list($user_id, $completed_courses);
        }
    }

    public function my_account_missing_licenses_notice($active_courses, $location) {
        global $flms_settings;
        $show_setting = $flms_settings['woocommerce']['missing_licenses_notice'];
        if($show_setting == 'show') {
            $show = true;
        } else {
            $show = false;
        }
        $show = apply_filters('flms_missing_licenses_notice', $show, $location);
        if($show == 'show') {
            $license_label = strtolower($flms_settings['labels']['license_singular']);
            $licenses_label = strtolower($flms_settings['labels']['license_plural']);
            $course_credits = new FLMS_Module_Course_Credits();
            $missing_credits = $course_credits->get_missing_credit_licenses($active_courses);
            if($missing_credits != false) {
                $course_label = strtolower($flms_settings['labels']['course_plural']);
                if(count($missing_credits) == 1) {
                    $message = "You may not receive credit for your active courses if you do not update your %s $license_label.";
                    $licenses = $missing_credits[0];
                } else if(count($missing_credits) == 2) {
                    $licenses = "$missing_credits[0] and $missing_credits[1]";
                    $message = "You may not receive credit for your active courses if you do not update your %s $licenses_label.";
                } else {
                    $last = array_pop($missing_credits);
                    $licenses = implode(', ', $missing_credits);
                    if ($licenses) {
                        $licenses .= ', and ';
                    }
                    $licenses .= $last;
                    $message = "You may not receive credit for your active courses if you do not update your %s $licenses_label.";
                }
                $link = '<a href="'.wc_get_endpoint_url('edit-account').'" title="Edit account" class="button wc-forward">Edit account details</a>';
                $message = apply_filters('flms_wc_my_account_missing_licenses', $message, $licenses);
                $print_message = sprintf($message, $licenses);
                //wc_print_notice( $print_message, 'error');
                echo flms_alert($print_message, false);
            }
        }
    }
      
    public function my_credits_tab_content() {
        global $flms_settings;
	    $completed_courses = flms_get_user_completed_courses();
        $active_courses = flms_get_user_active_courses();
        $course_name = 'My Course Credits';
        
        if(isset($flms_settings['labels']["credits"])) {
            $course_name = $flms_settings['labels']["credits"];
        }
        
        $this->my_account_missing_licenses_notice($active_courses, 'my-credits');

        if(!is_array($active_courses)) {
            $notice = '<p>You have no active '.strtolower($course_name).'.</p>';
        } else if(empty($active_courses)) {
            $notice = '<p>You have no active '.strtolower($course_name).'.</p>';
        } else {
            $credits = array();
            $course_credits = new FLMS_Module_Course_Credits();
            foreach($active_courses as $active_course) {
                $credits = $course_credits->get_course_credits($active_course, $credits);
            }
            
            $notice = $course_credits->course_credit_summary($credits);
        }

        echo "<h2>$course_name in Process</h2>";
        echo $notice;
    }

    public function my_licenses_tab_content() {
        global $flms_settings, $current_user;
        $user = wp_get_current_user();
        $this->woocommerce_save_account_details($user->ID);
	    //license content
        $field_data = '';
        if(flms_is_module_active('course_credits')) {
            $course_credits = new FLMS_Module_Course_Credits();
            $credit_data = $course_credits->get_user_license_fields($user,'woocommerce');
            if(isset($credit_data['field_data'])) {
                $field_data = $credit_data['field_data'];
            }
        }
        if($field_data != '') {
            $prefix = '';
            if(isset($flms_settings['woocommerce']['account_details_tab_explanation'])) {
                $prefix = $flms_settings['woocommerce']['account_details_tab_explanation'];
            }
            if($prefix != '') {
                echo '<p class="flms-accordion-description">'.$prefix.'</p>';
            }
            echo '<form class="woocommerce-EditAccountForm edit-account" action="" method="post" data-bitwarden-watching="1">';
            echo $field_data;
            echo '  <p>
            <input type="hidden" name="_wp_http_referer" value="/my-account/edit-account/">		
            <button type="submit" class="woocommerce-Button button" name="save_license_details" value="Save changes">Save changes</button>
            <input type="hidden" name="action" value="save_license_details">
            </p>';
            echo '</form>';
        } else {
            $label = strtolower($flms_settings['labels']['license_plural']);
            echo "<p>There are no current $label.</p>";
        }
    }

    public function my_groups_tab_content() {
        global $flms_settings, $current_user;
        $user = wp_get_current_user();
        $this->woocommerce_save_account_details($user->ID);
	    //license content
        $field_data = '';
        if(flms_is_module_active('groups')) {
            do_action('flms_before_my_groups');
            $groups = new FLMS_Module_Groups();
            $my_groups = $groups->get_user_groups($user);
            echo '<div id="flms-groups-container">';
                echo $my_groups;   
            echo '</div>';
        }
    }
      
    public function my_account_endpoint_slug($settings) {
        $settings[] = array(
            'name' => FLMS_PLUGIN_NAME .' Account Endpoints',
            'type' => 'title',
            'desc' => __( '' ),
            'id'   => 'fragment_lms' 
        );
        global $flms_settings;
        
        $label = 'My Courses';
        if(isset($flms_settings['woocommerce']['my_courses_tab_name'])) {
            $label = $flms_settings['woocommerce']['my_courses_tab_name'];
        }
        $settings[] = array(
            'title'    => __( $label, 'woocommerce' ),
            'desc'     => __( 'Endpoint for showing Fragment LMS course content', 'woocommerce' ),
            'id'       => 'flms_my_courses_endpoint',
            'type'     => 'text',
            'default'  => 'my-courses',
            'desc_tip' => true,
        );
        
        $label = 'My Credits';
        if(isset($flms_settings['woocommerce']['my_credits_tab_name'])) {
            $label = $flms_settings['woocommerce']['my_credits_tab_name'];
        }
        $settings[] = array(
            'title'    => __( $label, 'woocommerce' ),
            'desc'     => __( 'Endpoint for showing Fragment LMS course credits', 'woocommerce' ),
            'id'       => 'flms_my_credits_endpoint',
            'type'     => 'text',
            'default'  => 'my-credits',
            'desc_tip' => true,
        );

        $label = 'My Licenses';
        if(isset($flms_settings['woocommerce']['my_licenses_tab_name'])) {
            $label = $flms_settings['woocommerce']['my_licenses_tab_name'];
        }
        $settings[] = array(
            'title'    => __( $label, 'woocommerce' ),
            'desc'     => __( 'Endpoint for showing Fragment LMS course credit licenses', 'woocommerce' ),
            'id'       => 'flms_my_licenses_endpoint',
            'type'     => 'text',
            'default'  => 'my-licenses',
            'desc_tip' => true,
        );

        if(flms_is_module_active('groups')) {
            $label = 'My Groups';
            if(isset($flms_settings['woocommerce']['my_groups_tab_name'])) {
                $label = $flms_settings['woocommerce']['my_groups_tab_name'];
            }
            $settings[] = array(
                'title'    => __( $label, 'woocommerce' ),
                'desc'     => __( 'Endpoint for showing Fragment LMS user groups', 'woocommerce' ),
                'id'       => 'flms_my_groups_endpoint',
                'type'     => 'text',
                'default'  => 'my-groups',
                'desc_tip' => true,
            );
        }
        //page break for layout
        $settings[] = array( 'type' => 'sectionend', 'id' => 'flms_endpoint_section' );
        return $settings;
    }


    /**
     * Woocommerce fields
     */
    public function flms_simple_product_course_correlation() {
        global $woocommerce, $post;
        
		echo '<div class="show_if_simple">';
        woocommerce_wp_select( 
            array(
                'id' => 'flms_woocommerce_simple_course_ids',
                'name' => 'flms_woocommerce_simple_course_ids[]',
                'class' => 'select flms-wide-select flms-woo-select',
                'wrapper_class' => 'show_if_simple',
                'label' => __( FLMS_PLUGIN_NAME .' Course(s)', 'woocommerce' ),
                'value' => get_post_meta( $post->ID, 'flms_woocommerce_simple_course_ids', true ),
                'desc_tip' => true,
                'description' => sprintf('Grant access to %s content after purchasing this product.',FLMS_PLUGIN_NAME),
                'options' => flms_get_course_select_box(),
                'custom_attributes' => array('multiple' => 'multiple')
            )
        );
       
        echo '</div>';
    }

    
    
    public function save_simple_product_correlation($product_id) {
        delete_post_meta($product_id,'flms_woocommerce_course_id');
        $courses = array();
        if (isset($_POST['flms_woocommerce_simple_course_ids']) && is_array($_POST['flms_woocommerce_simple_course_ids'])) {
            // Sanitize and save the selected lesson IDs as a custom field
            foreach($_POST['flms_woocommerce_simple_course_ids'] as $course) {
                $courses[] = $course;
                add_post_meta($product_id,'flms_woocommerce_course_id', $course);
                //$courses[] = $_POST['flms_woocommerce_simple_course_ids'];
            }
            
        } 
        update_post_meta( $product_id, 'flms_woocommerce_simple_course_ids', $courses );
    }

    public function flms_variations_course_correlation( $loop, $variation_data, $variation ) {
        echo '<div class="options_group form-row show_if_variable form-row-full">';
        woocommerce_wp_select( 
            array(
                'id' => 'flms_woocommerce_variable_course_ids_' . $loop,
                'name' => 'flms_woocommerce_variable_course_ids[' . $loop . '][]',
                'class' => 'select full-width flms-wide-select flms-woo-select',
                'wrapper_class' => 'show_if_variable',
                'label' => __( FLMS_PLUGIN_NAME .' Course(s)', 'woocommerce' ),
                'value' => get_post_meta( $variation->ID, 'flms_woocommerce_variable_course_ids', true ),
                'desc_tip' => true,
                'description' => sprintf('Grant access to %s content after purchasing this product.',FLMS_PLUGIN_NAME),
                'options' => flms_get_course_select_box(),
                'custom_attributes' => array('multiple' => 'multiple')
            )
        );
        echo '</div>';
    }
    
    public function flms_variations_save_course_correlation( $variation_id, $i ) {
        delete_post_meta($variation_id,'flms_woocommerce_course_id');
        $courses = array();
        if (isset($_POST['flms_woocommerce_variable_course_ids'][$i]) && is_array($_POST['flms_woocommerce_variable_course_ids'][$i])) {
            // Sanitize and save the selected lesson IDs as a custom field
            foreach($_POST['flms_woocommerce_variable_course_ids'][$i] as $course) {
                $courses[] = sanitize_text_field($course);
                add_post_meta($variation_id,'flms_woocommerce_course_id', $course);
            }
            
        }

        update_post_meta( $variation_id, 'flms_woocommerce_variable_course_ids', $courses );
    }
    
    public function flms_variations_get_course_correlation_data( $variations ) {
        $variations['flms_woocommerce_course_id'] = '<div class="woocommerce_flms_woocommerce_course_id">Custom Field: <span>' . get_post_meta( $variations[ 'variation_id' ], 'flms_woocommerce_course_id', true ) . '</span></div>';
        return $variations;
    }

    public function purchase_course_options($post_id,$course_version) {
        //This only happens when not viewing the latest published course
        $return = '';
        $args = array(
            'post_type' => array('product','product_variation'),
            'meta_key' => 'flms_woocommerce_course_id',
            'meta_value' => array("$post_id:$course_version"), //'meta_value' => array('yes'),
            'meta_compare' => 'IN' //'meta_compare' => 'NOT IN'
        );
        $products = get_posts($args);
        //echo '<pre>'.print_r($products,true).'</pre>';
        if(empty($products)) {
            $return = '<p class="no-purchase-option">There are no options to purchase this course.</p>';
            if(current_user_can('manage_woocommerce')) {
                $return .= ' <a href="'.admin_url('edit.php?post_type=product').'" class="btn">Assign course to product</a> <a href="'.admin_url('post.php?post='.$post_id.'&action=edit').'" class="btn">Update course access type</a>';
            } else if(current_user_can('edit_others_posts')) {
                $return .= ' <a href="'.admin_url('post.php?post='.$post_id.'&action=edit').'" class="btn">Update course access type</a>';
            } else {
                $return .= 'There are no purchase options for this course currently.';
            }
            
            return apply_filters('flms_no_purchase_option', $return, array($post_id,$course_version));
        }
        $product_options = array();
        foreach ($products as $product_array) {
            $product = wc_get_product($product_array->ID);
            $type = $product->get_type();
            if($type == 'simple') {
                $product_id = $product_array->ID;
            } else {
                $product_id = $product->get_parent_id();
            }
            if(!in_array($product_id,$product_options)) {
                $product_options[] = $product_id;
            }
        }
        if(!empty($product_options)) {
            if(count($product_options) > 1) {
                $return = '<p>There are multiple purchase options for this course:</p>';
                $return .= '<ul>';
                foreach($product_options as $product_option) {
                    $return .= '<li><a href="'.get_permalink($product_option).'">'.get_the_title($product_option).'</a></li>';
                }
                $return .= '</ul>';
            } else {
                
                //$return = '<div><a href="'.get_permalink($product_options[0]).'" class="button button-primary">Purchase</a></div>';
                global $product;
                $return = '<div class="woocommerce">';
                $return .= '<div class="product course-add-product">';
                $product = wc_get_product($product_options[0]);
                $type = $product->get_type();
                wp_enqueue_script('flms-woocommerce-variations-toggle');
                ob_start();
                woocommerce_template_single_price();
                if($type == 'variable') {
                    woocommerce_variable_add_to_cart();
                } else {
                    woocommerce_simple_add_to_cart();
                }
                //do_action( 'woocommerce_' . $product->get_type() . '_add_to_cart' );
                global $product;
                $return .= ob_get_clean();
                $return .= '</div>';
                $return .= '</div>';
                '</div>';
            }
        } 
        return $return;
    }

    /**
     * Require accounts when purchasing a course
     */
    function filter_woocommerce_checkout_registration_required( $required ) {
        // Several can be added, separated by a comma
        $product_ids = array ( 30, 813 );
        
        // Loop through cart items
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product = wc_get_product($cart_item['product_id']);
            $type = $product->get_type();
            if($type == 'simple') {
                $product_id = $cart_item['product_id'];
                $courses = get_post_meta( $product_id, 'flms_woocommerce_simple_course_ids', true );
                if($courses != '') {
                    $required = true;
                }
            } else {
                $product_id = $product->get_parent_id();
                $courses = get_post_meta( $product_id, 'flms_woocommerce_variable_course_ids', true );
                if($courses != '') {
                    $required = true;
                }
            }
        }
        return $required;
    }

    //trigger enrollment when payment status changes, not used any longer, was for testing
    function enroll_on_status_change( $order_id, $old_status, $new_status, $order ) {
        if($new_status == 'completed') {
            $this->enroll_customer_courses($order_id);
        } 
    }

    /** Enroll user after payment completed */
    public function enroll_customer_courses($order_id) {
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $customer_id = $order->get_user_id();
            // Get and Loop Over Order Items
            foreach ( $order->get_items() as $item_id => $item ) {
                $product_id = $item->get_product_id();
                $product = $item->get_product();
                $type = $product->get_type();
                $seats = absint($item->get_meta('group_seats'));
                if($type == 'simple') {
                    $courses = get_post_meta( $product_id, 'flms_woocommerce_simple_course_ids', true );
                } else {
                    $variation_id = $item->get_variation_id();
                    $courses = get_post_meta( $variation_id, 'flms_woocommerce_variable_course_ids', true );
                }
                if(is_array($courses)) {
                    if(!empty($courses)) {
                        $course_progress = new FLMS_Course_Progress();
                        foreach($courses as $course) {
                            $course_info = explode(':',$course);
                            $course_id = $course_info[0];
                            $version = $course_info[1];
                            //abort if it's a group purchase
                            
                            if($seats > 0) {
                                //dont enroll them but do tag the user for open seats
                                //delete_user_meta($customer_id, 'flms_open_seats'); //for debugging
                                $open_seats = get_user_meta($customer_id, 'flms_open_seats',true);
                                if(!is_array($open_seats)) {
                                    $open_seats = array();
                                }
                                if(!isset($open_seats[$course])) {
                                    $open_seats[$course] = array();
                                }
                                $reporting = array();
                                if(flms_is_module_active('course_credits')) {
                                    global $flms_settings;
                                    $course_credits = new FLMS_Module_Course_Credits();
                                    $credit_fields = $course_credits->get_course_credit_fields(false);
                                    
                                    foreach($credit_fields as $credit_type) {
                                        $status = 'none';
                                        if(isset($flms_settings['course_credits'][$credit_type]['reporting-fee-status'])) {
                                            $status = $flms_settings['course_credits'][$credit_type]['reporting-fee-status'];
                                        }
                                        if($status != 'none') {
                                            $meta_key = strtolower(str_replace(' ','_',"$credit_type Reporting Fee"));
                                            if($item->meta_exists( $meta_key )) {
                                                $accepted = $item->get_meta( $meta_key );
                                                if($accepted == 'Accepted') {
                                                    $value = 1;
                                                } else {
                                                    $value = 0;
                                                }
                                                $reporting[$meta_key] = $value;
                                            }
                                        }
                                    }
                                }
                                $open_seats[$course][] = array(
                                    'seats' => $seats,
                                    'reporting_fees' => $reporting,
                                );
                                //save to user
                                update_user_meta($customer_id, 'flms_open_seats', $open_seats);

                            } else {
                                $enroll = $course_progress->enroll_user($customer_id, $course_id, $version);
                                $meta_id = $enroll['id'];

                                //log reporting field
                                //add to reporting log
                                if(flms_is_module_active('course_credits') && $meta_id > 0) {
                                    global $flms_settings;
                                    $course_credits = new FLMS_Module_Course_Credits();
                                    $credit_fields = $course_credits->get_course_credit_fields(false);
                                    
                                    foreach($credit_fields as $credit_type) {
                                        $status = 'none';
                                        if(isset($flms_settings['course_credits'][$credit_type]['reporting-fee-status'])) {
                                            $status = $flms_settings['course_credits'][$credit_type]['reporting-fee-status'];
                                        }
                                        if($status != 'none') {
                                            $meta_key = strtolower(str_replace(' ','_',"$credit_type Reporting Fee"));
                                            if($item->meta_exists( $meta_key )) {
                                                $accepted = $item->get_meta( $meta_key );
                                                if($accepted == 'Accepted') {
                                                    $value = 1;
                                                } else {
                                                    $value = 0;
                                                }
                                                global $wpdb;
                                                $wpdb->update( 
                                                    FLMS_REPORTING_TABLE, 
                                                    array( 
                                                        'accepts_reporting_fee' => $value,
                                                    ), 
                                                    array( 
                                                        'entry_id' => $meta_id,
                                                        'credit_type' => $credit_type
                                                    ) 
                                                );	  
                                            }  
                                        }
                                                
                                    }
                                }
                            }
                        }
                        //store course as metadata
                        update_post_meta($order_id, 'flms_customer_purchased_courses', $courses);
                    }
                }
            }

            //autocomplete order if needed
            //$order = wc_get_order( $order_id );
            //$order->update_status( 'completed' );
        }
    }

    /**
     * Unenroll customer
     */
    public function remove_course_access_on_refund($order_id) {
        $products = [];
		$refunds  = $order->get_refunds();

		foreach ( $refunds as $refund ) {
			$refunded_products = $refund->get_items();
			$products = array_merge( $products, $refunded_products );
		}

		$this->remove_course_access( $order_id, null, $products );

    }

    public function remove_course_access($order_id, $products = array()) {
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $customer_id = $order->get_user_id();
            // Get and Loop Over Order Items

            if(empty($products)) {
                $products = $order->get_items();
            }
            foreach ( $products as $item_id => $item ) {
                $product_id = $item->get_product_id();
                $product = $item->get_product();
                $type = $product->get_type();
                if($type == 'simple') {
                    $courses = get_post_meta( $product_id, 'flms_woocommerce_simple_course_ids', true );
                } else {
                    $variation_id = $item->get_variation_id();
                    $courses = get_post_meta( $variation_id, 'flms_woocommerce_variable_course_ids', true );
                }
                
                if(is_array($courses)) {
                    if(!empty($courses)) {
                        $course_progress = new FLMS_Course_Progress();
                        foreach($courses as $course) {
                            $course_info = explode(':',$course);
                            $course_id = $course_info[0];
                            $version = $course_info[1];
                            $course_progress->unenroll_user($customer_id, $course_id, $version);
                        }
                    }
                }
            }
        }
    }

    //simple product course list
    function flms_simple_course_metadata() {
        global $product;
        if(flms_is_module_active('groups')) {
            echo $this->display_group_purchase();
        }
        if( $value = $product->get_meta( 'flms_woocommerce_simple_course_ids' ) ) {
            /*$label = $this->get_woocommerce_module_field('associated_courses_label');
            if($label != '') {
                echo "<h2>$label</h2>";
            }
            echo $this->flms_display_course_list_from_bundle($value);
            */
            //display credit reporting fees if necessary
            if(flms_is_module_active('course_credits')) {
                echo $this->display_accepts_reporting_fee($value);
            }
        }
       
    }

    //simple product course list
    public function flms_shop_metadata() {
        global $product;
        //simple
        if( $value = $product->get_meta( 'flms_woocommerce_simple_course_ids' ) ) {
            echo $this->flms_display_course_list_from_bundle($value, 'shop', false);
            return;
        } 
        $show_course_credits = false;
        if(flms_is_module_active('course_credits')) {
            global $flms_settings;
            $show_course_credits = true; //default
            if(isset($flms_settings['woocommerce']["shop_course_credits"])) {
                $show_course_credits_option = $flms_settings['woocommerce']["shop_course_credits"];
                if($show_course_credits_option == 'hide') {
                    $show_course_credits = false;
                }
            }
        } 
        if($show_course_credits) {
            $course_credits = new FLMS_Module_Course_Credits();
            $has_courses = false;
            $variations = $product->get_children();
            $course_credits_array = array();
            foreach($variations as $variation_id) {
                $variation = wc_get_product($variation_id);
                if( $value = $variation->get_meta( 'flms_woocommerce_variable_course_ids' ) ) {
                    $has_courses = true;
                    if(is_array($value)) {
                        foreach($value as $course_information) {
                            $course_data = explode(':',$course_information);
                            //print_r($course_data);
                            $course_id = $course_data[0];
                            $course_version = $course_data[1];
                            $course_version_content = get_post_meta($course_id,'flms_version_content',true);	
                            //print_r($course_version_content);
                            if(!array_key_exists($course_information, $course_credits_array)) {
                                $course_credits_array[$course_information] = $course_credits->flms_get_course_credits_array($course_version_content, $course_version); 
                            }
                        }
                    }
                }
            }
            //print_r($course_credits_array);
            if($has_courses) {
                echo '<span class="flms-select-for-credits">Select options for course credits.</span>';
            }
        }
    }

    public function flms_display_course_credits($value) {


    }
    // Frontend: Display Commodity Code on product variations
    
    function flms_variation_course_metadata( $data, $product, $variation ) {
        global $post;
        if(flms_is_module_active('groups')) {
            $data['variation_description'] .= $this->display_group_purchase();
        }
        //echo '<pre>'.print_r($data,true).'</pre>';
        if( $value = $variation->get_meta( 'flms_woocommerce_variable_course_ids' ) ) {
            //print_r($value);
            /*$label = $this->get_woocommerce_module_field('associated_courses_label');
            if($label != '') {
                $data['price_html'] .= "<h2>$label</h2>";
            }*/
            //$data['price_html'] .= $this->flms_display_course_list_from_bundle($value);

            //display credit reporting fees if necessary
            if(flms_is_module_active('course_credits')) {
                $data['variation_description'] .= $this->display_accepts_reporting_fee($value);
            }
        }

        
        return $data;
    }

    public function display_accepts_reporting_fee($value) {
        global $flms_settings, $flms_course_credit_types;
        $html = '';
        $course_credits = $this->flms_get_course_credits_from_bundle($value);
        $reporting_label = flms_get_label('reporting_fee');
        $slect_label = flms_get_label('select_credit_reporting');
        foreach($flms_course_credit_types as $credit_type) {
            if($flms_settings['course_credits'][$credit_type]["reporting-fee-status"] != 'none') {
                if(isset($course_credits[$credit_type])) {
                    $reporting_types[] = $credit_type;
                    $credit_amount = $course_credits[$credit_type];
                    $html .= '<input type="hidden" name="product-has-reporting-fee['.$credit_type.']" value="1" />';
                    
                    if(isset($flms_settings['labels'][$credit_type])) {
                        $label = $flms_settings['labels'][$credit_type];
                    } else if(isset($flms_settings['course_credits'][$credit_type]["name"])) {
                        $label = $flms_settings['course_credits'][$credit_type]["name"];  
                    } else {
                        $label = $credit_type;
                    }
                    
                    $value = isset( $_POST['reporting-fees'][$credit_type] ) ? sanitize_text_field( $_POST['reporting-fees'][$credit_type] ) : '';
                    $html .= '<table class="variations-style reporting-fee-option"><tbody><tr><th class="label"><label>'.$label.' '.$reporting_label.'</label></th><td class="value">';
                        $html .= '<select name="reporting-fees['.$credit_type.']">';
                            $html .= '<option value="-1"';
                                if($value == -1) {
                                    $html .= ' selected';
                                }
                                $html .= '>'.$slect_label.'</opton>';
                                $fee_type = $flms_settings['course_credits'][$credit_type]["reporting-fee-status"];
                                $fee = $flms_settings['course_credits'][$credit_type]["reporting-fee"];
                                if($fee_type == 'per-credit') {
                                    //get number of credits
                                    $reporting_fee = $credit_amount * $fee;
                                } else {
                                    //flat fee
                                    $reporting_fee = $fee;
                                }
                                $reporting_fee = number_format(floatval($reporting_fee), 2);
                                $html .= '<option value="1" data-default-fee="'.$reporting_fee.'" class="flms-reporting-fee-value"';
                                if($value == 1) {
                                    $html .= ' selected';
                                }
                                
                                $html .= '>Accept '.get_woocommerce_currency_symbol().$reporting_fee.' '.$reporting_label.'</option>';
                                $html .= '<option value="0"';
                                if($value == 0) {
                                    $html .= ' selected';
                                }
                                $html .= '>Decline '.$reporting_label;
                            $html .= '</option>';
                        $html .= '<select>';
                        $html .= '</td></tr></tbody></table>';
                        if($flms_settings['course_credits'][$credit_type]["reporting-fee-description"] != '') {
                            $html .= '<span class="reporting-description">'.$flms_settings['course_credits'][$credit_type]["reporting-fee-description"].'</span>';
                        }
                    //$html .= '</p>';
                }
            }
        }
        return $html;
    }

    public function display_group_purchase() {
        wp_enqueue_script( 'flms-groups' );
        global $flms_settings;
        $html = '';
        $value = isset( $_POST['group_purchase_enabled'] ) ? sanitize_text_field( $_POST['group_purchase_enabled'] ) : 0;
        $label = isset( $flms_settings['woocommerce']['group_purchase_label'] ) ? sanitize_text_field( $flms_settings['woocommerce']['group_purchase_label'] ) : 'Group Purchase';
        
        $html .= '<table class="variations-style"><tbody><tr><th class="label"><label>'.$label.'</label></th><td class="value">';
            $html .= '<select name="group_purchase_enabled" id="group_purchase_enabled">';
                $options = array(
                    'individual' => 'Individual',
                    'group' => flms_get_label('groups_singular'),
                );
                $count = 0;
                foreach($options as $k => $v) {
                    $html .= '<option value="'.$count.'"';
                        if($value == $k) {
                            $html .= ' selected';
                        }    
                    $html .= '>'.$v.'</opton>';
                    $count++;
                }
            $html .= '<select>';
        $html .= '</td></tr></tbody></table>';

        $label = flms_get_label('seats_plural');
        $value = isset( $_POST['group_purchase_seats'] ) ? absint( $_POST['group_purchase_seats'] ) : 1;
        $html .= '<table class="variations-style group_seats_select" id="group_seats_select"><tbody><tr><th class="label"><label>'.$label.'</label></th><td class="value">';
            
            $html .= '<input type="number" name="group_seats" id="group_seats" value="'.$value.'" placeholder="1" class="input-text text" min="1">';
            $discount_enabled = isset( $flms_settings['woocommerce']['groups_bulk_purchase_discount_status'] ) ? sanitize_text_field( $flms_settings['woocommerce']['groups_bulk_purchase_discount_status'] ) : 'none';
            if($discount_enabled != 'none') {
                $discount_text = isset( $flms_settings['woocommerce']['group_discount_label'] ) ? sanitize_text_field( $flms_settings['woocommerce']['group_discount_label'] ) : '';
                if($discount_text != '') {
                    $html .= '<span class="discount-text">'.$discount_text.'</span>';
                }
            }
        $html .= '</td></tr></tbody></table>';
            
        //$html .= '</p>';
        return $html;
    }

    // Frontend: Display Commodity Code on cart
    
    function vp_cart_display_commodity_code( $item_name, $cart_item, $cart_item_key ) {
        if( ! is_cart() )
            return $item_name;

        //simple product
        /*if( $value = $cart_item['data']->get_meta( 'flms_woocommerce_simple_course_ids' ) ) {
            $item_name .= '<div class="flms-cart-item-data">';
            $label = $this->get_woocommerce_module_field('associated_courses_label');
            if($label != '') {
                $item_name .= $label;
            }
            $item_name .= $this->flms_display_course_list_from_bundle($value, 'cart');
            $item_name .= '</div>';
        }
        //variable product
        if( $value = $cart_item['data']->get_meta( 'flms_woocommerce_variable_course_ids' ) ) {
            $item_name .= '<div class="flms-cart-item-data">';
            $label = $this->get_woocommerce_module_field('associated_courses_label');
            if($label != '') {
                $item_name .= $label;
            }
            $item_name .= $this->flms_display_course_list_from_bundle($value, 'cart');
            $item_name .= '</div>';
        }*/

        if(flms_is_module_active('course_credits')) {
            if ( isset( $cart_item['accepts_reporting_fee'] ) ){
                $reporting_label = flms_get_label('reporting_fee');
                foreach($cart_item['accepts_reporting_fee'] as $k => $v) {
                    $label = flms_get_label(sanitize_text_field($k));
                    if((int) $v == 1) {
                        $value = 'Accepted';
                    } else {
                        $value = 'Declined';
                    }
                    $item_name .= '<div class="flms-cart-item-data">';
                    $item_name .= "$label $reporting_label: $value";
                    $item_name .= '</div>';
                }
                
            }
            if ( isset( $cart_item['group_seats'] ) ){
                $price = $cart_item['data']->get_price();
                $value = absint($cart_item['group_seats']);
                $label = flms_get_label('groups_singular') .' '.flms_get_label('seats_plural');
                $price_string = '('.get_woocommerce_currency_symbol() . number_format(floatval($price / $value),2) .'/'.strtolower(flms_get_label('seats_singular')).')';
                $item_name .= '<div class="flms-cart-item-data">';
                $item_name .= "$label: $value $price_string";
                $item_name .= '</div>';
                
            }
        }
        return $item_name;
    }

    // Frontend: Display Commodity Code on checkout
    
    function vp_checkout_display_commodity_code( $item_qty, $cart_item, $cart_item_key ) {
        //simple
        /*if( $value = $cart_item['data']->get_meta('flms_woocommerce_simple_course_ids') ) {
            $item_qty .= '<div class="flms-cart-item-data">';
            $label = $this->get_woocommerce_module_field('associated_courses_label');
            if($label != '') {
                $item_qty .= $label;
            }
            $item_qty .= $this->flms_display_course_list_from_bundle($value, 'checkout');
            $item_qty .= '</div>';
        }
        //variable
        if( $value = $cart_item['data']->get_meta('flms_woocommerce_variable_course_ids') ) {
            $item_qty .= '<div class="flms-cart-item-data">';
            $label = $this->get_woocommerce_module_field('associated_courses_label');
            if($label != '') {
                $item_qty .= $label;
            }
            $item_qty .= $this->flms_display_course_list_from_bundle($value, 'checkout');
            $item_qty .= '</div>';
        }*/
        /*if( $value = $cart_item['data']->get_meta('group_seats') ) {
            $item_qty .= '<div class="flms-cart-item-data">';
            $label = flms_get_label('groups_singular') .' '.flms_get_label('seats_plural');
            $item_qty .= "$label: $value";
            $item_qty .= '</div>';
        }*/
        return $item_qty;
    }

    // Save Commodity Code to order items (and display it on admin orders)
    
    function vp_order_item_save_commodity_code( $item, $cart_item_key, $cart_item, $order ) {
        //simple
        if( $value = $cart_item['data']->get_meta('flms_woocommerce_simple_course_ids') ) {
            $item->update_meta_data( 'flms_woocommerce_simple_course_ids', $value );
        }
        //variable
        if( $value = $cart_item['data']->get_meta('flms_woocommerce_variable_course_ids') ) {
            $item->update_meta_data( 'flms_woocommerce_variable_course_ids', $value );
        }
        //fees
        if(isset( $cart_item['accepts_reporting_fee'])) {
            $item->update_meta_data('accepts_reporting_fee', $cart_item['accepts_reporting_fee']);
        }
        //group seats
        if ( isset( $cart_item['group_seats'] ) ){
            $item->update_meta_data('group_seats', $cart_item['group_seats']);
        }
        return $item;
    }

    // Frontend & emails: Display Commodity Code on orders
    
    function vp_order_item_display_commodity_code( $item_id, $item, $order, $plain_text ) {
        // Not on admin
        //if( is_admin() ) return;

        /*if( $value = $item->get_meta('flms_woocommerce_simple_course_ids') ) {
            echo '<div class="flms-cart-item-data';
            if(is_wc_endpoint_url()) {
                echo ' style="font-size: 12px;"';
            }
            echo '">';
            $label = $this->get_woocommerce_module_field('associated_courses_label');
            if($label != '') {
                 $label;
            }
            echo $this->flms_display_course_list_from_bundle($value);
            echo '</div>';
        }
        if( $value = $item->get_meta('flms_woocommerce_variable_course_ids') ) {
            echo '<div class="flms-cart-item-data';
            if(is_wc_endpoint_url()) {
                echo ' style="font-size: 12px;"';
            }
            echo '">';
            $label = $this->get_woocommerce_module_field('associated_courses_label');
            if($label != '') {
                 $label;
            }
            echo $this->flms_display_course_list_from_bundle($value);
            echo '</div>';
        }*/
    }

    public function flms_get_course_credits_from_bundle($courses, $location = 'product', $display_course_title = true) {
        global $flms_course_version_content, $flms_settings;
        $course_credits = array();
        if(is_array($courses)) {
            if(!empty($courses)) {
                if(flms_is_module_active('course_credits')) {
                    foreach($courses as $course) {
                        $data = explode(':',$course);
                        $course_id = $data[0];
                        $course_version = $data[1];
                        $course = new FLMS_Course($course_id);
                        global $flms_active_version;
                        $flms_active_version = $course_version;
                        $title = $course->get_course_version_name($course_version);
                        $permalink = $course->get_course_version_permalink($course_version);
                        $course_credits_module = new FLMS_Module_Course_Credits();
                        $new_credits = $course_credits_module->flms_get_course_credits_array();
                        foreach($new_credits as $k => $v) {
                            if(!isset($course_credits[$k])) {
                                $course_credits[$k] = 0;
                            }
                            $course_credits[$k] += $v;
                        }
                    }
                    
                }
            }
        }

        return $course_credits;
    }
    
    public function flms_display_course_list_from_bundle($courses, $location = 'product', $display_course_title = true) {
        global $flms_course_version_content, $flms_settings;
        $list = '';
        if(is_array($courses)) {
            if(!empty($courses)) {
                $show_course_credits = false;
                if(flms_is_module_active('course_credits')) {
                    $show_course_credits = true; //default
                    if(isset($flms_settings['woocommerce']["{$location}_course_credits"])) {
                        $show_course_credits_option = $flms_settings['woocommerce']["{$location}_course_credits"];
                        if($show_course_credits_option == 'hide') {
                            $show_course_credits = false;
                        }
                    }
                } 
                if($show_course_credits) {
                    $course_name = 'Course';
                    if(isset($flms_settings['labels']["course_singular"])) {
                        $course_name = $flms_settings['labels']["course_singular"];
                    }
                    //course credits module active
                    $list .= '<div class="flms-product-courses">';
                    if($location == 'shop') {
                        $credits = array();
                        $list .= '<p class="course-link">';
                        
                        foreach($courses as $course) {
                            $data = explode(':',$course);
                            $course_id = $data[0];
                            $course_version = $data[1];
                            $course_class = new FLMS_Course($course_id);
                            $course_credits = new FLMS_Module_Course_Credits();
                            global $flms_active_version;
                            $flms_active_version = $course_version;
                            $title = $course_class->get_course_version_name($course_version);
                            $permalink = $course_class->get_course_version_permalink($course_version);
                            if($display_course_title) {
                                if(is_checkout()) {
                                    $list .= $title;
                                } else {
                                    $list .= $course_name.': <a href="'.$permalink.'" title="View '.$title.'">'.$title.'</a>';
                                }
                                
                            }   
                            $credits = $course_credits->get_course_credits($course, $credits);
                            
                        }
                        $course_credits = new FLMS_Module_Course_Credits();
                        $list .= $course_credits->print_shop_course_credits($credits);
                        $list .= '</p>';
                    } else {
                        $cc = 0;
                        foreach($courses as $course) {
                            $cc++;
                            $data = explode(':',$course);
                            $course_id = $data[0];
                            $course_version = $data[1];
                            $course = new FLMS_Course($course_id);
                            global $flms_active_version;
                            $flms_active_version = $course_version;
                            $title = $course->get_course_version_name($course_version);
                            $permalink = $course->get_course_version_permalink($course_version);
                        
                            $list .= '<p class="course-link">';
                                if($display_course_title) {
                                    if(is_checkout()) {
                                        //$list .= $title;
                                    } else {
                                        $list .= $course_name.': <a href="'.$permalink.'" title="View '.$title.'">'.$title.'</a> - '.$cc;
                                    }
                                    
                                }
                                if(!is_checkout()) {
                                    $course_credits = new FLMS_Module_Course_Credits();
                                    $list .= $course_credits->flms_get_course_credits(false, true, 'div');    
                                }
                            $list .= '</p>';
                        }
                    }
                    $list .= '</div>';
                } else {
                    if($display_course_title) {
                        $list .= '<p class="flms-product-courses">';
                        foreach($courses as $course) {
                            $data = explode(':',$course);
                            $course_id = $data[0];
                            $course_version = $data[1];
                            $course = new FLMS_Course($course_id);
                            $title = $course->get_course_version_name($course_version);
                            $permalink = $course->get_course_version_permalink($course_version);
                            /*if(is_checkout()) {
                                $list .= '<span class="course-link">'.$title.'</span>';
                            } else {
                                $list .= '<span class="course-link"><a href="'.$permalink.'" title="View '.$title.'">'.$title.'</a></span>';
                            }*/
                            
                        }
                        $list .= '</p>';
                    }
                }
            }
        }

        
        
        return $list;
    }

    /**
     * force sold individually
     */
    function force_sell_individually($individually, $product) {
        $type = $product->get_type();
        if($type == 'simple') {
            $courses = get_post_meta( $product->get_id(), 'flms_woocommerce_simple_course_ids', true );
            if(!empty($courses)) {
                $individually = true;
            }
            
        } else {
            $courses = get_post_meta( $product->get_id(), 'flms_woocommerce_variable_course_ids', true );    
            if(!empty($courses)) {
                $individually = true;
            }
        }
        return $individually;
    }

    public function force_account_login_on_checkout($registration_required) {
        if($registration_required) {
            //it's already required, we don't need to do anything
            return $registration_required;
        } else {
            $cart = WC()->cart;
            foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                $product = wc_get_product($cart_item['data']);
                $type = $product->get_type();
                if($type == 'simple') {
                    $product_id = $cart_item['product_id'];
                    $courses = get_post_meta( $post->ID, 'flms_woocommerce_simple_course_ids', true );
                    if($courses != '') {
                        return true;
                    }
                } else {
                    $variation_id = $cart_item['variation_id'];
                    $courses = get_post_meta( $variation_id, 'flms_woocommerce_variable_course_ids', true );
                    if($courses != '') {
                        return true;
                    }
                }
            }
        }
        
    }

    
    // -----------------------------------------
    // 2. Throw error if they didnt accept or decline the fee
    public function flms_reporting_fee_product_addon_validation( $passed, $product_id, $qty ){
        global $flms_settings;
        
        if(isset($_POST['product-has-reporting-fee']) && is_array($_POST['product-has-reporting-fee'])) {
            foreach($_POST['reporting-fees'] as $k => $v) {   
                $label = flms_get_label($k);
                if ((int) $v < 0) {
                    $reporting_label = flms_get_label('reporting_fee');
                    wc_add_notice( 'Please accept or decline the '.$label.' '.$reporting_label, 'error' );
                    $passed = false;
                } 
            }
        }
        /*if(!$passed) {
            $course_id = get_post_meta($product_id, 'flms_woocommerce_product_id', true);
            if($course_id != '') {
                wp_redirect(get_permalink($course_id));
                exit;
            }
        }*/
        return $passed;
    }
    
    // -----------------------------------------
    // 3. Save custom input field value into cart item data
    public function flms_reporting_fee_product_addon_cart_item_data( $cart_item, $product_id ){
        if( isset( $_POST['product-has-reporting-fee'] ) ) {
            foreach($_POST['reporting-fees'] as $k => $v) {   
                $label = flms_get_label($k);
                $cart_item['accepts_reporting_fee'][$k] = (int) $v;
            }
        }
        if( isset( $_POST['group_purchase_enabled'] ) ) {
            $enabled = sanitize_text_field(  $_POST['group_purchase_enabled'] );
            $cart_item['group_purchase'] = $enabled;
            if($enabled == 1) {
                if(isset($_POST['group_seats'])) {
                    $cart_item['group_seats'] = absint($_POST['group_seats']);
                }
            }
        }
        return $cart_item;
    }
    
    
    
    // -----------------------------------------
    // 5. Save custom input field value into order item meta
    public function flms_reporting_fee_product_addon_order_item_meta( $item_id, $values ) {
        if ( ! empty( $values['accepts_reporting_fee'] ) ) {
            foreach($values['accepts_reporting_fee'] as $k => $v) {   
                if((int) $v == 1) {
                    $text = 'Accepted';
                } else {
                    $text = 'Declined';
                }
                $meta_key = strtolower(str_replace(' ','_',"$k Reporting Fee"));
                wc_update_order_item_meta($item_id, $meta_key,  $text ); // Set it as order item meta
                
            }
        }

        if ( ! empty( $values['group_purchase'] ) ) {
            if($values['group_purchase'] == 1) {
                if ( ! empty( $values['group_seats'] ) ) {
                    wc_update_order_item_meta($item_id, 'flms_group_seats', absint( $values['group_seats']) ); // Set it as order item meta
                }
            }
        }
    }
    
    // -----------------------------------------
    // 6. Display custom input field value into order table
    public function flms_reporting_fee_product_addon_display_order( $cart_item, $order_item ){
        if( $value = $item->get_meta('flms_woocommerce_simple_course_ids') ) {

        }
        if( isset( $order_item['accepts_reporting_fee'] ) ){
            $cart_item['accepts_reporting_fee'] = $order_item['accepts_reporting_fee'];
        }
        if( isset( $order_item['group_seats'] ) ){
            $cart_item['group_seats'] = $order_item['group_seats'];
        }
        return $cart_item;
    }
    
    // -----------------------------------------
    // 7. Display custom input field value into order emails
    public function flms_reporting_fee_product_addon_display_emails( $fields ) { 
        $fields['accepts_reporting_fee'] = 'Custom Text Add-On';
        return $fields; 
    }

    public function add_reporting_fees_to_cart($cart) {
        global $flms_settings;
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if(isset($cart_item['accepts_reporting_fee'])) {
                $accepted_reporting = $cart_item['accepts_reporting_fee'];
                $paying_fees = array();
                foreach($accepted_reporting as $k => $v) {
                    if($v == 1) {
                        $paying_fees[] = $k;
                    }
                }
                $product = wc_get_product($cart_item['data']);
                $type = $product->get_type();
                if($type == 'simple') {
                    $product_id = $cart_item['product_id'];
                    $courses = get_post_meta( $product_id, 'flms_woocommerce_simple_course_ids', true );
                    $product_name = $product->get_name();
                } else {
                    $variation_id = $cart_item['variation_id'];
                    $variation = wc_get_product($variation_id);
                    $product = wc_get_product($variation->get_parent_id());
                    $product_name = $product->get_name();
                    $courses = get_post_meta( $variation_id, 'flms_woocommerce_variable_course_ids', true );
                }
                if($courses != '') {
                    global $flms_course_credit_types;
                    $course_credits = new FLMS_Module_Course_Credits();
                    $flms_course_credit_types = $course_credits->get_course_credit_fields();
                    $course_credits = $this->flms_get_course_credits_from_bundle($courses);
                    $reporting_label = flms_get_label('reporting_fee');
                    foreach($flms_course_credit_types as $credit_type) {
                        if(in_array($credit_type,$paying_fees)) {
                            if($flms_settings['course_credits'][$credit_type]["reporting-fee-status"] != 'none') {
                                if(isset($course_credits[$credit_type])) {
                                    
                                    $label = flms_get_label($credit_type);

                                    $reporting_types[] = $credit_type;
                                    $credit_amount = $course_credits[$credit_type];
                                    $fee_type = $flms_settings['course_credits'][$credit_type]["reporting-fee-status"];
                                    $fee = $flms_settings['course_credits'][$credit_type]["reporting-fee"];
                                    if($fee_type == 'per-credit') {
                                        //get number of credits
                                        $reporting_fee = $credit_amount * $fee;
                                    } else {
                                        //flat fee
                                        $reporting_fee = $fee;
                                    }
                                    if((int) $reporting_fee > 0) {
                                        
                                        $name      = "$label $reporting_label ($product_name)";
                                        $taxable   = true;
                                        $tax_class = '';
                                        //see if they purchased multiple seats
                                        if(isset($cart_item['group_seats'])) {
                                            $group_seats = absint($cart_item['group_seats']);
                                            $reporting_fee = $reporting_fee * $group_seats;
                                        }
                                        $reporting_fee = number_format(floatval($reporting_fee), 2);
                                        $cart->add_fee( $name, $reporting_fee, $taxable, $tax_class );
                                    }
                                    
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    public function purchasable_filter_for_course_credits( $value, $product  ) {
        global $flms_settings;
        $type = $product->get_type();
        if($type == 'simple') {
            $product_id = $product->get_ID();
            $courses = get_post_meta( $product_id, 'flms_woocommerce_simple_course_ids', true );
            $product_name = $product->get_name();
            if($courses != '') {
                global $flms_course_credit_types;
                $course_credits = new FLMS_Module_Course_Credits();
                $flms_course_credit_types = $course_credits->get_course_credit_fields();
                $course_credits = $this->flms_get_course_credits_from_bundle($courses);
                if(!empty($course_credits)) {
                    foreach($flms_course_credit_types as $credit_type) {
                        if($flms_settings['course_credits'][$credit_type]["reporting-fee-status"] != 'none') {
                            //add_filter('woocommerce_is_purchasable','__return_false');
                            if(is_shop()) {
                                $value = false;
                            }

                            //$button_text = __( "View product", "woocommerce" );
                            //return '<a class="button wptechnic-custom-view-product-button" href="' . $product->get_permalink() . '">'.add_to_cart_text().'</a>';
                        }
                    }
                }
            }
        } 
        return $value;
    }

    /** Add accordion to my account */
    public function woocommerce_start_account_accordion() {
        if(!flms_is_module_active('course_credits')) {
            return;
        }
        global $flms_settings;
        $credits_location = $flms_settings['woocommerce']['my_licensess_account_location'];
        if($credits_location == 'tab') {
            return;
        }
        $layout = $flms_settings['woocommerce']['profile_display'];
        
        global $wp_query;
        if($layout == 'accordion') {
            echo '<div class="flms-accordion">';
                echo '<div class="flms-accordion-section">';
                echo '<div class="flms-accordion-heading">';
        }
        if ( ! is_null( $wp_query ) && ! is_admin() && is_main_query() && in_the_loop() && is_page() && is_wc_endpoint_url() ) {
            $endpoint       = WC()->query->get_current_endpoint();
            $action         = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
            $endpoint_title = WC()->query->get_endpoint_title( $endpoint, $action );
            echo '<h3>'.$endpoint_title.'</h3>';
        }
        if($layout == 'accordion') {
            echo '<div class="toggle flms-primary-bg"></div>';
            echo '</div>'; //close tab heading
            echo '<div class="accordion-content to-toggle">';
        }
    }

     /**
     * Add fields to profile in my account (frontend)
     */
    public function woocommerce_add_account_details() {
        if(!flms_is_module_active('course_credits')) {
            return;
        }
        global $flms_settings;
        $credits_location = $flms_settings['woocommerce']['my_licensess_account_location'];
        if($credits_location == 'tab') {
            return;
        }
        $layout = $flms_settings['woocommerce']['profile_display'];
        $user = wp_get_current_user();
        $default = ''; //open
        if($layout == 'accordion') {
            echo '</div>'; //close tab content
            echo '</div>'; //close tab section
        }
        if(flms_is_module_active('course_credits')) {
            $course_credits = new FLMS_Module_Course_Credits();
            $field_data = '';

            $credit_data = $course_credits->get_user_license_fields($user,'woocommerce');
            if(isset($credit_data['field_data'])) {
                $field_data = $credit_data['field_data'];
            }
            
            if($field_data != '') {
                if($layout == 'accordion') {
                    echo '<div class="flms-accordion-section '.$default.'">';
                        echo '<div class="flms-accordion-heading">';
                    
                }
                if(isset($flms_settings['woocommerce']['account_details_tab_heading'])) {
                    $label = $flms_settings['woocommerce']['account_details_tab_heading'];
                } else {
                    $label = $flms_settings['labels']['credits'];
                }
                echo '<h3>'.$label.'</h3>';
                if($layout == 'accordion') {
                    echo '<div class="toggle flms-primary-bg"></div>';
                    echo '</div>';
                    echo '<div class="accordion-content to-toggle '.$default.'">';
                }
                $prefix = '';
                if(isset($flms_settings['woocommerce']['account_details_tab_explanation'])) {
                    $prefix = $flms_settings['woocommerce']['account_details_tab_explanation'];
                    echo '<p class="flms-accordion-description">'.$prefix.'</p>';
                }
                echo $field_data;
                if($layout == 'accordion') {
                    echo '</div>'; //close tab content
                    echo '</div>'; //close tab section
                }
            }

            
            
        }
        if($layout == 'accordion') {
            echo '</div>'; //close tab content
        }
    }
    /**
     * Save account details from frontend
     */
    public function woocommerce_save_account_details($user_id) {
        if(flms_is_module_active('course_credits')) {
            $course_credits = new FLMS_Module_Course_Credits();
            $course_credits->save_user_license_fields($user_id, $_POST);
        }
    }

    /** End accordion to my account */
    public function woocommerce_end_account_accordion() {
        global $flms_settings;
        $layout = $flms_settings['woocommerce']['profile_display'];
        if(flms_is_module_active('course_credits')) {
            if($layout == 'accordion') {
                echo '</div>';
            }
        }
    }

    public function flms_extra_woo_product_meta() {
        if(flms_is_module_active('course_taxonomies')) {
            $course_taxonomies = new FLMS_Module_Course_Taxonomies();
            echo $course_taxonomies->flms_get_course_taxonomies('woocommerce');
        }
    }

    public function flms_woo_additional_information() {
        if(flms_is_module_active('course_taxonomies')) {
            $course_taxonomies = new FLMS_Module_Course_Taxonomies();
            echo $course_taxonomies->flms_get_course_taxonomies('woocommerce');
        }
    }

    public function flms_woo_course_materials() {
        global $product;
        if(flms_is_module_active('course_materials')) {
            $type = $product->get_type();
            $product_id = $product->get_id();
            if($type == 'simple') {
                $courses = get_post_meta( $product_id, 'flms_woocommerce_simple_course_ids', true );
                if(!is_array($courses)) {
                    $courses = array();
                }
            } else {
                $variations = $product->get_children();
                $courses = array();
                foreach($variations as $variation_id) {
                    $variation_courses = get_post_meta( $variation_id, 'flms_woocommerce_variable_course_ids', true );    
                    if(is_array($variation_courses)) {
                        $courses = array_merge($courses, $variation_courses);
                    }
                }
                
            }
            if(!empty($courses)) {
                $course_materials = new FLMS_Module_Course_Materials();
                $course_materials_list = $course_materials->flms_ecommerce_course_materials($courses);
                if($course_materials_list != '') {
                    echo $course_materials_list;
                } 
            } 
        }
        return;
    }
    
    public function woocommerce_display_accept_decline_flms_reporting_fee_admin(  $item_id, $item, $product ) {
        if($product == null) {
            return;
        }
        $status = '';
        $order_id = wc_get_order_id_by_order_item_id($item_id);
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $status = $order->get_status();
            if($status == 'completed') {
                //return;
            }
        }
        $product_id = $product->get_id();
        $type = $product->get_type();
        if($type == 'simple') {
            $courses = get_post_meta( $product_id, 'flms_woocommerce_simple_course_ids', true );
        } else {
            $courses = get_post_meta( $product_id, 'flms_woocommerce_variable_course_ids', true );
        }
        if(is_array($courses)) {
            //print_r($item);
            if(flms_is_module_active('course_credits')) {
                global $flms_settings, $flms_course_credit_types;
                $html = '<div class="edit" style="display: none;">';
                $course_credits = $this->flms_get_course_credits_from_bundle($courses);
                $reporting_label = flms_get_label('reporting_fee');
                $slect_label = flms_get_label('select_credit_reporting');
                foreach($flms_course_credit_types as $credit_type) {
                    if($flms_settings['course_credits'][$credit_type]["reporting-fee-status"] != 'none') {
                        if(isset($course_credits[$credit_type])) {
                            $reporting_types[] = $credit_type;
                            $credit_amount = $course_credits[$credit_type];
                            $label = flms_get_label($credit_type);
                            $meta_key = strtolower(str_replace(' ','_',"$credit_type Reporting Fee"));
                            $accepted = $item->get_meta( $meta_key );
                                           //print_r($item);
                            if(isset($flms_settings['labels'][$credit_type])) {
                                $label = $flms_settings['labels'][$credit_type];
                            } else if(isset($flms_settings['course_credits'][$credit_type]["name"])) {
                                $label = $flms_settings['course_credits'][$credit_type]["name"];  
                            } else {
                                $label = $credit_type;
                            }
                            if($accepted == 'Accepted') {
                                $value = 1;
                            } else {
                                $value = 0;
                            }
                            //$value = isset( $_POST['reporting-fees'][$credit_type] ) ? sanitize_text_field( $_POST['reporting-fees'][$credit_type] ) : '';
                            $html .= '<p class="reporting-fee-option flms-flex gap-sm column"><label>'.$label.' '.$reporting_label.'</label>';
                                $html .= '<select name="reporting-fees-'.$item_id.'['.$credit_type.']">';
                                    
                                        $fee_type = $flms_settings['course_credits'][$credit_type]["reporting-fee-status"];
                                        $fee = $flms_settings['course_credits'][$credit_type]["reporting-fee"];
                                        if($fee_type == 'per-credit') {
                                            //get number of credits
                                            $reporting_fee = $credit_amount * $fee;
                                        } else {
                                            //flat fee
                                            $reporting_fee = $fee;
                                        }
                                        $reporting_fee = number_format(floatval($reporting_fee), 2);
                                        $html .= '<option value="1"';
                                        if($value == 1) {
                                            $html .= ' selected';
                                        }
                                        
                                        $html .= '>Accept '.get_woocommerce_currency_symbol().$reporting_fee.' '.$reporting_label.'</option>';
                                        $html .= '<option value="0"';
                                        if($value == 0) {
                                            $html .= ' selected';
                                        }
                                        $html .= '>Decline '.$reporting_label;
                                    $html .= '</option>';
                                $html .= '<select>';
                                $html .= '<input type="hidden" name="reporting-fee-'.$credit_type.'-'.$item_id.'" value="'.$reporting_fee.'" />';
                                if($flms_settings['course_credits'][$credit_type]["reporting-fee-description"] != '') {
                                    $html .= '<span class="reporting-description">'.$flms_settings['course_credits'][$credit_type]["reporting-fee-description"].'</span>';
                                }
                            $html .= '</p>';
                        }
                    }
                }
                $html .= '</div>';
                echo $html;
            }
        }
    }

    public function action_before_save_order_item_callback(  $order_id, $items ) {
        if(isset($items['order_item_id'])) {
            foreach($items['order_item_id'] as $item_id) {
                $reporting_fees_store = array();
                $reporting_fee_addons = array();
                if(isset($items['reporting-fees-'.$item_id])) {
                    $reporting_fees = $items['reporting-fees-'.$item_id];
                    $reporting_label = flms_get_label('reporting_fee');
                    foreach($reporting_fees as $k => $v) {
                        if((int) $v == 1) {
                            $text = 'Accepted';
                        } else {
                            $text = 'Declined';
                        }
                        $reporting_fees_store[$k] = $v;
                        if(isset($items['reporting-fee-'.$k.'-'.$item_id])) {
                            $reporting_fee_addons[$k] = $items['reporting-fee-'.$k.'-'.$item_id][0];
                        }
                        $meta_key = strtolower(str_replace(' ','_',"$k Reporting Fee"));
                        wc_update_order_item_meta($item_id, $meta_key,  $text ); // Set it as order item meta
                    }
                    wc_update_order_item_meta($item_id, 'flms_item_reporting_fees_addons', $reporting_fee_addons ); // Set it as order item meta
                }
            }
        }
    }

    public function woocommerce_accept_decline_flms_reporting_fee_admin(  $and_taxes, $order ) {
        if ( did_action( 'woocommerce_order_after_calculate_totals' ) >= 2 )
            return;
         
        if ( is_admin()  ) { //&& ! defined( 'DOING_AJAX' )
            $existing_fees = array();
            foreach ( $order->get_items('fee') as $item_id => $item ) {
                $fee_name = $item->get_name();
                $existing_fees[$item_id] = $fee_name;
            }
            //$course_credits_module = new FLMS_Module_Course_Credits();
            //$new_credits = $course_credits_module->flms_get_course_credits_array();
            foreach ( $order->get_items() as $item_id => $item ) {
                $product_name = $item->get_name();
                //$product = $item->get_product();
                //$product_name = $product->get_name();
                $reporting_label = flms_get_label('reporting_fee');
                $reporting_fees = $item->get_meta( 'flms_item_reporting_fees_addons' );
                if(is_array($reporting_fees)) {
                    foreach($reporting_fees as $k => $v) {
                        $label = flms_get_label($k);
                        $fee_name = "$label $reporting_label ($product_name)";
                        $meta_key = strtolower(str_replace(' ','_',"$k Reporting Fee"));
                        $accepted = $item->get_meta($meta_key);
                        $reporting_fees[$k] = (int) $v;
                        //$order->add_meta( 'flms_reporting_fees' );
                        $item_fee = new WC_Order_Item_Fee();
                        $imported_total_fee = absint($v);
                        $item_fee->set_name( $fee_name ); // Generic fee name
                        $item_fee->set_amount( absint($v) ); // Fee amount
                        $item_fee->set_tax_class( '' ); // default for ''
                        $item_fee->set_tax_status( 'taxable' ); // or 'none'
                        $item_fee->set_total( absint($v) ); // Fee amount
                        if(in_array($fee_name,$existing_fees)) {
                            $key = array_search ($fee_name, $existing_fees);
                            if($key !== false) {
                                $order->remove_item($key);
                            }
                        } 
                        if($accepted == 'Accepted') {
                            // Add Fee item to the order
                            $order->add_item( $item_fee );  
                            $order->calculate_totals();
                            $order->save();         
                        }
                    }
                }
            }
            
        }
    }

    public function hide_woo_meta_fields($fields) {
        $reporting_label = flms_get_label('reporting_fee');
        $course_credits_module = new FLMS_Module_Course_Credits();
        $new_credits = $course_credits_module->flms_get_course_credits_array();
        //print_r($new_credits);
        foreach($new_credits as $credit => $value) {
            $meta_key = strtolower(str_replace(' ','_',"$credit Reporting Fee"));
            $fields[] = $meta_key;
        }
        return $fields;
    }
    public function hide_credits_meta_fields($formatted_meta, $item) {
        $reporting_label = flms_get_label('reporting_fee');
        $course_credits_module = new FLMS_Module_Course_Credits();
        $new_credits = $course_credits_module->flms_get_course_credits_array();
        //print_r($new_credits);
        $hide = array();
        foreach($new_credits as $credit => $value) {
            $hide[] = strtolower(str_replace(' ','_',"$credit Reporting Fee"));
        }
        foreach($formatted_meta as $key => $meta){
            if(in_array($meta->key, $hide)) {
                unset($formatted_meta[$key]);
            }
        }
        return $formatted_meta;
    }

    /**
     * Remove order again button by returning an empty array of valid order statuses to order again
     */
    public function remove_order_again_button() {
        return array();
    }

    public function create_update_course_product($course_id) {
        
        $course = new FLMS_Course($course_id);
        global $flms_course_version_content, $flms_latest_version;

        $course_product_settings = get_post_meta($course_id,'flms_course_product_options', true);
        if($course_product_settings == '') {
            $course_product_settings = $this->get_course_product_defaults();
        }
        $current_type = apply_filters('flms_course_product_type', $course_product_settings['product_type']);
        $current_variation_attributes = $course_product_settings['variation_attributes'];

        $product_id = get_post_meta($course_id,'flms_woocommerce_product_id', true);
        
        if($product_id == '') {
            do_action('flms_before_course_product_created', $course_id, $current_type);
            //create the woo product
            if($current_type == 'simple') {
                $product = new WC_Product();
            } else {
                $product = new WC_Product_Variable();
            }
            $product = apply_filters('flms_create_course_product', $product);
            $product->set_name( get_the_title($course_id) );
            $product->set_status( 'publish' ); 
            $product->set_catalog_visibility( 'hidden' );
            $product->save();
            $product_id = $product->get_id();
            
            //tag that it's been processed
            update_post_meta($course_id, 'flms_woocommerce_product_id', $product_id);
            update_post_meta($product_id, 'flms_woocommerce_product_id', $course_id);

            //hide the product from the catalog
            wp_set_object_terms( $product_id, array( 'exclude-from-catalog', 'exclude-from-search' ), 'product_visibility' );
			update_post_meta( $product_id, '_visibility', '_visibility_hidden' );

            do_action('flms_after_course_product_created', $course_id, $product_id);
        }

        
        
        $product = wc_get_product($product_id);
        $product_type = $product->get_type();
        
        $course_identifier = "$course_id:$flms_latest_version";

        if($current_type == 'simple') {
            //update product type if it's wrong
            if($product_type != 'simple') {

                $product_classname = WC_Product_Factory::get_product_classname( $product_id, 'simple' );
                // Get the new product object from the correct classname
                $new_product       = new $product_classname( $product_id );
                // Save product to database and sync caches
                $new_product->save();
                $product = wc_get_product($product_id);
                //wp_remove_object_terms( $product_id, 'variable', 'product_type' );
                //wp_set_object_terms( $product_id, 'simple', 'product_type', true );

                
            }
            $price = absint($course_product_settings['simple_prices']['regular_price']);
            $product->set_regular_price( $price );
            $sale_price = '';
            if(isset($course_product_settings['simple_prices']['sale_price'])) {
                $sale_price = $course_product_settings['simple_prices']['sale_price'];
            }
            if($sale_price != '') {
                $product->set_sale_price( absint($sale_price) );
            }
            
            $product->save();

            //set courses
            $courses = array($course_identifier);
            delete_post_meta($product_id,'flms_woocommerce_course_id');
            foreach($courses as $course) {
                add_post_meta($product_id,'flms_woocommerce_course_id', $course);
            } 
            update_post_meta( $product_id, 'flms_woocommerce_simple_course_ids', $courses );
            
        } else if($current_type == 'variable') {
            //update product type if it's wrong
            if($product_type != 'variable') {
                //wp_remove_object_terms( $product_id, 'simple', 'product_type' );
                //wp_set_object_terms( $product_id, 'variable', 'product_type', true );
                $product_classname = WC_Product_Factory::get_product_classname( $product_id, 'variable' );
                // Get the new product object from the correct classname
                $new_product       = new $product_classname( $product_id );
                // Save product to database and sync caches
                $new_product->save();
                $product = wc_get_product($product_id);
            }

            //delete existing variations
            $variations = $product->get_children();
            if(!empty($variations)) {
                foreach($variations as $variation_id){
                    $var_product = wc_get_product($variation_id);  
                    $var_product->delete(true);                  
                }    
            }
            //set variations
            //create all combinations
            $result = array(array());
            //$attrib
            $att_settings = $course_product_settings['variation_attributes'];
            if(!empty($att_settings)) {
                foreach ($att_settings as $property => $property_values) {
                    $tmp = array();
                    foreach ($result as $result_item) {
                        foreach ($property_values as $property_value) {
                            $tmp[] = array_merge($result_item, array($property => $property_value));
                        }
                    }
                    $result = $tmp;
                }
                //send the output
                if(!empty($result)) {
                    $product_atts = array();
                    foreach($result as $option) {

                        $variation = new WC_Product_Variation();
                        $variation->set_parent_id( $product_id );
                        
                        $attributes = array();
                        $name = array();
                        $id = array();
                        foreach($option as $taxonomy => $attribute_id) {
                            $term = get_term_by('term_taxonomy_id', $attribute_id);
                            if($term !== false) {
                                $attributes[$taxonomy] = $term->slug;
                                $taxonomy_data = get_taxonomy($taxonomy);
                                $id[] = "$taxonomy_data->name-$attribute_id";
                                $tax_id = $term->term_taxonomy_id;
                                $attribute = new WC_Product_Attribute();
                                $attribute->set_id( $tax_id );
                                $attribute->set_name( $taxonomy );
                                $terms = get_terms(array('taxonomy' => $taxonomy, 'hide_empty' => 0));
                                $term_ids = array();
                                if(!empty($terms)) {
                                    foreach($terms as $term) {
                                        $term_ids[] = $term->term_id;
                                    }
                                }
                                $attribute->set_options( $term_ids ); // color att terms
                                $attribute->set_position( 1 );
                                $attribute->set_visible( 1 );
                                $attribute->set_variation( 1 );
                                $product_atts[] = $attribute;
                            }
                            
                        }
                        $id = implode('-',$id);

                        $variation->set_attributes( $attributes );
                        $price = 0;
                        $sale_price = '';
                        if(isset($course_product_settings['variation_prices'])) {
                            if(isset($course_product_settings['variation_prices'][$id])) {
                                if(isset($course_product_settings['variation_prices'][$id]['regular_price'])) {
                                    $price = absint($course_product_settings['variation_prices'][$id]['regular_price']);
                                }
                                if(isset($course_product_settings['variation_prices'][$id]['sale_price'])) {
                                    $sale_price = $course_product_settings['variation_prices'][$id]['sale_price'];
                                    if($sale_price != '') {
                                        $sale_price = absint($sale_price);
                                    }
                                }
                            }
                        }
                        $variation->set_regular_price( $price );
                        if($sale_price != '') {
                            $variation->set_sale_price( $sale_price );
                        }
                        $variation->save();
                        $variation_id = $variation->get_id();
                        update_post_meta($variation_id,'flms_woocommerce_course_id', $course_identifier);
                        update_post_meta( $variation_id, 'flms_woocommerce_variable_course_ids', array($course_identifier));

                        
                    }
                    
                    //set product atts
                    $product->set_attributes($product_atts);
                    $product->save();

                    update_post_meta($product_id, '_default_attributes', array());
                    
                }
            }
        }    
        return $product_id;
        
    }

    public function update_products_count($counts, $type, $perm) {
        if($type != 'product') {
            return $counts;
        }
        $products_with_course = self::get_course_product_ids('publish');
        $counts->publish -= count($products_with_course);
        $products_with_course = self::get_course_product_ids('draft');
        $counts->draft -= count($products_with_course);
        return $counts;
    }
    
    public function get_products_related_to_courses( $query ) {
    
        // Do nothing if not on product Admin page
        if ( ! is_admin() ) :
            return;
        endif;

        $flag = apply_filters( 'flms_show_hidden_products', false );

        // Make sure we're talking to the WP_Query
        if ( $query->is_main_query() && isset($query->query[ 'post_type' ]) && 'product' === $query->query[ 'post_type' ] && ! $flag ) :

            // this will hide campaign created product from product list in admin
            $query->set( 'post__not_in', self::get_course_product_ids() );

        endif;
    }

    public static function get_course_product_ids($post_status = 'any') {

		$campaigns = get_posts( array(
			'fields'          => 'ids',
			'posts_per_page'  => -1,
			'post_type' => 'flms-courses',
            'post_status' => $post_status,
		) );

		$prod_ids = array();

		foreach ( $campaigns as $campaign ) {
			$prod_ids[] = get_post_meta( $campaign, 'flms_woocommerce_product_id', true );	
		}

		return $prod_ids;
	}

    public function get_course_product_defaults() {
        global $flms_settings;
        $type = 'simple';
        if(isset($flms_settings['woocommerce']['default_product_type'])) {
            $type = $flms_settings['woocommerce']['default_product_type'];
        }
        $atts = array();
        if(isset($flms_settings['woocommerce']['default_variation_attributes'])) {
            $atts = $flms_settings['woocommerce']['default_variation_attributes'];
        }
        $simple_prices = array(
            'regular_price' => 0,
            'sale_price' => ''
        );
        return array(
            'product_type' => $type,
            'simple_prices' => $simple_prices,
            'variation_attributes' => $atts,
            'variation_prices' => array()
        );
    }

    public function save_course_product_settings($course_id, $data) {
        $product_type = 'simple';
        if(isset($data['product_type'])) {
            $product_type = $data['product_type'];
        }
        $atts = array();
        if(isset($data['course_product_attributes'])) {
            foreach($data['course_product_attributes'] as $k => $att_options) {
                foreach($att_options as $j => $v) {
                    if(!isset($atts[$k])) {
                        $atts[$k] = array();
                    }
                    $atts[$k][] = $v;
                }
            }
        }

        //save variation prices
        $variation_prices = array();
        $result = array(array());
        foreach ($atts as $property => $property_values) {
            $tmp = array();
            foreach ($result as $result_item) {
                foreach ($property_values as $property_value) {
                    $tmp[] = array_merge($result_item, array($property => $property_value));
                }
            }
            $result = $tmp;
        }
        if(!empty($result)) {
            foreach($result as $option) {
                $id = array();
                foreach($option as $taxonomy => $attribute_id) {
                    $taxonomy_data = get_taxonomy($taxonomy);
                    $id[] = "$taxonomy_data->name-$attribute_id";
                }
                $id = implode('-',$id);
                $price = 0;
                if(isset($data[$id.'-regular-price'])) {
                    $price = $data[$id.'-regular-price'];
                }
                $sale_price = '';
                if(isset($data[$id.'-sale-price'])) {
                    $sale_price = $data[$id.'-sale-price'];
                }
                $variation_prices[$id] = array(
                    'regular_price' => $price,
                    'sale_price' => $sale_price,
                );
            }
        }
        
        $price = 0;
        if(isset($data['simple-regular-price'])) {
            $price = absint($data['simple-regular-price']);
        }
        $sale_price = '';
        if(isset($data['simple-sale-price'])) {
            $sale_price = $data['simple-sale-price'];
            if($sale_price != '') {
                $sale_price = absint($sale_price);
            }
        }
        $simple_prices = array(
            'regular_price' => $price,
            'sale_price' => $sale_price
        );


        $product_settings = array(
            'product_type' => $product_type,
            'simple_prices' => $simple_prices,
            'variation_attributes' => $atts,
            'variation_prices' => $variation_prices
        );
        update_post_meta($course_id,'flms_course_product_options', $product_settings);
    }

    public function get_course_product_prices($course_id) {
        $current_settings = get_post_meta($course_id,'flms_course_product_options', true);
        if($current_settings == '') {
            $current_settings = $this->get_course_product_defaults();
        }
        $price = 0;
        if(isset($current_settings['simple_prices']['regular_price'])) {
            $price = $current_settings['simple_prices']['regular_price'];
        }
        $sale_price = '';
        if(isset($current_settings['simple_prices']['sale_price'])) {
            $sale_price = $current_settings['simple_prices']['sale_price'];
        }
        $return = '<div class="settings-field"><label>Regular Price  ('.get_woocommerce_currency_symbol().')</label><input type="number" name="simple-regular-price" value="'.$price.'" /></div>';
        $return .= '<div class="settings-field"><label>Sale Price  ('.get_woocommerce_currency_symbol().')</label><input type="number" name="simple-sale-price" value="'.$sale_price.'" /></div>';
        return $return;
    }

    public function get_course_product_variations($course_id, $attribute_ids) {
        if(empty($attribute_ids)) {
            return '<div><em>Select at least one attribute to get started.</em></div>';
        } 
        if(count($attribute_ids) == 1) {
            //return '<div><em>Not enough attributes to create variations. Consider setting this course as a simple product type.</em></div>';
        } 
        $current_settings = get_post_meta($course_id,'flms_course_product_options', true);
        if($current_settings == '') {
            $current_settings = $this->get_course_product_defaults();
        }
        //return $attribute_ids;
        $taxomies = array();
        foreach($attribute_ids as $attribute_id) {
            $term = get_term_by('term_taxonomy_id', $attribute_id);
            if ( false !== $term ) {
                $taxonomy = $term->taxonomy;
                if(!isset($taxomies[$taxonomy])) {
                    $taxomies[$taxonomy] = array();
                }
                $taxomies[$taxonomy][] = $attribute_id;
            }        
        }
        
        //create all combinations
        $result = array(array());
        foreach ($taxomies as $property => $property_values) {
            $tmp = array();
            foreach ($result as $result_item) {
                foreach ($property_values as $property_value) {
                    $tmp[] = array_merge($result_item, array($property => $property_value));
                }
            }
            $result = $tmp;
        }
        //send the output
        if(!empty($result)) {
            $variation_inputs = '<div class="course-product-variations">';
            $variation_inputs .= '<div class="settings-field col-3"><label class="heading">Variation</label><label class="heading">Regular Price ('.get_woocommerce_currency_symbol().')</label><label class="heading">Sale Price ('.get_woocommerce_currency_symbol().')</label></div>';
            
            foreach($result as $option) {
                $name = array();
                $id = array();
                foreach($option as $taxonomy => $attribute_id) {
                    $taxonomy_data = get_taxonomy($taxonomy);
                    //echo '<pre>'.print_r($taxonomy_data,true).'</pre>';
                    $term = get_term_by('term_taxonomy_id', $attribute_id);
                    $tax_id = $term->term_taxonomy_id;
                    $name[] = $term->name.' '.$taxonomy_data->labels->singular_name;
                    $id[] = "$taxonomy_data->name-$attribute_id";
                }
                $id = implode('-',$id);
                $name = implode(', ',$name);
                $price = 0;
                $sale_price = '';
                if(isset($current_settings['variation_prices'])) {
                    if(isset($current_settings['variation_prices'][$id])) {
                        if(isset($current_settings['variation_prices'][$id]['regular_price'])) {
                            $price = $current_settings['variation_prices'][$id]['regular_price'];
                        }
                        if(isset($current_settings['variation_prices'][$id]['sale_price'])) {
                            $sale_price = $current_settings['variation_prices'][$id]['sale_price'];
                        }
                    }
                }
                $variation_inputs .= '<div class="settings-field col-3 variation-option">';
                $variation_inputs .= '<div class="variation-name">'.$name .'</div>';
                $variation_inputs .= '<div><input type="number" name="'.$id.'-regular-price" value="'.$price.'" /></div>';
                $variation_inputs .= '<div><input type="number" name="'.$id.'-sale-price" value="'.$sale_price.'" /></div>';
                $variation_inputs .= '</div>';
            }
            $variation_inputs .= '</div>';
            
            
        }
        return $variation_inputs;
    }

    public function update_reporting_fee_checkout_display( $display_key, $meta, $data) {
        if(!flms_is_module_active('course_credits')) {
            return $display_key;
        }
        global $flms_settings;
        $course_credits = new FLMS_Module_Course_Credits();
        $credit_fields = $course_credits->get_course_credit_fields(false);
        foreach($credit_fields as $credit_type) {
            $meta_key = $credit_type.'_reporting_fee';
            if($meta_key == $display_key) {
                if(isset($flms_settings['labels'][$credit_type])) {
                    $label = $flms_settings['labels'][$credit_type];
                } else if(isset($flms_settings['course_credits'][$credit_type]["name"])) {
                    $label = $flms_settings['course_credits'][$credit_type]["name"];  
                } else {
                    $label = $credit_type;
                }
                return "$label Reporting Fee";
                break;
            }
                    
        }
        if($display_key == 'group_seats') {
            return flms_get_label('groups_singular') .' '.flms_get_label('seats_plural');
        }
        return $display_key;
    }

    
}
new FLMS_Module_Woocommerce();