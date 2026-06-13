<?php
/**
 * Vendor Store Page — Events + Gifts Intro
 *
 * Injected via dokan_store_profile_frame_after, between the vendor profile
 * header and the WooCommerce product loop.
 *
 * Available: $vendor_id (int), $amelia_employee_id (int), $shop_name (string)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$has_amelia = class_exists( 'AmeliaBooking\Infrastructure\WP\GutenbergBlock\AmeliaEventsGutenbergBlock' )
              || defined( 'AMELIA_VERSION' );

// Build shortcode — filter by employee if an ID is mapped
if ( $amelia_employee_id > 0 ) {
    $events_shortcode = '[ameliaevents employee="' . $amelia_employee_id . '"]';
} else {
    $events_shortcode = '[ameliaevents]';
}
?>

<!-- ─────────────────────────────────────────────────────────────
     EVENTS SECTION
     ───────────────────────────────────────────────────────────── -->
<section class="gm-vendor-events" id="gm-events" aria-label="<?php esc_attr_e( 'Upcoming events', 'gifting-marketplace' ); ?>">

    <div class="gm-section-head">
        <div class="gm-section-head__inner">
            <span class="gm-section-badge">
                <?php esc_html_e( 'Book an Experience', 'gifting-marketplace' ); ?>
            </span>
            <h2 class="gm-section-title">
                <?php esc_html_e( 'Upcoming Events', 'gifting-marketplace' ); ?>
            </h2>
            <p class="gm-section-desc">
                <?php esc_html_e( 'Pick an event, then scroll down to add gifts — we\'ll ship them so they arrive before your celebration.', 'gifting-marketplace' ); ?>
            </p>
        </div>
        <a href="#gm-gifts" class="gm-scroll-hint" aria-label="<?php esc_attr_e( 'Skip to gift products', 'gifting-marketplace' ); ?>">
            <?php esc_html_e( 'Skip to gifts', 'gifting-marketplace' ); ?> ↓
        </a>
    </div>

    <div class="gm-amelia-wrap">
        <?php if ( $has_amelia ) : ?>
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo do_shortcode( $events_shortcode );
            ?>
        <?php else : ?>
            <div class="gm-no-events">
                <div class="gm-no-events__icon" aria-hidden="true">🎉</div>
                <p><?php esc_html_e( 'No events are scheduled yet. Check back soon!', 'gifting-marketplace' ); ?></p>
            </div>
        <?php endif; ?>
    </div>

</section>

<!-- ─────────────────────────────────────────────────────────────
     GIFTS INTRO DIVIDER
     Renders just above the Dokan product loop (which follows in store.php).
     ───────────────────────────────────────────────────────────── -->
<div class="gm-gifts-intro" id="gm-gifts" aria-label="<?php esc_attr_e( 'Gift products', 'gifting-marketplace' ); ?>">
    <div class="gm-gifts-intro__inner">

        <div class="gm-gifts-intro__text">
            <span class="gm-section-badge gm-section-badge--amber">
                <?php esc_html_e( 'Make It Extra Special', 'gifting-marketplace' ); ?>
            </span>
            <h2 class="gm-section-title">
                <?php esc_html_e( 'Add Gift Products', 'gifting-marketplace' ); ?>
            </h2>
            <p class="gm-section-desc">
                <?php esc_html_e( 'Choose gifts below. They\'ll be packed and shipped before your event date so everything arrives on time.', 'gifting-marketplace' ); ?>
            </p>
        </div>

        <ul class="gm-feature-list" aria-label="<?php esc_attr_e( 'Delivery features', 'gifting-marketplace' ); ?>">
            <li class="gm-feature-pill"><span aria-hidden="true">🚚</span> <?php esc_html_e( 'Ships before event', 'gifting-marketplace' ); ?></li>
            <li class="gm-feature-pill"><span aria-hidden="true">📦</span> <?php esc_html_e( 'Tracked via Shiprocket', 'gifting-marketplace' ); ?></li>
            <li class="gm-feature-pill"><span aria-hidden="true">🎁</span> <?php esc_html_e( 'Gift-wrapped on request', 'gifting-marketplace' ); ?></li>
        </ul>

    </div>
</div>
