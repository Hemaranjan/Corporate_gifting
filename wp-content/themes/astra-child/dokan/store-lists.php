<?php
/**
 * Giftelier — Vendor Store Listing page.
 * Layout: Browse sidebar + 4-column vendor grid.
 */
defined( 'ABSPATH' ) || exit;

$listing_url   = get_permalink();
$paged         = max( 1, (int) ( $_GET['pno']          ?? 1 ) );
$per_page      = 12;
$search_query  = sanitize_text_field( wp_unslash( $_GET['store_search'] ?? '' ) );
$active_seg    = sanitize_key( wp_unslash( $_GET['segment'] ?? '' ) );
$active_browse = sanitize_key( wp_unslash( $_GET['browse']  ?? '' ) );
$active_trust  = sanitize_key( wp_unslash( $_GET['trust']   ?? '' ) );

/* ── Vendor query ─────────────────────────────────────────────────── */
$query_args = [
    'role__in' => [ 'seller', 'vendor' ],
    'number'   => $per_page,
    'offset'   => ( $paged - 1 ) * $per_page,
    'orderby'  => 'display_name',
    'order'    => 'ASC',
];

if ( $active_browse === 'new' ) {
    $query_args['orderby'] = 'registered';
    $query_args['order']   = 'DESC';
}

if ( $active_seg ) {
    $query_args['meta_query'] = [[
        'key'     => 'gm_vendor_segment',
        'value'   => '"' . $active_seg . '"',
        'compare' => 'LIKE',
    ]];
}

if ( $active_trust === 'verified' ) {
    $query_args['meta_query'] = [[
        'key'   => 'dokan_verification_status',
        'value' => 'approved',
    ]];
}

if ( $search_query ) {
    $query_args['search']         = '*' . esc_attr( $search_query ) . '*';
    $query_args['search_columns'] = [ 'display_name', 'user_login' ];
}

$user_query    = new WP_User_Query( $query_args );
$vendor_users  = $user_query->get_results();
$total_vendors = $user_query->get_total();
$total_pages   = $total_vendors > 0 ? ceil( $total_vendors / $per_page ) : 1;

$seg_labels = class_exists( 'GM_Vendor_Dashboard' ) ? GM_Vendor_Dashboard::SEGMENTS : [];

/* ── Sidebar navigation data ──────────────────────────────────────── */
$browse_nav = [
    ''          => 'All Vendors',
    'featured'  => 'Featured Vendors',
    'new'       => 'New Vendors',
    'top-rated' => 'Top Rated Vendors',
];

$industry_nav = [
    'corporate'    => 'Corporate',
    'hospitals'    => 'Healthcare',
    'school'       => 'Education',
    'construction' => 'Real Estate',
    'wedding'      => 'Events & Weddings',
];

$trust_nav = [
    'verified'      => 'Verified Vendors',
    'top-rated'     => 'Top Rated',
    'most-reviewed' => 'Most Reviewed',
];
?>

