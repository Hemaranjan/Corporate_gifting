<?php
/**
 * Customer Dashboard — Calendar with Indian festivals + personal occasions.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id    = get_current_user_id();
$first_name = wp_get_current_user()->first_name ?: wp_get_current_user()->display_name;
$occasions  = GM_Occasions::get_occasions( $user_id );
$gifts_raw  = GM_Occasions::get_gifts( $user_id );
$nonce      = wp_create_nonce( 'gm_occ_nonce' );

// Index gifts by occasion_id for JS
$gifts_by = [];
foreach ( $gifts_raw as $g ) {
    $gifts_by[ $g['occasion_id'] ][] = $g;
}

// Browse vendors / shop URL
$store_listing_id  = function_exists( 'dokan_get_option' ) ? dokan_get_option( 'store_listing', 'dokan_pages' ) : 0;
$browse_url        = $store_listing_id ? get_permalink( (int) $store_listing_id ) : home_url( '/store-listing/' );

/* ── Indian festivals 2025-2026 ──────────────────────────────────── */
$festivals = [
    // ─── 2025 ────────────────────────────────────────────────────
    '2025-01-01' => [['name'=>"New Year's Day",        'icon'=>'🎊','color'=>'#6366f1']],
    '2025-01-14' => [['name'=>'Makar Sankranti',       'icon'=>'🪁','color'=>'#f59e0b'],
                     ['name'=>'Pongal',                'icon'=>'🌾','color'=>'#10b981']],
    '2025-01-26' => [['name'=>'Republic Day',          'icon'=>'🇮🇳','color'=>'#ef4444']],
    '2025-02-02' => [['name'=>'Vasant Panchami',       'icon'=>'🌸','color'=>'#f59e0b']],
    '2025-02-14' => [['name'=>"Valentine's Day",       'icon'=>'❤️','color'=>'#e8386d']],
    '2025-02-26' => [['name'=>'Maha Shivratri',        'icon'=>'🕉️','color'=>'#6366f1']],
    '2025-03-14' => [['name'=>'Holi',                  'icon'=>'🎨','color'=>'#f97316']],
    '2025-03-31' => [['name'=>'Eid al-Fitr',           'icon'=>'🌙','color'=>'#10b981']],
    '2025-04-06' => [['name'=>'Ram Navami',            'icon'=>'🙏','color'=>'#f59e0b']],
    '2025-04-10' => [['name'=>'Mahavir Jayanti',       'icon'=>'🙏','color'=>'#f59e0b']],
    '2025-04-14' => [['name'=>'Baisakhi',              'icon'=>'🌾','color'=>'#10b981'],
                     ['name'=>'Ambedkar Jayanti',      'icon'=>'📚','color'=>'#6366f1']],
    '2025-04-18' => [['name'=>'Good Friday',           'icon'=>'✝️','color'=>'#6b7280']],
    '2025-04-20' => [['name'=>'Easter Sunday',         'icon'=>'🐣','color'=>'#10b981']],
    '2025-04-30' => [['name'=>'Akshaya Tritiya',       'icon'=>'🌻','color'=>'#f59e0b']],
    '2025-05-12' => [['name'=>'Buddha Purnima',        'icon'=>'🙏','color'=>'#f59e0b']],
    '2025-06-07' => [['name'=>'Eid al-Adha',           'icon'=>'🌙','color'=>'#10b981']],
    '2025-07-06' => [['name'=>'Muharram',              'icon'=>'🌙','color'=>'#10b981']],
    '2025-08-09' => [['name'=>'Raksha Bandhan',        'icon'=>'🤝','color'=>'#e8386d']],
    '2025-08-15' => [['name'=>'Independence Day',      'icon'=>'🇮🇳','color'=>'#ef4444']],
    '2025-08-16' => [['name'=>'Janmashtami',           'icon'=>'🦚','color'=>'#6366f1']],
    '2025-08-27' => [['name'=>'Ganesh Chaturthi',      'icon'=>'🐘','color'=>'#f59e0b']],
    '2025-09-05' => [['name'=>'Onam',                  'icon'=>'🌺','color'=>'#10b981']],
    '2025-09-22' => [['name'=>'Navratri begins',       'icon'=>'🪔','color'=>'#f97316']],
    '2025-10-02' => [['name'=>'Dussehra',              'icon'=>'🏹','color'=>'#f97316'],
                     ['name'=>'Gandhi Jayanti',        'icon'=>'🕊️','color'=>'#6b7280']],
    '2025-10-20' => [['name'=>'Diwali',                'icon'=>'🪔','color'=>'#f59e0b']],
    '2025-10-21' => [['name'=>'Govardhan Puja',        'icon'=>'🙏','color'=>'#f59e0b']],
    '2025-10-23' => [['name'=>'Bhai Dooj',             'icon'=>'👫','color'=>'#e8386d']],
    '2025-11-05' => [['name'=>'Guru Nanak Jayanti',    'icon'=>'🙏','color'=>'#f59e0b']],
    '2025-12-25' => [['name'=>'Christmas',             'icon'=>'🎄','color'=>'#10b981']],
    '2025-12-31' => [['name'=>"New Year's Eve",        'icon'=>'🎊','color'=>'#6366f1']],
    // ─── 2026 ────────────────────────────────────────────────────
    '2026-01-01' => [['name'=>"New Year's Day",        'icon'=>'🎊','color'=>'#6366f1']],
    '2026-01-14' => [['name'=>'Makar Sankranti',       'icon'=>'🪁','color'=>'#f59e0b'],
                     ['name'=>'Pongal',                'icon'=>'🌾','color'=>'#10b981']],
    '2026-01-22' => [['name'=>'Vasant Panchami',       'icon'=>'🌸','color'=>'#f59e0b']],
    '2026-01-26' => [['name'=>'Republic Day',          'icon'=>'🇮🇳','color'=>'#ef4444']],
    '2026-02-14' => [['name'=>"Valentine's Day",       'icon'=>'❤️','color'=>'#e8386d']],
    '2026-02-15' => [['name'=>'Maha Shivratri',        'icon'=>'🕉️','color'=>'#6366f1']],
    '2026-03-03' => [['name'=>'Holi',                  'icon'=>'🎨','color'=>'#f97316']],
    '2026-03-20' => [['name'=>'Eid al-Fitr',           'icon'=>'🌙','color'=>'#10b981']],
    '2026-03-27' => [['name'=>'Ram Navami',            'icon'=>'🙏','color'=>'#f59e0b']],
    '2026-04-03' => [['name'=>'Good Friday',           'icon'=>'✝️','color'=>'#6b7280']],
    '2026-04-05' => [['name'=>'Easter Sunday',         'icon'=>'🐣','color'=>'#10b981']],
    '2026-04-14' => [['name'=>'Baisakhi',              'icon'=>'🌾','color'=>'#10b981'],
                     ['name'=>'Ambedkar Jayanti',      'icon'=>'📚','color'=>'#6366f1']],
    '2026-05-19' => [['name'=>'Akshaya Tritiya',       'icon'=>'🌻','color'=>'#f59e0b']],
    '2026-05-27' => [['name'=>'Eid al-Adha',           'icon'=>'🌙','color'=>'#10b981']],
    '2026-05-31' => [['name'=>'Buddha Purnima',        'icon'=>'🙏','color'=>'#f59e0b']],
    '2026-08-04' => [['name'=>'Janmashtami',           'icon'=>'🦚','color'=>'#6366f1']],
    '2026-08-15' => [['name'=>'Independence Day',      'icon'=>'🇮🇳','color'=>'#ef4444']],
    '2026-08-25' => [['name'=>'Onam',                  'icon'=>'🌺','color'=>'#10b981']],
    '2026-08-28' => [['name'=>'Raksha Bandhan',        'icon'=>'🤝','color'=>'#e8386d']],
    '2026-09-15' => [['name'=>'Ganesh Chaturthi',      'icon'=>'🐘','color'=>'#f59e0b']],
    '2026-10-02' => [['name'=>'Gandhi Jayanti',        'icon'=>'🕊️','color'=>'#6b7280']],
    '2026-10-09' => [['name'=>'Navratri begins',       'icon'=>'🪔','color'=>'#f97316']],
    '2026-10-18' => [['name'=>'Dussehra',              'icon'=>'🏹','color'=>'#f97316']],
    '2026-11-08' => [['name'=>'Diwali',                'icon'=>'🪔','color'=>'#f59e0b']],
    '2026-11-11' => [['name'=>'Bhai Dooj',             'icon'=>'👫','color'=>'#e8386d']],
    '2026-11-24' => [['name'=>'Guru Nanak Jayanti',    'icon'=>'🙏','color'=>'#f59e0b']],
    '2026-12-25' => [['name'=>'Christmas',             'icon'=>'🎄','color'=>'#10b981']],
    '2026-12-31' => [['name'=>"New Year's Eve",        'icon'=>'🎊','color'=>'#6366f1']],
];
?>
<div class="gm-dash gm-calendar-page">

    <div class="gm-dash-welcome">
        <div class="gm-dash-welcome__text">
            <h2 class="gm-dash-welcome__name">Your Calendar, <?php echo esc_html( $first_name ); ?> 📅</h2>
            <p class="gm-dash-welcome__sub">Plan gifting around Indian festivals and your personal celebrations.</p>
        </div>
        <a href="<?php echo esc_url( $browse_url ); ?>" class="gm-btn gm-btn--ghost">Browse Vendors</a>
    </div>

    <!-- Data for JS -->
    <script>
    window.gmOcc = {
        ajaxUrl:   <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
        nonce:     <?php echo wp_json_encode( $nonce ); ?>,
        occasions: <?php echo wp_json_encode( array_values( $occasions ) ); ?>,
        festivals: <?php echo wp_json_encode( $festivals ); ?>,
        giftsBy:   <?php echo wp_json_encode( (object) $gifts_by ); ?>,
        browseUrl: <?php echo wp_json_encode( $browse_url ); ?>,
        shopUrl:   <?php echo wp_json_encode( wc_get_page_permalink( 'shop' ) ); ?>,
    };
    </script>

    <!-- Calendar card -->
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

