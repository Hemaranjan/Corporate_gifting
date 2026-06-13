<?php
/**
 * Giftelier — Single product page layout.
 * Layout: [ Browse sidebar ] [ Product detail + below ] [ Cockpit (if applicable) ]
 */
defined( 'ABSPATH' ) || exit;

global $product;

do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
    echo get_the_password_form();
    return;
}

// Cockpit eligibility
$ck_user_id   = get_current_user_id();
$ck_segment   = is_user_logged_in() ? get_user_meta( $ck_user_id, 'gm_customer_segment', true ) : '';
$show_cockpit = $ck_segment && class_exists( 'GM_Cockpit' ) && isset( GM_Cockpit::CONFIG[ $ck_segment ] );

$shop_url = get_permalink( wc_get_page_id( 'shop' ) );

$browse_items = [
    [ 'icon' => '🛍️', 'label' => 'All Products',     'url' => $shop_url ],
    [ 'icon' => '🏪', 'label' => 'Browse by Vendors', 'url' => add_query_arg( 'browse', 'vendors', $shop_url ) ],
    [ 'icon' => '✨', 'label' => 'New Arrivals',       'url' => add_query_arg( 'orderby', 'date',       $shop_url ) ],
    [ 'icon' => '⭐', 'label' => 'Best Rated',         'url' => add_query_arg( 'orderby', 'rating',     $shop_url ) ],
    [ 'icon' => '💰', 'label' => 'Price: Low–High',    'url' => add_query_arg( 'orderby', 'price',      $shop_url ) ],
];

$gift_categories = [
    'hamper'             => [ 'label' => 'Hamper',              'icon' => '🧺' ],
    'premium-gifts'      => [ 'label' => 'Premium Gifts',       'icon' => '✨' ],
    'personalized-gifts' => [ 'label' => 'Personalized Gifts',  'icon' => '🎨' ],
    'eco-friendly-gifts' => [ 'label' => 'Eco-Friendly Gifts',  'icon' => '🌿' ],
    'handcrafted-gifts'  => [ 'label' => 'Handcrafted Gifts',   'icon' => '🤲' ],
    'local-artisan-gifts'=> [ 'label' => 'Local Artisan Gifts', 'icon' => '🏺' ],
];
?>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( '', $product ); ?>>

    <div class="gm-shop-wrap<?php echo $show_cockpit ? ' gm-shop-wrap--with-cockpit' : ''; ?>">

        <!-- Browse sidebar (same nav as shop/category pages) -->
        <aside class="gm-store-sidebar">

            <div class="gm-sidebar-group">
                <span class="gm-sidebar-group__label">Browse</span>
                <ul class="gm-sidebar-nav">
                    <?php foreach ( $browse_items as $item ) : ?>
                    <li>
                        <a href="<?php echo esc_url( $item['url'] ); ?>" class="gm-sidebar-nav__item">
                            <span class="gm-sidebar-nav__icon"><?php echo $item['icon']; ?></span>
                            <?php echo esc_html( $item['label'] ); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="gm-sidebar-group">
                <span class="gm-sidebar-group__label">Gift Categories</span>
                <ul class="gm-sidebar-nav">
                    <?php foreach ( $gift_categories as $slug => $cat ) :
                        $term  = get_term_by( 'slug', $slug, 'product_cat' );
                        $count = $term ? (int) $term->count : 0;
                        $url   = $term ? get_term_link( $term ) : home_url( '/product-category/' . $slug . '/' );
                    ?>
                    <li>
                        <a href="<?php echo esc_url( $url ); ?>" class="gm-sidebar-nav__item">
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

        <!-- Product content -->
        <main class="gm-shop-main">

            <div class="gm-pdp-main">
                <div class="gm-pdp-gallery">
                    <?php do_action( 'woocommerce_before_single_product_summary' ); ?>
                </div>
                <div class="summary entry-summary gm-pdp-summary">
                    <?php do_action( 'woocommerce_single_product_summary' ); ?>
                </div>
            </div>

            <!-- Tabs, upsells, related products -->
            <div class="gm-pdp-below">
                <?php do_action( 'woocommerce_after_single_product_summary' ); ?>
            </div>

        </main>

        <?php if ( $show_cockpit ) :
            $ck_config     = GM_Cockpit::CONFIG[ $ck_segment ];
            $l1_items      = GM_Cockpit::get_l1_items( $ck_user_id, $ck_segment );
            [ $active_l1 ] = GM_Cockpit::get_active();
            $product_price = (float) $product->get_price();
            $segment       = $ck_segment;
            $context       = 'product';
        ?>
        <div class="gm-pdp-cockpit">
            <?php include GM_PATH . 'templates/customer/cockpit-panel.php'; ?>
        </div>
        <?php endif; ?>

    </div><!-- .gm-shop-wrap -->

    <meta itemprop="url" content="<?php the_permalink(); ?>" />

</div><!-- #product -->

<?php do_action( 'woocommerce_after_single_product' ); ?>
