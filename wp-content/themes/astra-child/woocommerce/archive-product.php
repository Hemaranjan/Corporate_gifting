<?php
/**
 * Giftelier — Shop / product archive.
 *
 * /shop/              → Amazon-style discovery homepage
 * /product-category/  → Three-column product grid (sidebar + products + cockpit)
 */
defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper',     10 );
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb',                 20 );
remove_action( 'woocommerce_after_main_content',  'woocommerce_output_content_wrapper_end', 10 );
remove_action( 'woocommerce_sidebar',             'woocommerce_get_sidebar',                10 );

do_action( 'woocommerce_before_main_content' ); // structured data only

/* Active filter params — shared by both branches */
$active_industry   = sanitize_key( wp_unslash( $_GET['industry'] ?? '' ) );
$active_program    = sanitize_key( wp_unslash( $_GET['program']  ?? '' ) );
$active_cat_filter = sanitize_key( wp_unslash( $_GET['cat']      ?? '' ) );
$browse_mode       = sanitize_key( wp_unslash( $_GET['browse']   ?? '' ) ); // 'vendors' for inline vendor grid
$vendor_slug       = sanitize_text_field( wp_unslash( $_GET['vendor']   ?? '' ) ); // user_nicename of selected vendor
$has_filter_params = (bool) ( $active_industry || $active_program || $active_cat_filter || $browse_mode || $vendor_slug );

/* ═══════════════════════════════════════════════════════════════
   DISCOVERY HOMEPAGE — main /shop/ page (no active filters)
   ═══════════════════════════════════════════════════════════════ */
if ( is_shop() && ! $has_filter_params ) :

$shop_url = get_permalink( wc_get_page_id( 'shop' ) );

// ── Zone 2: Quick-pick cards ───────────────────────────────────
$quick_picks = [
    [
        'title'    => 'New Arrivals',
        'products' => wc_get_products( [ 'status' => 'publish', 'limit' => 4, 'orderby' => 'date', 'order' => 'DESC' ] ),
        'link'     => add_query_arg( 'orderby', 'date', $shop_url ),
    ],
    [
        'title'    => 'Best Sellers',
        'products' => wc_get_products( [ 'status' => 'publish', 'limit' => 4, 'orderby' => 'rating', 'order' => 'DESC' ] ),
        'link'     => add_query_arg( 'orderby', 'rating', $shop_url ),
    ],
    [
        'title'    => 'Trending This Week',
        'products' => wc_get_products( [ 'status' => 'publish', 'limit' => 4, 'orderby' => 'popularity', 'order' => 'DESC' ] ),
        'link'     => add_query_arg( 'orderby', 'popularity', $shop_url ),
    ],
    [
        'title'    => 'Hamper Collection',
        'products' => wc_get_products( [ 'status' => 'publish', 'limit' => 4, 'category' => [ 'hamper' ] ] ),
        'link'     => home_url( '/product-category/hamper/' ),
    ],
];

// ── Zone 3–7: Industry strips (query by product tag) ──────────
$industries = [
    [
        'label'   => 'Corporate Gifting',
        'icon'    => '🏢',
        'color'   => '#5733a2',
        'bg'      => '#f3f0fa',
        'tag'     => 'corporate',
        'see_all' => home_url( '/product-tag/corporate/' ),
    ],
    [
        'label'   => 'Schools & Education',
        'icon'    => '🏫',
        'color'   => '#0e7490',
        'bg'      => '#ecfeff',
        'tag'     => 'school',
        'see_all' => home_url( '/product-tag/school/' ),
    ],
    [
        'label'   => 'Weddings & Events',
        'icon'    => '💒',
        'color'   => '#be185d',
        'bg'      => '#fdf2f8',
        'tag'     => 'wedding',
        'see_all' => home_url( '/product-tag/wedding/' ),
    ],
    [
        'label'   => 'Healthcare',
        'icon'    => '🏥',
        'color'   => '#0f766e',
        'bg'      => '#f0fdfa',
        'tag'     => 'hospitals',
        'see_all' => home_url( '/product-tag/hospitals/' ),
    ],
    [
        'label'   => 'Construction & Real Estate',
        'icon'    => '🏗️',
        'color'   => '#b45309',
        'bg'      => '#fffbeb',
        'tag'     => 'construction',
        'see_all' => home_url( '/product-tag/construction/' ),
    ],
];

