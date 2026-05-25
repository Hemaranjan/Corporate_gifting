<?php
/**
 * Giftelier — Customer dashboard horizontal top nav.
 * Overrides woocommerce/myaccount/navigation.php.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$user       = wp_get_current_user();
$first_name = $user->first_name ?: $user->display_name;
$nav_items  = wc_get_account_menu_items();
unset( $nav_items['customer-logout'] );

// Browse Vendors links externally to the Dokan store listing page
$store_listing_id  = function_exists( 'dokan_get_option' ) ? dokan_get_option( 'store_listing', 'dokan_pages' ) : 0;
$store_listing_url = $store_listing_id ? get_permalink( (int) $store_listing_id ) : home_url( '/store-listing/' );

do_action( 'woocommerce_before_account_navigation' );
?>
<nav class="gm-cnav" role="navigation" aria-label="Customer dashboard navigation">

    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="gm-cnav__brand">
        <?php
        $logo_id  = get_theme_mod( 'custom_logo' );
        $logo_img = $logo_id ? wp_get_attachment_image( $logo_id, 'full', false, [ 'class' => 'gm-cnav__logo-img' ] ) : '';
        if ( $logo_img ) :
            echo wp_kses_post( $logo_img );
        else : ?>
            <span class="gm-cnav__brand-text">Giftelier</span>
        <?php endif; ?>
    </a>

    <ul class="gm-cnav__menu">
        <?php foreach ( $nav_items as $endpoint => $label ) :
            $is_active = wc_is_current_account_menu_item( $endpoint );
            $url = ( $endpoint === 'giftelier-browse' )
                ? $store_listing_url
                : wc_get_account_endpoint_url( $endpoint );
        ?>
        <li>
            <a href="<?php echo esc_url( $url ); ?>"
               class="gm-cnav__item<?php echo $is_active ? ' is-active' : ''; ?>">
                <?php echo esc_html( $label ); ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <div class="gm-cnav__right">
        <span class="gm-cnav__username"><?php echo esc_html( $first_name ); ?></span>
        <a href="<?php echo esc_url( wc_logout_url( home_url( '/' ) ) ); ?>" class="gm-cnav__logout">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Log out
        </a>
    </div>

</nav>
<?php do_action( 'woocommerce_after_account_navigation' ); ?>
