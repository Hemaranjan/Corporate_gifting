<?php
/**
 * Budget Pots — segment-aware multi-envelope budget allocation.
 *
 * Each customer can belong to one of five segments (corporate, school, wedding,
 * hospitals, construction). Within that segment they create named "pots" — budget
 * envelopes for departments, events, projects, etc. — each with an allocated amount
 * and optional period dates and segment-specific metadata.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class GM_Budget_Pots {

    const TABLE = 'gm_budget_pots';

    const SEGMENTS = [
        'corporate'    => 'Corporate',
        'school'       => 'School',
        'wedding'      => 'Wedding',
        'hospitals'    => 'Hospitals',
        'construction' => 'Construction',
    ];

    public function __construct() {
        add_action( 'wp_ajax_gm_set_segment',  [ $this, 'ajax_set_segment'  ] );
        add_action( 'wp_ajax_gm_save_pot',     [ $this, 'ajax_save_pot'     ] );
        add_action( 'wp_ajax_gm_delete_pot',   [ $this, 'ajax_delete_pot'   ] );
    }

    /* ── Table ────────────────────────────────────────────────────── */

    public static function create_table() {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE IF NOT EXISTS {$table} (
            id           bigint(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id      bigint(20) UNSIGNED NOT NULL,
            segment      varchar(20) NOT NULL DEFAULT '',
            label        varchar(100) NOT NULL DEFAULT '',
            allocated    decimal(12,2) NOT NULL DEFAULT 0.00,
            period_start date DEFAULT NULL,
            period_end   date DEFAULT NULL,
            meta_json    longtext DEFAULT NULL,
            created_at   datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at   datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY user_segment (user_id, segment)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /* ── Queries ──────────────────────────────────────────────────── */

    public static function get_pots( $user_id, $segment = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        if ( $segment ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d AND segment = %s ORDER BY label ASC",
                $user_id, $segment
            ) );
        }

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY segment ASC, label ASC",
            $user_id
        ) );
    }

    public static function get_total_allocated( $user_id, $segment = '' ) {
        $pots = self::get_pots( $user_id, $segment );
        return (float) array_sum( array_column( (array) $pots, 'allocated' ) );
    }

    /* ── AJAX: set customer segment ───────────────────────────────── */

    public function ajax_set_segment() {
        check_ajax_referer( 'gm_dashboard', 'nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( 'Not logged in' );

        $segment = sanitize_key( wp_unslash( $_POST['segment'] ?? '' ) );
        if ( $segment && ! array_key_exists( $segment, self::SEGMENTS ) ) {
            wp_send_json_error( 'Invalid segment' );
        }
        update_user_meta( $user_id, 'gm_customer_segment', $segment );
        wp_send_json_success( [ 'segment' => $segment ] );
    }

    /* ── AJAX: save (create or update) a budget pot ───────────────── */

    public function ajax_save_pot() {
        check_ajax_referer( 'gm_dashboard', 'nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( 'Not logged in' );

        $id           = absint( $_POST['id'] ?? 0 );
        $segment      = sanitize_key( wp_unslash( $_POST['segment'] ?? '' ) );
        $label        = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );
        $allocated    = (float) ( $_POST['allocated'] ?? 0 );
        $period_start = sanitize_text_field( wp_unslash( $_POST['period_start'] ?? '' ) );
        $period_end   = sanitize_text_field( wp_unslash( $_POST['period_end'] ?? '' ) );

        // Decode and re-encode meta JSON to strip any injection
        $raw_meta  = wp_unslash( $_POST['meta_json'] ?? '{}' );
        $meta_arr  = json_decode( $raw_meta, true );
        $meta_json = wp_json_encode( is_array( $meta_arr ) ? array_map( 'sanitize_text_field', $meta_arr ) : [] );

        if ( ! $label ) wp_send_json_error( 'Name is required' );
        if ( ! array_key_exists( $segment, self::SEGMENTS ) ) wp_send_json_error( 'Invalid segment' );

        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $data = [
            'user_id'      => $user_id,
            'segment'      => $segment,
            'label'        => $label,
            'allocated'    => $allocated,
            'period_start' => $period_start ?: null,
            'period_end'   => $period_end   ?: null,
            'meta_json'    => $meta_json,
            'updated_at'   => current_time( 'mysql' ),
        ];

        if ( $id ) {
            $owner = $wpdb->get_var( $wpdb->prepare(
                "SELECT user_id FROM {$table} WHERE id = %d", $id
            ) );
            if ( (int) $owner !== $user_id ) wp_send_json_error( 'Not found' );
            $wpdb->update( $table, $data, [ 'id' => $id ] );
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $wpdb->insert( $table, $data );
            $id = $wpdb->insert_id;
        }

        wp_send_json_success( [
            'id'           => $id,
            'label'        => $label,
            'allocated'    => $allocated,
            'period_start' => $period_start,
            'period_end'   => $period_end,
            'meta_json'    => $meta_json,
        ] );
    }

    /* ── AJAX: delete a budget pot ────────────────────────────────── */

    public function ajax_delete_pot() {
        check_ajax_referer( 'gm_dashboard', 'nonce' );
        $user_id = get_current_user_id();
        if ( ! $user_id ) wp_send_json_error( 'Not logged in' );

        $id = absint( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( 'Missing ID' );

        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $deleted = $wpdb->delete( $table, [ 'id' => $id, 'user_id' => $user_id ] );

        if ( ! $deleted ) wp_send_json_error( 'Not found' );
        wp_send_json_success();
    }
}