// Pre-fetch products for each industry
foreach ( $industries as &$ind ) {
    $ind['products'] = wc_get_products( [
        'status'  => 'publish',
        'limit'   => 8,
        'tag'     => [ $ind['tag'] ],
        'orderby' => 'date',
        'order'   => 'DESC',
    ] );
}
unset( $ind );
?>

<div class="gm-homepage">

    <!-- ── Zone 1: Hero ─────────────────────────────────────── -->
    <div class="gm-hp-hero">
        <div class="gm-hp-hero__inner">
            <h1 class="gm-hp-hero__title">Gifts for every occasion</h1>
            <p class="gm-hp-hero__sub">Explore curated collections for every industry and milestone</p>
            <a href="<?php echo esc_url( add_query_arg( 'orderby', 'date', $shop_url ) ); ?>"
               class="gm-hp-hero__cta">Browse all gifts</a>
        </div>
    </div>

    <!-- ── Zone 2: Quick-picks ──────────────────────────────── -->
    <div class="gm-hp-wrap">

        <div class="gm-hp-quickpicks">
            <?php foreach ( $quick_picks as $qp ) : ?>
            <div class="gm-hp-qp-card">
                <div class="gm-hp-qp-card__header">
                    <span class="gm-hp-qp-card__title"><?php echo esc_html( $qp['title'] ); ?></span>
                    <a href="<?php echo esc_url( $qp['link'] ); ?>" class="gm-hp-qp-card__more">See more →</a>
                </div>
                <div class="gm-hp-qp-card__grid">
                    <?php foreach ( array_slice( $qp['products'], 0, 4 ) as $prod ) :
                        $img_id  = $prod->get_image_id();
                        $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' );
                    ?>
                    <a href="<?php echo esc_url( get_permalink( $prod->get_id() ) ); ?>" class="gm-hp-qp-thumb" target="_blank" rel="noopener noreferrer">
                        <img src="<?php echo esc_url( $img_url ); ?>"
                             alt="<?php echo esc_attr( $prod->get_name() ); ?>" loading="lazy" />
                    </a>
                    <?php endforeach; ?>
                    <?php if ( empty( $qp['products'] ) ) : ?>
                    <div class="gm-hp-qp-empty">No products yet</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ── Zones 3–7: Industry strips ──────────────────── -->
        <?php foreach ( $industries as $ind ) : ?>
        <div class="gm-hp-section" style="--ind-color:<?php echo esc_attr( $ind['color'] ); ?>;--ind-bg:<?php echo esc_attr( $ind['bg'] ); ?>">
            <div class="gm-hp-section__header">
                <span class="gm-hp-section__title">
                    <?php echo $ind['icon']; ?>
                    <?php echo esc_html( $ind['label'] ); ?>
                </span>
                <a href="<?php echo esc_url( $ind['see_all'] ); ?>" class="gm-hp-section__see-all">See all →</a>
            </div>

            <div class="gm-hp-strip">
                <?php if ( ! empty( $ind['products'] ) ) : ?>
                    <?php foreach ( $ind['products'] as $prod ) :
                        $img_id  = $prod->get_image_id();
                        $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
                        $price   = $prod->get_price_html();
                        $cart_url = $prod->is_purchasable() && $prod->is_in_stock()
                            ? esc_url( wc_get_cart_url() . '?add-to-cart=' . $prod->get_id() )
                            : esc_url( get_permalink( $prod->get_id() ) );
                        $cart_label = $prod->is_purchasable() && $prod->is_in_stock() ? 'Add to cart' : 'View product';
                    ?>
                    <div class="gm-hp-card">
                        <a href="<?php echo esc_url( get_permalink( $prod->get_id() ) ); ?>" class="gm-hp-card__img-wrap" target="_blank" rel="noopener noreferrer">
                            <img src="<?php echo esc_url( $img_url ); ?>"
                                 alt="<?php echo esc_attr( $prod->get_name() ); ?>"
                                 class="gm-hp-card__img" loading="lazy" />
                        </a>
                        <div class="gm-hp-card__body">
                            <a href="<?php echo esc_url( get_permalink( $prod->get_id() ) ); ?>" class="gm-hp-card__name" target="_blank" rel="noopener noreferrer">
                                <?php echo esc_html( $prod->get_name() ); ?>
                            </a>
                            <div class="gm-hp-card__price"><?php echo wp_kses_post( $price ); ?></div>
                            <a href="<?php echo $cart_url; ?>" class="gm-hp-card__cta">
                                <?php echo esc_html( $cart_label ); ?>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="gm-hp-strip__empty">
                        <span><?php echo $ind['icon']; ?></span>
                        <a href="<?php echo esc_url( $ind['see_all'] ); ?>">
                            Browse <?php echo esc_html( $ind['label'] ); ?> →
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

    </div><!-- .gm-hp-wrap -->

