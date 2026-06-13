<?php
/**
 * Astra Child – Gifting Marketplace
 * Theme functions: enqueue styles, Google Fonts, post-login redirect.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ── 1. Enqueue parent + child stylesheets + Google Fonts ─────── */
add_action( 'wp_enqueue_scripts', function () {
    $parent_version = wp_get_theme( 'astra' )->get( 'Version' );
    $child_version  = wp_get_theme()->get( 'Version' );

    wp_enqueue_style(
        'astra-parent-style',
        get_template_directory_uri() . '/style.css',
        [],
        $parent_version
    );

    wp_enqueue_style(
        'astra-child-style',
        get_stylesheet_uri(),
        [ 'astra-parent-style' ],
        $child_version
    );

    // Inter — clean, modern sans-serif
    wp_enqueue_style(
        'gm-google-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
        [],
        null
    );

    // Homepage-only styles (front page template)
    if ( is_front_page() ) {
        $home_css_path = get_stylesheet_directory() . '/assets/css/home.css';
        wp_enqueue_style(
            'gm-home',
            get_stylesheet_directory_uri() . '/assets/css/home.css',
            [ 'astra-child-style' ],
            file_exists( $home_css_path ) ? filemtime( $home_css_path ) : $child_version
        );
    }
}, 20 );

/* ── 2. Override WordPress site title with the brand name ─────── */
add_filter( 'pre_option_blogname', function () { return 'Giftelier'; } );
add_filter( 'option_blogname',     function () { return 'Giftelier'; } );

/* ── Always require users to set their own password on registration ── */
add_filter( 'pre_option_woocommerce_registration_generate_password', function () { return 'no'; } );
add_filter( 'option_woocommerce_registration_generate_password',     function () { return 'no'; } );

/* ── Validate confirm-password field on registration ───────────── */
add_filter( 'woocommerce_registration_errors', function ( $errors, $username, $email ) {
    $pass  = isset( $_POST['password']  ) ? $_POST['password']  : '';
    $pass2 = isset( $_POST['password2'] ) ? $_POST['password2'] : '';
    if ( $pass !== $pass2 ) {
        $errors->add( 'password_mismatch', __( 'Passwords do not match. Please try again.', 'woocommerce' ) );
    }
    if ( strlen( $pass ) < 8 ) {
        $errors->add( 'password_too_short', __( 'Password must be at least 8 characters.', 'woocommerce' ) );
    }
    return $errors;
}, 10, 3 );

/* ── 4. Apply Inter globally via Astra's custom CSS hook ──────── */
add_filter( 'astra_dynamic_theme_css', function ( $css ) {
    $css .= 'body,button,input,select,textarea{font-family:"Inter",-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;}';
    return $css;
} );

/* ── 3. Redirect after login → customer calendar dashboard ───── */
add_filter( 'woocommerce_login_redirect', function ( $redirect, $user ) {
    // Honour an explicit redirect_to (checkout, etc.)
    if ( ! empty( $_REQUEST['redirect_to'] ) ) {
        return sanitize_url( wp_unslash( $_REQUEST['redirect_to'] ) );
    }
    // Admins always go to wp-admin
    if ( in_array( 'administrator', (array) $user->roles ) ) {
        return admin_url();
    }
    // Vendors go to Dokan dashboard
    if ( function_exists( 'dokan_is_user_seller' ) && dokan_is_user_seller( $user->ID ) ) {
        return dokan_get_navigation_url();
    }
    return wc_get_account_endpoint_url( 'giftelier-calendar' );
}, 10, 2 );

// Also catch the standard wp-login.php redirect
add_filter( 'login_redirect', function ( $redirect_to, $request, $user ) {
    if ( is_wp_error( $user ) ) return $redirect_to;
    // Admins always go to wp-admin
    if ( in_array( 'administrator', (array) $user->roles ) ) {
        return admin_url();
    }
    if ( function_exists( 'dokan_is_user_seller' ) && dokan_is_user_seller( $user->ID ) ) {
        return dokan_get_navigation_url();
    }
    if ( in_array( 'customer', (array) $user->roles ) || in_array( 'subscriber', (array) $user->roles ) ) {
        return wc_get_account_endpoint_url( 'giftelier-calendar' );
    }
    return $redirect_to;
}, 10, 3 );

