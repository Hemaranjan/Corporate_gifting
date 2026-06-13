<?php
/**
 * Giftelier — Vendor Setup Wizard: Sync Step
 *
 * Injects a "Sync your products" step into Dokan's vendor setup wizard
 * between Payment and Ready!, letting new vendors connect their existing
 * platform on day one.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class GM_Vendor_Onboarding {

    public function __construct() {
        // Inject the sync step into Dokan's wizard
        add_filter( 'dokan_seller_wizard_steps', [ $this, 'add_sync_step' ] );

        // Enqueue our styles on the wizard page
        add_action( 'dokan_setup_wizard_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /* ── Register step ──────────────────────────────────────────────── */

    public function add_sync_step( array $steps ): array {
        // Rebuild array inserting 'gm_sync' before 'next_steps'
        $new = [];
        foreach ( $steps as $key => $step ) {
            if ( $key === 'next_steps' ) {
                $new['gm_sync'] = [
                    'name'    => __( 'Sync Products', 'gifting-marketplace' ),
                    'view'    => [ $this, 'render_sync_step' ],
                    'handler' => [ $this, 'handle_sync_step' ],
                ];
            }
            $new[ $key ] = $step;
        }
        return $new;
    }

    /* ── Assets ─────────────────────────────────────────────────────── */

    public function enqueue_assets(): void {
        wp_enqueue_style(
            'gm-onboarding',
            GM_URL . 'assets/css/onboarding.css',
            [],
            filemtime( GM_PATH . 'assets/css/onboarding.css' ) ?: '1.0.0'
        );
        wp_enqueue_script(
            'gm-onboarding',
            GM_URL . 'assets/js/onboarding.js',
            [ 'jquery' ],
            filemtime( GM_PATH . 'assets/js/onboarding.js' ) ?: '1.0.0',
            true
        );
    }

    /* ── View ───────────────────────────────────────────────────────── */

    public function render_sync_step(): void {
        // Back link to re-render this step after save attempt
        $skip_url = esc_url( add_query_arg( 'step', 'next_steps', dokan_get_navigation_url( 'settings' ) ) );
        // Dokan handles next step URL via its own get_next_step_link(), but we
        // grab it directly since we're inside the wizard object's method scope.
        // "Skip" sends a hidden flag so handle_sync_step() knows to no-op.
        ?>
        <div class="gm-ob-wrap">

            <h1 class="gm-ob-heading">
                🔄 <?php esc_html_e( 'Sync your existing products', 'gifting-marketplace' ); ?>
            </h1>
            <p class="gm-ob-sub">
                <?php esc_html_e( 'Already selling on another platform? Connect it now and we\'ll import your products automatically. You can always do this later from your Sync Hub.', 'gifting-marketplace' ); ?>
            </p>

            <form method="post" id="gm-ob-form">
                <?php wp_nonce_field( 'gm_ob_sync_save', 'gm_ob_nonce' ); ?>
                <input type="hidden" name="gm_ob_action" value="sync">
                <input type="hidden" name="gm_ob_platform" id="gm-ob-platform" value="">
                <input type="hidden" name="gm_ob_skip" id="gm-ob-skip" value="">

                <!-- ── Platform cards ───────────────────────────────── -->
                <div class="gm-ob-platforms">

                    <?php
                    $platforms = [
                        'shopify'       => [ 'icon' => '🛍️', 'label' => 'Shopify',          'desc' => 'Connect via Private App token' ],
                        'woocommerce'   => [ 'icon' => '🛒', 'label' => 'WooCommerce',       'desc' => 'Connect via REST API keys' ],
                        'csv'           => [ 'icon' => '📄', 'label' => 'CSV / Excel',        'desc' => 'Upload a product spreadsheet' ],
                        'google_sheets' => [ 'icon' => '📊', 'label' => 'Google Sheets',      'desc' => 'Paste your published Sheet URL' ],
                        'erp'           => [ 'icon' => '🔗', 'label' => 'ERP / REST API',     'desc' => 'Any JSON product feed' ],
                        'skip'          => [ 'icon' => '⏭️', 'label' => 'Skip for now',       'desc' => 'I\'ll set this up later' ],
                    ];
                    foreach ( $platforms as $key => $p ) : ?>
                    <div class="gm-ob-platform<?php echo $key === 'skip' ? ' gm-ob-platform--skip' : ''; ?>"
                         data-platform="<?php echo esc_attr( $key ); ?>">
                        <span class="gm-ob-platform__icon"><?php echo $p['icon']; ?></span>
                        <span class="gm-ob-platform__label"><?php echo esc_html( $p['label'] ); ?></span>
                        <span class="gm-ob-platform__desc"><?php echo esc_html( $p['desc'] ); ?></span>
                    </div>
                    <?php endforeach; ?>

                </div><!-- .gm-ob-platforms -->

                <!-- ── Credential panels (one per platform) ─────────── -->

                <!-- Shopify -->
                <div class="gm-ob-creds" data-creds-for="shopify" hidden>
                    <h3 class="gm-ob-creds__title">Connect your Shopify store</h3>
                    <div class="gm-ob-field">
                        <label class="gm-ob-label">Store URL <span class="gm-ob-req">*</span></label>
                        <input type="text" name="shopify_store_url" class="gm-ob-input"
                               placeholder="yourstore.myshopify.com">
                        <p class="gm-ob-hint">Enter just the domain — no https:// needed</p>
                    </div>
                    <div class="gm-ob-field">
                        <label class="gm-ob-label">Access Token <span class="gm-ob-req">*</span></label>
                        <input type="password" name="shopify_access_token" class="gm-ob-input"
                               placeholder="shpat_xxxxxxxxxxxx">
                        <p class="gm-ob-hint">Shopify Admin → Apps → Private Apps → Admin API access token</p>
                    </div>
                </div>

                <!-- WooCommerce -->
                <div class="gm-ob-creds" data-creds-for="woocommerce" hidden>
                    <h3 class="gm-ob-creds__title">Connect your WooCommerce store</h3>
                    <div class="gm-ob-field">
                        <label class="gm-ob-label">Store URL <span class="gm-ob-req">*</span></label>
                        <input type="text" name="woo_store_url" class="gm-ob-input"
                               placeholder="https://mystore.com">
                    </div>
                    <div class="gm-ob-field">
                        <label class="gm-ob-label">Consumer Key <span class="gm-ob-req">*</span></label>
                        <input type="text" name="woo_consumer_key" class="gm-ob-input"
                               placeholder="ck_xxxxxxxxxxxx">
                    </div>
                    <div class="gm-ob-field">
                        <label class="gm-ob-label">Consumer Secret <span class="gm-ob-req">*</span></label>
                        <input type="password" name="woo_consumer_secret" class="gm-ob-input"
                               placeholder="cs_xxxxxxxxxxxx">
                        <p class="gm-ob-hint">WooCommerce → Settings → Advanced → REST API</p>
                    </div>
                </div>

                <!-- CSV / Excel -->
                <div class="gm-ob-creds" data-creds-for="csv" hidden>
                    <h3 class="gm-ob-creds__title">Upload your product file</h3>
                    <p class="gm-ob-hint" style="margin-bottom:12px">
                        Supported: CSV, XLS, XLSX — max 5 MB.<br>
                        Required columns: <code>title</code>, <code>price</code>. Optional: <code>sku</code>, <code>description</code>, <code>image_url</code>, <code>stock</code>.
                    </p>
                    <div class="gm-ob-field">
                        <label class="gm-ob-label">Product file <span class="gm-ob-req">*</span></label>
                        <input type="file" name="csv_file" class="gm-ob-input-file" accept=".csv,.xls,.xlsx">
                    </div>
                </div>

                <!-- Google Sheets -->
                <div class="gm-ob-creds" data-creds-for="google_sheets" hidden>
                    <h3 class="gm-ob-creds__title">Connect your Google Sheet</h3>
                    <div class="gm-ob-field">
                        <label class="gm-ob-label">Google Sheets share URL <span class="gm-ob-req">*</span></label>
                        <input type="url" name="sheets_url" class="gm-ob-input"
                               placeholder="https://docs.google.com/spreadsheets/d/…">
                        <p class="gm-ob-hint">File → Share → "Anyone with the link can view" → copy the URL</p>
                    </div>
                </div>

                <!-- ERP / REST API -->
                <div class="gm-ob-creds" data-creds-for="erp" hidden>
                    <h3 class="gm-ob-creds__title">Connect your REST API / ERP</h3>
                    <div class="gm-ob-field">
                        <label class="gm-ob-label">API Endpoint URL <span class="gm-ob-req">*</span></label>
                        <input type="url" name="erp_api_url" class="gm-ob-input"
                               placeholder="https://erp.example.com/api/products">
                    </div>
                    <div class="gm-ob-field">
                        <label class="gm-ob-label">API Key</label>
                        <input type="password" name="erp_api_key" class="gm-ob-input" placeholder="Leave blank if not required">
                    </div>
                    <div class="gm-ob-field">
                        <label class="gm-ob-label">Auth Type</label>
                        <select name="erp_auth_type" class="gm-ob-input">
                            <option value="bearer">Bearer Token</option>
                            <option value="basic">Basic Auth</option>
                            <option value="query_param">Query Parameter</option>
                            <option value="none">None</option>
                        </select>
                    </div>
                </div>

                <!-- ── Sync interval (shown when a real platform is chosen) ── -->
                <div class="gm-ob-interval" id="gm-ob-interval-row" hidden>
                    <label class="gm-ob-label">Auto-sync every</label>
                    <select name="sync_interval" class="gm-ob-input gm-ob-input--narrow">
                        <option value="1hour" selected>Every hour</option>
                        <option value="30min">Every 30 minutes</option>
                        <option value="6hours">Every 6 hours</option>
                        <option value="daily">Daily</option>
                    </select>
                    <p class="gm-ob-hint">Products will be imported as drafts. You can review and publish from your Products page.</p>
                </div>

                <!-- ── Actions ──────────────────────────────────────── -->
                <p class="gm-ob-actions" id="gm-ob-actions" hidden>
                    <button type="submit" class="button-primary button button-large dokan-btn-theme gm-ob-submit-btn">
                        <?php esc_html_e( 'Connect & Continue →', 'gifting-marketplace' ); ?>
                    </button>
                    <a href="#" class="button button-large gm-ob-skip-link">
                        <?php esc_html_e( 'Skip for now', 'gifting-marketplace' ); ?>
                    </a>
                </p>

                <!-- Skip action (shown when skip card selected) -->
                <p class="gm-ob-skip-actions" id="gm-ob-skip-actions" hidden>
                    <button type="submit" class="button-primary button button-large dokan-btn-theme">
                        <?php esc_html_e( 'Continue to Dashboard →', 'gifting-marketplace' ); ?>
                    </button>
                </p>

            </form>
        </div><!-- .gm-ob-wrap -->
        <?php
    }

    /* ── Handler ────────────────────────────────────────────────────── */

    public function handle_sync_step(): void {
        if ( ! isset( $_POST['gm_ob_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gm_ob_nonce'] ) ), 'gm_ob_sync_save' ) ) {
            return;
        }

        $skip     = ! empty( $_POST['gm_ob_skip'] );
        $platform = sanitize_key( $_POST['gm_ob_platform'] ?? '' );

        // Skip or no platform chosen → just proceed
        if ( $skip || empty( $platform ) || $platform === 'skip' ) {
            return;
        }

        $vendor_id = get_current_user_id();
        $interval  = sanitize_key( $_POST['sync_interval'] ?? '1hour' );
        $creds     = [];

        switch ( $platform ) {
            case 'shopify':
                $creds = [
                    'store_url'    => sanitize_text_field( $_POST['shopify_store_url']    ?? '' ),
                    'access_token' => sanitize_text_field( $_POST['shopify_access_token'] ?? '' ),
                ];
                break;

            case 'woocommerce':
                $creds = [
                    'store_url'       => esc_url_raw( $_POST['woo_store_url']       ?? '' ),
                    'consumer_key'    => sanitize_text_field( $_POST['woo_consumer_key']    ?? '' ),
                    'consumer_secret' => sanitize_text_field( $_POST['woo_consumer_secret'] ?? '' ),
                ];
                break;

            case 'csv':
                // CSV is handled post-wizard via the Sync Hub upload; save intent only
                update_user_meta( $vendor_id, 'gm_onboarding_sync_pending', 'csv' );
                return;

            case 'google_sheets':
                $creds = [
                    'sheets_url' => esc_url_raw( $_POST['sheets_url'] ?? '' ),
                ];
                break;

            case 'erp':
                $creds = [
                    'api_url'   => esc_url_raw( $_POST['erp_api_url']   ?? '' ),
                    'api_key'   => sanitize_text_field( $_POST['erp_api_key']   ?? '' ),
                    'auth_type' => sanitize_key( $_POST['erp_auth_type'] ?? 'bearer' ),
                ];
                break;
        }

        // Validate required fields are present
        $has_creds = array_filter( $creds );
        if ( empty( $has_creds ) || ! class_exists( 'GM_Sync_Connection' ) ) {
            return;
        }

        // Create the connection — this schedules cron automatically
        $result = GM_Sync_Connection::create( $vendor_id, $platform, $creds, $interval );

        if ( ! is_wp_error( $result ) ) {
            update_user_meta( $vendor_id, 'gm_onboarding_sync_connection_id', $result );
            // Kick off an immediate background sync so the vendor has products on first login
            wp_schedule_single_event( time() + 30, 'gm_sync_periodic', [ $result ] );
        }
    }
}
