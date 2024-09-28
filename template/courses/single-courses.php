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

 get_header( 'flms' );

 do_action('flms_before_main_content'); ?>

 <header class="flms-header">
	<?php if ( apply_filters( 'flms_show_page_title', true ) ) : ?>
		<h1 class="flms-header__title page-title"><?php flms_page_title(); ?></h1>
	<?php endif; ?>

	<?php
	/**
	 * Hook: flms_after_heading
	 *
	 * @hooked flms_breadcrumbs - 10
	 * @hooked flms_course_taxonomies - 15
	 * @hooked flms_course_credits - 20
	 */
	do_action( 'flms_after_heading' );
	?>
</header>

<?php 
 do_action('flms_before_content');

 /**
  * Hook: flms_main_content
  *
  * @hooked flms_course_content - 10
  **/
 do_action('flms_main_content');

 do_action('flms_after_content');

 do_action('flms_after_main_content');

 get_footer('flms');