<?php
/**
 * Giftelier — Vendor store page template.
 * Overrides dokan/templates/store.php.
 * Breadcrumb is rendered inside store-header.php (below the nav bar).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$store_user   = dokan()->vendor->get( get_query_var( 'author' ) );
$store_info   = $store_user->get_shop_info();
$map_location = $store_user->get_location();

get_header( 'shop' );

/* Breadcrumb intentionally removed here — rendered in store-header.php */
?>

<?php do_action( 'woocommerce_before_main_content' ); ?>

<div class="dokan-store-wrap">

    <div id="dokan-primary" class="dokan-single-store">
        <div id="dokan-content" class="store-page-wrap woocommerce" role="main">

            <?php dokan_get_template_part( 'store-header' ); ?>

            <?php do_action( 'dokan_store_profile_frame_after', $store_user->data, $store_info ); ?>

            <?php if ( have_posts() ) { ?>

                <div class="seller-items">

                    <?php woocommerce_product_loop_start(); ?>

                    <?php while ( have_posts() ) : the_post(); ?>
                        <?php wc_get_template_part( 'content', 'product' ); ?>
                    <?php endwhile; ?>

                    <?php woocommerce_product_loop_end(); ?>

                </div>

                <?php dokan_content_nav( 'nav-below' ); ?>

            <?php } else { ?>

                <p class="dokan-info"><?php esc_html_e( 'No products were found of this vendor!', 'dokan-lite' ); ?></p>

            <?php } ?>

        </div>
    </div>

</div>

<?php do_action( 'woocommerce_after_main_content' ); ?>

<?php get_footer( 'shop' ); ?>
