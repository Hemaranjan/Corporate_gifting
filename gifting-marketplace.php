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
require_once GM_PATH . 'includes/class-quotes.php';
require_once GM_PATH . 'includes/class-budget-pots.php';

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

        // Quote request system
        new GM_Quotes();

        // Segment-aware budget pots
        new GM_Budget_Pots();

        // Admin — allow admins to map a vendor to an Amelia Employee ID
        add_action( 'show_user_profile',                [ $this, 'amelia_id_field'          ] );
        add_action( 'edit_user_profile',                [ $this, 'amelia_id_field'          ] );
        add_action( 'personal_options_update',          [ $this, 'save_amelia_id_field'     ] );
        add_action( 'edit_user_profile_update',         [ $this, 'save_amelia_id_field'     ] );
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

        // Quotes assets: product pages, customer quotes endpoint, vendor dashboard
        $need_quotes = is_singular( 'product' )
            || ( is_account_page() && is_wc_endpoint_url( 'giftelier-quotes' ) )
            || ( function_exists( 'dokan_is_seller_dashboard' ) && dokan_is_seller_dashboard() );
        if ( $need_quotes ) {
            $qv = filemtime( GM_PATH . 'assets/css/quotes.css' ) ?: '1.0.0';
            $qj = filemtime( GM_PATH . 'assets/js/quotes.js'  ) ?: '1.0.0';
            wp_enqueue_style(  'gm-quotes', GM_URL . 'assets/css/quotes.css', [ 'gm-google-fonts' ], $qv );
            wp_enqueue_script( 'gm-quotes', GM_URL . 'assets/js/quotes.js',  [ 'jquery' ], $qj, true );
        }

        // Calendar + occasions assets
        $need_cal = ( is_account_page() && is_wc_endpoint_url( 'giftelier-calendar' ) )
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
}

add_action( 'plugins_loaded', [ 'Gifting_Marketplace', 'get_instance' ] );

register_activation_hook( __FILE__, function () {
    update_option( 'gm_flush_rewrite_rules', 1 );
    GM_Budget_Pots::create_table();
} );

// Flush rewrite rules once to register the new giftelier-quotes endpoint.
if ( ! get_option( 'gm_quotes_rules_flushed' ) ) {
    add_action( 'init', function () {
        flush_rewrite_rules();
        update_option( 'gm_quotes_rules_flushed', '1' );
    }, 99 );
}

// One-time table creation for already-active installs.
if ( ! get_option( 'gm_budget_pots_table_created' ) ) {
    add_action( 'plugins_loaded', function () {
        GM_Budget_Pots::create_table();
        update_option( 'gm_budget_pots_table_created', '1' );
    }, 20 );
}
