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
//print_r($query_vars);
if(!isset($query_vars['print-exam-id']) || !isset($query_vars['print-exam-version'])) {
    wp_redirect(get_bloginfo('url'));
}
$user_id = get_current_user_id();
if($user_id == 0) {
	wp_redirect(get_bloginfo('url'));
}

$exam_id = $query_vars['print-exam-id'];
$exam_version = $query_vars['print-exam-version'];

$exam_questions = maybe_unserialize(get_user_meta($user_id, "flms_current_exam_questions_$exam_id", true)); 
//$exam_questions = ''; //for debugging
if($exam_questions == '') {
	$exam_questions = flms_get_exam_questions($exam_id, $exam_version);
	update_user_meta($user_id, "flms_current_exam_questions_$exam_id", maybe_serialize($exam_questions));
}

//print_r($exam_questions);
$exam_identifier = "$exam_id:$exam_version";
$questions = new FLMS_Questions();

$response = $questions->flms_output_exam_questions($exam_id, $exam_questions, $user_id, $exam_identifier, PHP_INT_MAX, 0, 0, 1, true, 'graded', 'print');
$exam_questions_html = $response['questions'];
if($exam_questions_html == '') {
	wp_redirect(get_permalink($exam_id));
	exit;
}

$exam_title = get_the_title($exam_id);
$title = '<h2>'.$exam_title.'</h2><div></div>';
$date = date('Y-m-d');
$pdf_title = strtolower(str_replace(' ','-',"$exam_title $date"));
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

$pdf->SetTitle("$exam_title");

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
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP - 5, PDF_MARGIN_RIGHT);
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
$pdf->SetFont('freesans', '', 10, '', true);
$pdf->AddPage();
$custom_css = '<style>div{padding:0 0 0 0;margin:0 0 0 0;}</style>';
$pdf->writeHTML($custom_css.$title.$exam_questions_html, false, false, false, false, '');
$pdf->Output($pdf_title, 'I');