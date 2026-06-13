<?php
/**
 * GM_Occasions — stores user occasions + gift assignments, exposes AJAX endpoints,
 * and renders the "Plan for an Occasion" panel on single product pages.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class GM_Occasions {

    const OCC_META  = 'gm_occasions';
    const GIFT_META = 'gm_occasion_gifts';

    public function __construct() {
        add_action( 'wp_ajax_gm_save_occasion',   [ $this, 'ajax_save'          ] );
        add_action( 'wp_ajax_gm_delete_occasion', [ $this, 'ajax_delete'        ] );
        add_action( 'wp_ajax_gm_assign_gift',     [ $this, 'ajax_assign_gift'   ] );
        add_action( 'wp_ajax_gm_unassign_gift',   [ $this, 'ajax_unassign_gift' ] );
        add_action( 'woocommerce_single_product_summary', [ $this, 'render_product_panel' ], 35 );

        // Cart: tag, display, persist
        add_filter( 'woocommerce_add_cart_item_data',             [ $this, 'tag_cart_item'      ], 10, 2 );
        add_filter( 'woocommerce_get_item_data',                  [ $this, 'display_in_cart'    ], 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item',[ $this, 'persist_to_order'   ], 10, 4 );

        // Enqueue occasion-cart JS on shop / product / browse / cart pages
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_cart_modal' ] );

        // Backfill existing occasions into the cockpit once per user
        add_action( 'wp', function () {
            if ( ! is_user_logged_in() ) return;
            if ( ! is_account_page() && ! is_shop() && ! is_product_category() && ! is_product_tag() ) return;
            $uid = get_current_user_id();
            if ( get_user_meta( $uid, 'gm_occ_cockpit_synced', true ) ) return;
            self::sync_to_cockpit( $uid );
            update_user_meta( $uid, 'gm_occ_cockpit_synced', '1' );
        } );
    }

    /* ── Cockpit sync ────────────────────────────────────────────── */

    /**
     * Ensure every occasion for $uid has a matching cockpit L1 entry,
     * and remove L1 entries whose occasion was deleted.
     * Safe to call multiple times — does not create duplicates.
     */
    public static function sync_to_cockpit( int $uid ): void {
        if ( ! class_exists( 'GM_Cockpit' ) ) return;

        $segment = get_user_meta( $uid, 'gm_customer_segment', true );
        if ( ! $segment || ! isset( GM_Cockpit::CONFIG[ $segment ] ) ) return;

        global $wpdb;
        $l1_table = $wpdb->prefix . GM_Cockpit::L1_TABLE;
        $l2_table = $wpdb->prefix . GM_Cockpit::L2_TABLE;

        $occasions = self::get_occasions( $uid );

        // Fetch all occasion-linked L1 rows for this user
        $existing_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, meta_json FROM {$l1_table} WHERE user_id=%d AND meta_json LIKE %s",
            $uid, '%"occasion_id"%'
        ) );

        // Build map: occasion_id → l1 row id
        $linked = [];
        foreach ( $existing_rows as $row ) {
            $meta = json_decode( $row->meta_json, true );
            if ( ! empty( $meta['occasion_id'] ) ) {
                $linked[ $meta['occasion_id'] ] = (int) $row->id;
            }
        }

        $occ_ids = array_column( $occasions, 'id' );

        // Create L1 entries for occasions that have none
        foreach ( $occasions as $occ ) {
            if ( isset( $linked[ $occ['id'] ] ) ) continue;
            $wpdb->insert( $l1_table, [
                'user_id'   => $uid,
                'segment'   => $segment,
                'name'      => ( $occ['icon'] ?? '🎉' ) . ' ' . $occ['title'],
                'meta_json' => wp_json_encode( [
                    'occasion_id' => $occ['id'],
                    'date'        => $occ['date'],
                    'icon'        => $occ['icon']  ?? '🎉',
                    'color'       => $occ['color'] ?? '#E8386D',
                ] ),
            ] );
        }

        // Delete L1 entries whose occasion was removed
        foreach ( $linked as $occ_id => $l1_id ) {
            if ( in_array( $occ_id, $occ_ids, true ) ) continue;
            $wpdb->delete( $l2_table, [ 'l1_id' => $l1_id, 'user_id' => $uid ] );
            $wpdb->delete( $l1_table, [ 'id'    => $l1_id, 'user_id' => $uid ] );
        }
    }

    /* ── Data helpers ─────────────────────────────────────────────── */

    public static function get_occasions( int $uid ): array {
        $v = get_user_meta( $uid, self::OCC_META, true );
        return is_array( $v ) ? $v : [];
    }

    private static function set_occasions( int $uid, array $list ): void {
        usort( $list, fn( $a, $b ) => strcmp( $a['date'], $b['date'] ) );
        update_user_meta( $uid, self::OCC_META, array_values( $list ) );
    }

    public static function get_gifts( int $uid ): array {
        $v = get_user_meta( $uid, self::GIFT_META, true );
        return is_array( $v ) ? $v : [];
    }

    /* ── AJAX: save occasion ─────────────────────────────────────── */

    public function ajax_save(): void {
        check_ajax_referer( 'gm_occ_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Login required' );

        $title      = sanitize_text_field( wp_unslash( $_POST['title']      ?? '' ) );
        $date       = sanitize_text_field( wp_unslash( $_POST['date']       ?? '' ) );
        $icon       = sanitize_text_field( wp_unslash( $_POST['icon']       ?? '🎉' ) );
        $color      = sanitize_hex_color( wp_unslash( $_POST['color']      ?? '#E8386D' ) ) ?: '#E8386D';
        $budget     = absint( $_POST['budget']     ?? 0 );
        $item_count = absint( $_POST['item_count'] ?? 0 );

        if ( ! $title || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            wp_send_json_error( 'Invalid input' );
        }

        $uid  = get_current_user_id();
        $list = self::get_occasions( $uid );
        $item = [
            'id'    => wp_generate_uuid4(),
            'title' => $title,
            'date'  => $date,
            'icon'  => $icon,
            'color' => $color,
        ];
        if ( $budget > 0 ) {
            $item['budget'] = $budget;
        }
        if ( $item_count > 0 ) {
            $item['item_count'] = $item_count;
        }
        $list[] = $item;
        self::set_occasions( $uid, $list );
        self::sync_to_cockpit( $uid );
        wp_send_json_success( [ 'occasions' => self::get_occasions( $uid ) ] );
    }

    /* ── AJAX: delete occasion ───────────────────────────────────── */

    public function ajax_delete(): void {
        check_ajax_referer( 'gm_occ_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Login required' );

        $id  = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
        $uid = get_current_user_id();

        $list = array_values( array_filter( self::get_occasions( $uid ), fn( $o ) => $o['id'] !== $id ) );
        self::set_occasions( $uid, $list );

        $gifts = array_values( array_filter( self::get_gifts( $uid ), fn( $g ) => $g['occasion_id'] !== $id ) );
        update_user_meta( $uid, self::GIFT_META, $gifts );

        self::sync_to_cockpit( $uid );
        wp_send_json_success( [ 'occasions' => $list ] );
    }

    /* ── AJAX: assign gift to occasion ───────────────────────────── */

    public function ajax_assign_gift(): void {
        check_ajax_referer( 'gm_occ_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Login required' );

        $occ_id = sanitize_text_field( wp_unslash( $_POST['occasion_id'] ?? '' ) );
        $pid    = absint( $_POST['product_id'] ?? 0 );
        if ( ! $occ_id || ! $pid ) wp_send_json_error( 'Invalid input' );

        $uid = get_current_user_id();

        // Verify occasion belongs to user
        $occasions = self::get_occasions( $uid );
        $occ = null;
        foreach ( $occasions as $o ) { if ( $o['id'] === $occ_id ) { $occ = $o; break; } }
        if ( ! $occ ) wp_send_json_error( 'Occasion not found' );

        $gifts = self::get_gifts( $uid );
        foreach ( $gifts as $g ) {
            if ( $g['occasion_id'] === $occ_id && $g['product_id'] === $pid ) {
                wp_send_json_success( [ 'message' => 'Already assigned' ] );
            }
        }

        $product = wc_get_product( $pid );
        $gifts[] = [
            'occasion_id'  => $occ_id,
            'product_id'   => $pid,
            'product_name' => $product ? $product->get_name() : '',
            'product_url'  => get_permalink( $pid ),
            'added_date'   => current_time( 'Y-m-d' ),
        ];
        update_user_meta( $uid, self::GIFT_META, $gifts );
        wp_send_json_success( [ 'message' => 'Assigned!' ] );
    }

    /* ── AJAX: unassign gift ─────────────────────────────────────── */

    public function ajax_unassign_gift(): void {
        check_ajax_referer( 'gm_occ_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) wp_send_json_error( 'Login required' );

        $occ_id = sanitize_text_field( wp_unslash( $_POST['occasion_id'] ?? '' ) );
        $pid    = absint( $_POST['product_id'] ?? 0 );
        $uid    = get_current_user_id();

        $gifts = array_values( array_filter(
            self::get_gifts( $uid ),
            fn( $g ) => ! ( $g['occasion_id'] === $occ_id && $g['product_id'] === $pid )
        ) );
        update_user_meta( $uid, self::GIFT_META, $gifts );
        wp_send_json_success( [ 'message' => 'Unassigned' ] );
    }

    /* ── Occasions with L1 IDs (for JS) ─────────────────────────────── */

    public static function get_occasions_for_js( int $uid ): array {
        $occasions = self::get_occasions( $uid );
        if ( empty( $occasions ) || ! class_exists( 'GM_Cockpit' ) ) return $occasions;

        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            'SELECT id, meta_json FROM ' . $wpdb->prefix . GM_Cockpit::L1_TABLE
            . ' WHERE user_id=%d AND meta_json LIKE %s',
            $uid, '%"occasion_id"%'
        ) );
        $occ_to_l1 = [];
        foreach ( $rows as $row ) {
            $m = json_decode( $row->meta_json, true );
            if ( ! empty( $m['occasion_id'] ) ) {
                $occ_to_l1[ $m['occasion_id'] ] = (int) $row->id;
            }
        }
        foreach ( $occasions as &$occ ) {
            $occ['l1_id'] = $occ_to_l1[ $occ['id'] ] ?? 0;
        }
        return array_values( $occasions );
    }

    /* ── Enqueue occasion-cart modal ─────────────────────────────────── */

    public function enqueue_cart_modal(): void {
        if ( ! is_user_logged_in() ) return;
        if ( ! ( is_shop() || is_product_category() || is_product_tag()
              || is_singular( 'product' ) || is_cart()
              || is_wc_endpoint_url( 'giftelier-browse' ) ) ) return;

        $uid      = get_current_user_id();
        $occasions = self::get_occasions_for_js( $uid );
        if ( empty( $occasions ) ) return;

        $v = file_exists( GM_PATH . 'assets/js/occasion-cart.js' )
            ? filemtime( GM_PATH . 'assets/js/occasion-cart.js' ) : '1.0.0';
        wp_enqueue_script( 'gm-occasion-cart', GM_URL . 'assets/js/occasion-cart.js', [ 'jquery' ], $v, true );
        wp_localize_script( 'gm-occasion-cart', 'gmOccCart', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'gm_occ_nonce' ),
            'occasions' => $occasions,
            'wcAjaxUrl' => WC_AJAX::get_endpoint( 'add_to_cart' ),
        ] );
    }

    /* ── Cart: tag item with occasion ────────────────────────────────── */

    public function tag_cart_item( array $data, int $product_id ): array {
        $occ_id = sanitize_text_field( wp_unslash( $_POST['gm_occasion_id'] ?? '' ) );
        if ( ! $occ_id ) return $data;

        $uid      = get_current_user_id();
        $found    = null;
        foreach ( self::get_occasions( $uid ) as $occ ) {
            if ( $occ['id'] === $occ_id ) { $found = $occ; break; }
        }
        if ( ! $found ) return $data;

        $data['gm_occasion_id']    = $occ_id;
        $data['gm_occasion_title'] = $found['title'] ?? '';
        $data['gm_occasion_icon']  = $found['icon']  ?? '🎉';
        $data['gm_occasion_color'] = $found['color'] ?? '#E8386D';
        return $data;
    }

    /* ── Cart: display occasion badge ────────────────────────────────── */

    public function display_in_cart( array $item_data, array $cart_item ): array {
        if ( empty( $cart_item['gm_occasion_id'] ) ) return $item_data;
        $icon  = esc_html( $cart_item['gm_occasion_icon']  ?? '🎉' );
        $title = esc_html( $cart_item['gm_occasion_title'] ?? 'Occasion' );
        $color = esc_attr( $cart_item['gm_occasion_color'] ?? '#E8386D' );
        $item_data[] = [
            'name'    => 'Occasion',
            'value'   => $icon . ' ' . $title,
            'display' => '<span class="gm-cart-occ-badge" style="background:' . $color . '20;color:' . $color . '">' . $icon . ' ' . $title . '</span>',
        ];
        return $item_data;
    }

    /* ── Order: persist occasion to line item ────────────────────────── */

    public function persist_to_order( $item, $cart_item_key, $cart_item, $order ): void {
        if ( empty( $cart_item['gm_occasion_id'] ) ) return;
        $item->update_meta_data( 'gm_occasion_id',    $cart_item['gm_occasion_id']    );
        $item->update_meta_data( 'gm_occasion_title', $cart_item['gm_occasion_title'] ?? '' );
    }

    /* ── Product page panel ──────────────────────────────────────── */

    public function render_product_panel(): void {
        if ( ! is_user_logged_in() ) return;

        $uid        = get_current_user_id();
        $occasions  = self::get_occasions( $uid );
        if ( empty( $occasions ) ) return;

        $product_id = get_the_ID();
        $gifts      = self::get_gifts( $uid );
        $assigned   = array_column(
            array_filter( $gifts, fn( $g ) => (int) $g['product_id'] === $product_id ),
            'occasion_id'
        );
        $nonce      = wp_create_nonce( 'gm_occ_nonce' );

        include GM_PATH . 'templates/customer/occasion-product-panel.php';
    }
}
