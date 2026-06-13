<?php
/**
 * Customer Dashboard — Browse Vendors
 * Renders the vendor store listing within the customer dashboard layout.
 * When the user has a cockpit segment, wraps in a two-column layout.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Tell store-lists.php to use the customer browse endpoint URL for all filter links
$listing_url = wc_get_account_endpoint_url( 'giftelier-browse' );

// Check if cockpit should be shown
$ck_user_id = get_current_user_id();
$ck_segment = get_user_meta( $ck_user_id, 'gm_customer_segment', true );
$show_cockpit = $ck_segment && class_exists( 'GM_Cockpit' ) && isset( GM_Cockpit::CONFIG[ $ck_segment ] );

if ( $show_cockpit ) :
    $ck_config  = GM_Cockpit::CONFIG[ $ck_segment ];
    $l1_items   = GM_Cockpit::get_l1_items( $ck_user_id, $ck_segment );
    [ $active_l1, $active_l2 ] = GM_Cockpit::get_active();
    $segment    = $ck_segment;
    $context    = 'browse';
?>

<div class="gm-browse-page">

    <!-- Vendor listing -->
    <div class="gm-browse-main">
        <?php include get_stylesheet_directory() . '/dokan/store-lists.php'; ?>
    </div>

    <!-- Cockpit sidebar -->
    <div class="gm-cockpit-wrap">
        <?php include GM_PATH . 'templates/customer/cockpit-panel.php'; ?>
    </div>

</div>

<!-- Mobile bar (shown on small screens) -->
<div class="gm-cockpit-mobile-bar" id="gm-cockpit-mobile-bar" role="button" aria-label="Open shopping cockpit">
    <span id="gm-ck-mobile-l1">Select <?php echo esc_html( $ck_config['l1_label'] ); ?>…</span>
    <div class="gm-cockpit-mobile-bar__right">
        <span id="gm-ck-mobile-remaining"></span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="18 15 12 9 6 15"/>
        </svg>
    </div>
</div>

<!-- Mobile drawer -->
<div class="gm-cockpit-drawer" id="gm-cockpit-drawer">
    <div class="gm-cockpit-drawer__backdrop"></div>
    <div class="gm-cockpit-drawer__panel">
        <?php
        // Re-render the cockpit inside the drawer (IDs will clash on desktop but
        // the drawer is display:none on desktop so only one is interactive at a time)
        ?>
        <div class="gm-cockpit-body" style="padding:20px">
            <p style="text-align:center;font-size:13px;color:#9ca3af;margin:0 0 12px">
                Select your <?php echo esc_html( strtolower( $ck_config['l1_label'] ) ); ?> to track budget
            </p>
            <a href="#" onclick="document.getElementById('gm-cockpit-drawer').classList.remove('gm-drawer--open');return false;"
               style="display:block;text-align:center;font-size:13px;color:#5733a2;font-weight:600;text-decoration:none;margin-top:8px">
                Close ×
            </a>
        </div>
    </div>
</div>

<?php else : ?>

    <?php include get_stylesheet_directory() . '/dokan/store-lists.php'; ?>

<?php endif; ?>
