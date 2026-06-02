<?php
/**
 * Shopping Cockpit — event/project + sub-group budget tracking across all 5 segments.
 *
 * Two DB tables:
 *   wp_gm_cockpit_l1 — top-level grouping (event / project / occasion / wedding)
 *   wp_gm_cockpit_l2 — sub-grouping (department / milestone / recipient group)
 *
 * Cart items are tagged with gm_cockpit_l1_id + gm_cockpit_l2_id so spend is
 * attributed to the correct L1+L2 pair when orders complete.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class GM_Cockpit {

    const L1_TABLE = 'gm_cockpit_l1';
    const L2_TABLE = 'gm_cockpit_l2';

    /* ── Segment configuration ───────────────────────────────────── */

    const CONFIG = [
        'corporate' => [
            'l1_label'        => 'Event',
            'l2_label'        => 'Department',
            'cap_field'       => null,
            'cap_blocking'    => false,
            'has_tier'        => false,
            'capacity_type'   => 'avg_page',
            'l1_meta_fields'  => [],
            'l2_meta_fields'  => [],
        ],
        'school' => [
            'l1_label'        => 'Event',
            'l2_label'        => 'Recipient Group',
            'cap_field'       => 'per_recipient_cap',
            'cap_blocking'    => false,
            'has_tier'        => false,
            'capacity_type'   => 'cap',
            'l1_meta_fields'  => [ 'term' ],
            'l2_meta_fields'  => [ 'per_recipient_cap' ],
        ],
        'wedding' => [
            'l1_label'        => 'Wedding Event',
            'l2_label'        => 'Milestone',
            'cap_field'       => null,
            'cap_blocking'    => false,
            'has_tier'        => false,
            'capacity_type'   => 'per_head',
            'l1_meta_fields'  => [ 'guest_count', 'per_head_budget' ],
            'l2_meta_fields'  => [ 'target_date' ],
        ],
        'hospitals' => [
            'l1_label'        => 'Occasion',
            'l2_label'        => 'Department',
            'cap_field'       => 'compliance_cap',
            'cap_blocking'    => true,
            'has_tier'        => false,
            'capacity_type'   => 'cap',
            'l1_meta_fields'  => [],
            'l2_meta_fields'  => [ 'compliance_cap' ],
        ],
        'construction' => [
            'l1_label'        => 'Project',
            'l2_label'        => 'Milestone',
            'cap_field'       => null,
            'cap_blocking'    => false,
            'has_tier'        => true,
            'capacity_type'   => 'avg_page',
            'l1_meta_fields'  => [ 'project_code' ],
            'l2_meta_fields'  => [ 'target_date', 'tier_min', 'tier_max' ],
        ],
    ];

    const TIER_BANDS = [
        'principal' => [ 'min' => 3000,  'max' => 8000,  'label' => 'Principal' ],
        'sub'       => [ 'min' => 1500,  'max' => 4000,  'label' => 'Sub-contractor' ],
        'labour'    => [ 'min' => 500,   'max' => 1500,  'label' => 'Labour Contractor' ],
        'client'    => [ 'min' => 5000,  'max' => 15000, 'label' => 'Client' ],
    ];

    const HOSPITAL_OCCASIONS = [
        "Nurse's Day (12 May)",
        "Doctor's Day (1 Jul)",
        'Diwali',
        'Hospital Foundation Day',
        'New Year',
        'Christmas',
    ];

    /* ── Boot ────────────────────────────────────────────────────── */

    public function __construct() {
        add_action( 'wp_ajax_gm_save_l1',            [ $this, 'ajax_save_l1'       ] );
        add_action( 'wp_ajax_gm_delete_l1',          [ $this, 'ajax_delete_l1'     ] );
        add_action( 'wp_ajax_gm_save_l2',            [ $this, 'ajax_save_l2'       ] );
        add_action( 'wp_ajax_gm_delete_l2',          [ $this, 'ajax_delete_l2'     ] );
        add_action( 'wp_ajax_gm_cockpit_set_active', [ $this, 'ajax_set_active'    ] );
        add_action( 'wp_ajax_gm_cockpit_get_data',   [ $this, 'ajax_get_data'      ] );

        add_filter( 'woocommerce_add_cart_item_data',             [ $this, 'tag_cart_item'       ], 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item',[ $this, 'persist_order_meta'  ], 10, 4 );
    }

    /* ── Table creation ──────────────────────────────────────────── */

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $l1 = $wpdb->prefix . self::L1_TABLE;
        $l2 = $wpdb->prefix . self::L2_TABLE;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( "CREATE TABLE IF NOT EXISTS {$l1} (
            id         bigint(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id    bigint(20) UNSIGNED NOT NULL,
            segment    varchar(20) NOT NULL DEFAULT '',
            name       varchar(100) NOT NULL DEFAULT '',
            meta_json  longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            KEY user_seg (user_id, segment)
        ) {$charset};" );

        dbDelta( "CREATE TABLE IF NOT EXISTS {$l2} (
            id         bigint(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            l1_id      bigint(20) UNSIGNED NOT NULL,
            user_id    bigint(20) UNSIGNED NOT NULL,
            name       varchar(100) NOT NULL DEFAULT '',
            allocated  decimal(12,2) NOT NULL DEFAULT 0.00,
            meta_json  longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            KEY l1 (l1_id),
            KEY user (user_id)
        ) {$charset};" );
    }

    /* ── Queries ─────────────────────────────────────────────────── */

    public static function get_l1_items( $user_id, $segment ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . $wpdb->prefix . self::L1_TABLE . ' WHERE user_id=%d AND segment=%s ORDER BY name ASC',
            $user_id, $segment
        ) );
    }

    public static function get_l2_items( $l1_id, $user_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT * FROM ' . $wpdb->prefix . self::L2_TABLE . ' WHERE l1_id=%d AND user_id=%d ORDER BY name ASC',
            $l1_id, $user_id
        ) );
    }

    public static function get_config( $segment ) {
        return self::CONFIG[ $segment ] ?? null;
    }

    /* Sum of completed/processing order items tagged to this L1+L2 */
    public static function get_spent( $user_id, $l1_id, $l2_id ) {
        $orders = wc_get_orders( [
            'customer' => $user_id,
            'limit'    => -1,
            'status'   => [ 'wc-completed', 'wc-processing' ],
        ] );
        $total = 0.0;
        foreach ( $orders as $order ) {
            foreach ( $order->get_items() as $item ) {
                if ( (int) $item->get_meta( 'gm_cockpit_l1_id' ) === (int) $l1_id &&
                     (int) $item->get_meta( 'gm_cockpit_l2_id' ) === (int) $l2_id ) {
                    $total += (float) $item->get_total();
                }
            }
        }
        return $total;
    }

    /* Sum of WC cart items currently tagged to this L1+L2 */
    public static function get_cart_total( $l1_id, $l2_id ) {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) return 0.0;
        $total = 0.0;
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( (int) ( $item['gm_cockpit_l1_id'] ?? 0 ) === (int) $l1_id &&
                 (int) ( $item['gm_cockpit_l2_id'] ?? 0 ) === (int) $l2_id ) {
                $total += (float) $item['line_total'];
            }
        }
        return $total;
    }

    /* Active L1/L2 from WC session */
    public static function get_active() {
        if ( ! function_exists( 'WC' ) || ! WC()->session ) return [ 0, 0 ];
        return [
            (int) ( WC()->session->get( 'gm_cockpit_l1' ) ?? 0 ),
            (int) ( WC()->session->get( 'gm_cockpit_l2' ) ?? 0 ),
        ];
    }

    /* ── AJAX: save L1 ────────────────────────────────────────────── */

    public function ajax_save_l1() {
        check_ajax_referer( 'gm_dashboard', 'nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( 'Not logged in' );

        $id      = absint( $_POST['id'] ?? 0 );
        $segment = sanitize_key( wp_unslash( $_POST['segment'] ?? '' ) );
        $name    = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $raw     = wp_unslash( $_POST['meta_json'] ?? '{}' );
        $meta    = wp_json_encode( array_map( 'sanitize_text_field', (array) json_decode( $raw, true ) ) );

        if ( ! $name || ! array_key_exists( $segment, self::CONFIG ) ) {
            wp_send_json_error( 'Name and valid segment are required' );
        }

        global $wpdb;
        $table = $wpdb->prefix . self::L1_TABLE;
        $data  = [ 'user_id' => $user_id, 'segment' => $segment, 'name' => $name, 'meta_json' => $meta ];

        if ( $id ) {
            $wpdb->update( $table, $data, [ 'id' => $id, 'user_id' => $user_id ] );
        } else {
            $wpdb->insert( $table, $data );
            $id = $wpdb->insert_id;
        }
        wp_send_json_success( [ 'id' => $id, 'name' => $name ] );
    }

    /* ── AJAX: delete L1 ──────────────────────────────────────────── */

    public function ajax_delete_l1() {
        check_ajax_referer( 'gm_dashboard', 'nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( 'Not logged in' );

        $id = absint( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( 'Missing id' );

        global $wpdb;
        // Cascade-delete L2 children
        $wpdb->delete( $wpdb->prefix . self::L2_TABLE, [ 'l1_id' => $id, 'user_id' => $user_id ] );
        $wpdb->delete( $wpdb->prefix . self::L1_TABLE, [ 'id' => $id, 'user_id' => $user_id ] );
        wp_send_json_success();
    }

    /* ── AJAX: save L2 ────────────────────────────────────────────── */

    public function ajax_save_l2() {
        check_ajax_referer( 'gm_dashboard', 'nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( 'Not logged in' );

        $id        = absint( $_POST['id'] ?? 0 );
        $l1_id     = absint( $_POST['l1_id'] ?? 0 );
        $name      = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $allocated = (float) ( $_POST['allocated'] ?? 0 );
        $raw       = wp_unslash( $_POST['meta_json'] ?? '{}' );
        $meta      = wp_json_encode( array_map( 'sanitize_text_field', (array) json_decode( $raw, true ) ) );

        if ( ! $name || ! $l1_id ) wp_send_json_error( 'Name and L1 are required' );

        // Verify l1 belongs to this user
        global $wpdb;
        $owner = $wpdb->get_var( $wpdb->prepare(
            'SELECT user_id FROM ' . $wpdb->prefix . self::L1_TABLE . ' WHERE id=%d', $l1_id
        ) );
        if ( (int) $owner !== $user_id ) wp_send_json_error( 'Forbidden' );

        $table = $wpdb->prefix . self::L2_TABLE;
        $data  = [ 'l1_id' => $l1_id, 'user_id' => $user_id, 'name' => $name, 'allocated' => $allocated, 'meta_json' => $meta ];

        if ( $id ) {
            $wpdb->update( $table, $data, [ 'id' => $id, 'user_id' => $user_id ] );
        } else {
            $wpdb->insert( $table, $data );
            $id = $wpdb->insert_id;
        }
        wp_send_json_success( [ 'id' => $id, 'name' => $name, 'allocated' => $allocated ] );
    }

    /* ── AJAX: delete L2 ──────────────────────────────────────────── */

    public function ajax_delete_l2() {
        check_ajax_referer( 'gm_dashboard', 'nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( 'Not logged in' );

        $id = absint( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( 'Missing id' );

        global $wpdb;
        $wpdb->delete( $wpdb->prefix . self::L2_TABLE, [ 'id' => $id, 'user_id' => $user_id ] );
        wp_send_json_success();
    }

    /* ── AJAX: set active L1/L2 in session ───────────────────────── */

    public function ajax_set_active() {
        check_ajax_referer( 'gm_dashboard', 'nonce' );
        if ( ! get_current_user_id() ) wp_send_json_error( 'Not logged in' );

        $l1 = absint( $_POST['l1_id'] ?? 0 );
        $l2 = absint( $_POST['l2_id'] ?? 0 );

        if ( function_exists( 'WC' ) && WC()->session ) {
            WC()->session->set( 'gm_cockpit_l1', $l1 );
            WC()->session->set( 'gm_cockpit_l2', $l2 );
        }
        wp_send_json_success();
    }

    /* ── AJAX: get cockpit data (L2 list OR full summary) ─────────── */

    public function ajax_get_data() {
        check_ajax_referer( 'gm_dashboard', 'nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( 'Not logged in' );

        $l1_id = absint( $_POST['l1_id'] ?? 0 );
        $l2_id = absint( $_POST['l2_id'] ?? 0 );

        if ( ! $l1_id ) wp_send_json_error( 'Missing l1_id' );

        $l2_rows = self::get_l2_items( $l1_id, $user_id );

        $l2_list = [];
        foreach ( $l2_rows as $row ) {
            $meta      = json_decode( $row->meta_json ?? '{}', true ) ?: [];
            $spent     = $l2_id === (int) $row->id ? self::get_spent( $user_id, $l1_id, (int) $row->id ) : null;
            $cart_tot  = $l2_id === (int) $row->id ? self::get_cart_total( $l1_id, (int) $row->id ) : null;
            $l2_list[] = [
                'id'        => (int) $row->id,
                'name'      => $row->name,
                'allocated' => (float) $row->allocated,
                'meta'      => $meta,
                'spent'     => $spent,
                'cart_total'=> $cart_tot,
                'remaining' => $spent !== null ? max( 0, (float) $row->allocated - $spent - $cart_tot ) : null,
            ];
        }

        $response = [ 'l2_list' => $l2_list ];

        // Full summary + cart items when both L1 and L2 are provided
        if ( $l2_id ) {
            $cart_items = [];
            if ( function_exists( 'WC' ) && WC()->cart ) {
                foreach ( WC()->cart->get_cart() as $key => $item ) {
                    if ( (int) ( $item['gm_cockpit_l1_id'] ?? 0 ) === $l1_id &&
                         (int) ( $item['gm_cockpit_l2_id'] ?? 0 ) === $l2_id ) {
                        $product = wc_get_product( $item['product_id'] );
                        $cart_items[] = [
                            'key'      => $key,
                            'name'     => $product ? $product->get_name() : __( 'Product', 'gifting-marketplace' ),
                            'qty'      => (int) $item['quantity'],
                            'price'    => (float) ( $product ? $product->get_price() : 0 ),
                            'subtotal' => (float) $item['line_total'],
                        ];
                    }
                }
            }
            $response['cart_items'] = $cart_items;
        }

        wp_send_json_success( $response );
    }

    /* ── Cart hooks ──────────────────────────────────────────────── */

    public function tag_cart_item( $cart_item_data, $product_id ) {
        $l1 = absint( $_POST['gm_cockpit_l1_id'] ?? 0 );
        $l2 = absint( $_POST['gm_cockpit_l2_id'] ?? 0 );
        if ( $l1 ) {
            $cart_item_data['gm_cockpit_l1_id'] = $l1;
            $cart_item_data['gm_cockpit_l2_id'] = $l2;
            // Make cart key unique per L1+L2 so WC doesn't merge across departments
            $cart_item_data['gm_cockpit_key'] = $l1 . '_' . $l2;
        }
        return $cart_item_data;
    }

    public function persist_order_meta( $item, $cart_item_key, $cart_item, $order ) {
        if ( ! empty( $cart_item['gm_cockpit_l1_id'] ) ) {
            $item->update_meta_data( 'gm_cockpit_l1_id', (int) $cart_item['gm_cockpit_l1_id'] );
        }
        if ( ! empty( $cart_item['gm_cockpit_l2_id'] ) ) {
            $item->update_meta_data( 'gm_cockpit_l2_id', (int) $cart_item['gm_cockpit_l2_id'] );
        }
    }
}
