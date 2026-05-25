<?php
/**
 * Giftelier — Vendor Store Listing page.
 * Overrides dokan/templates/store-lists.php.
 */
defined( 'ABSPATH' ) || exit;

/* ── Segment tabs ──────────────────────────────────────────────── */
$segments = [
    ''             => 'All',
    'corporate'    => 'Corporate',
    'school'       => 'School',
    'wedding'      => 'Wedding',
    'hospitals'    => 'Hospitals',
    'construction' => 'Construction',
];

/* ── Left nav: special items + WC product categories ──────────── */
$left_nav_specials = [
    ''           => [ 'icon' => '🏪', 'label' => 'All Stores'   ],
    'new'        => [ 'icon' => '✨', 'label' => 'New Arrivals'  ],
    'best-rated' => [ 'icon' => '⭐', 'label' => 'Best Rated'   ],
];

$wc_categories = get_terms( [
    'taxonomy'   => 'product_cat',
    'hide_empty' => true,
    'parent'     => 0,
    'orderby'    => 'name',
    'order'      => 'ASC',
    'exclude'    => get_option( 'default_product_cat' ),
] );

// Fallback gift categories if WC has none yet
$fallback_cats = [
    [ 'slug' => 'cakes-bakery',        'icon' => '🎂', 'name' => 'Cakes & Bakery'      ],
    [ 'slug' => 'flowers-plants',      'icon' => '🌸', 'name' => 'Flowers & Plants'     ],
    [ 'slug' => 'personalized-gifts',  'icon' => '🎁', 'name' => 'Personalized Gifts'   ],
    [ 'slug' => 'gift-hampers',        'icon' => '🧺', 'name' => 'Gift Hampers'          ],
    [ 'slug' => 'chocolates-sweets',   'icon' => '🍫', 'name' => 'Chocolates & Sweets'  ],
    [ 'slug' => 'wellness-spa',        'icon' => '💆', 'name' => 'Wellness & Spa'        ],
    [ 'slug' => 'kids-toys',           'icon' => '🧸', 'name' => 'Kids & Toys'           ],
    [ 'slug' => 'corporate-gifts',     'icon' => '💼', 'name' => 'Corporate Gifts'       ],
    [ 'slug' => 'wedding-gifts',       'icon' => '💒', 'name' => 'Wedding Gifts'         ],
    [ 'slug' => 'home-decor',          'icon' => '🏠', 'name' => 'Home & Decor'          ],
];

/* ── URL params ────────────────────────────────────────────────── */
$active_segment  = sanitize_key( $_GET['segment']      ?? '' );
$active_cat      = sanitize_key( $_GET['cat']          ?? '' );
$active_special  = sanitize_key( $_GET['filter']       ?? '' );
$search_query    = sanitize_text_field( $_GET['store_search'] ?? '' );
$paged           = max( 1, (int) ( $_GET['pno'] ?? 1 ) );
$per_page        = 12;
$listing_url     = get_permalink();

/* ── Build vendor query ────────────────────────────────────────── */
$query_args = [
    'role__in' => [ 'seller', 'vendor' ],
    'number'   => $per_page,
    'offset'   => ( $paged - 1 ) * $per_page,
];

// Segment filter
if ( $active_segment ) {
    $query_args['meta_query'] = [[
        'key'     => 'gm_vendor_segment',
        'value'   => '"' . $active_segment . '"',
        'compare' => 'LIKE',
    ]];
}

// Sort by special filter
if ( $active_special === 'new' ) {
    $query_args['orderby'] = 'registered';
    $query_args['order']   = 'DESC';
} elseif ( $active_special === 'best-rated' ) {
    $query_args['meta_key'] = 'dokan_profile_settings';
    $query_args['orderby']  = 'display_name';
    $query_args['order']    = 'ASC';
} else {
    $query_args['orderby'] = 'display_name';
    $query_args['order']   = 'ASC';
}

