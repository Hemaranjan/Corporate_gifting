<?php
/**
 * Creates the 6 universal gift-type WooCommerce product categories.
 * Same 6 categories apply across all 5 industry segments.
 * Runs once on init, then sets an option flag.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class GM_Category_Setup {

    const OPTION = 'gm_gift_cats_v1';

    /* Old program-specific slugs to remove */
    const OLD_SLUGS = [
        'employee-welcome-kits', 'employee-rewards', 'client-appreciation',
        'festival-gifts',        'event-giveaways',  'annual-day-gifts',
        'student-awards',        'staff-recognition', 'graduation-gifts',
        'return-gifts',          'guest-welcome-kits','wedding-hampers',
        'doctor-appreciation',   'staff-rewards',     'patient-welcome-kits',
        'housewarming-gifts',    'customer-handover-kits', 'channel-partner-gifts',
    ];

    /* 6 universal gift-type categories */
    const CATEGORIES = [
        'Hamper'        => 'hamper',
        'Premium'       => 'premium-gifts',
        'Personalized'  => 'personalized-gifts',
        'Eco-Friendly'  => 'eco-friendly-gifts',
        'Handcrafted'   => 'handcrafted-gifts',
        'Local Artisan' => 'local-artisan-gifts',
    ];

    public function __construct() {
        add_action( 'init', [ $this, 'maybe_create_categories' ], 20 );
    }

    public function maybe_create_categories() {
        if ( get_option( self::OPTION ) ) return;
        if ( ! taxonomy_exists( 'product_cat' ) ) return;

        /* Remove old program-specific categories */
        foreach ( self::OLD_SLUGS as $slug ) {
            $term = get_term_by( 'slug', $slug, 'product_cat' );
            if ( $term && ! is_wp_error( $term ) ) {
                wp_delete_term( $term->term_id, 'product_cat' );
            }
        }

        /* Create the 6 universal gift-type categories */
        foreach ( self::CATEGORIES as $name => $slug ) {
            if ( ! term_exists( $slug, 'product_cat' ) ) {
                wp_insert_term( $name, 'product_cat', [ 'slug' => $slug ] );
            }
        }

        update_option( self::OPTION, true );
    }
}
