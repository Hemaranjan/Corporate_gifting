<?php
/**
 * Giftelier store hero header — overrides dokan/templates/store-header.php
 */
defined( 'ABSPATH' ) || exit;

$store_user  = dokan()->vendor->get( get_query_var( 'author' ) );
$shop_name   = $store_user->get_shop_name() ?: $store_user->data->display_name;
$banner      = $store_user->get_banner();
$logo        = $store_user->get_avatar();
$tagline     = get_user_meta( $store_user->get_id(), 'gm_store_tagline',     true );
$description = get_user_meta( $store_user->get_id(), 'gm_store_description', true );
$rating_html = dokan_get_readable_seller_rating( $store_user->get_id() );
$store_url   = dokan_get_store_url( $store_user->get_id() );
$cart_count  = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
$cart_url    = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
?>

<!-- Store front nav -->
<nav class="gm-store-nav" role="navigation" aria-label="Store navigation">

    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="gm-store-nav__brand">
        <?php
        $logo_id   = get_theme_mod( 'custom_logo' );
        $logo_img  = $logo_id ? wp_get_attachment_image( $logo_id, 'full', false, [ 'class' => 'gm-store-nav__logo-img' ] ) : '';
        if ( $logo_img ) :
            echo wp_kses_post( $logo_img );
        else : ?>
            <span class="gm-store-nav__brand-text">Giftelier</span>
        <?php endif; ?>
    </a>

    <ul class="gm-store-nav__menu">
        <li><a href="<?php echo esc_url( $store_url ); ?>">Products</a></li>
        <li><a href="#gm-store-about">About</a></li>
        <li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a></li>
    </ul>

    <a href="<?php echo esc_url( $cart_url ); ?>" class="gm-store-nav__cart" aria-label="Cart">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
        <?php if ( $cart_count > 0 ) : ?>
            <span class="gm-store-nav__cart-count"><?php echo esc_html( $cart_count ); ?></span>
        <?php endif; ?>
    </a>

</nav>


<div id="gm-store-about" class="gm-store-hero">

    <div class="gm-store-hero__info">

        <?php if ( $logo ) : ?>
            <img src="<?php echo esc_url( $logo ); ?>"
                 alt="<?php echo esc_attr( $shop_name ); ?>"
                 class="gm-store-hero__logo" />
        <?php endif; ?>

        <h1 class="gm-store-hero__name"><?php echo esc_html( $shop_name ); ?></h1>

        <?php if ( $tagline ) : ?>
            <p class="gm-store-hero__tagline"><?php echo esc_html( $tagline ); ?></p>
        <?php endif; ?>

        <?php if ( $description ) : ?>
            <p class="gm-store-hero__desc"><?php echo nl2br( esc_html( $description ) ); ?></p>
        <?php endif; ?>

        <?php if ( $rating_html ) : ?>
            <div class="gm-store-hero__rating">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="#e8a045" stroke="none" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                <?php echo wp_kses_post( $rating_html ); ?>
            </div>
        <?php endif; ?>

    </div>

    <?php if ( $banner ) : ?>
        <div class="gm-store-hero__banner">
            <img src="<?php echo esc_url( $banner ); ?>"
                 alt="<?php echo esc_attr( $shop_name ); ?>" />
        </div>
    <?php endif; ?>

</div>
