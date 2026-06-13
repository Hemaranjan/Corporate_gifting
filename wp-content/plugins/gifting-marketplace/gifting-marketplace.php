<?php
/**
 * Plugin Name:  Giftelier
 * Plugin URI:
 * Description:  Vendor-page experience: Amelia event booking + WooCommerce gift products in one combined flow with Shiprocket fulfillment.
 * Version:      1.0.0
 * Author:       Giftelier
 * Text Domain:  gifting-marketplace
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'GM_URL',  plugin_dir_url( __FILE__ ) );
define( 'GM_PATH', plugin_dir_path( __FILE__ ) );

require_once GM_PATH . 'includes/class-customer-dashboard.php';
require_once GM_PATH . 'includes/class-admin-panel.php';
require_once GM_PATH . 'includes/class-vendor-dashboard.php';
require_once GM_PATH . 'includes/class-occasions.php';
require_once GM_PATH . 'includes/class-cockpit.php';
require_once GM_PATH . 'includes/class-category-setup.php';
require_once GM_PATH . 'includes/class-seed-data.php';
require_once GM_PATH . 'includes/class-product-industry.php';
require_once GM_PATH . 'includes/class-sync-hub.php';
require_once GM_PATH . 'includes/class-vendor-onboarding.php';

final class Gifting_Marketplace {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Assets
        add_action( 'wp_enqueue_scripts',               [ $this, 'enqueue'                  ] );


        // Vendor store page — events section disabled (removed per design update)

        // Ensure Amelia loads its scripts on Dokan store pages
        add_filter( 'amelia_load_scripts_and_styles',   [ $this, 'load_amelia_on_store_page'] );

        // Cart — show event reminder if an Amelia product is present
        add_action( 'woocommerce_before_cart',          [ $this, 'render_cart_event_banner' ] );

        // Order received — custom confirmation message
        add_filter( 'woocommerce_thankyou_order_received_text',
                                                        [ $this, 'order_received_text'      ], 10, 2 );

        // Customer dashboard
        new GM_Customer_Dashboard();

        // Giftelier team admin panel
        new GM_Admin_Panel();

        // Vendor dashboard — horizontal topnav
        new GM_Vendor_Dashboard();

        // Customer occasions + product panel
        new GM_Occasions();


        // Shopping cockpit (budget tracking while browsing)
        new GM_Cockpit();

        // One-time: create product categories for all industry sub-menus
        new GM_Category_Setup();

        // Seed demo vendors + products (runs once, cleans up previous seeds)
        new GM_Seed_Data();

        // Industry + Gifting Program selector on product add/edit screens
        new GM_Product_Industry();

        // Sync Hub — multi-platform product import for vendors
        new GM_Sync_Hub();

        // Vendor onboarding — injects sync step into Dokan setup wizard
        new GM_Vendor_Onboarding();

        // Admin — allow admins to map a vendor to an Amelia Employee ID
        add_action( 'show_user_profile',                [ $this, 'amelia_id_field'          ] );
        add_action( 'edit_user_profile',                [ $this, 'amelia_id_field'          ] );
        add_action( 'personal_options_update',          [ $this, 'save_amelia_id_field'     ] );
        add_action( 'edit_user_profile_update',         [ $this, 'save_amelia_id_field'     ] );

        // Admin — customer segment assignment
        add_action( 'show_user_profile',                [ $this, 'customer_segment_field'   ] );
        add_action( 'edit_user_profile',                [ $this, 'customer_segment_field'   ] );
        add_action( 'personal_options_update',          [ $this, 'save_customer_segment'    ] );
        add_action( 'edit_user_profile_update',         [ $this, 'save_customer_segment'    ] );
    }

    /* ── Assets ───────────────────────────────────────────────────── */

    public function enqueue() {
        $css_v = filemtime( GM_PATH . 'assets/css/gifting.css' ) ?: '1.0.0';
        $js_v  = filemtime( GM_PATH . 'assets/js/gifting.js'  ) ?: '1.0.0';

        wp_enqueue_style(
            'gm-google-fonts',
            'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap',
            [],
            null
        );

        wp_enqueue_style(
            'gifting-marketplace',
            GM_URL . 'assets/css/gifting.css',
            [ 'astra-child-style', 'gm-google-fonts' ],
            $css_v
        );
        wp_enqueue_script(
            'gifting-marketplace',
            GM_URL . 'assets/js/gifting.js',
            [ 'jquery' ],
            $js_v,
            true
        );
        wp_localize_script( 'gifting-marketplace', 'gmData', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'cartUrl' => wc_get_cart_url(),
            'nonce'   => wp_create_nonce( 'gm_nonce' ),
            'strings' => [
                'added'  => __( 'Added to your celebration!', 'gifting-marketplace' ),
                'booked' => __( 'Event booked — now pick your gifts below.', 'gifting-marketplace' ),
            ],
        ] );


        // Calendar + occasions assets (now also loaded on budget page)
        $need_cal = ( is_account_page() && (
                        is_wc_endpoint_url( 'giftelier-calendar' ) ||
                        is_wc_endpoint_url( 'giftelier-budget' )
                    ) )
                 || ( is_singular( 'product' ) && is_user_logged_in() );

        if ( $need_cal ) {
            $cal_css_v = filemtime( GM_PATH . 'assets/css/calendar.css' ) ?: '1.0.0';
            $cal_js_v  = filemtime( GM_PATH . 'assets/js/calendar.js'  ) ?: '1.0.0';
            wp_enqueue_style(
                'gm-calendar',
                GM_URL . 'assets/css/calendar.css',
                [ 'gm-google-fonts' ],
                $cal_css_v
            );
            wp_enqueue_script(
                'gm-calendar',
                GM_URL . 'assets/js/calendar.js',
                [ 'jquery' ],
                $cal_js_v,
                true
            );
        }
    }

    /* ── Vendor Store Page: Events + Gifts Intro ─────────────────── */

    /**
     * Fires after the store header (profile frame) and before the WooCommerce product loop.
     *
     * @param object $store_user_data  WP_User->data
     * @param array  $store_info       Dokan store meta
     */
    public function render_events_section( $store_user_data, $store_info ) {
        $vendor_id          = isset( $store_user_data->ID ) ? (int) $store_user_data->ID : 0;
        $amelia_employee_id = (int) get_user_meta( $vendor_id, '_amelia_employee_id', true );
        $shop_name          = isset( $store_info['store_name'] ) ? $store_info['store_name'] : '';

        include GM_PATH . 'templates/vendor-events-section.php';
    }

    /* ── Force Amelia scripts on store pages ─────────────────────── */

    public function load_amelia_on_store_page( $should_load ) {
        if ( function_exists( 'dokan_is_store_page' ) && dokan_is_store_page() ) {
            return true;
        }
        return $should_load;
    }

    /* ── Cart: Event Booking Banner ──────────────────────────────── */

    public function render_cart_event_banner() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) return;

        $has_amelia = false;
        foreach ( WC()->cart->get_cart() as $item ) {
            // Amelia injects its booking data into the cart item under 'amelia' key
            if ( ! empty( $item['amelia'] ) || ! empty( $item['ameliabooking'] ) ) {
                $has_amelia = true;
                break;
            }
            // Fallback: check if the product has an Amelia service meta
            if ( isset( $item['product_id'] ) &&
                 get_post_meta( (int) $item['product_id'], 'ameliaServiceId', true ) ) {
                $has_amelia = true;
                break;
            }
        }

        if ( $has_amelia ) {
            include GM_PATH . 'templates/cart-event-banner.php';
        }
    }

    /* ── Order Received: Custom Message ──────────────────────────── */

    public function order_received_text( $text, $order ) {
        return __( 'Your booking and gifts are confirmed! Check your email for event details and a shipment tracking link — your gifts will arrive before the event.', 'gifting-marketplace' );
    }

    /* ── Admin: Amelia Employee ID Field ─────────────────────────── */

    public function amelia_id_field( $user ) {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $val = (int) get_user_meta( $user->ID, '_amelia_employee_id', true );
        ?>
        <h3><?php esc_html_e( 'Amelia Booking Integration', 'gifting-marketplace' ); ?></h3>
        <table class="form-table">
            <tr>
                <th>
                    <label for="gm_amelia_employee_id">
                        <?php esc_html_e( 'Amelia Employee ID', 'gifting-marketplace' ); ?>
                    </label>
                </th>
                <td>
                    <input type="number" name="gm_amelia_employee_id" id="gm_amelia_employee_id"
                           value="<?php echo esc_attr( $val ?: '' ); ?>"
                           class="regular-text" min="1" />
                    <p class="description">
                        <?php esc_html_e( 'Link this vendor\'s store page to their Amelia Employee profile so their events appear on the store page. Find the ID in Amelia → Staff.', 'gifting-marketplace' ); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
        wp_nonce_field( 'gm_save_amelia_id', 'gm_amelia_nonce' );
    }

    public function save_amelia_id_field( $user_id ) {
        if ( ! isset( $_POST['gm_amelia_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gm_amelia_nonce'] ) ), 'gm_save_amelia_id' ) ) {
            return;
        }
        if ( current_user_can( 'edit_user', $user_id ) && isset( $_POST['gm_amelia_employee_id'] ) ) {
            $new_val = absint( $_POST['gm_amelia_employee_id'] );
            if ( $new_val > 0 ) {
                update_user_meta( $user_id, '_amelia_employee_id', $new_val );
            } else {
                delete_user_meta( $user_id, '_amelia_employee_id' );
            }
        }
    }

    /* ── Admin: Customer Segment Field ──────────────────────────────── */

    public function customer_segment_field( $user ) {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $val = get_user_meta( $user->ID, 'gm_customer_segment', true );
        $segments = [
            ''             => '— None (no cockpit) —',
            'corporate'    => 'Corporate',
            'school'       => 'School',
            'wedding'      => 'Wedding',
            'hospitals'    => 'Hospitals',
            'construction' => 'Construction',
        ];
        ?>
        <h3><?php esc_html_e( 'Giftelier Customer Type', 'gifting-marketplace' ); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="gm_customer_segment"><?php esc_html_e( 'Customer Segment', 'gifting-marketplace' ); ?></label></th>
                <td>
                    <select name="gm_customer_segment" id="gm_customer_segment">
                        <?php foreach ( $segments as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $val, $key ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'Sets which Shopping Cockpit mode appears on the Browse page for this customer.', 'gifting-marketplace' ); ?>
                    </p>
                    <?php wp_nonce_field( 'gm_save_segment', 'gm_segment_nonce' ); ?>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_customer_segment( $user_id ) {
        if ( ! isset( $_POST['gm_segment_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gm_segment_nonce'] ) ), 'gm_save_segment' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_user', $user_id ) ) return;

        $valid    = [ '', 'corporate', 'school', 'wedding', 'hospitals', 'construction' ];
        $new_val  = sanitize_key( wp_unslash( $_POST['gm_customer_segment'] ?? '' ) );

        if ( in_array( $new_val, $valid, true ) ) {
            if ( $new_val === '' ) {
                delete_user_meta( $user_id, 'gm_customer_segment' );
            } else {
                update_user_meta( $user_id, 'gm_customer_segment', $new_val );
            }
        }
    }
}

