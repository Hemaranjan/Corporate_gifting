<?php
/**
 * Customer Dashboard — Budget Management
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id   = get_current_user_id();
$yearly    = (int) get_user_meta( $user_id, 'gm_budget_yearly',    true );
$monthly   = (int) get_user_meta( $user_id, 'gm_budget_monthly',   true );
$quarterly = (int) get_user_meta( $user_id, 'gm_budget_quarterly', true );

// Calendar / occasions data
$occ_occasions = class_exists( 'GM_Occasions' ) ? GM_Occasions::get_occasions( $user_id ) : [];
$occ_gifts_raw = class_exists( 'GM_Occasions' ) ? GM_Occasions::get_gifts( $user_id ) : [];
$occ_nonce     = wp_create_nonce( 'gm_occ_nonce' );
$occ_gifts_by  = [];
foreach ( $occ_gifts_raw as $g ) {
    $occ_gifts_by[ $g['occasion_id'] ][] = $g;
}
$store_listing_id = function_exists( 'dokan_get_option' ) ? dokan_get_option( 'store_listing', 'dokan_pages' ) : 0;
$browse_url_cal   = $store_listing_id ? get_permalink( (int) $store_listing_id ) : home_url( '/store-listing/' );

$festivals = [
    '2025-01-01' => [['name'=>"New Year's Day",      'icon'=>'🎊','color'=>'#6366f1']],
    '2025-01-14' => [['name'=>'Makar Sankranti',     'icon'=>'🪁','color'=>'#f59e0b'],
                     ['name'=>'Pongal',              'icon'=>'🌾','color'=>'#10b981']],
    '2025-01-26' => [['name'=>'Republic Day',        'icon'=>'🇮🇳','color'=>'#ef4444']],
    '2025-02-02' => [['name'=>'Vasant Panchami',     'icon'=>'🌸','color'=>'#f59e0b']],
    '2025-02-14' => [['name'=>"Valentine's Day",     'icon'=>'❤️','color'=>'#e8386d']],
    '2025-02-26' => [['name'=>'Maha Shivratri',      'icon'=>'🕉️','color'=>'#6366f1']],
    '2025-03-14' => [['name'=>'Holi',                'icon'=>'🎨','color'=>'#f97316']],
    '2025-03-31' => [['name'=>'Eid al-Fitr',         'icon'=>'🌙','color'=>'#10b981']],
    '2025-04-06' => [['name'=>'Ram Navami',          'icon'=>'🙏','color'=>'#f59e0b']],
    '2025-04-10' => [['name'=>'Mahavir Jayanti',     'icon'=>'🙏','color'=>'#f59e0b']],
    '2025-04-14' => [['name'=>'Baisakhi',            'icon'=>'🌾','color'=>'#10b981'],
                     ['name'=>'Ambedkar Jayanti',    'icon'=>'📚','color'=>'#6366f1']],
    '2025-04-18' => [['name'=>'Good Friday',         'icon'=>'✝️','color'=>'#6b7280']],
    '2025-04-20' => [['name'=>'Easter Sunday',       'icon'=>'🐣','color'=>'#10b981']],
    '2025-04-30' => [['name'=>'Akshaya Tritiya',     'icon'=>'🌻','color'=>'#f59e0b']],
    '2025-05-12' => [['name'=>'Buddha Purnima',      'icon'=>'🙏','color'=>'#f59e0b']],
    '2025-06-07' => [['name'=>'Eid al-Adha',         'icon'=>'🌙','color'=>'#10b981']],
    '2025-07-06' => [['name'=>'Muharram',            'icon'=>'🌙','color'=>'#10b981']],
    '2025-08-09' => [['name'=>'Raksha Bandhan',      'icon'=>'🤝','color'=>'#e8386d']],
    '2025-08-15' => [['name'=>'Independence Day',    'icon'=>'🇮🇳','color'=>'#ef4444']],
    '2025-08-16' => [['name'=>'Janmashtami',         'icon'=>'🦚','color'=>'#6366f1']],
    '2025-08-27' => [['name'=>'Ganesh Chaturthi',    'icon'=>'🐘','color'=>'#f59e0b']],
    '2025-09-05' => [['name'=>'Onam',                'icon'=>'🌺','color'=>'#10b981']],
    '2025-09-22' => [['name'=>'Navratri begins',     'icon'=>'🪔','color'=>'#f97316']],
    '2025-10-02' => [['name'=>'Dussehra',            'icon'=>'🏹','color'=>'#f97316'],
                     ['name'=>'Gandhi Jayanti',      'icon'=>'🕊️','color'=>'#6b7280']],
    '2025-10-20' => [['name'=>'Diwali',              'icon'=>'🪔','color'=>'#f59e0b']],
    '2025-10-21' => [['name'=>'Govardhan Puja',      'icon'=>'🙏','color'=>'#f59e0b']],
    '2025-10-23' => [['name'=>'Bhai Dooj',           'icon'=>'👫','color'=>'#e8386d']],
    '2025-11-05' => [['name'=>'Guru Nanak Jayanti',  'icon'=>'🙏','color'=>'#f59e0b']],
    '2025-12-25' => [['name'=>'Christmas',           'icon'=>'🎄','color'=>'#10b981']],
    '2025-12-31' => [['name'=>"New Year's Eve",      'icon'=>'🎊','color'=>'#6366f1']],
    '2026-01-01' => [['name'=>"New Year's Day",      'icon'=>'🎊','color'=>'#6366f1']],
    '2026-01-14' => [['name'=>'Makar Sankranti',     'icon'=>'🪁','color'=>'#f59e0b'],
                     ['name'=>'Pongal',              'icon'=>'🌾','color'=>'#10b981']],
    '2026-01-22' => [['name'=>'Vasant Panchami',     'icon'=>'🌸','color'=>'#f59e0b']],
    '2026-01-26' => [['name'=>'Republic Day',        'icon'=>'🇮🇳','color'=>'#ef4444']],
    '2026-02-14' => [['name'=>"Valentine's Day",     'icon'=>'❤️','color'=>'#e8386d']],
    '2026-02-15' => [['name'=>'Maha Shivratri',      'icon'=>'🕉️','color'=>'#6366f1']],
    '2026-03-03' => [['name'=>'Holi',                'icon'=>'🎨','color'=>'#f97316']],
    '2026-03-20' => [['name'=>'Eid al-Fitr',         'icon'=>'🌙','color'=>'#10b981']],
    '2026-03-27' => [['name'=>'Ram Navami',          'icon'=>'🙏','color'=>'#f59e0b']],
    '2026-04-03' => [['name'=>'Good Friday',         'icon'=>'✝️','color'=>'#6b7280']],
    '2026-04-05' => [['name'=>'Easter Sunday',       'icon'=>'🐣','color'=>'#10b981']],
    '2026-04-14' => [['name'=>'Baisakhi',            'icon'=>'🌾','color'=>'#10b981'],
                     ['name'=>'Ambedkar Jayanti',    'icon'=>'📚','color'=>'#6366f1']],
    '2026-05-19' => [['name'=>'Akshaya Tritiya',     'icon'=>'🌻','color'=>'#f59e0b']],
    '2026-05-27' => [['name'=>'Eid al-Adha',         'icon'=>'🌙','color'=>'#10b981']],
    '2026-05-31' => [['name'=>'Buddha Purnima',      'icon'=>'🙏','color'=>'#f59e0b']],
    '2026-08-04' => [['name'=>'Janmashtami',         'icon'=>'🦚','color'=>'#6366f1']],
    '2026-08-15' => [['name'=>'Independence Day',    'icon'=>'🇮🇳','color'=>'#ef4444']],
    '2026-08-25' => [['name'=>'Onam',                'icon'=>'🌺','color'=>'#10b981']],
    '2026-08-28' => [['name'=>'Raksha Bandhan',      'icon'=>'🤝','color'=>'#e8386d']],
    '2026-09-15' => [['name'=>'Ganesh Chaturthi',    'icon'=>'🐘','color'=>'#f59e0b']],
    '2026-10-02' => [['name'=>'Gandhi Jayanti',      'icon'=>'🕊️','color'=>'#6b7280']],
    '2026-10-09' => [['name'=>'Navratri begins',     'icon'=>'🪔','color'=>'#f97316']],
    '2026-10-18' => [['name'=>'Dussehra',            'icon'=>'🏹','color'=>'#f97316']],
    '2026-11-08' => [['name'=>'Diwali',              'icon'=>'🪔','color'=>'#f59e0b']],
    '2026-11-11' => [['name'=>'Bhai Dooj',           'icon'=>'👫','color'=>'#e8386d']],
    '2026-11-24' => [['name'=>'Guru Nanak Jayanti',  'icon'=>'🙏','color'=>'#f59e0b']],
    '2026-12-25' => [['name'=>'Christmas',           'icon'=>'🎄','color'=>'#10b981']],
    '2026-12-31' => [['name'=>"New Year's Eve",      'icon'=>'🎊','color'=>'#6366f1']],
];

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
        <h2 class="gm-dash-page-title">Budget &amp; Events</h2>
        <p class="gm-dash-page-sub">Set your gifting budget, track spend, and plan events on one page.</p>
    </div>

    <script>
    window.gmOcc = {
        ajaxUrl:   <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
        nonce:     <?php echo wp_json_encode( $occ_nonce ); ?>,
        occasions: <?php echo wp_json_encode( array_values( $occ_occasions ) ); ?>,
        festivals: <?php echo wp_json_encode( $festivals ); ?>,
        giftsBy:   <?php echo wp_json_encode( (object) $occ_gifts_by ); ?>,
        browseUrl: <?php echo wp_json_encode( $browse_url_cal ); ?>,
    };
    </script>

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

    <!-- ── Events Calendar ─────────────────────────────────────────── -->
    <div class="gm-dash-card gm-cal-wrap">
        <div class="gm-cal-header">
            <button class="gm-cal-nav-btn" id="gm-cal-prev" aria-label="Previous month">&#8249;</button>
            <div class="gm-cal-header__center">
                <h3 class="gm-cal-month" id="gm-cal-heading"></h3>
                <button class="gm-cal-today-btn" id="gm-cal-today">Today</button>
            </div>
            <button class="gm-cal-nav-btn" id="gm-cal-next" aria-label="Next month">&#8250;</button>
        </div>
        <div class="gm-cal-grid" id="gm-cal-grid" role="grid"></div>
    </div>

    <!-- Legend -->
    <div class="gm-cal-legend">
        <span class="gm-cal-legend-item">
            <span class="gm-cal-legend-dot" style="background:#f59e0b"></span> Indian Festival
        </span>
        <span class="gm-cal-legend-item">
            <span class="gm-cal-legend-dot" style="background:#E8386D"></span> Your Occasion
        </span>
        <span class="gm-cal-legend-item">
            <span class="gm-cal-legend-dot" style="background:#5733a2;border-radius:50%"></span> Today
        </span>
    </div>

    <!-- Saved occasions list -->
    <div class="gm-dash-card">
        <div class="gm-dash-card__header">
            <h3 class="gm-dash-card__title">Your Occasions</h3>
            <span class="gm-dash-card__meta" id="gm-occ-count"></span>
        </div>
        <div id="gm-occasions-list">
            <p class="gm-dash-empty-text">Click any date on the calendar above to add an occasion.</p>
        </div>
    </div>

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


</div><!-- /.gm-dash -->

<!-- ── Add Occasion Modal ──────────────────────────────────────────── -->
<div class="gm-modal-overlay" id="gm-occ-modal" role="dialog" aria-modal="true">
    <div class="gm-modal">
        <div class="gm-modal__header">
            <h4 class="gm-modal__title">Add an Occasion</h4>
            <button class="gm-modal__close" id="gm-occ-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="gm-modal__body">
            <div class="gm-form-row">
                <span class="gm-form-label">Date</span>
                <div class="gm-modal-date-badge" id="gm-occ-date-display"></div>
                <input type="hidden" id="gm-occ-date" />
            </div>
            <div class="gm-form-row">
                <label class="gm-form-label" for="gm-occ-title">Occasion name *</label>
                <input type="text" id="gm-occ-title" class="gm-form-input"
                       placeholder="e.g. Mom's Birthday, Team Lunch…" maxlength="80" />
            </div>
            <div class="gm-form-row">
                <label class="gm-form-label" for="gm-occ-budget">Spend Limit (₹)</label>
                <input type="number" id="gm-occ-budget" class="gm-form-input"
                       placeholder="e.g. 5,000" min="0" step="100" style="max-width:200px" />
                <p class="gm-form-hint" style="margin-top:4px">Optional — tracks your gift budget for this event</p>
            </div>
            <div class="gm-form-row">
                <span class="gm-form-label">Icon</span>
                <div class="gm-icon-grid">
                    <?php
                    foreach ( ['🎉','🎂','💍','🎄','🏠','👶','🎓','❤️','🙏','💼','🎁','🌟','🥳','🎊','🪔','🌸'] as $i => $ic ) :
                        echo '<button type="button" class="gm-icon-opt' . ( $i === 0 ? ' is-selected' : '' ) . '" data-icon="' . esc_attr( $ic ) . '">' . $ic . '</button>';
                    endforeach;
                    ?>
                </div>
                <input type="hidden" id="gm-occ-icon" value="🎉" />
            </div>
            <div class="gm-form-row">
                <span class="gm-form-label">Colour</span>
                <div class="gm-color-grid">
                    <?php
                    foreach ( ['#E8386D','#5733a2','#F59E0B','#10B981','#3B82F6','#F97316','#EC4899','#14B8A6'] as $j => $col ) :
                        echo '<button type="button" class="gm-color-opt' . ( $j === 0 ? ' is-selected' : '' ) . '" data-color="' . esc_attr( $col ) . '" style="background:' . esc_attr( $col ) . '"></button>';
                    endforeach;
                    ?>
                </div>
                <input type="hidden" id="gm-occ-color" value="#E8386D" />
            </div>
        </div>
        <div class="gm-modal__footer">
            <button type="button" id="gm-occ-cancel" class="gm-btn gm-btn--ghost">Cancel</button>
            <button type="button" id="gm-occ-save" class="gm-btn gm-btn--primary">Save Occasion</button>
        </div>
    </div>
</div>
