<?php
/**
 * Giftelier Vendor Dashboard — customises Dokan's nav for gift suppliers.
 *
 * Replaces the default Dokan sidebar nav items with:
 *   Dashboard | Products | Orders | Analytics | Payments | Support (dropdown)
 * and injects the horizontal topnav CSS/JS.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class GM_Vendor_Dashboard {

    const SEGMENTS = [
        'corporate'    => 'Corporate',
        'school'       => 'School',
        'wedding'      => 'Wedding',
        'hospitals'    => 'Hospitals',
        'construction' => 'Construction',
    ];

    public function __construct() {
        add_filter( 'dokan_get_dashboard_nav',        [ $this, 'customise_nav'           ], 20     );
        add_action( 'wp_enqueue_scripts',             [ $this, 'enqueue_assets'          ]         );
        add_action( 'dokan_settings_after_store_name',[ $this, 'render_description_fields'], 10, 2 );
        add_action( 'dokan_store_profile_saved',      [ $this, 'save_description_fields' ], 10, 2  );
    }

    /* ── Nav items ──────────────────────────────────────────────────── */

    public function customise_nav( array $menus ): array {

        /* 1. Rename "Withdraw" → "Payments" */
        if ( isset( $menus['withdraw'] ) ) {
            $menus['withdraw']['title']     = __( 'Payments', 'gifting-marketplace' );
            $menus['withdraw']['icon']      = '<i class="fas fa-credit-card"></i>';
            $menus['withdraw']['icon_name'] = 'CreditCard';
            $menus['withdraw']['pos']       = 60;
        }

        /* 2. Remove Analytics */
        unset( $menus['analytics'] );

        /* 3. Support — dropdown with Help & support sub-items */
        $menus['support'] = [
            'title'     => __( 'Support', 'gifting-marketplace' ),
            'icon'      => '<i class="fas fa-headset"></i>',
            'icon_name' => 'Headset',
            'url'       => '#',
            'pos'       => 80,
            'submenu'   => [
                'contact' => [
                    'title' => __( 'Contact Giftelier', 'gifting-marketplace' ),
                    'icon'  => '<i class="fas fa-envelope"></i>',
                    'url'   => home_url( '/contact/' ),
                    'pos'   => 10,
                ],
                'faqs' => [
                    'title' => __( 'FAQs', 'gifting-marketplace' ),
                    'icon'  => '<i class="fas fa-question-circle"></i>',
                    'url'   => home_url( '/faqs/' ),
                    'pos'   => 20,
                ],
                'guidelines' => [
                    'title' => __( 'Vendor Guidelines', 'gifting-marketplace' ),
                    'icon'  => '<i class="fas fa-book"></i>',
                    'url'   => home_url( '/vendor-guidelines/' ),
                    'pos'   => 30,
                ],
                'tickets' => [
                    'title' => __( 'Ticket History', 'gifting-marketplace' ),
                    'icon'  => '<i class="fas fa-ticket-alt"></i>',
                    'url'   => home_url( '/my-account/ticket-history/' ),
                    'pos'   => 40,
                ],
            ],
        ];

        /* 5. Quotes — view and reply to customer quote requests */
        $menus['gm-quotes'] = [
            'title'     => __( 'Quotes', 'gifting-marketplace' ),
            'icon'      => '<i class="fas fa-file-invoice"></i>',
            'icon_name' => 'FileInvoice',
            'url'       => dokan_get_navigation_url( 'gm-quotes' ),
            'pos'       => 55,
        ];

        /* 6. Remove Settings from top-level bar (it lives under account avatar) */
        unset( $menus['settings'] );

        return $menus;
    }

    /* ── Store description fields in Vendor Settings ───────────────── */

    public function render_description_fields( $current_user, $profile_info ) {
        $user_id          = is_object( $current_user ) ? $current_user->ID : (int) $current_user;
        $tagline          = esc_attr( get_user_meta( $user_id, 'gm_store_tagline',     true ) );
        $description      = esc_textarea( get_user_meta( $user_id, 'gm_store_description', true ) );
        $saved_segments   = (array) get_user_meta( $user_id, 'gm_vendor_segment', true );
        ?>
        <div class="dokan-form-group">
            <label for="gm_store_tagline" class="dokan-w3 dokan-control-label">
                <?php esc_html_e( 'Store Tagline', 'gifting-marketplace' ); ?>
            </label>
            <div class="dokan-w5 dokan-text-left">
                <input type="text" id="gm_store_tagline" name="gm_store_tagline"
                       value="<?php echo $tagline; ?>"
                       class="dokan-form-control"
                       placeholder="<?php esc_attr_e( 'E.g. Handcrafted gifts for every occasion', 'gifting-marketplace' ); ?>" />
                <p class="help-block"><?php esc_html_e( 'A short one-line summary shown under your store name.', 'gifting-marketplace' ); ?></p>
            </div>
        </div>
        <div class="dokan-form-group">
            <label for="gm_store_description" class="dokan-w3 dokan-control-label">
                <?php esc_html_e( 'Store Description', 'gifting-marketplace' ); ?>
            </label>
            <div class="dokan-w5 dokan-text-left">
                <textarea id="gm_store_description" name="gm_store_description"
                          class="dokan-form-control" rows="5"
                          placeholder="<?php esc_attr_e( 'Tell customers what your store is about, what makes your products special...', 'gifting-marketplace' ); ?>"><?php echo $description; ?></textarea>
                <p class="help-block"><?php esc_html_e( 'Displayed in the hero section of your public store page.', 'gifting-marketplace' ); ?></p>
            </div>
        </div>
        <div class="dokan-form-group">
            <label class="dokan-w3 dokan-control-label">
                <?php esc_html_e( 'Store Category', 'gifting-marketplace' ); ?>
            </label>
            <div class="dokan-w5 dokan-text-left">
                <p class="help-block" style="margin-bottom:10px"><?php esc_html_e( 'Select the customer segments your store serves. Your store will appear in these categories on the Browse Vendors page.', 'gifting-marketplace' ); ?></p>
                <?php foreach ( self::SEGMENTS as $key => $label ) : ?>
                <label style="display:inline-flex;align-items:center;gap:6px;margin:0 16px 8px 0;font-weight:500;cursor:pointer;">
                    <input type="checkbox" name="gm_vendor_segment[]"
                           value="<?php echo esc_attr( $key ); ?>"
                           <?php checked( in_array( $key, $saved_segments, true ) ); ?> />
                    <?php echo esc_html( $label ); ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public function save_description_fields( $store_id, $dokan_settings ) {
        if ( isset( $_POST['gm_store_tagline'] ) ) {
            update_user_meta( $store_id, 'gm_store_tagline',
                sanitize_text_field( wp_unslash( $_POST['gm_store_tagline'] ) ) );
        }
        if ( isset( $_POST['gm_store_description'] ) ) {
            update_user_meta( $store_id, 'gm_store_description',
                sanitize_textarea_field( wp_unslash( $_POST['gm_store_description'] ) ) );
        }
        $segments = [];
        if ( ! empty( $_POST['gm_vendor_segment'] ) && is_array( $_POST['gm_vendor_segment'] ) ) {
            $allowed = array_keys( self::SEGMENTS );
            foreach ( $_POST['gm_vendor_segment'] as $seg ) {
                $seg = sanitize_key( $seg );
                if ( in_array( $seg, $allowed, true ) ) {
                    $segments[] = $seg;
                }
            }
        }
        update_user_meta( $store_id, 'gm_vendor_segment', $segments );
    }

    /* ── Assets ─────────────────────────────────────────────────────── */

    public function enqueue_assets() {
        if ( ! function_exists( 'dokan_is_seller_dashboard' ) ) return;

        if ( dokan_is_seller_dashboard() || is_account_page() ) {
            wp_enqueue_style(
                'gm-vendor-topnav',
                GM_URL . 'assets/css/vendor-topnav.css',
                [ 'gm-google-fonts' ],
                filemtime( GM_PATH . 'assets/css/vendor-topnav.css' ) ?: '1.0.0'
            );
        }

        $is_store_page    = function_exists( 'dokan_is_store_page' )    && dokan_is_store_page();
        $is_store_listing = function_exists( 'dokan_is_store_listing' ) && dokan_is_store_listing();
        if ( $is_store_page || $is_store_listing ) {
            wp_enqueue_style(
                'gm-store-front',
                GM_URL . 'assets/css/store-front.css',
                [ 'gm-google-fonts' ],
                filemtime( GM_PATH . 'assets/css/store-front.css' ) ?: '1.0.0'
            );
        }
    }

}
