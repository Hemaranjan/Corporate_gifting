<?php
/**
 * "Plan for an Occasion" panel — shown on single product pages for logged-in users.
 * Variables: $occasions, $product_id, $assigned (array of occasion_ids), $nonce
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="gm-occ-panel" id="gm-occ-panel" data-product="<?php echo esc_attr( $product_id ); ?>">

    <script>
    window.gmOcc = window.gmOcc || {};
    window.gmOcc.ajaxUrl   = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
    window.gmOcc.nonce     = <?php echo wp_json_encode( $nonce ); ?>;
    window.gmOcc.occasions = <?php echo wp_json_encode( array_values( $occasions ) ); ?>;
    window.gmOcc.productId = <?php echo (int) $product_id; ?>;
    window.gmOcc.assigned  = <?php echo wp_json_encode( array_values( $assigned ) ); ?>;
    </script>

    <div class="gm-occ-panel__hdr">
        <span class="gm-occ-panel__icon">🎉</span>
        <div>
            <h4 class="gm-occ-panel__title">Plan for an Occasion</h4>
            <p class="gm-occ-panel__sub">Add this gift to one of your saved occasions.</p>
        </div>
    </div>

    <div class="gm-occ-panel__list">
        <?php foreach ( $occasions as $occ ) :
            $is_assigned = in_array( $occ['id'], $assigned, true );
            $date_fmt    = date_i18n( 'd M Y', strtotime( $occ['date'] ) );
        ?>
        <div class="gm-occ-item<?php echo $is_assigned ? ' is-assigned' : ''; ?>"
             data-id="<?php echo esc_attr( $occ['id'] ); ?>">
            <div class="gm-occ-item__dot" style="background:<?php echo esc_attr( $occ['color'] ); ?>20;color:<?php echo esc_attr( $occ['color'] ); ?>">
                <?php echo esc_html( $occ['icon'] ); ?>
            </div>
            <div class="gm-occ-item__info">
                <span class="gm-occ-item__name"><?php echo esc_html( $occ['title'] ); ?></span>
                <span class="gm-occ-item__date"><?php echo esc_html( $date_fmt ); ?></span>
            </div>
            <button class="gm-occ-item__btn gm-btn gm-btn--sm <?php echo $is_assigned ? 'gm-btn--assigned' : 'gm-btn--primary'; ?>"
                    data-occasion="<?php echo esc_attr( $occ['id'] ); ?>">
                <?php echo $is_assigned ? '✓ Added' : '+ Add'; ?>
            </button>
        </div>
        <?php endforeach; ?>
    </div>

    <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'giftelier-calendar' ) ); ?>"
       class="gm-occ-panel__link">Manage occasions →</a>
</div>
