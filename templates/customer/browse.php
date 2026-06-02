<?php
/**
 * Customer Dashboard — Browse Products
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// Search & filter params
$search   = isset( $_GET['gm_s'] )   ? sanitize_text_field( wp_unslash( $_GET['gm_s'] ) )   : '';
$category = isset( $_GET['gm_cat'] ) ? absint( $_GET['gm_cat'] ) : 0;
$vendor   = isset( $_GET['gm_v'] )   ? absint( $_GET['gm_v'] )   : 0;
$sort     = isset( $_GET['gm_sort'] ) ? sanitize_key( $_GET['gm_sort'] ) : 'popularity';

// Build product query
$args = [
    'post_type'      => 'product',
    'posts_per_page' => 12,
    'post_status'    => 'publish',
];

if ( $search ) {
    $args['s'] = $search;
}
if ( $category ) {
    $args['tax_query'] = [ [ 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $category ] ];
}
if ( $vendor ) {
    $args['author'] = $vendor;
}

switch ( $sort ) {
    case 'price_asc':  $args['orderby'] = 'meta_value_num'; $args['meta_key'] = '_price'; $args['order'] = 'ASC';  break;
    case 'price_desc': $args['orderby'] = 'meta_value_num'; $args['meta_key'] = '_price'; $args['order'] = 'DESC'; break;
    case 'newest':     $args['orderby'] = 'date';           $args['order']    = 'DESC';   break;
    default:           $args['orderby'] = 'comment_count';  $args['order']    = 'DESC';   break; // popularity
}

$product_query = new WP_Query( $args );

// Product categories for filter
$categories = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 20 ] );

// Vendors for filter (Dokan sellers)
$vendors = [];
if ( function_exists( 'dokan_get_sellers' ) ) {
    $seller_result = dokan_get_sellers( [ 'status' => 'approved', 'number' => 50 ] );
    $vendors = $seller_result['users'] ?? [];
}

// Recommended products (based on user's previous categories)
$user_id    = get_current_user_id();
$past_cats  = [];
$past_orders = wc_get_orders( [ 'customer' => $user_id, 'limit' => 5, 'status' => [ 'wc-completed' ] ] );
foreach ( $past_orders as $order ) {
    foreach ( $order->get_items() as $item ) {
        $terms = get_the_terms( $item->get_product_id(), 'product_cat' );
        if ( $terms ) foreach ( $terms as $t ) $past_cats[] = $t->term_id;
    }
}
$past_cats = array_unique( $past_cats );
$browse_url = wc_get_account_endpoint_url( 'giftelier-browse' );

// ── Cockpit bootstrap ──────────────────────────────────────────────
$ck_segment  = get_user_meta( $user_id, 'gm_customer_segment', true );
$has_cockpit = $ck_segment && class_exists( 'GM_Cockpit' ) && isset( GM_Cockpit::CONFIG[ $ck_segment ] );

if ( $has_cockpit ) {
    $ck_cfg      = GM_Cockpit::CONFIG[ $ck_segment ];
    $ck_l1_items = GM_Cockpit::get_l1_items( $user_id, $ck_segment );
    [ $ck_active_l1, $ck_active_l2 ] = GM_Cockpit::get_active();

    $ck_l2_items = $ck_active_l1 ? GM_Cockpit::get_l2_items( $ck_active_l1, $user_id ) : [];

    // Segment label shown in header badge
    $ck_seg_labels = [
        'corporate'    => 'Corporate',
        'school'       => 'School',
        'wedding'      => 'Wedding',
        'hospitals'    => 'Hospital',
        'construction' => 'Construction',
    ];
    $ck_seg_label = $ck_seg_labels[ $ck_segment ] ?? ucfirst( $ck_segment );

    $budget_url = wc_get_account_endpoint_url( 'giftelier-budget' );
}
?>
<div class="gm-dash">
    <div class="gm-dash-page-header">
        <h2 class="gm-dash-page-title">Browse Gifts</h2>
        <p class="gm-dash-page-sub">Discover perfect gifts from our curated vendors.</p>
    </div>

    <?php if ( $has_cockpit ) : ?>
    <div class="gm-browse-page">
    <div class="gm-browse-main">
    <?php endif; ?>

    <!-- Search & filters -->
    <div class="gm-dash-card gm-browse-filters">
        <form method="GET" action="<?php echo esc_url( $browse_url ); ?>" class="gm-filter-form">
            <div class="gm-filter-row">
                <div class="gm-filter-search">
                    <input type="text" name="gm_s" class="gm-form-input"
                           value="<?php echo esc_attr( $search ); ?>"
                           placeholder="Search gifts…" />
                </div>
                <div class="gm-filter-selects">
                    <select name="gm_cat" class="gm-form-select">
                        <option value="">All Categories</option>
                        <?php foreach ( $categories as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat->term_id ); ?>"
                                <?php selected( $category, $cat->term_id ); ?>>
                            <?php echo esc_html( $cat->name ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ( $vendors ) : ?>
                    <select name="gm_v" class="gm-form-select">
                        <option value="">All Vendors</option>
                        <?php foreach ( $vendors as $v ) :
                            $info = dokan_get_store_info( $v->ID );
                            $name = $info['store_name'] ?? $v->display_name;
                        ?>
                        <option value="<?php echo esc_attr( $v->ID ); ?>"
                                <?php selected( $vendor, $v->ID ); ?>>
                            <?php echo esc_html( $name ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <select name="gm_sort" class="gm-form-select">
                        <option value="popularity" <?php selected( $sort, 'popularity' ); ?>>Most Popular</option>
                        <option value="newest"     <?php selected( $sort, 'newest'     ); ?>>Newest</option>
                        <option value="price_asc"  <?php selected( $sort, 'price_asc'  ); ?>>Price: Low→High</option>
                        <option value="price_desc" <?php selected( $sort, 'price_desc' ); ?>>Price: High→Low</option>
                    </select>
                    <button type="submit" class="gm-btn gm-btn--primary">Search</button>
                    <?php if ( $search || $category || $vendor ) : ?>
                    <a href="<?php echo esc_url( $browse_url ); ?>" class="gm-btn gm-btn--ghost-rose">Clear</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Recommendations (if user has history) -->
    <?php if ( $past_cats && ! $search && ! $category ) :
        $rec_query = new WP_Query( [
            'post_type'      => 'product',
            'posts_per_page' => 4,
            'post_status'    => 'publish',
            'orderby'        => 'rand',
            'tax_query'      => [ [ 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $past_cats ] ],
        ] );
        if ( $rec_query->have_posts() ) :
    ?>
    <div class="gm-dash-card">
        <div class="gm-dash-card__header">
            <h3 class="gm-dash-card__title">✨ Recommended for You</h3>
            <span class="gm-dash-card__meta">Based on your past purchases</span>
        </div>
        <div class="gm-product-grid gm-product-grid--4">
            <?php while ( $rec_query->have_posts() ) : $rec_query->the_post();
                $product  = wc_get_product( get_the_ID() );
                if ( ! $product ) continue;
            ?>
            <?php include __DIR__ . '/partials/product-card.php'; ?>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
    </div>
    <?php endif; endif; ?>

    <!-- Product grid -->
    <div class="gm-dash-card">
        <div class="gm-dash-card__header">
            <h3 class="gm-dash-card__title">
                <?php echo $search ? 'Results for "' . esc_html( $search ) . '"' : 'All Gifts'; ?>
            </h3>
            <span class="gm-dash-card__meta"><?php echo $product_query->found_posts; ?> products</span>
        </div>
        <?php if ( $product_query->have_posts() ) : ?>
        <div class="gm-product-grid">
            <?php while ( $product_query->have_posts() ) : $product_query->the_post();
                $product = wc_get_product( get_the_ID() );
                if ( ! $product ) continue;
            ?>
            <div class="gm-product-card" data-product-id="<?php echo esc_attr( get_the_ID() ); ?>">
                <a href="<?php the_permalink(); ?>" class="gm-product-card__img-link">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <?php the_post_thumbnail( 'woocommerce_thumbnail', [ 'class' => 'gm-product-card__img' ] ); ?>
                    <?php else : ?>
                        <div class="gm-product-card__img gm-product-card__img--placeholder">🎁</div>
                    <?php endif; ?>
                </a>
                <div class="gm-product-card__body">
                    <?php
                    $vendor_id   = (int) get_post_field( 'post_author', get_the_ID() );
                    $vendor_info = function_exists( 'dokan_get_store_info' ) ? dokan_get_store_info( $vendor_id ) : [];
                    $vname       = $vendor_info['store_name'] ?? '';
                    ?>
                    <?php if ( $vname ) : ?>
                    <span class="gm-product-card__vendor"><?php echo esc_html( $vname ); ?></span>
                    <?php endif; ?>
                    <h4 class="gm-product-card__name">
                        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h4>
                    <div class="gm-product-card__price" data-price="<?php echo esc_attr( (float) $product->get_price() ); ?>">
                        <?php echo wp_kses_post( $product->get_price_html() ); ?>
                    </div>
                    <div class="gm-product-card__actions">
                        <?php if ( $product->is_purchasable() && $product->is_in_stock() ) : ?>
                        <button class="gm-btn gm-btn--primary gm-btn--sm gm-add-to-cart"
                                data-product-id="<?php echo esc_attr( get_the_ID() ); ?>">
                            Add to Cart
                        </button>
                        <?php else : ?>
                        <a href="<?php the_permalink(); ?>" class="gm-btn gm-btn--outline gm-btn--sm">View</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
        <?php else : ?>
        <div class="gm-dash-empty">
            <span class="gm-dash-empty__icon">🔍</span>
            <p>No products found. <a href="<?php echo esc_url( $browse_url ); ?>">Clear filters</a> to browse all gifts.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Vendor storefronts CTA -->
    <div class="gm-dash-card gm-dash-card--soft">
        <div class="gm-cta-row">
            <div class="gm-cta-row__text">
                <h3>Explore Vendor Storefronts</h3>
                <p>Browse curated stores and book an event along with your gifts.</p>
            </div>
            <a href="<?php echo esc_url( home_url( '/stores/' ) ); ?>" class="gm-btn gm-btn--outline">All Vendors →</a>
        </div>
    </div>

    <?php if ( $has_cockpit ) : ?>
    </div><!-- /.gm-browse-main -->

    <!-- ── Shopping Cockpit (right panel) ──────────────────────────── -->
    <div class="gm-cockpit-wrap">
        <div class="gm-cockpit">

            <!-- Header -->
            <div class="gm-cockpit-header">
                <span class="gm-cockpit-title">Shopping Cockpit</span>
                <span class="gm-cockpit-seg-badge"><?php echo esc_html( $ck_seg_label ); ?></span>
            </div>

            <div class="gm-cockpit-body">

                <?php if ( $ck_l1_items ) : ?>

                <!-- L1 selector -->
                <div class="gm-cockpit-section">
                    <label class="gm-cockpit-label"><?php echo esc_html( $ck_cfg['l1_label'] ); ?></label>
                    <select id="gm-ck-l1" class="gm-cockpit-select">
                        <option value="">— Select <?php echo esc_html( $ck_cfg['l1_label'] ); ?> —</option>
                        <?php foreach ( $ck_l1_items as $l1 ) : ?>
                        <option value="<?php echo esc_attr( $l1->id ); ?>"
                                <?php selected( $ck_active_l1, (int) $l1->id ); ?>>
                            <?php echo esc_html( $l1->name ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- L2 selector -->
                <div class="gm-cockpit-section">
                    <label class="gm-cockpit-label"><?php echo esc_html( $ck_cfg['l2_label'] ); ?></label>
                    <select id="gm-ck-l2" class="gm-cockpit-select"
                            <?php echo $ck_active_l1 ? '' : 'disabled'; ?>>
                        <option value="">— Select <?php echo esc_html( $ck_cfg['l2_label'] ); ?> —</option>
                        <?php foreach ( $ck_l2_items as $l2 ) : ?>
                        <option value="<?php echo esc_attr( $l2->id ); ?>"
                                <?php selected( $ck_active_l2, (int) $l2->id ); ?>>
                            <?php echo esc_html( $l2->name ); ?>
                            — ₹<?php echo number_format( (float) $l2->allocated, 0 ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ( $ck_cfg['has_tier'] ) : ?>
                <!-- Tier selector (Construction only) -->
                <div class="gm-cockpit-section">
                    <label class="gm-cockpit-label">Contractor Tier</label>
                    <div class="gm-cockpit-tier-wrap">
                        <?php foreach ( GM_Cockpit::TIER_BANDS as $tier_key => $tier ) : ?>
                        <button type="button" class="gm-cockpit-tier-btn"
                                data-tier="<?php echo esc_attr( $tier_key ); ?>">
                            <?php echo esc_html( $tier['label'] ); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="gm-cockpit-divider"></div>

                <!-- Budget summary (populated by JS) -->
                <div id="gm-ck-summary" style="display:none">
                    <div class="gm-cockpit-summary">
                        <div class="gm-ck-row">
                            <span class="gm-ck-row__label">
                                <span class="gm-ck-dot gm-ck-dot--grey"></span> Allocated
                            </span>
                            <span class="gm-ck-row__value" id="gm-ck-allocated">—</span>
                        </div>
                        <div class="gm-ck-row">
                            <span class="gm-ck-row__label">
                                <span class="gm-ck-dot gm-ck-dot--rose"></span> Spent
                            </span>
                            <span class="gm-ck-row__value" id="gm-ck-spent">—</span>
                        </div>
                        <div class="gm-ck-row">
                            <span class="gm-ck-row__label">
                                <span class="gm-ck-dot gm-ck-dot--amber"></span> In cart
                            </span>
                            <span class="gm-ck-row__value" id="gm-ck-cart-amt">—</span>
                        </div>
                        <div class="gm-ck-row">
                            <span class="gm-ck-row__label">
                                <span class="gm-ck-dot gm-ck-dot--green"></span> Remaining
                            </span>
                            <span class="gm-ck-row__value" id="gm-ck-remaining">—</span>
                        </div>
                        <div class="gm-ck-bar-wrap">
                            <div class="gm-ck-bar-spent" id="gm-ck-bar-spent" style="width:0%"></div>
                            <div class="gm-ck-bar-cart"  id="gm-ck-bar-cart"  style="width:0%"></div>
                        </div>
                    </div>
                </div>

                <!-- Warning strip -->
                <div class="gm-cockpit-warn" id="gm-ck-warn" style="display:none"></div>

                <!-- Segment-specific extra info (guest count, project code, compliance cap) -->
                <div class="gm-cockpit-capacity" id="gm-ck-extra-info" style="display:none"></div>

                <!-- Capacity estimate -->
                <div class="gm-cockpit-capacity" id="gm-ck-capacity" style="display:none"></div>

                <!-- Cart preview -->
                <div id="gm-ck-cart-section" style="display:none">
                    <div class="gm-cockpit-divider"></div>
                    <div class="gm-cockpit-cart-header">In Cart</div>
                    <div class="gm-cockpit-cart-items" id="gm-ck-cart-items"></div>
                </div>

                <?php if ( ! $ck_active_l1 ) : ?>
                <div class="gm-cockpit-prompt">
                    Select a <?php echo esc_html( strtolower( $ck_cfg['l1_label'] ) ); ?> and
                    <?php echo esc_html( strtolower( $ck_cfg['l2_label'] ) ); ?> above to see your budget here.
                </div>
                <?php endif; ?>

                <?php else : ?>
                <!-- No L1 items yet -->
                <div class="gm-cockpit-prompt">
                    No <?php echo esc_html( strtolower( $ck_cfg['l1_label'] ) ); ?>s set up yet.<br>
                    <a href="<?php echo esc_url( $budget_url ); ?>">Set up your budget →</a>
                </div>
                <?php endif; ?>

            </div><!-- /.gm-cockpit-body -->
        </div><!-- /.gm-cockpit -->
    </div><!-- /.gm-cockpit-wrap -->

    </div><!-- /.gm-browse-page -->

    <!-- Mobile cockpit bar -->
    <div class="gm-cockpit-mobile-bar" id="gm-cockpit-mobile-bar">
        <span id="gm-ck-mobile-l1">Shopping Cockpit</span>
        <div class="gm-cockpit-mobile-bar__right">
            <span id="gm-ck-mobile-remaining"></span>
            <span>▲ Budget</span>
        </div>
    </div>

    <!-- Mobile cockpit drawer -->
    <div class="gm-cockpit-drawer" id="gm-cockpit-drawer">
        <div class="gm-cockpit-drawer__backdrop"></div>
        <div class="gm-cockpit-drawer__panel">
            <div style="padding:16px 16px 8px; font-weight:700; font-size:14px; border-bottom:1px solid #f3f4f6; margin-bottom:12px">
                Shopping Cockpit
            </div>
            <div style="padding:0 16px 16px; font-size:13px; color:#6b7280; line-height:1.6">
                <p><strong id="gm-ck-drawer-l1" style="color:#111827"></strong></p>
                <p>Remaining: <strong id="gm-ck-drawer-remaining" style="color:#5733a2">—</strong></p>
                <p style="margin-top:8px; font-size:12px">Open on a larger screen for full budget details.</p>
            </div>
        </div>
    </div>

    <?php endif; // has_cockpit ?>

</div>
