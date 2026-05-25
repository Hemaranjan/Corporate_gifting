<?php
/**
 * Giftelier Vendor Dashboard — horizontal top navigation.
 * Overrides dokan/templates/global/dashboard-nav.php
 *
 * @var string $active_menu  Current active menu key passed from Main::dashboard_side_navigation()
 */

defined( 'ABSPATH' ) || exit;

$nav_items  = dokan_get_dashboard_nav();
$user       = wp_get_current_user();
$avatar     = get_avatar_url( $user->ID, [ 'size' => 32 ] );
$store_url  = dokan_get_store_url( dokan_get_current_user_id() );
$logout_url = wp_logout_url( home_url() );

/* URL-based active detection — Dokan's $active_menu is unreliable */
$current_path = trailingslashit( strtok( $_SERVER['REQUEST_URI'] ?? '', '?' ) );

/* Support sub-links (hardcoded — not processed through Dokan's nav pipeline) */
$support_items = [
    [ 'label' => 'Contact Giftelier',  'url' => home_url( '/contact/' )                    ],
    [ 'label' => 'FAQs',               'url' => home_url( '/faqs/' )                       ],
    [ 'label' => 'Vendor Guidelines',  'url' => home_url( '/vendor-guidelines/' )           ],
    [ 'label' => 'Ticket History',     'url' => home_url( '/my-account/ticket-history/' )   ],
];