</div><!-- .gm-homepage -->

<?php

/* ═══════════════════════════════════════════════════════════════
   PRODUCT GRID — category / tag / search pages
   ═══════════════════════════════════════════════════════════════ */
else :

$is_filtered_shop = is_shop(); // true when routed here via ?industry/program/cat params
$ck_user_id   = get_current_user_id();
$ck_segment   = is_user_logged_in() ? get_user_meta( $ck_user_id, 'gm_customer_segment', true ) : '';
$show_cockpit = $ck_segment && class_exists( 'GM_Cockpit' ) && isset( GM_Cockpit::CONFIG[ $ck_segment ] );

$shop_url        = get_permalink( wc_get_page_id( 'shop' ) );
$active_cat_slug = is_product_category() ? get_queried_object()->slug : '';
$current_orderby = sanitize_key( $_GET['orderby'] ?? '' );
// $active_industry / $active_program / $active_cat_filter already set above

/* Pre-compute context params for links that must preserve the active filters */
$ctx_params = array_filter( [
    'industry' => $active_industry,
    'program'  => $active_program,
    'cat'      => $active_cat_filter,
] );

$browse_items = [
    ''       => [ 'icon' => '🛍️', 'label' => 'All Products'    ],
    'date'   => [ 'icon' => '✨', 'label' => 'New Arrivals'     ],
    'rating' => [ 'icon' => '⭐', 'label' => 'Best Rated'       ],
    'price'  => [ 'icon' => '💰', 'label' => 'Price: Low–High'  ],
];

$vendors_url = add_query_arg(
    array_filter( [
        'industry' => $active_industry,
        'program'  => $active_program,
        'browse'   => 'vendors',
    ] ),
    $shop_url
);
?>

