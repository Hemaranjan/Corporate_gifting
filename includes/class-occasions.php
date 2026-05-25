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

        $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
        $date  = sanitize_text_field( wp_unslash( $_POST['date']  ?? '' ) );
        $icon  = sanitize_text_field( wp_unslash( $_POST['icon']  ?? '🎉' ) );
        $color = sanitize_hex_color( wp_unslash( $_POST['color'] ?? '#E8386D' ) ) ?: '#E8386D';

        if ( ! $title || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            wp_send_json_error( 'Invalid input' );
        }

        $uid  = get_current_user_id();
        $list = self::get_occasions( $uid );
        $list[] = [
            'id'    => wp_generate_uuid4(),
            'title' => $title,
            'date'  => $date,
            'icon'  => $icon,
            'color' => $color,
        ];
        self::set_occasions( $uid, $list );
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
