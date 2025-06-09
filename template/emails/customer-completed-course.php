<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

$email_improvements_enabled = FeaturesUtil::feature_is_enabled( 'email_improvements' );

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php echo $email_improvements_enabled ? '<div class="email-introduction">' : ''; ?>
<?php /* translators: %s: Customer username */ ?>
<p><?php printf( esc_html__( 'Hi %s,', 'woocommerce' ), esc_html( $user_first_name ) ); ?></p>

	<?php /* translators: %s: Site title */ ?>
	<p><?php printf( esc_html__( 'Congratulations! You passed the "%s" course.', 'woocommerce' ), esc_html( $course_title ) ); ?></p>
	<div class="hr hr-top"></div>
	<p><?php printf( esc_html__( 'Certificates can be accessed from <a href="%s">your account</a> at any time.' ), wc_get_page_permalink( 'myaccount' ) ); ?></p>
	<?php 
	/**
	 * Show course-defined additional content
	 */
	if($flms_additional_content != '') {
		echo apply_filters('the_content', $flms_additional_content);
	} ?> 
    <p><?php printf( esc_html__('Thanks for using %s'), get_bloginfo('name')); ?></p>
<?php echo $email_improvements_enabled ? '</div>' : ''; ?>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
/*if ( $additional_content ) {
	echo $email_improvements_enabled ? '<table border="0" cellpadding="0" cellspacing="0" width="100%"><tr><td class="email-additional-content email-additional-content-aligned">' : '';
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
	echo $email_improvements_enabled ? '</td></tr></table>' : '';
}*/

do_action( 'woocommerce_email_footer', $email );

?>