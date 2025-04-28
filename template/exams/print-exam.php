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

$exam_questions = maybe_unserialize(get_user_meta($user_id, "flms_current_exam_questions_{$exam_id}_$exam_version", true)); 
//$exam_questions = ''; //for debugging
if($exam_questions == '') {
	$exam_questions = flms_get_exam_questions($exam_id, $exam_version);
	update_user_meta($user_id, "flms_current_exam_questions_{$exam_id}_$exam_version", maybe_serialize($exam_questions));
}

//print_r($exam_questions);
$exam_identifier = "$exam_id:$exam_version";
$questions = new FLMS_Questions();
$before_title = apply_filters('flms_before_print_exam_title', '');
$before_exam_content = apply_filters('flms_before_print_exam_content', '');
$after_exam_content = apply_filters('flms_after_print_exam_content', '');
$response = $questions->flms_output_exam_questions($exam_id, $exam_questions, $user_id, $exam_identifier, PHP_INT_MAX, 0, 0, 1, true, 'graded', 'print');
$exam_questions_html = $response['questions'];
if($exam_questions_html == '') {
	wp_redirect(get_permalink($exam_id));
	exit;
}

$exam_title = flms_get_the_title($exam_id, 'flms-print-exam');
$title = apply_filters('flms_print_exam_title', '<h2>'.$exam_title.'</h2>', $exam_id);
$title .= '<div></div>';
$date = date('Y-m-d');
$pdf_title = strtolower(str_replace(' ','-',"$exam_title $date"));

require_once dirname( FLMS_PLUGIN_FILE ) . '/vendor/autoload.php';

$mpdf = new \Mpdf\Mpdf();
$mpdf->defaultheaderline = 0;
$mpdf->defaultfooterline = 0;
$mpdf->setFooter('{PAGENO}');
$mpdf->SetTitle("$exam_title");
$custom_css = '<style>div{padding:0 0 0 0;margin:0 0 0 0;}input[disabled]{border: 1px solid black;}.flms-question{page-break-inside: avoid;}table{vertical-align:top;}.flms-question-heading-table {margin-top: 10px;}</style>';
$mpdf->writeHTML($custom_css.$before_title.$title.$before_exam_content.$exam_questions_html.$after_exam_content);
$mpdf->Output($pdf_title, 'I');