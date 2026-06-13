<?php
/**
 * GM_Product_Industry — Industry + Gift Category selector on product edit screens.
 *
 * Adds two dropdowns (Industry → Gift Category) to:
 *   • WP Admin product meta box
 *   • Dokan vendor product editor
 *
 * On save:
 *   - Assigns the selected gift-type slug as the product's WooCommerce category.
 *   - Adds the industry as a WooCommerce product tag (for shop-page strip queries).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class GM_Product_Industry {

    const INDUSTRIES = [
        'corporate'    => 'Corporate Gifting',
        'school'       => 'Schools & Education',
        'wedding'      => 'Weddings & Events',
        'hospitals'    => 'Healthcare',
        'construction' => 'Construction & Real Estate',
    ];

    const GIFT_TYPES = [
        'hamper'             => '🧺 Hamper',
        'premium-gifts'      => '✨ Premium',
        'personalized-gifts' => '🎨 Personalized',
        'eco-friendly-gifts' => '🌿 Eco-Friendly',
        'handcrafted-gifts'  => '🤲 Handcrafted',
        'local-artisan-gifts'=> '🏺 Local Artisan',
    ];

    /* Gifting programs per industry — used by the vendor product editor */
    const PROGRAMS = [
        'corporate' => [
            'employee-gifting'    => '🤝 Employee Gifting',
            'client-appreciation' => '🎖️ Client Appreciation',
            'festival-gifting'    => '🎉 Festival Gifting',
            'event-giveaways'     => '📢 Event Giveaways',
        ],
        'school' => [
            'annual-day-gifts'   => '🏆 Annual Day Gifts',
            'student-awards'     => '🥇 Student Awards',
            'staff-recognition'  => '👩‍🏫 Staff Recognition',
            'graduation-gifts'   => '🎓 Graduation Gifts',
        ],
        'wedding' => [
            'return-gifts'        => '🎁 Return Gifts',
            'guest-welcome-kits'  => '🙏 Guest Welcome Kits',
            'wedding-hampers'     => '💍 Wedding Hampers',
            'event-favours'       => '🎊 Event Favours',
        ],
        'hospitals' => [
            'doctor-appreciation'  => '👨‍⚕️ Doctor Appreciation',
            'staff-rewards'        => '🏅 Staff Rewards',
            'patient-welcome-kits' => '🛏️ Patient Welcome Kits',
            'recovery-gifts'       => '💊 Recovery Gifts',
        ],
        'construction' => [
            'housewarming-gifts'      => '🏠 Housewarming Gifts',
            'customer-handover-kits'  => '🔑 Customer Handover Kits',
            'partner-appreciation'    => '🤝 Partner Appreciation',
            'project-milestone-gifts' => '🏗️ Project Milestone Gifts',
        ],
    ];

    public function __construct() {
        add_action( 'add_meta_boxes',    [ $this, 'add_meta_box'     ] );
        add_action( 'save_post_product', [ $this, 'save_meta_box'    ], 10, 2 );

        add_action( 'dokan_product_edit_after_title', [ $this, 'render_dokan_fields' ], 10, 2 );
        add_action( 'dokan_new_product_added',        [ $this, 'save_dokan_fields'   ], 10, 2 );
        add_action( 'dokan_product_updated',          [ $this, 'save_dokan_fields'   ], 10, 2 );

        /* Hide Dokan's native tags field — industry/program tags are managed automatically */
        add_action( 'wp_head', [ $this, 'hide_dokan_tags' ] );
    }

    public function hide_dokan_tags() {
        if ( ! function_exists( 'dokan_is_seller_dashboard' ) ) return;
        if ( ! dokan_is_seller_dashboard() ) return;
        echo '<style>.dokan-add-tag-section,.dokan-product-tag-content,.dokan-tag-content{display:none!important}</style>';
    }

    /* ── WP Admin meta box ──────────────────────────────────────── */

    public function add_meta_box() {
        add_meta_box(
            'gm_product_industry',
            'Industry & Gift Category',
            [ $this, 'render_meta_box' ],
            'product',
            'side',
            'high'
        );
    }

    public function render_meta_box( $post ) {
        $saved_industry = get_post_meta( $post->ID, '_gm_industry',       true );
        $saved_program  = get_post_meta( $post->ID, '_gm_gifting_program', true );
        $saved_type     = get_post_meta( $post->ID, '_gm_gift_type',       true );
        wp_nonce_field( 'gm_product_industry_save', 'gm_pi_nonce' );
        $this->render_fields( $saved_industry, $saved_program, $saved_type, 'admin' );
    }

    public function save_meta_box( $post_id, $post ) {
        if ( ! isset( $_POST['gm_pi_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gm_pi_nonce'] ) ), 'gm_product_industry_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        $this->persist( $post_id );
    }

    /* ── Dokan vendor editor ───────────────────────────────────── */

    public function render_dokan_fields( $post, $post_id ) {
        $vendor_id       = get_current_user_id();
        $vendor_segments = array_filter( (array) get_user_meta( $vendor_id, 'gm_vendor_segment', true ) );
        $saved_industry  = get_post_meta( $post->ID, '_gm_industry',        true );
        $saved_program   = get_post_meta( $post->ID, '_gm_gifting_program',  true );
        $saved_type      = get_post_meta( $post->ID, '_gm_gift_type',        true );

        wp_nonce_field( 'gm_product_industry_save', 'gm_pi_nonce' );
        echo '<div class="dokan-form-group" style="margin-top:20px">';

        if ( empty( $vendor_segments ) ) {
            $settings_url = function_exists( 'dokan_get_navigation_url' )
                ? dokan_get_navigation_url( 'settings/store' ) : '#';
            echo '<p style="color:#92400e;background:#fffbeb;border:1px solid #fcd34d;'
               . 'padding:12px 16px;border-radius:6px;margin:0">'
               . '<strong>Industry not configured.</strong> '
               . '<a href="' . esc_url( $settings_url ) . '">Set your Store Industry</a>'
               . ' in your profile before adding products.</p>';
            echo '</div>';
            return;
        }

        /* ── Industry — locked to vendor profile ── */
        if ( count( $vendor_segments ) === 1 ) {
            $ind_key   = reset( $vendor_segments );
            $ind_label = self::INDUSTRIES[ $ind_key ] ?? $ind_key;
            $store_url = function_exists( 'dokan_get_navigation_url' )
                ? dokan_get_navigation_url( 'settings/store' ) : '#';
            ?>
            <p>
                <label class="dokan-label"><strong>Industry</strong></label>
                <span style="display:block;padding:8px 12px;margin-top:4px;background:#f3f0fa;
                             border:1px solid #d8d0ee;border-radius:4px;font-weight:600;color:#3d1f8a;">
                    <?php echo esc_html( $ind_label ); ?>
                </span>
                <input type="hidden" name="gm_industry" value="<?php echo esc_attr( $ind_key ); ?>" />
                <small style="color:#888;margin-top:4px;display:block">
                    Set by your <a href="<?php echo esc_url( $store_url ); ?>">store profile</a>.
                </small>
            </p>
            <?php
        } else {
            /* Multiple segments — filtered dropdown */
            $ind_key   = in_array( $saved_industry, $vendor_segments, true ) ? $saved_industry : '';
            ?>
            <p>
                <label class="dokan-label" for="gm_industry"><strong>Industry</strong></label>
                <select id="gm_industry" name="gm_industry" class="dokan-form-control" style="margin-top:4px"
                        onchange="gmUpdatePrograms(this.value)">
                    <option value="">— Select Industry —</option>
                    <?php foreach ( $vendor_segments as $key ) :
                        if ( ! isset( self::INDUSTRIES[ $key ] ) ) continue; ?>
                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $ind_key, $key ); ?>>
                        <?php echo esc_html( self::INDUSTRIES[ $key ] ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <?php
        }

        /* ── Gifting Program — filtered to the vendor's industry ── */
        $ind_for_programs = count( $vendor_segments ) === 1 ? reset( $vendor_segments ) : $ind_key;
        $programs         = isset( self::PROGRAMS[ $ind_for_programs ] ) ? self::PROGRAMS[ $ind_for_programs ] : [];
        ?>
        <p style="margin-top:8px" id="gm-program-field">
            <label class="dokan-label" for="gm_gifting_program"><strong>Gifting Program</strong></label>
            <select id="gm_gifting_program" name="gm_gifting_program" class="dokan-form-control" style="margin-top:4px">
                <option value="">— Select Gifting Program —</option>
                <?php foreach ( $programs as $p_slug => $p_label ) : ?>
                <option value="<?php echo esc_attr( $p_slug ); ?>" <?php selected( $saved_program, $p_slug ); ?>>
                    <?php echo esc_html( $p_label ); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </p>

        <?php if ( count( $vendor_segments ) > 1 ) :
            /* JS programs map — used to repopulate the dropdown when industry changes */
            $js_programs = [];
            foreach ( $vendor_segments as $seg_key ) {
                if ( isset( self::PROGRAMS[ $seg_key ] ) ) {
                    foreach ( self::PROGRAMS[ $seg_key ] as $ps => $pl ) {
                        $js_programs[ $seg_key ][] = [ 'value' => $ps, 'label' => $pl ];
                    }
                }
            }
            ?>
        <script>
        var gmPrograms = <?php echo wp_json_encode( $js_programs ); ?>;
        function gmUpdatePrograms(ind) {
            var sel = document.getElementById('gm_gifting_program');
            if (!sel) return;
            sel.innerHTML = '<option value="">— Select Gifting Program —</option>';
            var opts = gmPrograms[ind] || [];
            opts.forEach(function(o) {
                var opt = document.createElement('option');
                opt.value = o.value; opt.text = o.label;
                sel.appendChild(opt);
            });
        }
        </script>
        <?php endif; ?>

        <!-- Gift Category -->
        <p style="margin-top:8px">
            <label class="dokan-label" for="gm_gift_type"><strong>Gift Category</strong></label>
            <select id="gm_gift_type" name="gm_gift_type" class="dokan-form-control" style="margin-top:4px">
                <option value="">— Select Gift Category —</option>
                <?php foreach ( self::GIFT_TYPES as $slug => $label ) : ?>
                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $saved_type, $slug ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
        echo '</div>';
    }

    public function save_dokan_fields( $product_id, $data ) {
        if ( ! isset( $_POST['gm_pi_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gm_pi_nonce'] ) ), 'gm_product_industry_save' ) ) {
            return;
        }
        $this->persist( $product_id );
    }

    /* ── Shared render ─────────────────────────────────────────── */

    private function render_fields( $saved_industry, $saved_program, $saved_type, $context ) {
        $label_class = $context === 'dokan' ? 'dokan-label' : '';
        $input_class = $context === 'dokan' ? 'dokan-form-control' : 'widefat';
        $programs    = $saved_industry && isset( self::PROGRAMS[ $saved_industry ] )
                       ? self::PROGRAMS[ $saved_industry ] : [];
        ?>
        <p>
            <label class="<?php echo esc_attr( $label_class ); ?>" for="gm_industry">
                <strong>Industry</strong>
            </label>
            <select id="gm_industry" name="gm_industry"
                    class="<?php echo esc_attr( $input_class ); ?>" style="margin-top:4px"
                    onchange="gmAdminUpdatePrograms(this.value)">
                <option value="">— Select Industry —</option>
                <?php foreach ( self::INDUSTRIES as $key => $label ) : ?>
                <option value="<?php echo esc_attr( $key ); ?>"
                    <?php selected( $saved_industry, $key ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p style="margin-top:8px">
            <label class="<?php echo esc_attr( $label_class ); ?>" for="gm_gifting_program">
                <strong>Gifting Program</strong>
            </label>
            <select id="gm_gifting_program" name="gm_gifting_program"
                    class="<?php echo esc_attr( $input_class ); ?>" style="margin-top:4px">
                <option value="">— Select Gifting Program —</option>
                <?php foreach ( $programs as $p_slug => $p_label ) : ?>
                <option value="<?php echo esc_attr( $p_slug ); ?>"
                    <?php selected( $saved_program, $p_slug ); ?>>
                    <?php echo esc_html( $p_label ); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p style="margin-top:8px">
            <label class="<?php echo esc_attr( $label_class ); ?>" for="gm_gift_type">
                <strong>Gift Category</strong>
            </label>
            <select id="gm_gift_type" name="gm_gift_type"
                    class="<?php echo esc_attr( $input_class ); ?>" style="margin-top:4px">
                <option value="">— Select Gift Category —</option>
                <?php foreach ( self::GIFT_TYPES as $slug => $label ) : ?>
                <option value="<?php echo esc_attr( $slug ); ?>"
                    <?php selected( $saved_type, $slug ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </p>
        <script>
        var gmAdminPrograms = <?php echo wp_json_encode( self::PROGRAMS ); ?>;
        function gmAdminUpdatePrograms(ind) {
            var sel = document.getElementById('gm_gifting_program');
            if (!sel) return;
            sel.innerHTML = '<option value="">— Select Gifting Program —</option>';
            var opts = gmAdminPrograms[ind] || {};
            Object.keys(opts).forEach(function(slug) {
                var opt = document.createElement('option');
                opt.value = slug; opt.text = opts[slug];
                sel.appendChild(opt);
            });
        }
        </script>
        <?php
    }

    /* ── Persist to DB ─────────────────────────────────────────── */

    private function persist( $product_id ) {
        $valid_industries = array_keys( self::INDUSTRIES );
        $valid_types      = array_keys( self::GIFT_TYPES );

        $all_programs = [];
        foreach ( self::PROGRAMS as $progs ) {
            $all_programs = array_merge( $all_programs, array_keys( $progs ) );
        }

        $industry  = sanitize_key( wp_unslash( $_POST['gm_industry']        ?? '' ) );
        $gift_type = sanitize_key( wp_unslash( $_POST['gm_gift_type']        ?? '' ) );
        $program   = sanitize_key( wp_unslash( $_POST['gm_gifting_program']  ?? '' ) );

        if ( ! in_array( $industry,  $valid_industries, true ) ) $industry  = '';
        if ( ! in_array( $gift_type, $valid_types,      true ) ) $gift_type = '';
        if ( ! in_array( $program,   $all_programs,     true ) ) $program   = '';

        /* Industry meta */
        if ( $industry ) {
            update_post_meta( $product_id, '_gm_industry', $industry );
        } else {
            delete_post_meta( $product_id, '_gm_industry' );
        }

        /* Gift category meta + WooCommerce product_cat term */
        if ( $gift_type ) {
            update_post_meta( $product_id, '_gm_gift_type', $gift_type );
            $existing = wp_get_object_terms( $product_id, 'product_cat', [ 'fields' => 'slugs' ] );
            $keep     = array_diff( $existing, $valid_types );
            $term_obj = get_term_by( 'slug', $gift_type, 'product_cat' );
            if ( $term_obj ) $keep[] = $gift_type;
            wp_set_object_terms( $product_id, array_values( $keep ), 'product_cat' );
        } else {
            delete_post_meta( $product_id, '_gm_gift_type' );
        }

        /* Gifting program meta */
        if ( $program ) {
            update_post_meta( $product_id, '_gm_gifting_program', $program );
        } else {
            delete_post_meta( $product_id, '_gm_gifting_program' );
        }

        /* Product tags: industry + program (replaces any previous industry/program tags) */
        $existing_tags = wp_get_object_terms( $product_id, 'product_tag', [ 'fields' => 'slugs' ] );
        $keep_tags     = array_diff( $existing_tags, $valid_industries, $all_programs );
        if ( $industry ) $keep_tags[] = $industry;
        if ( $program  ) $keep_tags[] = $program;
        wp_set_object_terms( $product_id, array_values( array_unique( $keep_tags ) ), 'product_tag' );
    }
}
