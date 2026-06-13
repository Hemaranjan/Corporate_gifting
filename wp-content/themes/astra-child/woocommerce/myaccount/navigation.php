<?php
/**
 * Giftelier — Customer dashboard top nav.
 *
 * Main bar : Browse Vendors | [5 segments with submenus] | Shop
 * Username → dropdown : Dashboard | Budget | My Orders | Log out
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$user       = wp_get_current_user();
$first_name = $user->first_name ?: $user->display_name;

$main_bar_endpoints = [];
$submenu_endpoints  = [ 'giftelier-overview', 'giftelier-budget', 'giftelier-orders' ];
$all_items          = wc_get_account_menu_items();
unset( $all_items['customer-logout'] );

$shop_url = wc_get_page_permalink( 'shop' );

/* Context used for active-state detection on program links */
$_nav_active_ind  = sanitize_key( $_GET['industry'] ?? '' );
$_nav_active_prog = sanitize_key( $_GET['program']  ?? '' );

// Per-industry gifting programs — each submenu item links to the industry tag page
$segments_nav = [
    [
        'label' => 'Corporate Gifting',
        'icon'  => '🏢',
        'tag'   => 'corporate',
        'subs'  => [
            '🤝 Employee Gifting'   => 'employee-gifting',
            '🎖️ Client Appreciation' => 'client-appreciation',
            '🎉 Festival Gifting'   => 'festival-gifting',
            '📢 Event Giveaways'    => 'event-giveaways',
        ],
    ],
    [
        'label' => 'Schools & Education',
        'icon'  => '🏫',
        'tag'   => 'school',
        'subs'  => [
            '🏆 Annual Day Gifts'  => 'annual-day-gifts',
            '🥇 Student Awards'    => 'student-awards',
            '👩‍🏫 Staff Recognition' => 'staff-recognition',
            '🎓 Graduation Gifts'  => 'graduation-gifts',
        ],
    ],
    [
        'label' => 'Weddings & Events',
        'icon'  => '💒',
        'tag'   => 'wedding',
        'subs'  => [
            '🎁 Return Gifts'         => 'return-gifts',
            '🙏 Guest Welcome Kits'   => 'guest-welcome-kits',
            '💍 Wedding Hampers'      => 'wedding-hampers',
            '🎊 Event Favours'        => 'event-favours',
        ],
    ],
    [
        'label' => 'Healthcare',
        'icon'  => '🏥',
        'tag'   => 'hospitals',
        'subs'  => [
            '👨‍⚕️ Doctor Appreciation'  => 'doctor-appreciation',
            '🏅 Staff Rewards'          => 'staff-rewards',
            '🛏️ Patient Welcome Kits'   => 'patient-welcome-kits',
            '💊 Recovery Gifts'         => 'recovery-gifts',
        ],
    ],
    [
        'label' => 'Construction & Real Estate',
        'icon'  => '🏗️',
        'tag'   => 'construction',
        'subs'  => [
            '🏠 Housewarming Gifts'      => 'housewarming-gifts',
            '🔑 Customer Handover Kits'  => 'customer-handover-kits',
            '🤝 Partner Appreciation'    => 'partner-appreciation',
            '🏗️ Project Milestone Gifts' => 'project-milestone-gifts',
        ],
    ],
];