<div class="gm-shop-wrap<?php echo $show_cockpit ? ' gm-shop-wrap--with-cockpit' : ''; ?>">

    <aside class="gm-store-sidebar">

        <div class="gm-sidebar-group">
            <span class="gm-sidebar-group__label">Browse</span>
            <ul class="gm-sidebar-nav">
                <?php $browse_i = 0; foreach ( $browse_items as $orderby => $item ) :
                    if ( $orderby === '' ) {
                        if ( $is_filtered_shop ) {
                            $base      = array_filter( [ 'industry' => $active_industry, 'program' => $active_program ] );
                            $is_active = ( ! $active_cat_filter && ! $current_orderby && $browse_mode !== 'vendors' );
                            $url       = $base ? add_query_arg( $base, $shop_url ) : $shop_url;
                        } else {
                            $is_active = ( ! $active_cat_slug && ! $current_orderby && ! $has_filter_params );
                            $url       = $shop_url;
                        }
                    } elseif ( $is_filtered_shop ) {
                        // On filtered shop: preserve industry/program/cat, just change ordering
                        $is_active = ( $current_orderby === $orderby );
                        $url       = add_query_arg( array_merge( $ctx_params, [ 'orderby' => $orderby ] ), $shop_url );
                    } else {
                        $is_active = ( $current_orderby === $orderby && ! $active_cat_slug );
                        $url       = add_query_arg( 'orderby', $orderby, $shop_url );
                    }
                ?>
                <li>
                    <a href="<?php echo esc_url( $url ); ?>"
                       class="gm-sidebar-nav__item<?php echo $is_active ? ' is-active' : ''; ?>">
                        <span class="gm-sidebar-nav__icon"><?php echo $item['icon']; ?></span>
                        <?php echo esc_html( $item['label'] ); ?>
                    </a>
                </li>
                <?php if ( $browse_i === 0 ) : ?>
                <li>
                    <a href="<?php echo esc_url( $vendors_url ); ?>"
                       class="gm-sidebar-nav__item<?php echo $browse_mode === 'vendors' ? ' is-active' : ''; ?>">
                        <span class="gm-sidebar-nav__icon">🏪</span>
                        Browse by Vendors
                    </a>
                </li>
                <?php endif; $browse_i++; endforeach; ?>
            </ul>
        </div>

        <?php
        $gift_categories = [
            'hamper'             => [ 'label' => 'Hamper',               'icon' => '🧺' ],
            'premium-gifts'      => [ 'label' => 'Premium Gifts',        'icon' => '✨' ],
            'personalized-gifts' => [ 'label' => 'Personalized Gifts',   'icon' => '🎨' ],
            'eco-friendly-gifts' => [ 'label' => 'Eco-Friendly Gifts',   'icon' => '🌿' ],
            'handcrafted-gifts'  => [ 'label' => 'Handcrafted Gifts',    'icon' => '🤲' ],
            'local-artisan-gifts'=> [ 'label' => 'Local Artisan Gifts',  'icon' => '🏺' ],
        ];
        ?>
        <div class="gm-sidebar-group">
            <span class="gm-sidebar-group__label">Gift Categories</span>
            <ul class="gm-sidebar-nav">
                <?php foreach ( $gift_categories as $slug => $cat ) :
                    $is_active = ( $active_cat_slug === $slug || $active_cat_filter === $slug );
                    $term      = get_term_by( 'slug', $slug, 'product_cat' );
                    $count     = $term ? (int) $term->count : 0;

                    if ( $is_filtered_shop || $active_industry || $active_program || is_tax( 'product_tag' ) ) {
                        /* Combined filter mode — build shop URL preserving industry+program, toggling cat */
                        $ind = $active_industry ?: ( is_tax( 'product_tag' ) ? get_queried_object()->slug : '' );
                        $base = array_filter( [ 'industry' => $ind, 'program' => $active_program ] );
                        $url  = ( $active_cat_filter === $slug )
                            ? add_query_arg( $base ?: [], $shop_url )           // deselect cat
                            : add_query_arg( array_merge( $base, [ 'cat' => $slug ] ), $shop_url ); // select cat
                    } else {
                        $url = $term ? get_term_link( $term ) : home_url( '/product-category/' . $slug . '/' );
                    }
                ?>
                <li>
                    <a href="<?php echo esc_url( $url ); ?>"
                       class="gm-sidebar-nav__item<?php echo $is_active ? ' is-active' : ''; ?>">
                        <span class="gm-sidebar-nav__icon"><?php echo $cat['icon']; ?></span>
                        <?php echo esc_html( $cat['label'] ); ?>
                        <?php if ( $count ) : ?>
                        <span class="gm-sidebar-nav__count"><?php echo $count; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

    </aside>

    <main class="gm-shop-main">

        <?php if ( $browse_mode === 'vendors' && $vendor_slug ) :
            // ── Inline vendor store ──────────────────────────────────
            $vend_user = get_user_by( 'slug', $vendor_slug );
            $back_url  = add_query_arg(
                array_filter( [ 'industry' => $active_industry, 'program' => $active_program, 'browse' => 'vendors' ] ),
                $shop_url
            );
        ?>
            <div class="gm-inline-store">
                <a href="<?php echo esc_url( $back_url ); ?>" class="gm-inline-store__back">
                    ← Back to vendors
                </a>

                <?php if ( $vend_user ) :
                    $vi_vendor  = dokan()->vendor->get( $vend_user->ID );
                    $vi_name    = $vi_vendor->get_shop_name() ?: $vend_user->display_name;
                    $vi_banner  = $vi_vendor->get_banner();
                    $vi_logo    = $vi_vendor->get_avatar();
                    $vi_tagline = get_user_meta( $vend_user->ID, 'gm_store_tagline',     true );
                    $vi_desc    = get_user_meta( $vend_user->ID, 'gm_store_description', true );
                    $vi_rating  = dokan_get_readable_seller_rating( $vend_user->ID );
                ?>

                <div class="gm-store-hero">
                    <div class="gm-store-hero__info">
                        <?php if ( $vi_logo ) : ?>
                        <img src="<?php echo esc_url( $vi_logo ); ?>"
                             alt="<?php echo esc_attr( $vi_name ); ?>"
                             class="gm-store-hero__logo" />
                        <?php endif; ?>
                        <h2 class="gm-store-hero__name"><?php echo esc_html( $vi_name ); ?></h2>
                        <?php if ( $vi_tagline ) : ?>
                        <p class="gm-store-hero__tagline"><?php echo esc_html( $vi_tagline ); ?></p>
                        <?php endif; ?>
                        <?php if ( $vi_desc ) : ?>
                        <p class="gm-store-hero__desc"><?php echo nl2br( esc_html( $vi_desc ) ); ?></p>
                        <?php endif; ?>
                        <?php if ( $vi_rating ) : ?>
                        <div class="gm-store-hero__rating">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="#e8a045" stroke="none" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <?php echo wp_kses_post( $vi_rating ); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ( $vi_banner ) : ?>
                    <div class="gm-store-hero__banner">
                        <img src="<?php echo esc_url( is_array( $vi_banner ) ? $vi_banner[0] : $vi_banner ); ?>"
                             alt="<?php echo esc_attr( $vi_name ); ?>" />
                    </div>
                    <?php endif; ?>
                </div>

                <?php
                $vi_query = new WP_Query( [
                    'post_type'      => 'product',
                    'post_status'    => 'publish',
                    'posts_per_page' => 12,
                    'author'         => $vend_user->ID,
                ] );
                if ( $vi_query->have_posts() ) :
                    woocommerce_product_loop_start();
                    while ( $vi_query->have_posts() ) :
                        $vi_query->the_post();
                        wc_get_template_part( 'content', 'product' );
                    endwhile;
                    woocommerce_product_loop_end();
                    wp_reset_postdata();
                else : ?>
                <p style="padding:24px 0;color:#6b7280;">This vendor has no products yet.</p>
                <?php endif; ?>

                <?php else : ?>
                <p style="padding:24px 0;color:#6b7280;">Vendor not found.</p>
                <?php endif; ?>
            </div><!-- .gm-inline-store -->

        <?php elseif ( $browse_mode === 'vendors' ) :
            // ── Inline vendor grid ───────────────────────────────────
            $v_args = [
                'role__in' => [ 'seller', 'vendor' ],
                'number'   => 24,
                'orderby'  => 'display_name',
                'order'    => 'ASC',
            ];
            if ( $active_industry ) {
                $v_args['meta_query'] = [[
                    'key'     => 'gm_vendor_segment',
                    'value'   => '"' . $active_industry . '"',
                    'compare' => 'LIKE',
                ]];
            }
            $v_query   = new WP_User_Query( $v_args );
            $v_vendors = $v_query->get_results();
        ?>
            <?php if ( $v_vendors ) : ?>
            <div class="gm-vendor-grid">
                <?php foreach ( $v_vendors as $v_user ) :
                    $vendor         = dokan()->vendor->get( $v_user->ID );
                    $store_name     = $vendor->get_shop_name() ?: $v_user->display_name;
                    $inline_vnd_url = add_query_arg(
                        array_filter( [ 'industry' => $active_industry, 'program' => $active_program, 'browse' => 'vendors', 'vendor' => $v_user->user_nicename ] ),
                        $shop_url
                    );
                    $logo           = $vendor->get_avatar();
                    $banner         = $vendor->get_banner();
                    $tagline        = get_user_meta( $v_user->ID, 'gm_store_tagline', true );
                    $rating         = $vendor->get_rating();
                    $vsegments      = array_filter( (array) get_user_meta( $v_user->ID, 'gm_vendor_segment', true ) );
                    $seg_labels     = class_exists( 'GM_Vendor_Dashboard' ) ? GM_Vendor_Dashboard::SEGMENTS : [];
                ?>
                <a href="<?php echo esc_url( $inline_vnd_url ); ?>" class="gm-vendor-card">
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
                        <span class="gm-vendor-card__cta">Browse Store →</span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else : ?>
            <div class="gm-store-listing__empty">
                <div class="gm-store-listing__empty-icon">🏪</div>
                <h3>No vendors found</h3>
                <p><?php echo $active_industry ? 'No vendors in this industry yet.' : 'No vendors available right now.'; ?></p>
            </div>
            <?php endif; ?>

        <?php else : ?>
            <?php if ( woocommerce_product_loop() ) : ?>
                <?php do_action( 'woocommerce_before_shop_loop' ); ?>
                <?php woocommerce_product_loop_start(); ?>
                <?php while ( have_posts() ) : the_post(); ?>
                    <?php do_action( 'woocommerce_shop_loop' ); ?>
                    <?php wc_get_template_part( 'content', 'product' ); ?>
                <?php endwhile; ?>
                <?php woocommerce_product_loop_end(); ?>
                <?php do_action( 'woocommerce_after_shop_loop' ); ?>
            <?php else : ?>
                <?php do_action( 'woocommerce_no_products_found' ); ?>
            <?php endif; ?>
        <?php endif; ?>

    </main>

    <?php if ( $show_cockpit ) :
        $ck_config = GM_Cockpit::CONFIG[ $ck_segment ];
        $l1_items  = GM_Cockpit::get_l1_items( $ck_user_id, $ck_segment );
        [ $active_l1, $active_l2 ] = GM_Cockpit::get_active();
        $segment   = $ck_segment;
        $context   = 'browse';
    ?>
    <div class="gm-shop-cockpit">
        <?php include GM_PATH . 'templates/customer/cockpit-panel.php'; ?>
    </div>
    <?php endif; ?>