add_action( 'plugins_loaded', [ 'Gifting_Marketplace', 'get_instance' ] );

/* ── Combined product filtering ─────────────────────────────────────────
 *  Uses woocommerce_product_query (not pre_get_posts) so that WooCommerce
 *  has already converted the shop page to a product archive and is_tax()
 *  checks are reliable.
 *
 *  Shop page:    ?industry=corporate&program=employee-gifting&cat=hamper
 *  Tag archive:  /product-tag/corporate/?program=...&cat=...
 *  Cat archive:  /product-category/hamper/?industry=...&program=...
 */
add_action( 'woocommerce_product_query', function ( $q ) {
    $industry = sanitize_key( wp_unslash( $_GET['industry'] ?? '' ) );
    $program  = sanitize_key( wp_unslash( $_GET['program']  ?? '' ) );
    $cat      = sanitize_key( wp_unslash( $_GET['cat']      ?? '' ) );
    $orderby  = sanitize_key( wp_unslash( $_GET['orderby']  ?? '' ) );

    if ( ! $industry && ! $program && ! $cat && ! $orderby ) return;

    // ── Taxonomy filters ─────────────────────────────────────────
    $extra = [];

    if ( $q->is_tax( 'product_tag' ) ) {
        // Tag archive: WC already filters by the tag in the URL
        if ( $program ) $extra[] = [ 'taxonomy' => 'product_tag', 'field' => 'slug', 'terms' => [ $program ], 'operator' => 'IN' ];
        if ( $cat     ) $extra[] = [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => [ $cat     ], 'operator' => 'IN' ];
    } elseif ( $q->is_tax( 'product_cat' ) ) {
        // Category archive: WC already filters by the category
        if ( $industry ) $extra[] = [ 'taxonomy' => 'product_tag', 'field' => 'slug', 'terms' => [ $industry ], 'operator' => 'IN' ];
        if ( $program  ) $extra[] = [ 'taxonomy' => 'product_tag', 'field' => 'slug', 'terms' => [ $program  ], 'operator' => 'IN' ];
    } else {
        // Shop page: apply all three params
        if ( $industry ) $extra[] = [ 'taxonomy' => 'product_tag', 'field' => 'slug', 'terms' => [ $industry ], 'operator' => 'IN' ];
        if ( $program  ) $extra[] = [ 'taxonomy' => 'product_tag', 'field' => 'slug', 'terms' => [ $program  ], 'operator' => 'IN' ];
        if ( $cat      ) $extra[] = [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => [ $cat      ], 'operator' => 'IN' ];
    }

    if ( ! empty( $extra ) ) {
        $tax_query             = (array) $q->get( 'tax_query' );
        $tax_query             = array_merge( $tax_query, $extra );
        $tax_query['relation'] = 'AND';
        $q->set( 'tax_query', $tax_query );
    }

    // ── New Arrivals: last 7 days ─────────────────────────────────
    if ( $orderby === 'date' ) {
        $q->set( 'date_query', [
            [ 'after' => '1 week ago', 'inclusive' => true ],
        ] );
    }

    // ── Best Rated: average rating > 4 ───────────────────────────
    if ( $orderby === 'rating' ) {
        $meta_query             = (array) $q->get( 'meta_query' );
        $meta_query[]           = [
            'key'     => '_wc_average_rating',
            'value'   => '4',
            'compare' => '>',
            'type'    => 'DECIMAL(10,2)',
        ];
        $meta_query['relation'] = 'AND';
        $q->set( 'meta_query', $meta_query );
    }
} );