/* Account dropdown links */
$account_items = [
    [
        'label' => 'Vendor Profile',
        'url'   => dokan_get_navigation_url( 'settings/store' ),
        'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    ],
    [
        'label' => 'Business Documents',
        'url'   => home_url( '/vendor-documents/' ),
        'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
    ],
    [
        'label' => 'Bank Details',
        'url'   => dokan_get_navigation_url( 'settings/payment' ),
        'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
    ],
    [
        'label' => 'GST Information',
        'url'   => home_url( '/vendor-gst/' ),
        'icon'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 14l6-6"/><circle cx="9" cy="8" r="1" fill="currentColor"/><circle cx="15" cy="14" r="1" fill="currentColor"/><rect x="3" y="3" width="18" height="18" rx="2"/></svg>',
    ],
];
?>

<div class="gm-vendor-topnav" role="navigation" aria-label="Vendor navigation">

    <!-- Brand -->
    <div class="gm-vnav-brand">
        <?php
        $logo_id = get_theme_mod( 'custom_logo' );
        $logo    = $logo_id ? wp_get_attachment_image( $logo_id, 'full', false, [ 'class' => 'gm-vnav-logo-img' ] ) : '';
        if ( $logo ) :
            echo wp_kses_post( $logo );
        else : ?>
            <span class="gm-vnav-logo-text">Giftelier</span>
        <?php endif; ?>
    </div>

    <!-- Nav links -->
    <ul class="gm-vnav-menu">
        <?php foreach ( $nav_items as $key => $item ) :
            if ( empty( $item['title'] ) ) continue;

            $url        = ! empty( $item['url'] ) && $item['url'] !== '#'
                            ? $item['url']
                            : dokan_get_navigation_url( $key );
            $item_path  = trailingslashit( parse_url( $url, PHP_URL_PATH ) );

            /* Active: URL-based detection — dashboard requires exact match to avoid
               matching every sub-page; all others use prefix match */
            if ( $item_path && $item_path !== '/' ) {
                $is_active = ( $key === 'dashboard' )
                    ? ( $current_path === $item_path )
                    : ( 0 === strpos( $current_path, $item_path ) );
            } else {
                $is_active = false;
            }

            if ( $key === 'support' ) : /* ── Support dropdown ── */ ?>

                <li class="gm-vnav-item gm-vnav-item--has-dropdown<?php echo $is_active ? ' gm-vnav-item--active' : ''; ?>">
                    <button class="gm-vnav-support-btn" aria-haspopup="true" aria-expanded="false">
                        <?php echo esc_html( $item['title'] ); ?>
                        <svg class="gm-vnav-chevron" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <ul class="gm-vnav-support-menu gm-dropdown-menu" role="menu">
                        <li class="gm-dropdown-header" role="presentation"><?php esc_html_e( 'Help &amp; support', 'gifting-marketplace' ); ?></li>
                        <?php foreach ( $support_items as $si ) : ?>
                        <li role="none"><a role="menuitem" href="<?php echo esc_url( $si['url'] ); ?>"><?php echo esc_html( $si['label'] ); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </li>

            <?php else : /* ── Regular link ── */
                $cls = 'gm-vnav-item' . ( $is_active ? ' gm-vnav-item--active' : '' );
            ?>
                <li class="<?php echo esc_attr( $cls ); ?>">
                    <a href="<?php echo esc_url( $url ); ?>">
                        <?php echo esc_html( $item['title'] ); ?>
                        <?php if ( ! empty( $item['counts'] ) ) : ?>
                            <span class="gm-vnav-badge"><?php echo esc_html( $item['counts'] ); ?></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endif; ?>

        <?php endforeach; ?>
    </ul>

    <!-- Right: Add Product button + store link + account dropdown -->
    <div class="gm-vnav-right">

        <a href="<?php echo esc_url( dokan_get_new_product_url() ); ?>"
           class="gm-vnav-add-product">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Product
        </a>

        <a href="<?php echo esc_url( $store_url ); ?>" class="gm-vnav-store-link"
           title="<?php esc_attr_e( 'Visit Store', 'dokan-lite' ); ?>" target="_blank">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        </a>

        <div class="gm-vnav-account">
            <button class="gm-vnav-avatar-btn" aria-label="Account settings" aria-haspopup="true" aria-expanded="false">
                <img src="<?php echo esc_url( $avatar ); ?>"
                     alt="<?php echo esc_attr( $user->display_name ); ?>"
                     class="gm-vnav-avatar" />
            </button>

            <ul class="gm-vnav-account-menu gm-dropdown-menu" role="menu">
                <li class="gm-dropdown-header" role="presentation"><?php esc_html_e( 'Account settings', 'gifting-marketplace' ); ?></li>
                <?php foreach ( $account_items as $ai ) : ?>
                <li role="none">
                    <a role="menuitem" href="<?php echo esc_url( $ai['url'] ); ?>">
                        <?php echo $ai['icon']; // phpcs:ignore — SVG, no user data ?>
                        <?php echo esc_html( $ai['label'] ); ?>
                    </a>
                </li>
                <?php endforeach; ?>
                <li class="gm-dropdown-divider" role="none"></li>
                <li role="none">
                    <a role="menuitem" href="<?php echo esc_url( $logout_url ); ?>" class="gm-dropdown-logout">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        <?php esc_html_e( 'Log Out', 'dokan-lite' ); ?>
                    </a>
                </li>
            </ul>
        </div>

    </div>

</div>

<script>
/* Giftelier topnav dropdowns — inline so it runs immediately, no timing issues */
(function () {
    function initDropdown(btnSel, menuSel) {
        var btn  = document.querySelector(btnSel);
        var menu = document.querySelector(menuSel);
        if (!btn || !menu) return;

        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var isOpen = menu.classList.contains('gm-dropdown--open');
            closeAll();
            if (!isOpen) {
                menu.classList.add('gm-dropdown--open');
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    }

    function closeAll() {
        document.querySelectorAll('.gm-dropdown-menu').forEach(function (m) {
            m.classList.remove('gm-dropdown--open');
        });
        document.querySelectorAll('[aria-expanded]').forEach(function (b) {
            b.setAttribute('aria-expanded', 'false');
        });
    }

    document.addEventListener('click', closeAll);

    initDropdown('.gm-vnav-support-btn',  '.gm-vnav-support-menu');
    initDropdown('.gm-vnav-avatar-btn',   '.gm-vnav-account-menu');
})();
</script>