/* ── 4. Theme supports ─────────────────────────────────────────── */
add_action( 'after_setup_theme', function () {
    add_theme_support( 'woocommerce' );
    add_theme_support( 'wc-product-gallery-zoom' );
    add_theme_support( 'wc-product-gallery-lightbox' );
    add_theme_support( 'wc-product-gallery-slider' );
} );

/* ── 5. Remove Astra's default page title on store, front & account pages ── */
add_filter( 'astra_the_title_enabled', function ( $val ) {
    if ( is_front_page() ) {
        return false;
    }
    if ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() ) {
        return false;
    }
    if ( function_exists( 'dokan_is_seller_dashboard' ) && dokan_is_seller_dashboard() ) {
        return false;
    }
    if ( is_account_page() ) {
        return false;
    }
    if ( function_exists( 'dokan_is_store_listing' ) && dokan_is_store_listing() ) {
        return false;
    }
    return $val;
} );

/* ── 6. Hide Astra's page header banner on the front page ──────── */
add_filter( 'astra_banner_options_meta', function ( $meta ) {
    if ( is_front_page() ) {
        $meta['ast-hfb-above-header-display'] = 'disabled';
        $meta['ast-hfb-below-header-display'] = 'disabled';
        $meta['ast-hfb-header-display']       = 'disabled';
    }
    return $meta;
} );

/* ── 6b. Suppress Astra's breadcrumb on store pages (we render our own) ── */
add_filter( 'astra_breadcrumb_trail', function ( $breadcrumb ) {
    if ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() ) {
        return '';
    }
    return $breadcrumb;
} );

/* Remove Astra's dynamically hooked breadcrumb action on store pages */
add_action( 'wp', function () {
    if ( ! function_exists( 'dokan_is_store_page' ) || ! dokan_is_store_page() ) return;
    if ( ! function_exists( 'astra_get_option' ) ) return;

    $position = astra_get_option( 'breadcrumb-position' );
    if ( ! $position ) return;

    $instance = Astra_Breadcrumbs_Markup::get_instance();
    remove_action( $position,                [ $instance, 'astra_hook_breadcrumb_position' ], 15 );
    remove_action( 'astra_before_archive_title', [ $instance, 'astra_hook_breadcrumb_position' ], 15 );
    remove_action( 'astra_header_markup_after',  [ $instance, 'astra_hook_breadcrumb_position' ], 15 );
    remove_action( 'astra_header_after',         [ $instance, 'astra_hook_breadcrumb_position' ], 15 );
    remove_action( 'astra_entry_top',            [ $instance, 'astra_hook_breadcrumb_position' ], 15 );
}, 20 );

/* ── 7. Force full-width (no sidebar) on store listing and Dokan pages ── */
add_filter( 'astra_page_layout', function ( $layout ) {
    if ( function_exists( 'dokan_is_store_listing' ) && dokan_is_store_listing() ) return 'no-sidebar';
    if ( function_exists( 'dokan_is_store_page' )    && dokan_is_store_page()    ) return 'no-sidebar';
    return $layout;
} );

/* ── 7a. Hide Astra site header — our custom nav replaces it everywhere ── */
add_filter( 'astra_main_header_display', function ( $display ) {
    if ( function_exists( 'dokan_is_seller_dashboard' ) && dokan_is_seller_dashboard() ) return 'disabled';
    if ( function_exists( 'dokan_is_store_page' )       && dokan_is_store_page()       ) return 'disabled';
    if ( function_exists( 'dokan_is_store_listing' )    && dokan_is_store_listing()    ) return 'disabled';
    if ( is_account_page() ) return 'disabled';
    if ( is_shop() || is_product() || is_product_category() || is_product_tag() ) return 'disabled';
    if ( is_cart() || is_checkout() ) return 'disabled';
    return $display;
} );

/* ── 7a. Render navs full-width via astra_header_after ──────────────── */

// Customer dashboard nav
add_action( 'astra_header_after', function () {
    if ( ! is_account_page() ) return;
    wc_get_template( 'myaccount/navigation.php' );
} );

// Vendor dashboard nav — move outside Dokan's content wrapper
add_action( 'astra_header_after', function () {
    if ( ! function_exists( 'dokan_is_seller_dashboard' ) ) return;
    if ( ! dokan_is_seller_dashboard() ) return;
    dokan_get_template_part( 'global/dashboard-nav' );
} );

