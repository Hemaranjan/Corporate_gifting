<?php
/**
 * Cart page — event booking reminder banner.
 * Shown when an Amelia booking product is present in the cart.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="gm-cart-event-banner" role="note" aria-label="<?php esc_attr_e( 'Event booking notice', 'gifting-marketplace' ); ?>">
    <div class="gm-cart-event-banner__inner">
        <div class="gm-cart-event-banner__icon" aria-hidden="true">🎉</div>
        <div class="gm-cart-event-banner__text">
            <strong><?php esc_html_e( 'Event booking added!', 'gifting-marketplace' ); ?></strong>
            <span><?php esc_html_e( 'Add gift products below — they\'ll be shipped before your event date.', 'gifting-marketplace' ); ?></span>
        </div>
        <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"
           class="gm-cart-event-banner__cta">
            <?php esc_html_e( '+ Add Gifts', 'gifting-marketplace' ); ?>
        </a>
    </div>
</div>