/* ── Product links: open in new tab for logged-in customers ─────────────── */
add_action( 'wp', function () {
    if ( ! is_user_logged_in() ) return;
    remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
    add_action( 'woocommerce_before_shop_loop_item', function () {
        global $product;
        $link = apply_filters( 'woocommerce_loop_product_link', get_the_permalink(), $product );
        echo '<a href="' . esc_url( $link ) . '" class="woocommerce-LoopProduct-link woocommerce-loop-product__link" target="_blank" rel="noopener noreferrer">';
    }, 10 );
} );

/* ── Dokan vendor listing: filter by industry when ?industry= is set ─────── */
add_filter( 'dokan_get_sellers_args', function ( $args ) {
    $industry = sanitize_key( wp_unslash( $_GET['industry'] ?? '' ) );
    if ( ! $industry ) return $args;

    $meta_query             = isset( $args['meta_query'] ) ? (array) $args['meta_query'] : [];
    $meta_query[]           = [
        'key'     => 'gm_vendor_segment',
        'value'   => $industry,
        'compare' => 'LIKE',
    ];
    $meta_query['relation'] = 'AND';
    $args['meta_query']     = $meta_query;

    return $args;
} );

register_activation_hook( __FILE__, function () {
    update_option( 'gm_flush_rewrite_rules', 1 );
    GM_Cockpit::create_tables();
    GM_Sync_Hub::create_tables();
} );

// One-time cockpit table creation on first load (for sites already active)
if ( ! get_option( 'gm_cockpit_tables_created' ) ) {
    add_action( 'init', function () {
        GM_Cockpit::create_tables();
        update_option( 'gm_cockpit_tables_created', '1' );
    }, 99 );
}

// One-time sync hub table creation
if ( ! get_option( 'gm_sync_tables_created' ) ) {
    add_action( 'init', function () {
        GM_Sync_Hub::create_tables();
        update_option( 'gm_sync_tables_created', '1' );
    }, 99 );
}