</div><!-- .gm-shop-wrap -->

<?php
if ( $show_cockpit ) :
    $ck_config = GM_Cockpit::CONFIG[ $ck_segment ];
?>
<div class="gm-cockpit-mobile-bar" id="gm-cockpit-mobile-bar" role="button" aria-label="Open shopping cockpit">
    <span id="gm-ck-mobile-l1">Select <?php echo esc_html( $ck_config['l1_label'] ); ?>…</span>
    <div class="gm-cockpit-mobile-bar__right">
        <span id="gm-ck-mobile-remaining"></span>
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="18 15 12 9 6 15"/>
        </svg>
    </div>
</div>
<div class="gm-cockpit-drawer" id="gm-cockpit-drawer">
    <div class="gm-cockpit-drawer__backdrop"></div>
    <div class="gm-cockpit-drawer__panel">
        <div class="gm-cockpit-body" style="padding:20px">
            <p style="text-align:center;font-size:13px;color:#9ca3af;margin:0 0 12px">
                Select your <?php echo esc_html( strtolower( $ck_config['l1_label'] ) ); ?> to track budget
            </p>
            <a href="#" onclick="document.getElementById('gm-cockpit-drawer').classList.remove('gm-drawer--open');return false;"
               style="display:block;text-align:center;font-size:13px;color:#5733a2;font-weight:600;text-decoration:none;margin-top:8px">
                Close ×
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; // end product grid branch ?>

<?php
do_action( 'woocommerce_after_main_content' );
do_action( 'woocommerce_sidebar' );
get_footer( 'shop' );
?>
