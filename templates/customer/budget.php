<?php
/**
 * Customer Dashboard — Budget Management
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id   = get_current_user_id();
$yearly    = (int) get_user_meta( $user_id, 'gm_budget_yearly',    true );
$monthly   = (int) get_user_meta( $user_id, 'gm_budget_monthly',   true );
$quarterly = (int) get_user_meta( $user_id, 'gm_budget_quarterly', true );

$year_spent = GM_Customer_Dashboard::get_yearly_spend();
$year_left  = max( 0, $yearly - $year_spent );
$year_pct   = $yearly > 0 ? min( 100, round( ( $year_spent / $yearly ) * 100 ) ) : 0;

// Category spend from orders this year
$orders = wc_get_orders( [
    'customer'   => $user_id,
    'limit'      => -1,
    'date_after' => date( 'Y-01-01' ),
    'status'     => [ 'wc-completed', 'wc-processing' ],
] );

$cat_spend = [];
foreach ( $orders as $order ) {
    foreach ( $order->get_items() as $item ) {
        $product_id = $item->get_product_id();
        $terms = get_the_terms( $product_id, 'product_cat' );
        if ( $terms ) {
            foreach ( $terms as $term ) {
                $cat_spend[ $term->name ] = ( $cat_spend[ $term->name ] ?? 0 ) + (float) $item->get_total();
            }
        }
    }
}
arsort( $cat_spend );
?>
<div class="gm-dash">
    <div class="gm-dash-page-header">
        <h2 class="gm-dash-page-title">Budget Management</h2>
        <p class="gm-dash-page-sub">Set and track your gifting budget across the year.</p>
    </div>

    <!-- Budget form -->
    <div class="gm-dash-card">
        <div class="gm-dash-card__header">
            <h3 class="gm-dash-card__title">Set Your Budget</h3>
        </div>
        <form id="gm-budget-form" class="gm-form">
            <?php wp_nonce_field( 'gm_dashboard', 'gm_nonce_field' ); ?>
            <div class="gm-form-row gm-form-row--3col">
                <div class="gm-form-group">
                    <label class="gm-form-label">Annual Budget (₹)</label>
                    <input type="number" name="yearly" class="gm-form-input" min="0"
                           value="<?php echo esc_attr( $yearly ); ?>" placeholder="e.g. 50000" />
                    <p class="gm-form-hint">Total you plan to spend on gifts this year</p>
                </div>
                <div class="gm-form-group">
                    <label class="gm-form-label">Monthly Limit (₹)</label>
                    <input type="number" name="monthly" class="gm-form-input" min="0"
                           value="<?php echo esc_attr( $monthly ); ?>" placeholder="e.g. 4000" />
                    <p class="gm-form-hint">Alert me when monthly spend exceeds this</p>
                </div>
                <div class="gm-form-group">
                    <label class="gm-form-label">Quarterly Limit (₹)</label>
                    <input type="number" name="quarterly" class="gm-form-input" min="0"
                           value="<?php echo esc_attr( $quarterly ); ?>" placeholder="e.g. 12000" />
                    <p class="gm-form-hint">Alert me when quarterly spend exceeds this</p>
                </div>
            </div>
            <div class="gm-form-actions">
                <button type="submit" class="gm-btn gm-btn--primary">Save Budget</button>
                <span class="gm-form-feedback" id="gm-budget-feedback"></span>
            </div>
        </form>
    </div>

    <!-- Annual progress -->
    <?php if ( $yearly > 0 ) : ?>
    <div class="gm-dash-card">
        <div class="gm-dash-card__header">
            <h3 class="gm-dash-card__title">Annual Progress</h3>
            <span class="gm-dash-card__badge"><?php echo date( 'Y' ); ?></span>
        </div>
        <div class="gm-budget-overview">
            <div class="gm-budget-ring-wrap">
                <svg class="gm-budget-ring" viewBox="0 0 120 120">
                    <circle class="gm-budget-ring__bg"  cx="60" cy="60" r="52" />
                    <circle class="gm-budget-ring__fill" cx="60" cy="60" r="52"
                            stroke-dasharray="<?php echo round( ( $year_pct / 100 ) * 327 ); ?> 327" />
                    <text x="60" y="56" class="gm-budget-ring__pct"><?php echo $year_pct; ?>%</text>
                    <text x="60" y="72" class="gm-budget-ring__sub">used</text>
                </svg>
            </div>
            <div class="gm-budget-meta">
                <div class="gm-budget-meta-row">
                    <span class="gm-budget-meta-row__dot gm-budget-meta-row__dot--rose"></span>
                    <span class="gm-budget-meta-row__label">Spent</span>
                    <span class="gm-budget-meta-row__value">₹<?php echo number_format( $year_spent, 0 ); ?></span>
                </div>
                <div class="gm-budget-meta-row">
                    <span class="gm-budget-meta-row__dot gm-budget-meta-row__dot--green"></span>
                    <span class="gm-budget-meta-row__label">Remaining</span>
                    <span class="gm-budget-meta-row__value">₹<?php echo number_format( $year_left, 0 ); ?></span>
                </div>
                <div class="gm-budget-meta-row">
                    <span class="gm-budget-meta-row__dot gm-budget-meta-row__dot--grey"></span>
                    <span class="gm-budget-meta-row__label">Annual budget</span>
                    <span class="gm-budget-meta-row__value">₹<?php echo number_format( $yearly, 0 ); ?></span>
                </div>
            </div>
        </div>
        <?php if ( $year_pct >= 80 ) : ?>
        <div class="gm-alert gm-alert--warning">⚠️ You've used <?php echo $year_pct; ?>% of your annual budget.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Category-wise spend -->
    <?php if ( $cat_spend ) : ?>
    <div class="gm-dash-card">
        <div class="gm-dash-card__header">
            <h3 class="gm-dash-card__title">Spend by Category</h3>
            <span class="gm-dash-card__badge"><?php echo date( 'Y' ); ?></span>
        </div>
        <div class="gm-cat-spend-list">
            <?php
            $max_spend = max( $cat_spend ) ?: 1;
            $colors = [ '#E8386D', '#F59E0B', '#7C3AED', '#10B981', '#3B82F6', '#F97316' ];
            $i = 0;
            foreach ( $cat_spend as $cat => $amount ) :
                $pct   = round( ( $amount / $max_spend ) * 100 );
                $color = $colors[ $i % count( $colors ) ];
                $i++;
            ?>
            <div class="gm-cat-spend-row">
                <span class="gm-cat-spend-row__name"><?php echo esc_html( $cat ); ?></span>
                <div class="gm-cat-spend-row__bar-wrap">
                    <div class="gm-cat-spend-row__bar" style="width: <?php echo $pct; ?>%; background: <?php echo esc_attr( $color ); ?>"></div>
                </div>
                <span class="gm-cat-spend-row__amount">₹<?php echo number_format( $amount, 0 ); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Upcoming committed spend -->
    <div class="gm-dash-card">
        <div class="gm-dash-card__header">
            <h3 class="gm-dash-card__title">Upcoming Committed Spend</h3>
        </div>
        <?php
        $pending_orders = wc_get_orders( [
            'customer' => $user_id,
            'limit'    => -1,
            'status'   => [ 'wc-pending', 'wc-on-hold', 'wc-processing' ],
        ] );
        $committed = 0;
        foreach ( $pending_orders as $o ) $committed += (float) $o->get_total();
        ?>
        <?php if ( $committed > 0 ) : ?>
        <div class="gm-committed-spend">
            <div class="gm-committed-spend__amount">₹<?php echo number_format( $committed, 0 ); ?></div>
            <div class="gm-committed-spend__label">In active / pending orders</div>
        </div>
        <?php else : ?>
        <p class="gm-dash-empty-text">No pending orders at the moment.</p>
        <?php endif; ?>
    </div>
</div>
