<?php
/**
 * Customer Dashboard — Budget Management
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id   = get_current_user_id();
$yearly    = (int) get_user_meta( $user_id, 'gm_budget_yearly',    true );
$monthly   = (int) get_user_meta( $user_id, 'gm_budget_monthly',   true );
$quarterly = (int) get_user_meta( $user_id, 'gm_budget_quarterly', true );
$segment   = get_user_meta( $user_id, 'gm_customer_segment', true ) ?: '';

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

// Segment planner data
$segment_labels = [
    'corporate'    => 'Corporate',
    'school'       => 'School',
    'wedding'      => 'Wedding',
    'hospitals'    => 'Hospitals',
    'construction' => 'Construction',
];

$pots            = class_exists( 'GM_Budget_Pots' ) ? GM_Budget_Pots::get_pots( $user_id, $segment ) : [];
$total_allocated = class_exists( 'GM_Budget_Pots' ) ? GM_Budget_Pots::get_total_allocated( $user_id, $segment ) : 0.0;
$alloc_pct       = $yearly > 0 ? min( 100, round( ( $total_allocated / $yearly ) * 100 ) ) : 0;
?>
<div class="gm-dash">
    <div class="gm-dash-page-header">
        <h2 class="gm-dash-page-title">Budget Management</h2>
        <p class="gm-dash-page-sub">Set and track your gifting budget across the year.</p>
    </div>

    <!-- Segment selector -->
    <div class="gm-dash-card gm-segment-card">
        <div class="gm-dash-card__header">
            <h3 class="gm-dash-card__title">My Gifting Segment</h3>
            <p class="gm-dash-card__sub">Select your segment to unlock a tailored finance planning module.</p>
        </div>
        <div class="gm-segment-pills">
            <button class="gm-segment-pill <?php echo ! $segment ? 'gm-segment-pill--active' : ''; ?>" data-segment="">
                General
            </button>
            <?php foreach ( $segment_labels as $slug => $label ) : ?>
            <button class="gm-segment-pill <?php echo $segment === $slug ? 'gm-segment-pill--active' : ''; ?>" data-segment="<?php echo esc_attr( $slug ); ?>">
                <?php echo esc_html( $label ); ?>
            </button>
            <?php endforeach; ?>
        </div>
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
                    <label class="gm-form-label">Annual Budget (&#8377;)</label>
                    <input type="number" name="yearly" class="gm-form-input" min="0"
                           value="<?php echo esc_attr( $yearly ); ?>" placeholder="e.g. 50000" />
                    <p class="gm-form-hint">Total you plan to spend on gifts this year</p>
                </div>
                <div class="gm-form-group">
                    <label class="gm-form-label">Monthly Limit (&#8377;)</label>
                    <input type="number" name="monthly" class="gm-form-input" min="0"
                           value="<?php echo esc_attr( $monthly ); ?>" placeholder="e.g. 4000" />
                    <p class="gm-form-hint">Alert me when monthly spend exceeds this</p>
                </div>
                <div class="gm-form-group">
                    <label class="gm-form-label">Quarterly Limit (&#8377;)</label>
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
                    <span class="gm-budget-meta-row__value">&#8377;<?php echo number_format( $year_spent, 0 ); ?></span>
                </div>
                <div class="gm-budget-meta-row">
                    <span class="gm-budget-meta-row__dot gm-budget-meta-row__dot--green"></span>
                    <span class="gm-budget-meta-row__label">Remaining</span>
                    <span class="gm-budget-meta-row__value">&#8377;<?php echo number_format( $year_left, 0 ); ?></span>
                </div>
                <div class="gm-budget-meta-row">
                    <span class="gm-budget-meta-row__dot gm-budget-meta-row__dot--grey"></span>
                    <span class="gm-budget-meta-row__label">Annual budget</span>
                    <span class="gm-budget-meta-row__value">&#8377;<?php echo number_format( $yearly, 0 ); ?></span>
                </div>
            </div>
        </div>
        <?php if ( $year_pct >= 80 ) : ?>
        <div class="gm-alert gm-alert--warning">You have used <?php echo $year_pct; ?>% of your annual budget.</div>
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
                <span class="gm-cat-spend-row__amount">&#8377;<?php echo number_format( $amount, 0 ); ?></span>
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
            <div class="gm-committed-spend__amount">&#8377;<?php echo number_format( $committed, 0 ); ?></div>
            <div class="gm-committed-spend__label">In active / pending orders</div>
        </div>
        <?php else : ?>
        <p class="gm-dash-empty-text">No pending orders at the moment.</p>
        <?php endif; ?>
    </div>

    <?php if ( $segment ) : ?>
    <!-- Segment Finance Planning Module -->
    <div class="gm-dash-card" id="gm-planner-card">
        <div class="gm-dash-card__header">
            <h3 class="gm-dash-card__title">
                <?php echo esc_html( $segment_labels[ $segment ] ); ?> Finance Planning
            </h3>
            <button class="gm-btn gm-btn--primary gm-btn--sm" id="gm-add-pot-btn">+ Add Allocation</button>
        </div>

        <?php if ( $pots ) : ?>
        <!-- Summary -->
        <div class="gm-planner-summary">
            <div class="gm-planner-summary-item">
                <span class="gm-planner-summary-label">Annual Budget</span>
                <span class="gm-planner-summary-value">&#8377;<?php echo number_format( $yearly, 0 ); ?></span>
            </div>
            <div class="gm-planner-summary-item">
                <span class="gm-planner-summary-label">Total Allocated</span>
                <span class="gm-planner-summary-value gm-planner-total-allocated <?php echo $total_allocated > $yearly && $yearly > 0 ? 'gm-text-danger' : ''; ?>">
                    &#8377;<?php echo number_format( $total_allocated, 0 ); ?>
                </span>
            </div>
            <div class="gm-planner-summary-item">
                <span class="gm-planner-summary-label">Unallocated</span>
                <span class="gm-planner-summary-value gm-planner-unallocated">
                    &#8377;<?php echo number_format( max( 0, $yearly - $total_allocated ), 0 ); ?>
                </span>
            </div>
        </div>

        <!-- Pots table -->
        <table class="gm-pots-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Details</th>
                    <th>Allocated (&#8377;)</th>
                    <th>Period</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="gm-pots-tbody">
                <?php foreach ( $pots as $pot ) :
                    $meta = json_decode( $pot->meta_json ?? '{}', true ) ?: [];

                    // Build details string per segment
                    $details = [];
                    switch ( $segment ) {
                        case 'corporate':
                            if ( ! empty( $meta['department_code'] ) )    $details[] = 'Dept: ' . $meta['department_code'];
                            if ( ! empty( $meta['approval_threshold'] ) ) $details[] = 'Approval: &#8377;' . number_format( (float) $meta['approval_threshold'], 0 );
                            break;
                        case 'school':
                            if ( ! empty( $meta['event_type'] ) ) $details[] = $meta['event_type'];
                            if ( ! empty( $meta['term'] ) )        $details[] = $meta['term'];
                            if ( ! empty( $meta['per_recipient_cap'] ) ) $details[] = 'Cap: &#8377;' . number_format( (float) $meta['per_recipient_cap'], 0 ) . '/person';
                            break;
                        case 'wedding':
                            if ( ! empty( $meta['guest_count'] ) && ! empty( $meta['per_head_budget'] ) )
                                $details[] = $meta['guest_count'] . ' guests &times; &#8377;' . number_format( (float) $meta['per_head_budget'], 0 );
                            if ( ! empty( $meta['milestone'] ) ) $details[] = $meta['milestone'];
                            break;
                        case 'hospitals':
                            if ( ! empty( $meta['department'] ) )     $details[] = 'Dept: ' . $meta['department'];
                            if ( ! empty( $meta['compliance_cap'] ) ) $details[] = 'Cap: &#8377;' . number_format( (float) $meta['compliance_cap'], 0 ) . '/recipient';
                            break;
                        case 'construction':
                            if ( ! empty( $meta['project_name'] ) ) $details[] = $meta['project_name'];
                            if ( ! empty( $meta['milestone'] ) )    $details[] = $meta['milestone'];
                            if ( ! empty( $meta['contractor_tier'] ) ) $details[] = ucfirst( $meta['contractor_tier'] );
                            break;
                    }
                ?>
                <tr data-pot-id="<?php echo esc_attr( $pot->id ); ?>">
                    <td><?php echo esc_html( $pot->label ); ?></td>
                    <td class="gm-pots-table__details"><?php echo wp_kses_post( implode( ' &middot; ', $details ) ?: '&mdash;' ); ?></td>
                    <td class="gm-pots-table__amount">&#8377;<?php echo number_format( (float) $pot->allocated, 0 ); ?></td>
                    <td class="gm-pots-table__period">
                        <?php
                        if ( $pot->period_start ) echo esc_html( date_i18n( 'd M Y', strtotime( $pot->period_start ) ) );
                        if ( $pot->period_start && $pot->period_end ) echo ' &ndash; ';
                        if ( $pot->period_end ) echo esc_html( date_i18n( 'd M Y', strtotime( $pot->period_end ) ) );
                        if ( ! $pot->period_start && ! $pot->period_end ) echo '&mdash;';
                        ?>
                    </td>
                    <td>
                        <button class="gm-pots-delete-btn" data-id="<?php echo esc_attr( $pot->id ); ?>">Remove</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Allocation bar -->
        <?php if ( $yearly > 0 ) : ?>
        <div class="gm-allocation-bar-wrap">
            <div class="gm-allocation-bar-label">
                <span>Budget allocation</span>
                <span><?php echo $alloc_pct; ?>% of annual budget allocated</span>
            </div>
            <div class="gm-allocation-bar">
                <div class="gm-allocation-bar__fill <?php echo $total_allocated > $yearly ? 'gm-allocation-bar__fill--over' : ''; ?>"
                     style="width: <?php echo $alloc_pct; ?>%"></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $total_allocated > $yearly && $yearly > 0 ) : ?>
        <div class="gm-alert gm-alert--warning" style="margin-top:16px;">
            Total allocated (&#8377;<?php echo number_format( $total_allocated, 0 ); ?>) exceeds your annual budget. Consider adjusting your allocations.
        </div>
        <?php endif; ?>

        <?php else : ?>
        <p class="gm-dash-empty-text">No allocations yet. Click <strong>+ Add Allocation</strong> to start planning your <?php echo esc_html( $segment_labels[ $segment ] ); ?> gifting budget.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<?php if ( $segment ) : ?>
<!-- Add Allocation Modal -->
<div id="gm-pot-modal-backdrop" class="gm-pot-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="gm-pot-modal-title">
    <div class="gm-pot-modal">
        <div class="gm-pot-modal__header">
            <h3 class="gm-pot-modal__title" id="gm-pot-modal-title">Add Allocation</h3>
            <button id="gm-pot-modal-close" class="gm-pot-modal__close" aria-label="Close">&times;</button>
        </div>
        <form id="gm-pot-form">
            <input type="hidden" id="gm-pot-id" name="id" value="" />
            <div class="gm-pot-modal__body">
                <div class="gm-form-group">
                    <label class="gm-form-label" for="gm-pot-label">Allocation Name <span style="color:#ef4444">*</span></label>
                    <input type="text" id="gm-pot-label" name="label" class="gm-form-input" required
                           placeholder="<?php
                           switch ( $segment ) {
                               case 'corporate':    echo 'e.g. HR Department'; break;
                               case 'school':       echo 'e.g. Annual Day 2025'; break;
                               case 'wedding':      echo 'e.g. Reception Favors'; break;
                               case 'hospitals':    echo 'e.g. ICU Staff Recognition'; break;
                               case 'construction': echo 'e.g. Prestige Tower Handover'; break;
                               default: echo 'Enter a name';
                           }
                           ?>" />
                </div>

                <div class="gm-form-group">
                    <label class="gm-form-label" for="gm-pot-allocated">Allocated Amount (&#8377;) <span style="color:#ef4444">*</span></label>
                    <input type="number" id="gm-pot-allocated" name="allocated" class="gm-form-input" min="0" required placeholder="0" />
                    <?php if ( $segment === 'wedding' ) : ?>
                    <div id="gm-wedding-calc-hint" class="gm-wedding-calc-hint"></div>
                    <?php endif; ?>
                </div>

                <!-- Segment-specific extra fields injected by JS -->
                <div id="gm-pot-extra-fields"></div>

                <div class="gm-form-row gm-form-row--2col">
                    <div class="gm-form-group">
                        <label class="gm-form-label" for="gm-pot-period-start">
                            <?php echo $segment === 'wedding' ? 'Event Date' : 'Period Start'; ?>
                        </label>
                        <input type="date" id="gm-pot-period-start" name="period_start" class="gm-form-input" />
                    </div>
                    <?php if ( $segment !== 'wedding' ) : ?>
                    <div class="gm-form-group">
                        <label class="gm-form-label" for="gm-pot-period-end">Period End</label>
                        <input type="date" id="gm-pot-period-end" name="period_end" class="gm-form-input" />
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="gm-pot-modal__footer">
                <button type="button" id="gm-pot-modal-cancel" class="gm-btn gm-btn--ghost">Cancel</button>
                <button type="submit" id="gm-pot-save-btn" class="gm-btn gm-btn--primary">Save</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
