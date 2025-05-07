<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

echo $email_heading . "\n\n";

echo '<p>Dear Vendor,</p>';
echo '<p>You have received a new order. Here are the details:</p>';

// Include order details template
//wc_get_template( 'emails/email-order-details.php', array( 'order' => $order ) );

echo '<p>Best regards,</p>';
echo '<p>Your Company</p>';
?>