<div class="gm-shop-wrap">

    <!-- Browse sidebar -->
    <aside class="gm-store-sidebar">

        <div class="gm-sidebar-group">
            <span class="gm-sidebar-group__label">Browse</span>
            <ul class="gm-sidebar-nav">
                <?php foreach ( $browse_nav as $key => $label ) :
                    if ( $key === '' ) {
                        $is_active = ( ! $active_browse && ! $active_seg && ! $active_trust );
                        $url       = $listing_url;
                    } else {
                        $is_active = ( $active_browse === $key );
                        $url       = add_query_arg( 'browse', $key, $listing_url );
                    }
                ?>
                <li>
                    <a href="<?php echo esc_url( $url ); ?>"
                       class="gm-sidebar-nav__item<?php echo $is_active ? ' is-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="gm-sidebar-group">
            <span class="gm-sidebar-group__label">Industry Served</span>
            <ul class="gm-sidebar-nav">
                <?php foreach ( $industry_nav as $seg => $label ) :
                    $is_active = ( $active_seg === $seg );
                    $url       = add_query_arg( 'segment', $seg, $listing_url );
                ?>
                <li>
                    <a href="<?php echo esc_url( $url ); ?>"
                       class="gm-sidebar-nav__item<?php echo $is_active ? ' is-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="gm-sidebar-group">
            <span class="gm-sidebar-group__label">Trust</span>
            <ul class="gm-sidebar-nav">
                <?php foreach ( $trust_nav as $key => $label ) :
                    $is_active = ( $active_trust === $key );
                    $url       = add_query_arg( 'trust', $key, $listing_url );
                ?>
                <li>
                    <a href="<?php echo esc_url( $url ); ?>"
                       class="gm-sidebar-nav__item<?php echo $is_active ? ' is-active' : ''; ?>">
                        <?php echo esc_html( $label ); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

    </aside>

    <!-- Vendor grid -->
    <main class="gm-shop-main">

        <?php if ( $vendor_users ) : ?>
        <div class="gm-vendor-grid">
            <?php foreach ( $vendor_users as $user ) :
                $vendor     = dokan()->vendor->get( $user->ID );
                $store_name = $vendor->get_shop_name() ?: $user->display_name;
                $store_url  = $vendor->get_shop_url();
                $banner     = $vendor->get_banner();
                $vsegments  = array_filter( (array) get_user_meta( $user->ID, 'gm_vendor_segment', true ) );
            ?>
            <div class="gm-vendor-card">

                <a href="<?php echo esc_url( $store_url ); ?>" class="gm-vendor-card__banner" tabindex="-1" aria-hidden="true">
                    <?php if ( $banner ) : ?>
                        <img src="<?php echo esc_url( is_array( $banner ) ? $banner[0] : $banner ); ?>"
                             alt="<?php echo esc_attr( $store_name ); ?>" loading="lazy" />
                    <?php else : ?>
                        <div class="gm-vendor-card__banner-placeholder"></div>
                    <?php endif; ?>
                </a>

                <div class="gm-vendor-card__body">
                    <h3 class="gm-vendor-card__name"><?php echo esc_html( $store_name ); ?></h3>
                    <?php if ( $vsegments && $seg_labels ) : ?>
                    <div class="gm-vendor-card__tags">
                        <?php foreach ( $vsegments as $s ) :
                            if ( isset( $seg_labels[ $s ] ) ) : ?>
                        <span class="gm-vendor-card__tag gm-vendor-card__tag--<?php echo esc_attr( $s ); ?>">
                            <?php echo esc_html( $seg_labels[ $s ] ); ?>
                        </span>
                        <?php endif; endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="gm-vendor-card__footer">
                    <a href="<?php echo esc_url( $store_url ); ?>" class="gm-vendor-card__cta-link">
                        Browse Store →
                    </a>
                </div>

            </div>
            <?php endforeach; ?>
        </div>

        <?php if ( $total_pages > 1 ) : ?>
        <div class="gm-store-listing__pagination">
            <?php for ( $p = 1; $p <= $total_pages; $p++ ) :
                $page_url = add_query_arg( array_filter( [
                    'pno'          => $p > 1 ? $p : null,
                    'segment'      => $active_seg    ?: null,
                    'browse'       => $active_browse ?: null,
                    'trust'        => $active_trust  ?: null,
                    'store_search' => $search_query  ?: null,
                ] ), $listing_url );
            ?>
            <a href="<?php echo esc_url( $page_url ); ?>"
               class="gm-page-btn<?php echo $p === $paged ? ' is-active' : ''; ?>">
                <?php echo $p; ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

        <?php else : ?>
        <div class="gm-store-listing__empty">
            <div class="gm-store-listing__empty-icon">🏪</div>
            <h3>No vendors found</h3>
            <p>No vendors available right now.</p>
        </div>
        <?php endif; ?>

    </main>

</div><!-- .gm-shop-wrap -->