// Remove Dokan's dashboard nav from its default position inside the content wrapper
add_action( 'init', function () {
    remove_action( 'dokan_dashboard_content_before',
        [ 'WeDevs\Dokan\Dashboard\Templates\Main', 'dashboard_side_navigation' ] );
}, 99 );

// Store page nav (individual vendor store)
add_action( 'astra_header_after', function () {
    if ( ! function_exists( 'dokan_is_store_page' ) || ! dokan_is_store_page() ) return;
    $store_user = dokan()->vendor->get( get_query_var( 'author' ) );
    if ( ! $store_user ) return;
    $store_url  = dokan_get_store_url( $store_user->get_id() );
    $cart_count = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
    $cart_url   = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
    $logo_id    = get_theme_mod( 'custom_logo' );
    $logo_img   = $logo_id ? wp_get_attachment_image( $logo_id, 'full', false, [ 'class' => 'gm-store-nav__logo-img' ] ) : '';
    ?>
    <nav class="gm-store-nav" role="navigation" aria-label="Store navigation">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="gm-store-nav__brand">
            <?php if ( $logo_img ) : echo wp_kses_post( $logo_img ); else : ?>
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
    <?php
} );

// Store listing nav — brand name + logout only
add_action( 'astra_header_after', function () {
    if ( ! function_exists( 'dokan_is_store_listing' ) || ! dokan_is_store_listing() ) return;
    $logo_id  = get_theme_mod( 'custom_logo' );
    $logo_img = $logo_id ? wp_get_attachment_image( $logo_id, 'full', false, [ 'class' => 'gm-store-nav__logo-img' ] ) : '';
    ?>
    <nav class="gm-store-nav gm-store-listing-nav" role="navigation" aria-label="Store navigation">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="gm-store-nav__brand">
            <?php echo $logo_img ? wp_kses_post( $logo_img ) : '<span class="gm-store-nav__brand-text">Giftelier</span>'; ?>
        </a>
        <div class="gm-store-nav__right-actions">
            <?php if ( is_user_logged_in() ) : ?>
                <a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>" class="gm-snav-btn gm-snav-btn--ghost">Logout</a>
            <?php else : ?>
                <a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="gm-snav-btn gm-snav-btn--primary">Login</a>
            <?php endif; ?>
        </div>
    </nav>
    <?php
} );

// Body class for store listing (CSS targeting)
add_filter( 'body_class', function ( $classes ) {
    if ( function_exists( 'dokan_is_store_listing' ) && dokan_is_store_listing() ) {
        $classes[] = 'dokan-store-listing';
    }
    if ( is_shop() || is_product() || is_product_category() || is_product_tag() ) {
        $classes[] = 'gm-shop-page';
    }
    return $classes;
} );

// Shop / category pages — show customer dashboard nav for logged-in users, public nav otherwise
add_action( 'astra_header_after', function () {
    if ( ! ( is_shop() || is_product_category() || is_product_tag() ) ) return;
    if ( is_user_logged_in() ) {
        wc_get_template( 'myaccount/navigation.php' );
        return;
    }
    // Guest: public nav
    $logo_id    = get_theme_mod( 'custom_logo' );
    $logo_img   = $logo_id ? wp_get_attachment_image( $logo_id, 'full', false, [ 'class' => 'gm-store-nav__logo-img' ] ) : '';
    $cart_count = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
    $cart_url   = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
    $listing_id = function_exists( 'dokan_get_option' ) ? dokan_get_option( 'store_listing', 'dokan_pages' ) : 0;
    $listing_url = $listing_id ? get_permalink( (int) $listing_id ) : home_url( '/store-listing/' );
    ?>
    <nav class="gm-store-nav gm-public-nav" role="navigation" aria-label="Site navigation">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="gm-store-nav__brand">
            <?php echo $logo_img ? wp_kses_post( $logo_img ) : '<span class="gm-store-nav__brand-text">Giftelier</span>'; ?>
        </a>
        <ul class="gm-store-nav__menu">
            <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a></li>
            <li><a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Shop</a></li>
            <li><a href="<?php echo esc_url( $listing_url ); ?>">Browse Vendors</a></li>
        </ul>
        <div class="gm-store-nav__right-actions">
            <a href="<?php echo esc_url( $cart_url ); ?>" class="gm-store-nav__cart" aria-label="Cart">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <?php if ( $cart_count > 0 ) : ?>
                    <span class="gm-store-nav__cart-count"><?php echo esc_html( $cart_count ); ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="gm-snav-btn gm-snav-btn--primary">Login</a>
        </div>
    </nav>
    <?php
} );

