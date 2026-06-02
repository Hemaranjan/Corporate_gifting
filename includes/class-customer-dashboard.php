<?php
/**
 * Customer Dashboard — WooCommerce My Account extension.
 *
 * Registers six custom endpoints, overrides the My Account navigation,
 * and routes each endpoint to its template.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class GM_Customer_Dashboard {

    const ENDPOINTS = [
        'giftelier-overview'  => 'Dashboard',
        'giftelier-budget'    => 'Budget',
        'giftelier-calendar'  => 'Calendar',
        'giftelier-analytics' => 'Analytics',
        'giftelier-browse'    => 'Browse Vendors',
        'giftelier-orders'    => 'My Orders',
        'giftelier-quotes'    => 'Quotes',
    ];

    public function __construct() {
        add_action( 'init',                                 [ $this, 'register_endpoints'      ] );
        add_filter( 'woocommerce_account_menu_items',       [ $this, 'menu_items'              ] );
        add_filter( 'woocommerce_get_query_vars',           [ $this, 'add_query_vars'          ] );
        add_action( 'wp_enqueue_scripts',                   [ $this, 'enqueue'                 ] );

        // Flush rewrite rules once after activation
        add_action( 'wp',                                   [ $this, 'maybe_flush_rules'       ] );

        foreach ( array_keys( self::ENDPOINTS ) as $slug ) {
            add_action( "woocommerce_account_{$slug}_endpoint", [ $this, 'render_endpoint' ] );
        }

        // Access guard: unauthenticated → login, vendors → Dokan dashboard
        add_action( 'template_redirect', [ $this, 'guard_endpoints' ], 5 );

        // Remove Dokan vendor-related sections from My Account
        add_action( 'wp', [ $this, 'remove_become_a_vendor' ], 20 );
        add_action( 'wp', [ $this, 'remove_vendor_dashboard_button' ], 20 );

        // AJAX handlers
        add_action( 'wp_ajax_gm_save_budget',    [ $this, 'ajax_save_budget'    ] );
        add_action( 'wp_ajax_gm_cancel_booking', [ $this, 'ajax_cancel_booking' ] );
    }

    public function remove_become_a_vendor() {
        if ( function_exists( 'dokan' ) && isset( dokan()->frontend_manager->become_a_vendor ) ) {
            remove_action( 'woocommerce_after_my_account', [ dokan()->frontend_manager->become_a_vendor, 'render_become_a_vendor_section' ] );
        }
    }

    public function remove_vendor_dashboard_button() {
        if ( ! function_exists( 'dokan' ) ) return;
        $fm = dokan()->frontend_manager ?? null;
        if ( ! $fm ) return;
        // Remove "Go to Vendor Dashboard" button Dokan injects into account dashboard
        if ( isset( $fm->dashboard ) ) {
            remove_action( 'woocommerce_account_dashboard', [ $fm->dashboard, 'show_vendor_dashboard_notice' ], 10 );
            remove_action( 'woocommerce_account_dashboard', [ $fm->dashboard, 'vendor_dashboard_notice' ], 10 );
        }
        // Remove from after_my_account hooks too
        remove_action( 'woocommerce_after_my_account', [ $fm, 'show_vendor_dashboard_notice' ], 10 );
        // Suppress all actions on account_dashboard to prevent vendor content leaking
        remove_all_actions( 'woocommerce_account_dashboard' );
    }

    /* ── Access guard ────────────────────────────────────────────── */

    public function guard_endpoints() {
        if ( ! is_account_page() ) return;

        $on_our_endpoint = false;
        foreach ( array_keys( self::ENDPOINTS ) as $slug ) {
            if ( is_wc_endpoint_url( $slug ) ) {
                $on_our_endpoint = true;
                break;
            }
        }
        if ( ! $on_our_endpoint ) return;

        // Calendar is now merged into Budget — redirect old URL
        if ( is_wc_endpoint_url( 'giftelier-calendar' ) ) {
            wp_safe_redirect( wc_get_account_endpoint_url( 'giftelier-budget' ) );
            exit;
        }

        // TC07: unauthenticated users → login page
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
            exit;
        }

        // TC09: vendors → Dokan dashboard (admins are exempt)
        $uid = get_current_user_id();
        if ( ! current_user_can( 'manage_options' )
             && function_exists( 'dokan_is_user_seller' )
             && dokan_is_user_seller( $uid ) ) {
            wp_safe_redirect( dokan_get_navigation_url() );
            exit;
        }
    }

    /* ── Endpoints & rewrite ──────────────────────────────────────── */

    public function register_endpoints() {
        foreach ( array_keys( self::ENDPOINTS ) as $slug ) {
            add_rewrite_endpoint( $slug, EP_ROOT | EP_PAGES );
        }
    }

    public function add_query_vars( $vars ) {
        foreach ( array_keys( self::ENDPOINTS ) as $slug ) {
            $vars[ $slug ] = $slug;
        }
        return $vars;
    }

    public function maybe_flush_rules() {
        if ( get_option( 'gm_flush_rewrite_rules' ) || get_option( 'gm_flush_quotes_rules' ) ) {
            flush_rewrite_rules();
            delete_option( 'gm_flush_rewrite_rules' );
            delete_option( 'gm_flush_quotes_rules' );
        }
    }

    /* ── Navigation ──────────────────────────────────────────────── */

    public function menu_items( $items ) {
        $logout = isset( $items['customer-logout'] ) ? [ 'customer-logout' => $items['customer-logout'] ] : [];

        // Analytics merged into Dashboard; Calendar merged into Budget
        $hidden = [ 'giftelier-analytics', 'giftelier-calendar' ];

        $new = [];
        foreach ( self::ENDPOINTS as $slug => $label ) {
            if ( in_array( $slug, $hidden ) ) continue;
            $new[ $slug ] = __( $label, 'gifting-marketplace' );
        }

        return array_merge( $new, $logout );
    }

    /* ── Template router ──────────────────────────────────────────── */

    public function render_endpoint() {
        // Determine which endpoint fired
        global $wp_query;
        foreach ( array_keys( self::ENDPOINTS ) as $slug ) {
            if ( isset( $wp_query->query_vars[ $slug ] ) ) {
                $tpl = GM_PATH . "templates/customer/{$this->slug_to_tpl($slug)}.php";
                if ( file_exists( $tpl ) ) {
                    include $tpl;
                }
                return;
            }
        }
    }

    private function slug_to_tpl( $slug ) {
        $map = [
            'giftelier-overview'  => 'overview',
            'giftelier-budget'    => 'budget',
            'giftelier-calendar'  => 'calendar',
            'giftelier-analytics' => 'analytics',
            'giftelier-browse'    => 'browse',
            'giftelier-orders'    => 'my-orders',
            'giftelier-quotes'    => 'quotes',
        ];
        return $map[ $slug ] ?? 'overview';
    }

    /* ── Assets ──────────────────────────────────────────────────── */

    public function enqueue() {
        $is_dokan_dash = function_exists( 'dokan_is_seller_dashboard' ) && dokan_is_seller_dashboard();
        if ( ! is_account_page() && ! $is_dokan_dash ) return;

        wp_enqueue_style(
            'gm-google-fonts',
            'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap',
            [],
            null
        );

        wp_enqueue_style(
            'gm-dashboard',
            GM_URL . 'assets/css/dashboard.css',
            [ 'gifting-marketplace', 'gm-google-fonts' ],
            '2.5.0'
        );
        wp_enqueue_script(
            'gm-dashboard',
            GM_URL . 'assets/js/dashboard.js',
            [ 'jquery', 'gifting-marketplace' ],
            '1.1.0',
            true
        );
        wp_localize_script( 'gm-dashboard', 'gmDash', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'gm_dashboard' ),
            'userId'  => get_current_user_id(),
        ] );

        // Cockpit assets — browse page and budget page when a segment is set
        $on_browse = is_wc_endpoint_url( 'giftelier-browse' );

        if ( $on_browse && class_exists( 'GM_Cockpit' ) ) {
            $user_id = get_current_user_id();
            $segment = get_user_meta( $user_id, 'gm_customer_segment', true );

            if ( $segment && isset( GM_Cockpit::CONFIG[ $segment ] ) ) {
                $ck_v = filemtime( GM_PATH . 'assets/css/cockpit.css' ) ?: '1.0.0';
                $ck_j = filemtime( GM_PATH . 'assets/js/cockpit.js'  ) ?: '1.0.0';

                wp_enqueue_style(  'gm-cockpit', GM_URL . 'assets/css/cockpit.css', [ 'gm-dashboard' ], $ck_v );
                wp_enqueue_script( 'gm-cockpit', GM_URL . 'assets/js/cockpit.js',  [ 'jquery', 'gm-dashboard' ], $ck_j, true );

                $ck_config = GM_Cockpit::CONFIG[ $segment ];

                // Resolve active L1/L2 from session
                $active_l1 = 0; $active_l2 = 0; $active_l1_meta = [];
                if ( $on_browse ) {
                    [ $active_l1, $active_l2 ] = GM_Cockpit::get_active();
                    if ( $active_l1 ) {
                        foreach ( GM_Cockpit::get_l1_items( $user_id, $segment ) as $r ) {
                            if ( (int) $r->id === $active_l1 ) {
                                $active_l1_meta = json_decode( $r->meta_json ?? '{}', true ) ?: [];
                                break;
                            }
                        }
                    }
                }

                wp_localize_script( 'gm-cockpit', 'gmCockpit', [
                    'segment'      => $segment,
                    'config'       => $ck_config,
                    'budgetUrl'    => wc_get_account_endpoint_url( 'giftelier-budget' ),
                    'activeL1'     => $active_l1,
                    'activeL2'     => $active_l2,
                    'activeL1Meta' => $active_l1_meta,
                    'tierBands'    => GM_Cockpit::TIER_BANDS,
                ] );
            }
        }
    }

    /* ── AJAX: save budget ───────────────────────────────────────── */

    public function ajax_save_budget() {
        check_ajax_referer( 'gm_dashboard', 'nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( 'Not logged in' );

        $yearly    = absint( $_POST['yearly']    ?? 0 );
        $monthly   = absint( $_POST['monthly']   ?? 0 );
        $quarterly = absint( $_POST['quarterly'] ?? 0 );

        update_user_meta( $user_id, 'gm_budget_yearly',    $yearly    );
        update_user_meta( $user_id, 'gm_budget_monthly',   $monthly   );
        update_user_meta( $user_id, 'gm_budget_quarterly', $quarterly );

        wp_send_json_success( [ 'message' => __( 'Budget saved!', 'gifting-marketplace' ) ] );
    }

    /* ── AJAX: cancel booking (stub) ─────────────────────────────── */

    public function ajax_cancel_booking() {
        check_ajax_referer( 'gm_dashboard', 'nonce' );
        // Extend: integrate with Amelia's booking cancellation API
        wp_send_json_success( [ 'message' => __( 'Cancellation requested.', 'gifting-marketplace' ) ] );
    }

    /* ── Helpers ─────────────────────────────────────────────────── */

    public static function get_user_orders( $limit = 5 ) {
        $user_id = get_current_user_id();
        return wc_get_orders( [
            'customer' => $user_id,
            'limit'    => $limit,
            'orderby'  => 'date',
            'order'    => 'DESC',
            'status'   => [ 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending' ],
        ] );
    }

    public static function get_yearly_spend() {
        $user_id = get_current_user_id();
        $orders  = wc_get_orders( [
            'customer'   => $user_id,
            'limit'      => -1,
            'date_after' => date( 'Y-01-01' ),
            'status'     => [ 'wc-completed', 'wc-processing' ],
        ] );
        $total = 0;
        foreach ( $orders as $order ) {
            $total += (float) $order->get_total();
        }
        return $total;
    }
}