</div>

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

<!-- ── Event Detail Modal ───────────────────────────────────────────── -->
<div class="gm-modal-overlay" id="gm-occ-detail-modal" role="dialog" aria-modal="true" aria-labelledby="gm-det-title">
    <div class="gm-modal gm-modal--detail">
        <div class="gm-modal__header gm-det-header" id="gm-det-header">
            <h4 class="gm-modal__title" id="gm-det-title"></h4>
            <button class="gm-modal__close" id="gm-det-close" aria-label="Close">&times;</button>
        </div>
        <div class="gm-modal__body">
            <div class="gm-det-meta">
                <span class="gm-det-meta__date" id="gm-det-date"></span>
                <span class="gm-det-meta__rel"  id="gm-det-rel"></span>
            </div>
            <div class="gm-det-budget" id="gm-det-budget" style="display:none"></div>
            <div class="gm-det-gifts">
                <p class="gm-det-gifts__label">Planned Gifts</p>
                <div id="gm-det-gifts-list"></div>
            </div>
        </div>
        <div class="gm-modal__footer gm-det-footer">
            <button type="button" id="gm-det-remove" class="gm-btn gm-btn--ghost-rose">Remove</button>
            <a href="#" id="gm-det-add-gift" class="gm-btn gm-btn--primary">🎁 Browse Gifts</a>
        </div>
    </div>
</div>
