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
if(!isset($query_vars['certificate-course']) || !isset($query_vars['certificate-course-version']) || !isset($query_vars['certificate-user'])) {
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
$pdf_title = strtolower(str_replace(' ','-',"$course_title $certificate_label $date"));
$certificates = $course->get_course_certificates();
if(empty($certificates)) {
    wp_redirect(get_bloginfo('url').'/my-account/?display-error=no-course-certificate');
} else {
    require_once( dirname( FLMS_PLUGIN_FILE ) . '/assets/library/TCPDF-main/config/tcpdf_config.php');
    // Include the main TCPDF library 
    require_once( dirname( FLMS_PLUGIN_FILE ) . '/assets/library/TCPDF-main/tcpdf.php');

    //Custom class so we can customize header and footer of pdf
    class BABEL_WORKBOOK_PDF extends TCPDF {
        public function Header() {
        //Set logo in header of pdf
            /*$logo = get_theme_mod( 'custom_logo' );
            $image = wp_get_attachment_image_src( $logo , 'full' );
            if($image) {
                $image_url = str_replace(get_bloginfo('url').'/','',$image[0]);
                $this->Image($image_url, 15, 10, 40, '', 'png', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }*/
        }
    
        //set page numbers for footer  
        public function Footer() {
            $this->SetY(-15);
            if ($this->page == 1){
                return;
            } else {
                return;
                //$this->Cell(0, 0, floor($this->page - 1), 0, false, 'R', 0, '', 0, false, 'T', 'M');
            }
        }

        

    }

    // create new PDF document
    $pdf = new BABEL_WORKBOOK_PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, array(215.9, 279.4), true, 'UTF-8', false);

    $pdf->SetTitle("$course_title $certificate_label");

    // set document information
    $pdf->SetCreator(PDF_CREATOR);

    // -------------------- SET PDF DEFAULTS -------------------------------------

    //$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH);
    //$pdf->setFooterData(array(0,64,0), array(0,64,128));

    // set header and footer fonts
    //$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    //$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    // set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    // set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP - 25, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    // set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // set default font subsetting mode
    $pdf->setFontSubsetting(true);

    // Set font
    // dejavusans is a UTF-8 Unicode font, if you only need to
    // print standard ASCII chars, you can use core fonts like
    // helvetica or times to reduce file size.
    //$pdf->SetFont('helvetica', '', 12, '', true);
    
    $pdf->setPrintHeader(false);


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
                        $continue = false;                        
                    } else if($user_license_number == '' && $required == 'required') {
                        //user cant get certificate
                        $continue = false;                        
                    } 
                }
            } 

            if($continue) {
                $has_pages = true;

                if(isset($settings['font-family'])) {
                    $pdf->SetFont($settings['font-family'], '', 12, '', true);
                } else {
                    $pdf->SetFont('freesans', '', 12, '', true);
                }
    
                //Add page
                $pdf->AddPage();
    
                //Set background color for cpver
                $cover_bg_color = $settings['background_color'];
                $pdf->Rect(0, 0, $pdf->getPageWidth(),   $pdf->getPageHeight(), 'DF', "",  hex2rgb($cover_bg_color));

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
                $content = apply_filters('the_content',apply_shortcodes(get_the_content(null, false, $certificate_id)));
                
                //reset user and post details
                $post = $temp_post;
                $current_user = $temp_current_user;
                //
                //$course_id = $query_vars['certificate-course'];
                //$course_version

                //see if we have a cuistom logo for the pdf, and if not then use the default from the customizer
                /*$cover_logo_image = get_field('cover_logo_image');
                if($cover_logo_image) {
                    $image_url = str_replace(get_bloginfo('url').'/','',$cover_logo_image);
                    $ext = pathinfo($image_url, PATHINFO_EXTENSION);
                } else {
                    $logo = get_theme_mod( 'custom_logo' );
                    $image = wp_get_attachment_image_src( $logo , 'full' );
                    if($image) {
                        $image_url = str_replace(get_bloginfo('url').'/','',$image[0]);
                        $ext = pathinfo($image_url, PATHINFO_EXTENSION);
                    }
                }*/

                // Add the cover content and logo to the pdf
                $pdf->writeHTML($content, false, false, false, false, '');
                //$pdf->writeHTML($content, true, true, true, false, '');
            } 
        }
    }

    /*if($image_url) {
        $pdf->Image($image_url, '','', 70, '', $ext, '', '', false, 300, 'C', false, false, 0, false, false, false);
    }*/

    //show header on all following pages
    //$pdf->setPrintHeader(true);

    //this replaces margins in the html for pretty-print, the vertical space unit (h) and the number spaces to add (n)
    /*$tagvs = array(
        'div' => array(0 => array('h' => 1, 'n' => 1), 1 => array('h' => 2, 'n' => .5)),
        'p' => array(0 => array('h' => 2, 'n' => 2), 1 => array('h' => 1, 'n' => .5)),
        'ol' => array(0 => array('h' => 2, 'n' => 2), 1 => array('h' => 1, 'n' => .5)),
        'ul' => array(0 => array('h' => 2, 'n' => 2), 1 => array('h' => 1, 'n' => .5)),
        'h1' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)), 
        'h2' => array(0 => array('h' => 0, 'n' => 0), 1 => array('h' => 0, 'n' => 0)),
        'h3' => array(0 => array('h' => 1, 'n' => .5), 1 => array('h' => 1, 'n' => 1)),
        'section' => array(0 => array('h' => 1, 'n' => .5), 1 => array('h' => 1, 'n' => 1)));
    $pdf->setHtmlVSpace($tagvs);

    // Set some content to print
    /*$html = babel_workbook_content($post_id,false,false); 

    //This explodes the sections and makes indidiual pages but creates a bug that removes the header image
    $sections = explode('<div class="babel-pdf-pagebreak"></div>',$html);
    if(is_array($sections)) {
        foreach($sections as $section) {
            if($section != '') {
                $pdf->AddPage();
                $pdf->writeHTML($section, true, false, true, false, '');
            }
        }
    }*/
    /*$pdf->AddPage();
    $pdf->writeHTML($content, true, false, true, false, '');*/

    //Add a page to show our content
    //$pdf->AddPage();

    //add said content to the pdf
    //$pdf->writeHTML($html, true, false, true, false, '');
    
    //setting pdf filename
   
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
        $pdf->Output($pdf_title, 'I');
    } else {
        wp_redirect(get_bloginfo('url').'/my-account/?display-error=licenses-unavailable');
    }
}