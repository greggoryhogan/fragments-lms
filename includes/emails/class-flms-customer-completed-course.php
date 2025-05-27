<?php
/**
 * Class WC_Email_New_Order file
 *
 * @package WooCommerce\Emails
 */

 //adapted from https://neilmatthews.com/creating-a-custom-email-in-woocommerce/
 
use Automattic\WooCommerce\Utilities\FeaturesUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'FLMS_Email_Customer_Completed_Course' ) && class_exists('WC_Email') ) :

	/**
	 * New Order Email.
	 *
	 * An email sent to the admin when a new order is received/paid for.
	 *
	 * @class       WC_Email_New_Order
	 * @version     2.0.0
	 * @package     WooCommerce\Classes\Emails
	 * @extends     WC_Email
	 */
	class FLMS_Email_Customer_Completed_Course extends WC_Email {

		public function __construct() {
            $this->id = 'wc_vendor_email';
            $this->title = 'Course Completed';
            $this->description = 'This email is sent when a customer completes a course.';
            $this->heading = 'Course Completed!';
            $this->subject = 'BHFE Course Completed';

            $this->template_html  = 'template/emails/customer-completed-course.php';
            $this->template_plain = 'template/emails/plain/vendor-email.php';

            parent::__construct();
        }

        public function trigger( $user_id, $course_id, $course_version ) {
            if ( ! $user_id ) return;

            //$this->object = wc_get_order( $order_id );
            $this->user = get_user_by('id', $user_id);
            $this->course_id = $course_id;
            $this->course_version = $course_version;
            $course = new FLMS_Course($course_id, $course_version);
            $this->course_title = $course->get_course_version_name($course_version);
            $this->recipient = $this->user->user_email;

            if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
                //return;
            }
            //error_log("Trigger fired for user ID: $user_id and course ID: $course_id");
            $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
        }

        public function get_content_html() {
            return wc_get_template_html( $this->template_html, array(
                'user'         => $this->user,
                'user_first_name' => $this->user->user_firstname,
                'course_id' => $this->course_id,
                'course_version' => $this->course_version,
                'course_title' => $this->course_title,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => false,
                'email'         => $this,
            ), FLMS_ABSPATH, FLMS_ABSPATH );
        }

        public function get_content_plain() {
            return wc_get_template_html( $this->template_plain, array(
                'user'         => $this->user,
                'course_id' => $this->course_id,
                'course_version' => $this->course_version,
                'course_title' => $this->course_title,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => true,
                'email'         => $this,
            ), FLMS_ABSPATH );
        }

	}

endif;

return new FLMS_Email_Customer_Completed_Course();
