<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

echo $email_heading . "\n\n";

echo "Dear Vendor,\n";
echo "You have received a new order. Here are the details:\n\n";

// Include order details template
//wc_get_template( 'emails/plain/email-order-details.php', array( 'order' => $order ) );

echo "Best regards,\n";
echo "Your Company\n";

?>