// WC category filter — find vendor IDs with products in this category
if ( $active_cat ) {
    $cat_products = get_posts( [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'numberposts'    => -1,
        'fields'         => 'ids',
        'tax_query'      => [[
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $active_cat,
        ]],
    ] );
    if ( $cat_products ) {
        $cat_vendor_ids = array_values( array_unique( array_map( function( $pid ) {
            return (int) get_post_field( 'post_author', $pid );
        }, $cat_products ) ) );
        $query_args['include'] = $cat_vendor_ids;
        unset( $query_args['offset'], $query_args['number'] ); // include ignores offset
    } else {
        $query_args['include'] = [ 0 ]; // force no results
    }
}

// Search
if ( $search_query ) {
    $query_args['search']         = '*' . esc_attr( $search_query ) . '*';
    $query_args['search_columns'] = [ 'display_name', 'user_login' ];
}

$user_query    = new WP_User_Query( $query_args );
$vendor_users  = $user_query->get_results();
$total_vendors = $user_query->get_total();
$total_pages   = $active_cat ? 1 : ceil( $total_vendors / $per_page );

/* ── URL helper ────────────────────────────────────────────────── */
function gm_listing_url( $listing_url, $params = [] ) {
    $defaults = array_filter( [
        'segment'      => sanitize_key( $_GET['segment'] ?? '' ),
        'store_search' => sanitize_text_field( $_GET['store_search'] ?? '' ),
    ] );
    return add_query_arg( array_filter( array_merge( $defaults, $params ) ), $listing_url );
}
?>

<!-- ── Top nav ─────────────────────────────────────────────────── -->
<nav class="gm-store-nav gm-store-listing-nav" role="navigation" aria-label="Store navigation">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="gm-store-nav__brand">
        <?php
        $logo_id  = get_theme_mod( 'custom_logo' );
        $logo_img = $logo_id ? wp_get_attachment_image( $logo_id, 'full', false, [ 'class' => 'gm-store-nav__logo-img' ] ) : '';
        echo $logo_img ? wp_kses_post( $logo_img ) : '<span class="gm-store-nav__brand-text">Giftelier</span>';
        ?>
    </a>
    <div class="gm-store-nav__right-actions">
        <?php if ( is_user_logged_in() ) : ?>
            <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'giftelier-overview' ) ); ?>" class="gm-snav-btn gm-snav-btn--ghost">My Dashboard</a>
        <?php else : ?>
            <a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="gm-snav-btn gm-snav-btn--primary">Login / Sign Up</a>
        <?php endif; ?>
    </div>
</nav>

<!-- ── Segment tab bar ─────────────────────────────────────────── -->
<div class="gm-segment-bar">
    <div class="gm-segment-bar__inner">
        <ul class="gm-segment-tabs" role="tablist">
            <?php foreach ( $segments as $key => $label ) :
                $is_active = ( $key === $active_segment );
                $tab_url   = add_query_arg( array_filter( [
                    'segment'      => $key ?: null,
                    'cat'          => $active_cat ?: null,
                    'filter'       => $active_special ?: null,
                    'store_search' => $search_query ?: null,
                ] ), $listing_url );
            ?>
            <li role="presentation">
                <a href="<?php echo esc_url( $tab_url ); ?>"
                   class="gm-segment-tab<?php echo $is_active ? ' is-active' : ''; ?>">
                    <?php echo esc_html( $label ); ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <form class="gm-store-search" method="get" action="<?php echo esc_url( $listing_url ); ?>">
            <?php if ( $active_segment ) : ?><input type="hidden" name="segment" value="<?php echo esc_attr( $active_segment ); ?>"><?php endif; ?>
            <?php if ( $active_cat    ) : ?><input type="hidden" name="cat"     value="<?php echo esc_attr( $active_cat ); ?>"><?php endif; ?>
            <?php if ( $active_special) : ?><input type="hidden" name="filter"  value="<?php echo esc_attr( $active_special ); ?>"><?php endif; ?>
            <input type="text" name="store_search" class="gm-store-search__input"
                   value="<?php echo esc_attr( $search_query ); ?>" placeholder="Search vendors…" />
            <button type="submit" class="gm-store-search__btn" aria-label="Search">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </button>
        </form>
    </div>
