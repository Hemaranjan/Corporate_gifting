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
?>
<div class="gm-dash">
    <div class="gm-dash-page-header">
        <h2 class="gm-dash-page-title">Browse Gifts</h2>
        <p class="gm-dash-page-sub">Discover perfect gifts from our curated vendors.</p>
    </div>

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
                    <div class="gm-product-card__price">
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
</div>
