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
            $this->description = 'This email is sent to the vendor when a new order is placed.';
            $this->heading = 'New Order Notification';
            $this->subject = 'New Order Received';

            $this->template_html  = 'template/emails/vendor-email.php';
            $this->template_plain = 'template/emails/plain/vendor-email.php';

            add_action( 'woocommerce_order_status_completed_notification', array( $this, 'trigger' ) );

            parent::__construct();
        }

        public function trigger( $order_id ) {
            if ( ! $order_id ) return;

            $this->object = wc_get_order( $order_id );
            $this->recipient = 'vendor@example.com'; // Replace with the vendor's email address

            if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
                return;
            }

            $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
        }

        public function get_content_html() {
            return wc_get_template_html( $this->template_html, array(
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => false,
                'email'         => $this,
            ), FLMS_ABSPATH, FLMS_ABSPATH );
        }

        public function get_content_plain() {
            return wc_get_template_html( $this->template_plain, array(
                'order'         => $this->object,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => true,
                'email'         => $this,
            ), FLMS_ABSPATH );
        }

	}

endif;

return new FLMS_Email_Customer_Completed_Course();