</div>

<!-- ── Two-column layout: sidebar + grid ───────────────────────── -->
<div class="gm-store-page-wrap">

    <!-- Left sidebar -->
    <aside class="gm-store-sidebar">

        <!-- Special filters -->
        <div class="gm-sidebar-group">
            <span class="gm-sidebar-group__label">Browse</span>
            <ul class="gm-sidebar-nav">
                <?php foreach ( $left_nav_specials as $key => $item ) :
                    $is_active = ( $key === '' ) ? ( ! $active_cat && ! $active_special ) : ( $active_special === $key );
                    $url = add_query_arg( array_filter( [
                        'segment' => $active_segment ?: null,
                        'filter'  => $key ?: null,
                    ] ), $listing_url );
                ?>
                <li>
                    <a href="<?php echo esc_url( $url ); ?>"
                       class="gm-sidebar-nav__item<?php echo $is_active ? ' is-active' : ''; ?>">
                        <span class="gm-sidebar-nav__icon"><?php echo $item['icon']; ?></span>
                        <?php echo esc_html( $item['label'] ); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Gift categories -->
        <div class="gm-sidebar-group">
            <span class="gm-sidebar-group__label">Gift Categories</span>
            <ul class="gm-sidebar-nav">
                <?php
                $has_wc_cats = ! empty( $wc_categories ) && ! is_wp_error( $wc_categories );
                if ( $has_wc_cats ) :
                    foreach ( $wc_categories as $term ) :
                        $is_active = ( $active_cat === $term->slug );
                        $url = add_query_arg( array_filter( [
                            'segment' => $active_segment ?: null,
                            'cat'     => $is_active ? null : $term->slug,
                        ] ), $listing_url );
                ?>
                <li>
                    <a href="<?php echo esc_url( $url ); ?>"
                       class="gm-sidebar-nav__item<?php echo $is_active ? ' is-active' : ''; ?>">
                        <span class="gm-sidebar-nav__icon">🎁</span>
                        <?php echo esc_html( $term->name ); ?>
                        <?php if ( $term->count ) : ?>
                        <span class="gm-sidebar-nav__count"><?php echo (int) $term->count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach;
                else :
                    // Show fallback list
                    foreach ( $fallback_cats as $fcat ) :
                        $is_active = ( $active_cat === $fcat['slug'] );
                        $url = add_query_arg( array_filter( [
                            'segment' => $active_segment ?: null,
                            'cat'     => $is_active ? null : $fcat['slug'],
                        ] ), $listing_url );
                ?>
                <li>
                    <a href="<?php echo esc_url( $url ); ?>"
                       class="gm-sidebar-nav__item<?php echo $is_active ? ' is-active' : ''; ?>">
                        <span class="gm-sidebar-nav__icon"><?php echo $fcat['icon']; ?></span>
                        <?php echo esc_html( $fcat['name'] ); ?>
                    </a>
                </li>
                <?php endforeach;
                endif; ?>
            </ul>
        </div>

    </aside>

    <!-- Main vendor grid -->
    <main class="gm-store-main">

        <?php if ( $active_cat || $active_special || $search_query ) : ?>
        <div class="gm-store-listing__meta">
            <span class="gm-store-listing__count">
                <?php printf( '<strong>%d</strong> vendor%s', $total_vendors, $total_vendors !== 1 ? 's' : '' ); ?>
                <?php if ( $active_cat ) {
                    $term_obj = get_term_by( 'slug', $active_cat, 'product_cat' );
                    if ( $term_obj ) echo ' in <strong>' . esc_html( $term_obj->name ) . '</strong>';
                } ?>
                <?php if ( $active_special && isset( $left_nav_specials[ $active_special ] ) ) {
                    echo ' · ' . esc_html( $left_nav_specials[ $active_special ]['label'] );
                } ?>
            </span>
            <a href="<?php echo esc_url( add_query_arg( array_filter( ['segment' => $active_segment ?: null] ), $listing_url ) ); ?>"
               class="gm-store-listing__clear">Clear filter ×</a>
        </div>
        <?php endif; ?>

        <?php if ( $vendor_users ) : ?>
        <div class="gm-vendor-grid">
            <?php foreach ( $vendor_users as $user ) :
                $vendor    = dokan()->vendor->get( $user->ID );
                $store_name = $vendor->get_shop_name() ?: $user->display_name;
                $store_url  = $vendor->get_shop_url();
                $logo       = $vendor->get_avatar();
                $banner     = $vendor->get_banner();
                $tagline    = get_user_meta( $user->ID, 'gm_store_tagline', true );
                $rating     = $vendor->get_rating();
                $vsegments  = array_filter( (array) get_user_meta( $user->ID, 'gm_vendor_segment', true ) );
                $seg_labels = GM_Vendor_Dashboard::SEGMENTS;
            ?>
            <a href="<?php echo esc_url( $store_url ); ?>" class="gm-vendor-card">
                <div class="gm-vendor-card__banner">
                    <?php if ( $banner ) : ?>
                        <img src="<?php echo esc_url( is_array( $banner ) ? $banner[0] : $banner ); ?>"
                             alt="<?php echo esc_attr( $store_name ); ?>" loading="lazy" />
                    <?php else : ?>
                        <div class="gm-vendor-card__banner-placeholder"></div>
                    <?php endif; ?>
                    <?php if ( $logo ) : ?>
                    <div class="gm-vendor-card__logo-wrap">
                        <img src="<?php echo esc_url( $logo ); ?>"
                             alt="<?php echo esc_attr( $store_name ); ?>"
                             class="gm-vendor-card__logo" loading="lazy" />
                    </div>
                    <?php endif; ?>
                </div>
                <div class="gm-vendor-card__body">
                    <h3 class="gm-vendor-card__name"><?php echo esc_html( $store_name ); ?></h3>
                    <?php if ( $tagline ) : ?>
                    <p class="gm-vendor-card__tagline"><?php echo esc_html( $tagline ); ?></p>
                    <?php endif; ?>
                    <?php if ( ! empty( $rating['count'] ) ) : ?>
                    <div class="gm-vendor-card__rating">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="#F59E0B" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        <span><?php echo number_format( (float) $rating['rating'], 1 ); ?></span>
                        <span class="gm-vendor-card__rating-count">(<?php echo (int) $rating['count']; ?>)</span>
                    </div>
                    <?php endif; ?>
                    <?php if ( $vsegments ) : ?>
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
                    <span class="gm-vendor-card__cta">Visit Store →</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if ( $total_pages > 1 ) : ?>
        <div class="gm-store-listing__pagination">
            <?php for ( $p = 1; $p <= $total_pages; $p++ ) :
                $page_url = add_query_arg( array_filter( [
                    'pno'          => $p > 1 ? $p : null,
                    'segment'      => $active_segment ?: null,
                    'cat'          => $active_cat ?: null,
                    'filter'       => $active_special ?: null,
                    'store_search' => $search_query ?: null,
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
            <p><?php echo ( $active_cat || $active_segment ) ? 'No vendors match this filter yet.' : 'No vendors available right now.'; ?></p>
            <?php if ( $active_cat || $active_segment ) : ?>
            <a href="<?php echo esc_url( $listing_url ); ?>" class="gm-snav-btn gm-snav-btn--primary" style="margin-top:8px">Browse all vendors</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </main>
</div>