do_action( 'woocommerce_before_account_navigation' );
?>
<nav class="gm-cnav" role="navigation" aria-label="Customer dashboard navigation">

    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="gm-cnav__brand">
        <?php
        $logo_id  = get_theme_mod( 'custom_logo' );
        $logo_img = $logo_id ? wp_get_attachment_image( $logo_id, 'full', false, [ 'class' => 'gm-cnav__logo-img' ] ) : '';
        if ( $logo_img ) : echo wp_kses_post( $logo_img );
        else : ?><span class="gm-cnav__brand-text">Giftelier</span><?php endif; ?>
    </a>

    <ul class="gm-cnav__menu">

        <?php foreach ( $main_bar_endpoints as $endpoint ) :
            if ( ! isset( $all_items[ $endpoint ] ) ) continue;
            $is_active = wc_is_current_account_menu_item( $endpoint );
        ?>
        <li>
            <a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>"
               class="gm-cnav__item<?php echo $is_active ? ' is-active' : ''; ?>">
                <?php echo esc_html( $all_items[ $endpoint ] ); ?>
            </a>
        </li>
        <?php endforeach; ?>

        <!-- Segment items with hover submenus -->
        <?php foreach ( $segments_nav as $seg ) : ?>
        <li class="gm-cnav__has-sub">
            <span class="gm-cnav__item gm-cnav__item--has-sub">
                <?php echo esc_html( $seg['label'] ); ?>
                <svg class="gm-cnav__chevron" width="11" height="11" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </span>
            <ul class="gm-cnav__subnav" role="menu">
                <?php foreach ( $seg['subs'] as $label => $program_slug ) :
                    $_link_args = [
                        'industry' => $seg['tag'],
                        'program'  => $program_slug,
                        // no ?cat= — program selection always shows all gift categories first
                    ];
                    $url       = add_query_arg( $_link_args, $shop_url );
                    $is_active = ( $_nav_active_prog === $program_slug && $_nav_active_ind === $seg['tag'] );
                ?>
                <li role="none">
                    <a href="<?php echo esc_url( $url ); ?>"
                       class="gm-cnav__subnav-item<?php echo $is_active ? ' is-active' : ''; ?>"
                       role="menuitem">
                        <?php echo esc_html( $label ); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </li>
        <?php endforeach; ?>

    </ul>

    <?php if ( is_shop() || is_product_category() || is_product_tag() || is_product() ) : ?>
    <form class="gm-cnav__search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
        <input type="hidden" name="post_type" value="product" />
        <input type="text" name="s" class="gm-cnav__search-input"
               value="<?php echo esc_attr( get_search_query() ); ?>"
               placeholder="Search products…" />
    </form>
    <?php endif; ?>

    <div class="gm-cnav__right">

        <?php
        $cart_count = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
        $cart_url   = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
        ?>
        <a href="<?php echo esc_url( $cart_url ); ?>" class="gm-cnav__cart" aria-label="Cart">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <?php if ( $cart_count > 0 ) : ?>
                <span class="gm-cnav__cart-count"><?php echo esc_html( $cart_count ); ?></span>
            <?php endif; ?>
        </a>

        <div class="gm-cnav__user-menu" id="gm-cnav-user-menu">
            <button class="gm-cnav__user-trigger" id="gm-cnav-user-trigger"
                    aria-haspopup="true" aria-expanded="false">
                <?php echo esc_html( $first_name ); ?>
                <svg class="gm-cnav__chevron" width="12" height="12" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>

            <div class="gm-cnav__dropdown" id="gm-cnav-dropdown" role="menu">
                <?php foreach ( $submenu_endpoints as $endpoint ) :
                    if ( ! isset( $all_items[ $endpoint ] ) ) continue;
                    $is_active = wc_is_current_account_menu_item( $endpoint );
                ?>
                <a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>"
                   class="gm-cnav__dropdown-item<?php echo $is_active ? ' is-active' : ''; ?>"
                   role="menuitem">
                    <?php echo esc_html( $all_items[ $endpoint ] ); ?>
                </a>
                <?php endforeach; ?>
                <div class="gm-cnav__dropdown-divider"></div>
                <a href="<?php echo esc_url( wc_logout_url( home_url( '/' ) ) ); ?>"
                   class="gm-cnav__dropdown-item gm-cnav__dropdown-item--logout"
                   role="menuitem">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Log out
                </a>
            </div>
        </div>

    </div>

</nav>

<script>
(function () {
    var trigger = document.getElementById('gm-cnav-user-trigger');
    var menu    = document.getElementById('gm-cnav-user-menu');
    if (!trigger || !menu) return;
    trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = menu.classList.toggle('is-open');
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function () {
        menu.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
    });
})();
</script>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>
