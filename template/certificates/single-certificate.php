<?php
/**
 * The Template for displaying product archives, including the main shop page which is a post type archive
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/archive-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.4.0
 */

defined( 'ABSPATH' ) || exit;

global $wp;
if(!isset($wp->query_vars)) {
    wp_redirect(get_bloginfo('url'));
}
$query_vars = $wp->query_vars;
if(!isset($query_vars['certificate-course']) || !isset($query_vars['certificate-course-version']) || !isset($query_vars['certificate-user']) || !isset($query_vars['certificate-entry-id'])) {
    wp_redirect(get_bloginfo('url'));
}


function hex2rgb($hex) {
    $hex = str_replace("#", "", $hex);
    
    if(strlen($hex) == 3) {
    $r = hexdec(substr($hex,0,1).substr($hex,0,1));
    $g = hexdec(substr($hex,1,1).substr($hex,1,1));
    $b = hexdec(substr($hex,2,1).substr($hex,2,1));
    } else {
    $r = hexdec(substr($hex,0,2));
    $g = hexdec(substr($hex,2,2));
    $b = hexdec(substr($hex,4,2));
    }
    $rgb = array($r, $g, $b);
    
    return $rgb; // returns an array with the rgb values
} 

$course_id = $query_vars['certificate-course'];
$course_version = $query_vars['certificate-course-version'];
$user_id = $query_vars['certificate-user'];
global $flms_settings, $current_user;
$current_user = get_user_by('ID', $user_id);
if($current_user === false) {
    wp_redirect(get_bloginfo('url').'/my-account/?display-error=invalid-user-id');
}
$certificate_label = $flms_settings['labels']['certificate_singular'];
$course = new FLMS_Course($course_id);
global  $flms_active_version, $flms_settings;
$flms_active_version = $course_version;
$course_title = $course->get_course_version_name($course_version);
$date = date('Y-m-d');
$pdf_title = strtolower(str_replace(' ','-',"$course_title $certificate_label $date")).'.pdf';
$certificates = $course->get_course_certificates();
if(empty($certificates)) {
    wp_redirect(get_bloginfo('url').'/my-account/?display-error=no-course-certificate');
} else {
    //mpdf
    //require_once( dirname( FLMS_PLUGIN_FILE ) . '/assets/library/mpdf-8.1.0/config/tcpdf_config.php');
    require_once dirname( FLMS_PLUGIN_FILE ) . '/vendor/autoload.php';

    $mpdf = new \Mpdf\Mpdf();


    // create new PDF document
    //$pdf = new BABEL_WORKBOOK_PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, array(215.9, 279.4), true, 'UTF-8', false);

    $mpdf->SetTitle("$course_title $certificate_label");

    // set document information
    /*$border_size = 1.2;
    $page_width = $mpdf->getPageWidth();
    $page_height = $mpdf->getPageHeight();
    $left = 16;
    $top = 16;
    $border_width = $page_width - ($left * 2);
    $border_height = $page_height - ($top * 2);
    $offset = ($page_width - ($border_size * 2)) / 2;*/


    // -------------------- REAL PDF CONTENT -------------------------------------
    $count = 0;
    $content = '';
    $has_pages = false;
    $missing_certificates = array();
    foreach($certificates as $certificate_id) {
        //check if post exists
        if ( get_post_status( $certificate_id ) ) {
            //get certificate settings
            $settings = get_post_meta($certificate_id,'flms_certificate_settings', true);

            // set margins
            $margin_top = $settings['margin_top'];
            $margin_left = $settings['margin_left'];
            $mpdf->SetMargins($margin_left, $margin_top, $margin_left, true); //PDF_MARGIN_TOP

            $continue = true;

            if(isset($settings['credit_restrictions'])) {
                $restrictions = $settings['credit_restrictions'];
                foreach($restrictions as $restriction) {
                    $user_has_license = get_user_meta( $user_id, "flms_has-license-$restriction", true);
                    $user_license_number = get_user_meta( $user_id, "flms_license-$restriction", true);
                    $required = 'none';
                    if(isset($flms_settings['course_credits'][$restriction]["license-required"])) {
                        $required = $flms_settings['course_credits'][$restriction]["license-required"];
                    }
                    if($user_has_license == '' && ($required == 'required' || $required == 'optional')) {
                        //user cant get certificate
                        $continue = apply_filters('flms_certificate_user_has_license', false, $user_id, $restriction);                        
                    } else if($user_license_number == '' && $required == 'required') {
                        //user cant get certificate
                        $continue = apply_filters('flms_certificate_user_has_license_number', false, $user_id, $restriction);                        
                    } 
                }
            } 

            if($continue) {
                $has_pages = true;

                //Set background color for cpver
                $cover_bg_color = $settings['background_color'];
                $border_color = $settings['border_color'];
                $border_width = $settings['border_width'];
                
                global $post;
                $temp_post = $post;
                $post = get_post($course_id);
                global $flms_active_version;
                $flms_active_version = $course_version;
                global $current_user;
                $temp_current_user = wp_get_current_user();
                $new_user = get_user_by('ID', $user_id);
                $current_user = $new_user;
                //$content = get_the_content(null, false, $certificate_id);
                $font_size = 16;
                if(isset($settings['font_size'])) {
                    $font_size = $settings['font_size'];
                }
                $content = '<style>
                .page {
                    font-size: '.$font_size.'px;
                    position: relative;
                    margin: 0;
                    padding: 0;
                    width: 100%;
                    height: 100%;
                    overflow: hidden;
                    background-color: '.$cover_bg_color.';
                    border-color: '.$border_color.';
                    border-width: '.$border_width.';
                    border-style: solid;
                    padding: '.$settings['padding_top'].'mm '.$settings['padding_left'].'mm;
                }</style>';
                $content .= apply_filters('the_content',apply_shortcodes(get_the_content(null, false, $certificate_id)));
                $content = '<div class="page">'.$content.'</div>';
                //reset user and post details
                $post = $temp_post;
                $current_user = $temp_current_user;
                
                $mpdf->writeHTML($content);
                
            } 
        }
    }

    if($has_pages) {
        // Close and output PDF document
        // This method has several options, check the source code documentation for more information.
        //http://www.fpdf.org/en/doc/output.htm
        /* 
        I: send the file inline to the browser. The PDF viewer is used if available.
        D: send to the browser and force a file download with the name given by name.
        F: save to a local file with the name given by name (may include a path).
        S: return the document as a string
        */
        $mpdf->Output($pdf_title, 'I');
    } else {
        wp_redirect(get_bloginfo('url').'/my-account/?display-error=licenses-unavailable');
    }
}