// Cart / checkout pages — same nav as shop
add_action( 'astra_header_after', function () {
    if ( ! ( is_cart() || is_checkout() ) ) return;
    if ( is_user_logged_in() ) {
        wc_get_template( 'myaccount/navigation.php' );
        return;
    }
    $logo_id     = get_theme_mod( 'custom_logo' );
    $logo_img    = $logo_id ? wp_get_attachment_image( $logo_id, 'full', false, [ 'class' => 'gm-store-nav__logo-img' ] ) : '';
    $listing_id  = function_exists( 'dokan_get_option' ) ? dokan_get_option( 'store_listing', 'dokan_pages' ) : 0;
    $listing_url = $listing_id ? get_permalink( (int) $listing_id ) : home_url( '/store-listing/' );
    ?>
    <nav class="gm-store-nav gm-public-nav" role="navigation" aria-label="Site navigation">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="gm-store-nav__brand">
            <?php echo $logo_img ? wp_kses_post( $logo_img ) : '<span class="gm-store-nav__brand-text">Giftelier</span>'; ?>
        </a>
        <ul class="gm-store-nav__menu">
            <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a></li>
            <li><a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Shop</a></li>
            <li><a href="<?php echo esc_url( $listing_url ); ?>">Browse Vendors</a></li>
        </ul>
        <div class="gm-store-nav__right-actions">
            <a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="gm-snav-btn gm-snav-btn--primary">Login</a>
        </div>
    </nav>
    <?php
} );

// Single product pages — full customer nav for logged-in users, public nav for guests
add_action( 'astra_header_after', function () {
    if ( ! is_product() ) return;
    if ( is_user_logged_in() ) {
        wc_get_template( 'myaccount/navigation.php' );
        return;
    }
    $logo_id    = get_theme_mod( 'custom_logo' );
    $logo_img   = $logo_id ? wp_get_attachment_image( $logo_id, 'full', false, [ 'class' => 'gm-store-nav__logo-img' ] ) : '';
    $cart_count = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
    $cart_url   = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
    $listing_id = function_exists( 'dokan_get_option' ) ? dokan_get_option( 'store_listing', 'dokan_pages' ) : 0;
    $listing_url = $listing_id ? get_permalink( (int) $listing_id ) : home_url( '/store-listing/' );
    ?>
    <nav class="gm-store-nav gm-public-nav" role="navigation" aria-label="Site navigation">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="gm-store-nav__brand">
            <?php echo $logo_img ? wp_kses_post( $logo_img ) : '<span class="gm-store-nav__brand-text">Giftelier</span>'; ?>
        </a>
        <ul class="gm-store-nav__menu">
            <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a></li>
            <li><a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Shop</a></li>
            <li><a href="<?php echo esc_url( $listing_url ); ?>">Browse Vendors</a></li>
        </ul>
        <div class="gm-store-nav__right-actions">
            <a href="<?php echo esc_url( $cart_url ); ?>" class="gm-store-nav__cart" aria-label="Cart">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                <?php if ( $cart_count > 0 ) : ?>
                    <span class="gm-store-nav__cart-count"><?php echo esc_html( $cart_count ); ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="gm-snav-btn gm-snav-btn--primary">Login</a>
        </div>
    </nav>
    <?php
} );

/* ── 7b. Redirect bare /my-account/ to the custom overview ────────── */
add_action( 'template_redirect', function () {
    if ( is_account_page() && is_user_logged_in() && ! is_wc_endpoint_url() ) {
        wp_safe_redirect( wc_get_account_endpoint_url( 'giftelier-overview' ) );
        exit;
    }
} );

/* ── 8. Ensure Amelia loads on account/dashboard pages ─────────── */
add_filter( 'amelia_load_scripts_and_styles', function ( $load ) {
    if ( is_account_page() ) return true;
    return $load;
} );
