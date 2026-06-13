<?php
/**
 * Giftelier — Shopping Cockpit panel HTML.
 *
 * Required vars in scope:
 *   $segment       string   e.g. 'corporate'
 *   $ck_config     array    GM_Cockpit::CONFIG[ $segment ]
 *   $l1_items      array    rows from GM_Cockpit::get_l1_items()
 *   $active_l1     int
 *   $context       string   'browse' | 'product'  (optional, defaults 'browse')
 *
 * Product-page only:
 *   $product_price float    current product's price
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$context       = isset( $context ) ? $context : 'browse';
$product_price = isset( $product_price ) ? (float) $product_price : 0.0;
$budget_url    = wc_get_account_endpoint_url( 'giftelier-budget' );

$seg_labels = [
    'corporate'    => 'Corporate',
    'school'       => 'School',
    'wedding'      => 'Wedding',
    'hospitals'    => 'Hospitals',
    'construction' => 'Construction',
];

/* ── Shared: header ──────────────────────────────────────────────── */
?>
<div class="gm-cockpit<?php echo $context === 'product' ? ' gm-cockpit--product-page' : ''; ?>"
     <?php echo $context === 'product' ? 'data-product-price="' . esc_attr( $product_price ) . '"' : ''; ?>>

    <div class="gm-cockpit-header">
        <span class="gm-cockpit-title">🧭 Shopping Cockpit</span>
        <span class="gm-cockpit-seg-badge">
            <?php echo esc_html( $seg_labels[ $segment ] ?? ucfirst( $segment ) ); ?>
        </span>
    </div>

    <div class="gm-cockpit-body">

    <?php if ( empty( $l1_items ) ) : ?>

        <div class="gm-cockpit-prompt">
            No <?php echo esc_html( strtolower( $ck_config['l1_label'] ) ); ?>s set up yet.<br>
            <a href="<?php echo esc_url( $budget_url ); ?>">Set up your budget →</a>
        </div>

    <?php elseif ( $context === 'product' ) : ?>

        <?php /* ── Product page: event selector + budget summary + impact ── */ ?>

        <div class="gm-cockpit-section">
            <label class="gm-cockpit-label" for="gm-ck-l1">
                <?php echo esc_html( $ck_config['l1_label'] ); ?>
            </label>
            <select class="gm-cockpit-select" id="gm-ck-l1">
                <option value="">Select <?php echo esc_html( $ck_config['l1_label'] ); ?>…</option>
                <?php foreach ( $l1_items as $l1 ) : ?>
                <option value="<?php echo esc_attr( $l1->id ); ?>"
                    <?php selected( (int) $active_l1, (int) $l1->id ); ?>>
                    <?php echo esc_html( $l1->name ); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="gm-cockpit-prompt" id="gm-ck-no-selection"
             style="<?php echo $active_l1 ? 'display:none' : ''; ?>">
            Select a <?php echo esc_html( strtolower( $ck_config['l1_label'] ) ); ?> to see your budget.
        </div>

        <!-- Budget summary (shown after event selected) -->
        <div id="gm-ck-summary" class="gm-cockpit-summary"
             style="<?php echo $active_l1 ? '' : 'display:none'; ?>">
            <div class="gm-ck-row">
                <span class="gm-ck-row__label"><span class="gm-ck-dot gm-ck-dot--grey"></span>Budget</span>
                <span class="gm-ck-row__value" id="gm-ck-allocated">—</span>
            </div>
            <div class="gm-ck-row">
                <span class="gm-ck-row__label"><span class="gm-ck-dot gm-ck-dot--rose"></span>Spent</span>
                <span class="gm-ck-row__value" id="gm-ck-spent">—</span>
            </div>
            <div class="gm-ck-row">
                <span class="gm-ck-row__label"><span class="gm-ck-dot gm-ck-dot--amber"></span>In Cart</span>
                <span class="gm-ck-row__value" id="gm-ck-cart-amt">—</span>
            </div>
            <div class="gm-ck-bar-wrap">
                <div class="gm-ck-bar-spent" id="gm-ck-bar-spent" style="width:0%"></div>
                <div class="gm-ck-bar-cart"  id="gm-ck-bar-cart"  style="width:0%"></div>
            </div>
            <div class="gm-ck-row" style="margin-top:4px">
                <span class="gm-ck-row__label"><span class="gm-ck-dot gm-ck-dot--green"></span>Remaining</span>
                <span class="gm-ck-row__value" id="gm-ck-remaining">—</span>
            </div>
            <!-- Item counts (shown only when item_count > 0) -->
            <div id="gm-ck-items-section" style="display:none">
                <div class="gm-cockpit-divider" style="margin:8px 0"></div>
                <div class="gm-ck-row">
                    <span class="gm-ck-row__label"><span class="gm-ck-dot gm-ck-dot--grey"></span>Items Planned</span>
                    <span class="gm-ck-row__value" id="gm-ck-items-planned">—</span>
                </div>
                <div class="gm-ck-row">
                    <span class="gm-ck-row__label"><span class="gm-ck-dot gm-ck-dot--rose"></span>Purchased</span>
                    <span class="gm-ck-row__value" id="gm-ck-items-purchased">—</span>
                </div>
                <div class="gm-ck-row">
                    <span class="gm-ck-row__label"><span class="gm-ck-dot gm-ck-dot--amber"></span>In Cart</span>
                    <span class="gm-ck-row__value" id="gm-ck-items-in-cart">—</span>
                </div>
                <div class="gm-ck-row">
                    <span class="gm-ck-row__label"><span class="gm-ck-dot gm-ck-dot--green"></span>Left to Buy</span>
                    <span class="gm-ck-row__value" id="gm-ck-items-remaining">—</span>
                </div>
            </div>
        </div>

        <!-- Budget impact of adding this item -->
        <?php if ( $product_price > 0 ) : ?>
        <div id="gm-ck-product-impact" class="gm-cockpit-impact"
             style="<?php echo $active_l1 ? '' : 'display:none'; ?>">
            <div class="gm-cockpit-impact__label">
                Adding this item
                <strong><?php echo wc_price( $product_price ); ?></strong>
            </div>
            <div class="gm-ck-row gm-ck-row--impact">
                <span class="gm-ck-row__label">Remaining after</span>
                <span class="gm-ck-row__value" id="gm-ck-after-add">—</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Warning strip -->
        <div id="gm-ck-warn" class="gm-cockpit-warn" style="display:none"></div>

    <?php else : ?>

        <?php /* ── Browse page: L1 selector + always-visible budget summary ── */ ?>

        <div class="gm-cockpit-section">
            <label class="gm-cockpit-label" for="gm-ck-l1">
                <?php echo esc_html( $ck_config['l1_label'] ); ?>
            </label>
            <select class="gm-cockpit-select" id="gm-ck-l1">
                <option value="">Select <?php echo esc_html( $ck_config['l1_label'] ); ?>…</option>
                <?php foreach ( $l1_items as $l1 ) : ?>
                <option value="<?php echo esc_attr( $l1->id ); ?>"
                    <?php selected( (int) $active_l1, (int) $l1->id ); ?>>
                    <?php echo esc_html( $l1->name ); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="gm-ck-summary" class="gm-cockpit-summary">
            <div class="gm-ck-row">
                <span class="gm-ck-row__label"><span class="gm-ck-dot gm-ck-dot--grey"></span>Allocated</span>
                <span class="gm-ck-row__value" id="gm-ck-allocated">—</span>
            </div>
            <div class="gm-ck-row">
                <span class="gm-ck-row__label"><span class="gm-ck-dot gm-ck-dot--rose"></span>Spent</span>
                <span class="gm-ck-row__value" id="gm-ck-spent">—</span>
            </div>
            <div class="gm-ck-row">
                <span class="gm-ck-row__label"><span class="gm-ck-dot gm-ck-dot--amber"></span>In Cart</span>
                <span class="gm-ck-row__value" id="gm-ck-cart-amt">—</span>
            </div>
            <div class="gm-ck-bar-wrap">
                <div class="gm-ck-bar-spent" id="gm-ck-bar-spent" style="width:0%"></div>
                <div class="gm-ck-bar-cart"  id="gm-ck-bar-cart"  style="width:0%"></div>
            </div>
            <div class="gm-ck-row" style="margin-top:4px">
                <span class="gm-ck-row__label"><span class="gm-ck-dot gm-ck-dot--green"></span>Remaining</span>
                <span class="gm-ck-row__value" id="gm-ck-remaining">—</span>
            </div>
            <!-- Item counts (shown only when item_count > 0) -->
            <div id="gm-ck-items-section" style="display:none">
                <div class="gm-cockpit-divider" style="margin:8px 0"></div>
                <div class="gm-ck-row">
                    <span class="gm-ck-row__label"><span class="gm-ck-dot gm-ck-dot--grey"></span>Items Planned</span>
                    <span class="gm-ck-row__value" id="gm-ck-items-planned">—</span>
                </div>
                <div class="gm-ck-row">
                    <span class="gm-ck-row__label"><span class="gm-ck-dot gm-ck-dot--rose"></span>Purchased</span>
                    <span class="gm-ck-row__value" id="gm-ck-items-purchased">—</span>
                </div>
                <div class="gm-ck-row">
                    <span class="gm-ck-row__label"><span class="gm-ck-dot gm-ck-dot--amber"></span>In Cart</span>
                    <span class="gm-ck-row__value" id="gm-ck-items-in-cart">—</span>
                </div>
                <div class="gm-ck-row">
                    <span class="gm-ck-row__label"><span class="gm-ck-dot gm-ck-dot--green"></span>Left to Buy</span>
                    <span class="gm-ck-row__value" id="gm-ck-items-remaining">—</span>
                </div>
            </div>
        </div>

        <div id="gm-ck-warn"     class="gm-cockpit-warn"     style="display:none"></div>
        <div id="gm-ck-capacity" class="gm-cockpit-capacity" style="display:none"></div>
        <span id="gm-ck-extra-info" class="gm-cockpit-meta-tag" style="display:none"></span>

        <div id="gm-ck-cart-section" style="display:none">
            <div class="gm-cockpit-divider"></div>
            <div class="gm-cockpit-cart-header">In Cart</div>
            <div id="gm-ck-cart-items" class="gm-cockpit-cart-items"></div>
        </div>

    <?php endif; ?>

    </div><!-- .gm-cockpit-body -->
</div><!-- .gm-cockpit -->
