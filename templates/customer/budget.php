<?php
/**
 * Customer Dashboard — Budget Management
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id   = get_current_user_id();
$yearly    = (int) get_user_meta( $user_id, 'gm_budget_yearly',    true );
$monthly   = (int) get_user_meta( $user_id, 'gm_budget_monthly',   true );
$quarterly = (int) get_user_meta( $user_id, 'gm_budget_quarterly', true );

// Cockpit setup data
$ck_segment  = get_user_meta( $user_id, 'gm_customer_segment', true );
$has_cockpit_setup = $ck_segment && class_exists( 'GM_Cockpit' ) && isset( GM_Cockpit::CONFIG[ $ck_segment ] );
if ( $has_cockpit_setup ) {
    $ck_cfg      = GM_Cockpit::CONFIG[ $ck_segment ];
    $ck_l1_items = GM_Cockpit::get_l1_items( $user_id, $ck_segment );
}

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

    <?php if ( $has_cockpit_setup ) : ?>
    <!-- ── Cockpit L1/L2 setup ──────────────────────────────────────── -->
    <div class="gm-dash-card" id="gm-cockpit-setup">
        <div class="gm-dash-card__header">
            <h3 class="gm-dash-card__title">
                <?php echo esc_html( $ck_cfg['l1_label'] ); ?>s &amp; <?php echo esc_html( $ck_cfg['l2_label'] ); ?>s
            </h3>
            <button type="button" class="gm-btn gm-btn--primary gm-btn--sm" id="gm-setup-add-l1">
                + Add <?php echo esc_html( $ck_cfg['l1_label'] ); ?>
            </button>
        </div>

        <!-- Add L1 inline form -->
        <div id="gm-setup-l1-form" style="display:none">
            <form class="gm-setup-inline-form">
                <div class="gm-form-row">
                    <input type="text" name="l1_name" class="gm-form-input"
                           placeholder="<?php echo esc_attr( $ck_cfg['l1_label'] ); ?> name" required />
                    <?php if ( in_array( 'project_code', $ck_cfg['l1_meta_fields'] ) ) : ?>
                    <input type="text" name="l1_meta_project_code" class="gm-form-input"
                           placeholder="Project code" style="max-width:140px" />
                    <?php endif; ?>
                    <?php if ( in_array( 'guest_count', $ck_cfg['l1_meta_fields'] ) ) : ?>
                    <input type="number" name="l1_meta_guest_count" class="gm-form-input"
                           placeholder="Guest count" min="1" style="max-width:130px" />
                    <input type="number" name="l1_meta_per_head_budget" class="gm-form-input"
                           placeholder="Per-head budget (₹)" min="0" style="max-width:170px" />
                    <?php endif; ?>
                    <?php if ( in_array( 'term', $ck_cfg['l1_meta_fields'] ) ) : ?>
                    <select name="l1_meta_term" class="gm-form-select" style="max-width:150px">
                        <option value="">Select term</option>
                        <option value="Term 1">Term 1</option>
                        <option value="Term 2">Term 2</option>
                        <option value="Term 3">Term 3</option>
                    </select>
                    <?php endif; ?>
                </div>
                <div class="gm-form-row">
                    <button type="submit" class="gm-btn gm-btn--primary gm-btn--sm">Add</button>
                    <button type="button" class="gm-btn gm-btn--ghost-rose gm-btn--sm"
                            onclick="document.getElementById('gm-setup-l1-form').style.display='none'">Cancel</button>
                </div>
            </form>
        </div>

        <!-- L1 list -->
        <div class="gm-l1-list">
            <?php if ( $ck_l1_items ) :
                foreach ( $ck_l1_items as $l1 ) :
                    $l1_meta  = json_decode( $l1->meta_json ?? '{}', true ) ?: [];
                    $l2_rows  = GM_Cockpit::get_l2_items( (int) $l1->id, $user_id );
                    $l2_total = 0;
                    foreach ( (array) $l2_rows as $lr ) $l2_total += (float) $lr->allocated;
            ?>
            <div class="gm-l1-item" data-id="<?php echo esc_attr( $l1->id ); ?>">
                <div class="gm-l1-item-header">
                    <span class="gm-l1-item-name"><?php echo esc_html( $l1->name ); ?></span>
                    <?php if ( ! empty( $l1_meta['project_code'] ) ) : ?>
                    <span class="gm-cockpit-meta-tag"><?php echo esc_html( $l1_meta['project_code'] ); ?></span>
                    <?php endif; ?>
                    <?php if ( ! empty( $l1_meta['guest_count'] ) ) : ?>
                    <span class="gm-cockpit-meta-tag"><?php echo esc_html( $l1_meta['guest_count'] ); ?> guests</span>
                    <?php endif; ?>
                    <?php if ( ! empty( $l1_meta['term'] ) ) : ?>
                    <span class="gm-cockpit-meta-tag"><?php echo esc_html( $l1_meta['term'] ); ?></span>
                    <?php endif; ?>
                    <span class="gm-l1-meta">₹<?php echo number_format( $l2_total, 0 ); ?> total</span>
                    <div class="gm-l1-actions">
                        <button type="button" class="gm-btn gm-btn--ghost gm-btn--sm gm-l1-add-l2"
                                data-id="<?php echo esc_attr( $l1->id ); ?>">
                            + <?php echo esc_html( $ck_cfg['l2_label'] ); ?>
                        </button>
                        <button type="button" class="gm-l2-delete-btn gm-l1-delete-btn"
                                data-id="<?php echo esc_attr( $l1->id ); ?>" title="Delete event">×</button>
                    </div>
                </div>

                <!-- L2 items -->
                <?php if ( $l2_rows ) : ?>
                <div class="gm-l2-list">
                    <?php foreach ( $l2_rows as $l2 ) :
                        $l2_meta = json_decode( $l2->meta_json ?? '{}', true ) ?: [];
                    ?>
                    <div class="gm-l2-item" data-id="<?php echo esc_attr( $l2->id ); ?>">
                        <span class="gm-l2-item-name"><?php echo esc_html( $l2->name ); ?></span>
                        <span class="gm-l2-item-amount">₹<?php echo number_format( (float) $l2->allocated, 0 ); ?></span>
                        <?php if ( ! empty( $l2_meta['per_recipient_cap'] ) ) : ?>
                        <span class="gm-l2-item-meta">cap ₹<?php echo number_format( (float) $l2_meta['per_recipient_cap'], 0 ); ?>/head</span>
                        <?php elseif ( ! empty( $l2_meta['compliance_cap'] ) ) : ?>
                        <span class="gm-l2-item-meta">cap ₹<?php echo number_format( (float) $l2_meta['compliance_cap'], 0 ); ?>/recipient</span>
                        <?php elseif ( ! empty( $l2_meta['target_date'] ) ) : ?>
                        <span class="gm-l2-item-meta"><?php echo esc_html( $l2_meta['target_date'] ); ?></span>
                        <?php endif; ?>
                        <button type="button" class="gm-l2-delete-btn"
                                data-id="<?php echo esc_attr( $l2->id ); ?>" title="Remove">×</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Add L2 inline form -->
                <div class="gm-setup-l2-form" style="display:none">
                    <form class="gm-setup-inline-form">
                        <div class="gm-form-row">
                            <input type="text" name="l2_name" class="gm-form-input"
                                   placeholder="<?php echo esc_attr( $ck_cfg['l2_label'] ); ?> name" required />
                            <input type="number" name="l2_allocated" class="gm-form-input"
                                   placeholder="Budget (₹)" min="0" style="max-width:140px" required />
                            <?php if ( in_array( 'per_recipient_cap', $ck_cfg['l2_meta_fields'] ) ) : ?>
                            <input type="number" name="l2_meta_per_recipient_cap" class="gm-form-input"
                                   placeholder="Cap per recipient (₹)" min="0" style="max-width:180px" />
                            <?php endif; ?>
                            <?php if ( in_array( 'compliance_cap', $ck_cfg['l2_meta_fields'] ) ) : ?>
                            <input type="number" name="l2_meta_compliance_cap" class="gm-form-input"
                                   placeholder="Compliance cap (₹)" min="0" style="max-width:180px" />
                            <?php endif; ?>
                            <?php if ( in_array( 'target_date', $ck_cfg['l2_meta_fields'] ) ) : ?>
                            <input type="date" name="l2_meta_target_date" class="gm-form-input"
                                   style="max-width:160px" />
                            <?php endif; ?>
                        </div>
                        <div class="gm-form-row">
                            <button type="submit" class="gm-btn gm-btn--primary gm-btn--sm">Add</button>
                            <button type="button" class="gm-btn gm-btn--ghost-rose gm-btn--sm gm-l2-cancel-btn">Cancel</button>
                        </div>
                    </form>
                </div>
            </div><!-- /.gm-l1-item -->
            <?php endforeach; ?>
            <?php else : ?>
            <p class="gm-dash-empty-text" style="padding:16px 0">
                No <?php echo esc_html( strtolower( $ck_cfg['l1_label'] ) ); ?>s yet.
                Click "+ Add <?php echo esc_html( $ck_cfg['l1_label'] ); ?>" to get started.
            </p>
            <?php endif; ?>
        </div><!-- /.gm-l1-list -->
    </div><!-- /.gm-dash-card -->
    <?php endif; // has_cockpit_setup ?>

</div>
