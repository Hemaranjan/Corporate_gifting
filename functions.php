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
    // Vendors go to Dokan dashboard
    if ( function_exists( 'dokan_is_user_seller' ) && dokan_is_user_seller( $user->ID ) ) {
        return dokan_get_navigation_url();
    }
    return wc_get_account_endpoint_url( 'giftelier-calendar' );
}, 10, 2 );

// Also catch the standard wp-login.php redirect
add_filter( 'login_redirect', function ( $redirect_to, $request, $user ) {
    if ( is_wp_error( $user ) ) return $redirect_to;
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

/* ── 7. Hide site header on Dokan vendor dashboard, store pages and customer account ── */
add_filter( 'astra_main_header_display', function ( $display ) {
    if ( function_exists( 'dokan_is_seller_dashboard' ) && dokan_is_seller_dashboard() ) {
        return 'disabled';
    }
    if ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() ) {
        return 'disabled';
    }
    if ( function_exists( 'dokan_is_store_listing' ) && dokan_is_store_listing() ) {
        return 'disabled';
    }
    if ( is_account_page() ) {
        return 'disabled';
    }
    return $display;
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
