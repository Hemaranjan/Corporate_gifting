<?php
/**
 * Vendor dashboard — Giftelier Sync Hub page.
 *
 * Renders a 4-step import wizard inside the Dokan vendor dashboard layout.
 * Dokan handles get_header/get_footer — this file outputs content only.
 *
 * @package GiftingMarketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$vendor_id   = get_current_user_id();
$saved_config = get_user_meta( $vendor_id, 'gm_sync_config', true );

if ( ! is_array( $saved_config ) ) {
	$saved_config = array();
}

// Saved field helpers.
$cfg_platform        = isset( $saved_config['platform'] ) ? sanitize_text_field( $saved_config['platform'] ) : '';
$cfg_shopify_url     = isset( $saved_config['shopify_url'] ) ? esc_url( $saved_config['shopify_url'] ) : '';
$cfg_shopify_token   = isset( $saved_config['shopify_token'] ) ? esc_attr( $saved_config['shopify_token'] ) : '';
$cfg_woo_url         = isset( $saved_config['woo_url'] ) ? esc_url( $saved_config['woo_url'] ) : '';
$cfg_woo_key         = isset( $saved_config['woo_key'] ) ? esc_attr( $saved_config['woo_key'] ) : '';
$cfg_woo_secret      = isset( $saved_config['woo_secret'] ) ? esc_attr( $saved_config['woo_secret'] ) : '';
$cfg_sheets_url      = isset( $saved_config['sheets_url'] ) ? esc_url( $saved_config['sheets_url'] ) : '';
$cfg_erp_endpoint    = isset( $saved_config['erp_endpoint'] ) ? esc_url( $saved_config['erp_endpoint'] ) : '';
$cfg_erp_key         = isset( $saved_config['erp_key'] ) ? esc_attr( $saved_config['erp_key'] ) : '';
$cfg_erp_auth_type   = isset( $saved_config['erp_auth_type'] ) ? esc_attr( $saved_config['erp_auth_type'] ) : 'bearer';
$cfg_erp_field_map   = isset( $saved_config['erp_field_map'] ) ? esc_textarea( $saved_config['erp_field_map'] ) : '';

$add_product_url = function_exists( 'dokan_get_navigation_url' )
	? dokan_get_navigation_url( 'new-product' )
	: admin_url( 'post-new.php?post_type=product' );

$my_products_url = function_exists( 'dokan_get_navigation_url' )
	? dokan_get_navigation_url( 'products' )
	: admin_url( 'edit.php?post_type=product' );

$nonce = wp_create_nonce( 'gm_sync_hub' );

// Load saved connections for this vendor.
$connections = class_exists( 'GM_Sync_Connection' ) ? GM_Sync_Connection::get_for_vendor( $vendor_id ) : [];

// Platform display helpers.
$platform_icons = array(
	'shopify'     => '🛍️',
	'woocommerce' => '🛒',
	'erp'         => '🔗',
	'csv'         => '📄',
	'sheets'      => '📊',
	'fresh'       => '✨',
);
$platform_labels = array(
	'shopify'     => __( 'Shopify', 'gifting-marketplace' ),
	'woocommerce' => __( 'WooCommerce', 'gifting-marketplace' ),
	'erp'         => __( 'Other Platform', 'gifting-marketplace' ),
	'csv'         => __( 'Excel / CSV', 'gifting-marketplace' ),
	'sheets'      => __( 'Google Sheets', 'gifting-marketplace' ),
	'fresh'       => __( 'Start Fresh', 'gifting-marketplace' ),
);
?>

<div class="gm-sync-hub gm-vdash-wrap">

	<div class="gm-sync-hub__header gm-vdash-header">
		<h2><?php esc_html_e( 'Sync Hub', 'gifting-marketplace' ); ?></h2>
		<p class="gm-sync-hub__header-sub">
			<?php esc_html_e( 'Import and sync your products from any platform.', 'gifting-marketplace' ); ?>
		</p>
	</div>

	<!-- =========================================================
	     CONNECTIONS SECTION — always visible
	     ========================================================= -->
	<?php if ( ! empty( $connections ) ) : ?>

	<div class="gm-sync-connections" id="gm-sync-connections-section">

		<div class="gm-sync-connections__header">
			<h3 class="gm-sync-connections__heading">
				<?php esc_html_e( 'Your Active Syncs', 'gifting-marketplace' ); ?>
			</h3>
			<button type="button" class="gm-sync-btn gm-sync-btn--primary gm-sync-add-connection-btn" id="gm-sync-add-connection-btn">
				<?php esc_html_e( 'Add New Connection', 'gifting-marketplace' ); ?>
				<span class="gm-sync-btn__plus" aria-hidden="true">+</span>
			</button>
		</div>

		<div id="gm-sync-connections-list" class="gm-sync-connections-list">

			<?php foreach ( $connections as $conn ) :
				$conn_id        = isset( $conn['id'] ) ? absint( $conn['id'] ) : 0;
				$conn_platform  = isset( $conn['platform'] ) ? sanitize_text_field( $conn['platform'] ) : '';
				$conn_status    = isset( $conn['status'] ) ? sanitize_text_field( $conn['status'] ) : 'active';
				$conn_last_sync = isset( $conn['last_synced'] ) ? absint( $conn['last_synced'] ) : 0;
				$conn_next_sync = isset( $conn['next_sync'] ) ? absint( $conn['next_sync'] ) : 0;
				$conn_count     = isset( $conn['products_synced'] ) ? absint( $conn['products_synced'] ) : 0;

				$conn_icon  = isset( $platform_icons[ $conn_platform ] ) ? $platform_icons[ $conn_platform ] : '🔗';
				$conn_label = isset( $platform_labels[ $conn_platform ] ) ? $platform_labels[ $conn_platform ] : ucfirst( $conn_platform );

				$is_paused = ( 'paused' === $conn_status );

				// Last synced relative time.
				if ( $conn_last_sync > 0 ) {
					$diff_last   = max( 0, time() - $conn_last_sync );
					$diff_last_m = (int) floor( $diff_last / 60 );
					if ( $diff_last_m < 60 ) {
						/* translators: %d: number of minutes */
						$last_synced_str = sprintf( _n( 'Last synced: %d minute ago', 'Last synced: %d minutes ago', $diff_last_m, 'gifting-marketplace' ), $diff_last_m );
					} elseif ( $diff_last_m < 1440 ) {
						$diff_last_h = (int) floor( $diff_last_m / 60 );
						/* translators: %d: number of hours */
						$last_synced_str = sprintf( _n( 'Last synced: %d hour ago', 'Last synced: %d hours ago', $diff_last_h, 'gifting-marketplace' ), $diff_last_h );
					} else {
						$last_synced_str = __( 'Last synced: more than a day ago', 'gifting-marketplace' );
					}
				} else {
					$last_synced_str = __( 'Never synced', 'gifting-marketplace' );
				}

				// Next sync relative time.
				if ( $conn_next_sync > 0 && ! $is_paused ) {
					$diff_next   = max( 0, $conn_next_sync - time() );
					$diff_next_m = (int) ceil( $diff_next / 60 );
					if ( $diff_next_m < 60 ) {
						/* translators: %d: number of minutes */
						$next_sync_str = sprintf( _n( 'Next sync: in %d minute', 'Next sync: in %d minutes', max( 1, $diff_next_m ), 'gifting-marketplace' ), max( 1, $diff_next_m ) );
					} else {
						$diff_next_h = (int) ceil( $diff_next_m / 60 );
						/* translators: %d: number of hours */
						$next_sync_str = sprintf( _n( 'Next sync: in %d hour', 'Next sync: in %d hours', $diff_next_h, 'gifting-marketplace' ), $diff_next_h );
					}
				} else {
					$next_sync_str = $is_paused ? __( 'Sync paused', 'gifting-marketplace' ) : __( 'Next sync: —', 'gifting-marketplace' );
				}
			?>

			<div class="gm-sync-connection-card" data-connection-id="<?php echo esc_attr( $conn_id ); ?>" data-platform="<?php echo esc_attr( $conn_platform ); ?>">

				<div class="gm-sync-connection-card__identity">
					<span class="gm-sync-connection-card__icon" aria-hidden="true"><?php echo esc_html( $conn_icon ); ?></span>
					<span class="gm-sync-connection-card__name"><?php echo esc_html( $conn_label ); ?></span>
					<span class="gm-sync-connection-card__badge gm-sync-status-badge gm-sync-status-badge--<?php echo esc_attr( $conn_status ); ?>">
						<?php echo $is_paused ? esc_html__( 'Paused', 'gifting-marketplace' ) : esc_html__( 'Active', 'gifting-marketplace' ); ?>
					</span>
				</div>

				<div class="gm-sync-connection-card__meta">
					<span class="gm-sync-connection-card__meta-item"><?php echo esc_html( $last_synced_str ); ?></span>
					<span class="gm-sync-connection-card__meta-sep" aria-hidden="true">·</span>
					<span class="gm-sync-connection-card__meta-item"><?php echo esc_html( $next_sync_str ); ?></span>
					<span class="gm-sync-connection-card__meta-sep" aria-hidden="true">·</span>
					<span class="gm-sync-connection-card__meta-item">
						<?php
						/* translators: %d: number of products */
						printf( esc_html( _n( 'Products synced: %d', 'Products synced: %d', $conn_count, 'gifting-marketplace' ) ), esc_html( $conn_count ) );
						?>
					</span>
				</div>

				<div class="gm-sync-connection-card__actions">
					<button type="button"
						class="gm-sync-btn gm-sync-btn--secondary gm-sync-conn-sync-now"
						data-connection-id="<?php echo esc_attr( $conn_id ); ?>">
						<?php esc_html_e( 'Sync Now', 'gifting-marketplace' ); ?>
					</button>
					<button type="button"
						class="gm-sync-btn gm-sync-btn--secondary gm-sync-conn-pause-resume"
						data-connection-id="<?php echo esc_attr( $conn_id ); ?>"
						data-current-status="<?php echo esc_attr( $conn_status ); ?>">
						<?php echo $is_paused ? esc_html__( 'Resume', 'gifting-marketplace' ) : esc_html__( 'Pause', 'gifting-marketplace' ); ?>
					</button>
					<button type="button"
						class="gm-sync-btn gm-sync-btn--ghost gm-sync-conn-settings"
						data-connection-id="<?php echo esc_attr( $conn_id ); ?>"
						aria-label="<?php esc_attr_e( 'Connection settings', 'gifting-marketplace' ); ?>">
						&#9881; <?php esc_html_e( 'Settings', 'gifting-marketplace' ); ?>
					</button>
					<button type="button"
						class="gm-sync-btn gm-sync-btn--danger gm-sync-conn-delete"
						data-connection-id="<?php echo esc_attr( $conn_id ); ?>"
						aria-label="<?php esc_attr_e( 'Delete connection', 'gifting-marketplace' ); ?>">
						<?php esc_html_e( 'Delete', 'gifting-marketplace' ); ?>
					</button>
				</div>

			</div><!-- .gm-sync-connection-card -->

			<?php endforeach; ?>

		</div><!-- #gm-sync-connections-list -->

	</div><!-- .gm-sync-connections -->

	<!-- Wizard shown only when "Add New Connection +" is clicked (JS toggles hidden attr) -->
	<div class="gm-sync-wizard-wrap" id="gm-sync-wizard-wrap" hidden>

	<?php else : ?>

	<!-- No connections yet — show wizard directly -->
	<div class="gm-sync-wizard-wrap" id="gm-sync-wizard-wrap">

	<?php endif; ?>

	<!-- Step Indicators -->
	<div class="gm-sync-steps" role="tablist" aria-label="<?php esc_attr_e( 'Wizard steps', 'gifting-marketplace' ); ?>">
		<div class="gm-sync-steps__item gm-sync-steps__item--active" data-step="1" role="tab" aria-selected="true">
			<span class="gm-sync-steps__number">1</span>
			<span class="gm-sync-steps__label"><?php esc_html_e( 'Platform', 'gifting-marketplace' ); ?></span>
		</div>
		<div class="gm-sync-steps__connector" aria-hidden="true"></div>
		<div class="gm-sync-steps__item" data-step="2" role="tab" aria-selected="false">
			<span class="gm-sync-steps__number">2</span>
			<span class="gm-sync-steps__label"><?php esc_html_e( 'Connect', 'gifting-marketplace' ); ?></span>
		</div>
		<div class="gm-sync-steps__connector" aria-hidden="true"></div>
		<div class="gm-sync-steps__item" data-step="3" role="tab" aria-selected="false">
			<span class="gm-sync-steps__number">3</span>
			<span class="gm-sync-steps__label"><?php esc_html_e( 'Review', 'gifting-marketplace' ); ?></span>
		</div>
		<div class="gm-sync-steps__connector" aria-hidden="true"></div>
		<div class="gm-sync-steps__item" data-step="4" role="tab" aria-selected="false">
			<span class="gm-sync-steps__number">4</span>
			<span class="gm-sync-steps__label"><?php esc_html_e( 'Results', 'gifting-marketplace' ); ?></span>
		</div>
	</div>

	<!-- =========================================================
	     STEP 1 — Choose Platform
	     ========================================================= -->
	<div class="gm-sync-pane gm-sync-pane--active" data-pane="1" role="tabpanel">

		<div class="gm-sync-pane__inner">
			<h3 class="gm-sync-pane__heading">
				<?php esc_html_e( 'How do you manage your products?', 'gifting-marketplace' ); ?>
			</h3>
			<p class="gm-sync-pane__sub">
				<?php esc_html_e( 'Connect your existing product source and we\'ll handle the rest.', 'gifting-marketplace' ); ?>
			</p>

			<div class="gm-sync-platforms">

				<button type="button"
					class="gm-sync-platform-card<?php echo 'shopify' === $cfg_platform ? ' gm-sync-platform-card--selected' : ''; ?>"
					data-platform="shopify"
					aria-pressed="<?php echo 'shopify' === $cfg_platform ? 'true' : 'false'; ?>">
					<span class="gm-sync-platform-card__icon" aria-hidden="true">🛍️</span>
					<span class="gm-sync-platform-card__name"><?php esc_html_e( 'Shopify', 'gifting-marketplace' ); ?></span>
					<span class="gm-sync-platform-card__desc"><?php esc_html_e( 'Sync products from your Shopify store via Private App token.', 'gifting-marketplace' ); ?></span>
				</button>

				<button type="button"
					class="gm-sync-platform-card<?php echo 'woocommerce' === $cfg_platform ? ' gm-sync-platform-card--selected' : ''; ?>"
					data-platform="woocommerce"
					aria-pressed="<?php echo 'woocommerce' === $cfg_platform ? 'true' : 'false'; ?>">
					<span class="gm-sync-platform-card__icon" aria-hidden="true">🛒</span>
					<span class="gm-sync-platform-card__name"><?php esc_html_e( 'WooCommerce', 'gifting-marketplace' ); ?></span>
					<span class="gm-sync-platform-card__desc"><?php esc_html_e( 'Pull products from another WooCommerce site using the REST API.', 'gifting-marketplace' ); ?></span>
				</button>

				<button type="button"
					class="gm-sync-platform-card<?php echo 'erp' === $cfg_platform ? ' gm-sync-platform-card--selected' : ''; ?>"
					data-platform="erp"
					aria-pressed="<?php echo 'erp' === $cfg_platform ? 'true' : 'false'; ?>">
					<span class="gm-sync-platform-card__icon" aria-hidden="true">🔗</span>
					<span class="gm-sync-platform-card__name"><?php esc_html_e( 'Other Platform', 'gifting-marketplace' ); ?></span>
					<span class="gm-sync-platform-card__desc"><?php esc_html_e( 'REST API or ERP — bring any JSON product feed.', 'gifting-marketplace' ); ?></span>
				</button>

				<button type="button"
					class="gm-sync-platform-card<?php echo 'csv' === $cfg_platform ? ' gm-sync-platform-card--selected' : ''; ?>"
					data-platform="csv"
					aria-pressed="<?php echo 'csv' === $cfg_platform ? 'true' : 'false'; ?>">
					<span class="gm-sync-platform-card__icon" aria-hidden="true">📄</span>
					<span class="gm-sync-platform-card__name"><?php esc_html_e( 'Excel / CSV', 'gifting-marketplace' ); ?></span>
					<span class="gm-sync-platform-card__desc"><?php esc_html_e( 'Upload a spreadsheet file (.csv or .xlsx) with your product list.', 'gifting-marketplace' ); ?></span>
				</button>

				<button type="button"
					class="gm-sync-platform-card<?php echo 'sheets' === $cfg_platform ? ' gm-sync-platform-card--selected' : ''; ?>"
					data-platform="sheets"
					aria-pressed="<?php echo 'sheets' === $cfg_platform ? 'true' : 'false'; ?>">
					<span class="gm-sync-platform-card__icon" aria-hidden="true">📊</span>
					<span class="gm-sync-platform-card__name"><?php esc_html_e( 'Google Sheets', 'gifting-marketplace' ); ?></span>
					<span class="gm-sync-platform-card__desc"><?php esc_html_e( 'Paste a shared Google Sheet URL and we\'ll read your product data.', 'gifting-marketplace' ); ?></span>
				</button>

				<button type="button"
					class="gm-sync-platform-card gm-sync-platform-card--start-fresh<?php echo 'fresh' === $cfg_platform ? ' gm-sync-platform-card--selected' : ''; ?>"
					data-platform="fresh"
					aria-pressed="<?php echo 'fresh' === $cfg_platform ? 'true' : 'false'; ?>">
					<span class="gm-sync-platform-card__icon" aria-hidden="true">✨</span>
					<span class="gm-sync-platform-card__name"><?php esc_html_e( 'Start Fresh', 'gifting-marketplace' ); ?></span>
					<span class="gm-sync-platform-card__desc"><?php esc_html_e( 'No existing store — add products manually one by one.', 'gifting-marketplace' ); ?></span>
				</button>

			</div><!-- .gm-sync-platforms -->

			<!-- "Start Fresh" message panel -->
			<div class="gm-sync-fresh-msg" aria-hidden="true" hidden>
				<div class="gm-sync-fresh-msg__inner">
					<span class="gm-sync-fresh-msg__icon" aria-hidden="true">✨</span>
					<h4><?php esc_html_e( 'Ready to build your catalogue?', 'gifting-marketplace' ); ?></h4>
					<p><?php esc_html_e( 'You can add products one at a time from the product editor. Come back here any time you want to import in bulk.', 'gifting-marketplace' ); ?></p>
					<a href="<?php echo esc_url( $add_product_url ); ?>" class="gm-sync-btn gm-sync-btn--primary">
						<?php esc_html_e( 'Add Your First Product', 'gifting-marketplace' ); ?>
					</a>
				</div>
			</div>

			<div class="gm-sync-pane__actions">
				<button type="button" class="gm-sync-btn gm-sync-btn--primary gm-sync-next-btn" data-next="2" disabled>
					<?php esc_html_e( 'Continue', 'gifting-marketplace' ); ?>
					<span class="gm-sync-btn__arrow" aria-hidden="true">→</span>
				</button>
			</div>

		</div>
	</div><!-- step 1 pane -->


	<!-- =========================================================
	     STEP 2 — Connect / Configure
	     ========================================================= -->
	<div class="gm-sync-pane" data-pane="2" role="tabpanel" hidden>
		<div class="gm-sync-pane__inner">

			<h3 class="gm-sync-pane__heading">
				<?php esc_html_e( 'Connect your', 'gifting-marketplace' ); ?>
				<span class="gm-sync-platform-label"></span>
			</h3>

			<!-- Hidden field to carry platform choice -->
			<input type="hidden" id="gm-sync-chosen-platform" value="<?php echo esc_attr( $cfg_platform ); ?>">
			<input type="hidden" id="gm-sync-nonce" value="<?php echo esc_attr( $nonce ); ?>">

			<!-- ---- Shopify form ---- -->
			<form class="gm-sync-form gm-sync-form--shopify" data-platform-form="shopify" novalidate>
				<div class="gm-sync-field">
					<label class="gm-sync-label" for="gm-shopify-url">
						<?php esc_html_e( 'Store URL', 'gifting-marketplace' ); ?>
						<span class="gm-sync-label__required" aria-hidden="true">*</span>
					</label>
					<input
						type="url"
						id="gm-shopify-url"
						name="shopify_url"
						class="gm-sync-input"
						placeholder="yourstore.myshopify.com"
						value="<?php echo $cfg_shopify_url; ?>"
						autocomplete="off"
						required>
					<span class="gm-sync-field__hint">
						<?php esc_html_e( 'e.g. yourstore.myshopify.com — no https:// needed.', 'gifting-marketplace' ); ?>
					</span>
				</div>
				<div class="gm-sync-field">
					<label class="gm-sync-label" for="gm-shopify-token">
						<?php esc_html_e( 'Access Token', 'gifting-marketplace' ); ?>
						<span class="gm-sync-label__required" aria-hidden="true">*</span>
					</label>
					<input
						type="password"
						id="gm-shopify-token"
						name="shopify_token"
						class="gm-sync-input"
						placeholder="shpat_xxxxxxxxxxxx"
						value="<?php echo $cfg_shopify_token; ?>"
						autocomplete="new-password"
						required>
					<span class="gm-sync-field__hint">
						<?php esc_html_e( 'Find in Shopify Admin → Apps → Private Apps → Admin API access token.', 'gifting-marketplace' ); ?>
					</span>
				</div>

				<!-- Sync Settings -->
				<div class="gm-sync-field gm-sync-field--sync-settings">
					<h4 class="gm-sync-field__heading"><?php esc_html_e( 'Sync Settings', 'gifting-marketplace' ); ?></h4>
					<div class="gm-sync-setting-row">
						<label class="gm-sync-label" for="gm-shopify-sync-interval">
							<?php esc_html_e( 'Sync interval', 'gifting-marketplace' ); ?>
						</label>
						<select id="gm-shopify-sync-interval" name="sync_interval" class="gm-sync-select">
							<option value="15"><?php esc_html_e( 'Every 15 min', 'gifting-marketplace' ); ?></option>
							<option value="30"><?php esc_html_e( 'Every 30 min', 'gifting-marketplace' ); ?></option>
							<option value="60" selected><?php esc_html_e( 'Every hour', 'gifting-marketplace' ); ?></option>
							<option value="360"><?php esc_html_e( 'Every 6 hours', 'gifting-marketplace' ); ?></option>
							<option value="1440"><?php esc_html_e( 'Daily', 'gifting-marketplace' ); ?></option>
						</select>
					</div>
					<div class="gm-sync-setting-row gm-sync-setting-row--checkbox">
						<label class="gm-sync-cb-label">
							<input type="checkbox" name="enable_webhook" class="gm-sync-checkbox" value="1">
							<span class="gm-sync-cb-label__text">
								<?php esc_html_e( 'Enable real-time webhook sync', 'gifting-marketplace' ); ?>
							</span>
						</label>
						<span class="gm-sync-field__hint">
							<?php esc_html_e( 'Get instant updates when products change on source', 'gifting-marketplace' ); ?>
						</span>
					</div>
					<p class="gm-sync-field__note">
						<?php esc_html_e( 'Products will be imported as drafts. You can publish them from your Products page.', 'gifting-marketplace' ); ?>
					</p>
				</div>

				<div class="gm-sync-form__actions">
					<button type="button" class="gm-sync-btn gm-sync-btn--secondary gm-sync-test-btn" data-test="shopify">
						<?php esc_html_e( 'Test Connection', 'gifting-marketplace' ); ?>
					</button>
					<span class="gm-sync-test-result" aria-live="polite"></span>
				</div>
			</form>

			<!-- ---- WooCommerce form ---- -->
			<form class="gm-sync-form gm-sync-form--woocommerce" data-platform-form="woocommerce" novalidate>
				<div class="gm-sync-field">
					<label class="gm-sync-label" for="gm-woo-url">
						<?php esc_html_e( 'Store URL', 'gifting-marketplace' ); ?>
						<span class="gm-sync-label__required" aria-hidden="true">*</span>
					</label>
					<input
						type="url"
						id="gm-woo-url"
						name="woo_url"
						class="gm-sync-input"
						placeholder="https://mystore.com"
						value="<?php echo $cfg_woo_url; ?>"
						autocomplete="off"
						required>
				</div>
				<div class="gm-sync-field">
					<label class="gm-sync-label" for="gm-woo-key">
						<?php esc_html_e( 'Consumer Key', 'gifting-marketplace' ); ?>
						<span class="gm-sync-label__required" aria-hidden="true">*</span>
					</label>
					<input
						type="text"
						id="gm-woo-key"
						name="woo_key"
						class="gm-sync-input"
						placeholder="ck_xxxxxxxxxxxx"
						value="<?php echo $cfg_woo_key; ?>"
						autocomplete="off"
						required>
				</div>
				<div class="gm-sync-field">
					<label class="gm-sync-label" for="gm-woo-secret">
						<?php esc_html_e( 'Consumer Secret', 'gifting-marketplace' ); ?>
						<span class="gm-sync-label__required" aria-hidden="true">*</span>
					</label>
					<input
						type="password"
						id="gm-woo-secret"
						name="woo_secret"
						class="gm-sync-input"
						placeholder="cs_xxxxxxxxxxxx"
						value="<?php echo $cfg_woo_secret; ?>"
						autocomplete="new-password"
						required>
					<span class="gm-sync-field__hint">
						<?php esc_html_e( 'WooCommerce → Settings → Advanced → REST API → Add key.', 'gifting-marketplace' ); ?>
					</span>
				</div>

				<!-- Sync Settings -->
				<div class="gm-sync-field gm-sync-field--sync-settings">
					<h4 class="gm-sync-field__heading"><?php esc_html_e( 'Sync Settings', 'gifting-marketplace' ); ?></h4>
					<div class="gm-sync-setting-row">
						<label class="gm-sync-label" for="gm-woo-sync-interval">
							<?php esc_html_e( 'Sync interval', 'gifting-marketplace' ); ?>
						</label>
						<select id="gm-woo-sync-interval" name="sync_interval" class="gm-sync-select">
							<option value="15"><?php esc_html_e( 'Every 15 min', 'gifting-marketplace' ); ?></option>
							<option value="30"><?php esc_html_e( 'Every 30 min', 'gifting-marketplace' ); ?></option>
							<option value="60" selected><?php esc_html_e( 'Every hour', 'gifting-marketplace' ); ?></option>
							<option value="360"><?php esc_html_e( 'Every 6 hours', 'gifting-marketplace' ); ?></option>
							<option value="1440"><?php esc_html_e( 'Daily', 'gifting-marketplace' ); ?></option>
						</select>
					</div>
					<div class="gm-sync-setting-row gm-sync-setting-row--checkbox">
						<label class="gm-sync-cb-label">
							<input type="checkbox" name="enable_webhook" class="gm-sync-checkbox" value="1">
							<span class="gm-sync-cb-label__text">
								<?php esc_html_e( 'Enable real-time webhook sync', 'gifting-marketplace' ); ?>
							</span>
						</label>
						<span class="gm-sync-field__hint">
							<?php esc_html_e( 'Get instant updates when products change on source', 'gifting-marketplace' ); ?>
						</span>
					</div>
					<p class="gm-sync-field__note">
						<?php esc_html_e( 'Products will be imported as drafts. You can publish them from your Products page.', 'gifting-marketplace' ); ?>
					</p>
				</div>

				<div class="gm-sync-form__actions">
					<button type="button" class="gm-sync-btn gm-sync-btn--secondary gm-sync-test-btn" data-test="woocommerce">
						<?php esc_html_e( 'Test Connection', 'gifting-marketplace' ); ?>
					</button>
					<span class="gm-sync-test-result" aria-live="polite"></span>
				</div>
			</form>

			<!-- ---- CSV / Excel form ---- -->
			<form class="gm-sync-form gm-sync-form--csv" data-platform-form="csv" novalidate enctype="multipart/form-data">
				<div class="gm-sync-field">
					<label class="gm-sync-label" for="gm-csv-file">
						<?php esc_html_e( 'Upload File', 'gifting-marketplace' ); ?>
						<span class="gm-sync-label__required" aria-hidden="true">*</span>
					</label>

					<div class="gm-sync-dropzone" id="gm-sync-dropzone" role="button" tabindex="0"
						aria-label="<?php esc_attr_e( 'Drop CSV or XLSX file here or click to browse', 'gifting-marketplace' ); ?>">
						<span class="gm-sync-dropzone__icon" aria-hidden="true">📂</span>
						<span class="gm-sync-dropzone__primary">
							<?php esc_html_e( 'Drag & drop your file here', 'gifting-marketplace' ); ?>
						</span>
						<span class="gm-sync-dropzone__secondary">
							<?php esc_html_e( 'or', 'gifting-marketplace' ); ?>
							<span class="gm-sync-dropzone__browse"><?php esc_html_e( 'browse to upload', 'gifting-marketplace' ); ?></span>
						</span>
						<span class="gm-sync-dropzone__meta">
							<?php esc_html_e( 'Supported formats: .csv, .xlsx — max 10 MB', 'gifting-marketplace' ); ?>
						</span>
						<input
							type="file"
							id="gm-csv-file"
							name="csv_file"
							class="gm-sync-dropzone__input"
							accept=".csv,.xlsx"
							aria-hidden="true"
							tabindex="-1">
					</div>

					<span class="gm-sync-dropzone__filename" aria-live="polite"></span>
				</div>

				<div class="gm-sync-field gm-sync-field--column-map">
					<h4 class="gm-sync-field__heading">
						<?php esc_html_e( 'Column Mapping Guide', 'gifting-marketplace' ); ?>
					</h4>
					<p class="gm-sync-field__hint">
						<?php esc_html_e( 'Make sure your file has a header row. Recognised column names:', 'gifting-marketplace' ); ?>
					</p>
					<ul class="gm-sync-column-map-list">
						<li><code>title</code> / <code>name</code> — <?php esc_html_e( 'Product title', 'gifting-marketplace' ); ?></li>
						<li><code>price</code> / <code>regular_price</code> — <?php esc_html_e( 'Regular price (numeric)', 'gifting-marketplace' ); ?></li>
						<li><code>sku</code> — <?php esc_html_e( 'Stock keeping unit', 'gifting-marketplace' ); ?></li>
						<li><code>description</code> / <code>body</code> — <?php esc_html_e( 'Product description', 'gifting-marketplace' ); ?></li>
						<li><code>status</code> — <code>publish</code> / <code>draft</code></li>
						<li><code>image_url</code> / <code>image</code> — <?php esc_html_e( 'Featured image URL', 'gifting-marketplace' ); ?></li>
						<li><code>categories</code> — <?php esc_html_e( 'Comma-separated category names', 'gifting-marketplace' ); ?></li>
					</ul>
				</div>
			</form>

			<!-- ---- Google Sheets form ---- -->
			<form class="gm-sync-form gm-sync-form--sheets" data-platform-form="sheets" novalidate>
				<div class="gm-sync-field">
					<label class="gm-sync-label" for="gm-sheets-url">
						<?php esc_html_e( 'Google Sheet Share URL', 'gifting-marketplace' ); ?>
						<span class="gm-sync-label__required" aria-hidden="true">*</span>
					</label>
					<input
						type="url"
						id="gm-sheets-url"
						name="sheets_url"
						class="gm-sync-input"
						placeholder="https://docs.google.com/spreadsheets/d/…/edit?usp=sharing"
						value="<?php echo $cfg_sheets_url; ?>"
						autocomplete="off"
						required>
					<span class="gm-sync-field__hint">
						<?php esc_html_e( 'In Google Sheets: File → Share → Share with anyone (Viewer). Copy the link and paste it above.', 'gifting-marketplace' ); ?>
					</span>
				</div>
				<div class="gm-sync-field gm-sync-field--column-map">
					<p class="gm-sync-field__hint">
						<?php esc_html_e( 'Your sheet must have a header row using the same column names listed for CSV above. The first sheet (tab) will be used.', 'gifting-marketplace' ); ?>
					</p>
				</div>

				<!-- Sync Settings -->
				<div class="gm-sync-field gm-sync-field--sync-settings">
					<h4 class="gm-sync-field__heading"><?php esc_html_e( 'Sync Settings', 'gifting-marketplace' ); ?></h4>
					<div class="gm-sync-setting-row">
						<label class="gm-sync-label" for="gm-sheets-sync-interval">
							<?php esc_html_e( 'Sync interval', 'gifting-marketplace' ); ?>
						</label>
						<select id="gm-sheets-sync-interval" name="sync_interval" class="gm-sync-select">
							<option value="15"><?php esc_html_e( 'Every 15 min', 'gifting-marketplace' ); ?></option>
							<option value="30"><?php esc_html_e( 'Every 30 min', 'gifting-marketplace' ); ?></option>
							<option value="60" selected><?php esc_html_e( 'Every hour', 'gifting-marketplace' ); ?></option>
							<option value="360"><?php esc_html_e( 'Every 6 hours', 'gifting-marketplace' ); ?></option>
							<option value="1440"><?php esc_html_e( 'Daily', 'gifting-marketplace' ); ?></option>
						</select>
					</div>
					<div class="gm-sync-setting-row gm-sync-setting-row--checkbox">
						<label class="gm-sync-cb-label">
							<input type="checkbox" name="enable_webhook" class="gm-sync-checkbox" value="1">
							<span class="gm-sync-cb-label__text">
								<?php esc_html_e( 'Enable real-time webhook sync', 'gifting-marketplace' ); ?>
							</span>
						</label>
						<span class="gm-sync-field__hint">
							<?php esc_html_e( 'Get instant updates when products change on source', 'gifting-marketplace' ); ?>
						</span>
					</div>
					<p class="gm-sync-field__note">
						<?php esc_html_e( 'Products will be imported as drafts. You can publish them from your Products page.', 'gifting-marketplace' ); ?>
					</p>
				</div>
			</form>

			<!-- ---- ERP / REST API form ---- -->
			<form class="gm-sync-form gm-sync-form--erp" data-platform-form="erp" novalidate>
				<div class="gm-sync-field">
					<label class="gm-sync-label" for="gm-erp-endpoint">
						<?php esc_html_e( 'API Endpoint URL', 'gifting-marketplace' ); ?>
						<span class="gm-sync-label__required" aria-hidden="true">*</span>
					</label>
					<input
						type="url"
						id="gm-erp-endpoint"
						name="erp_endpoint"
						class="gm-sync-input"
						placeholder="https://erp.example.com/api/products"
						value="<?php echo $cfg_erp_endpoint; ?>"
						autocomplete="off"
						required>
				</div>
				<div class="gm-sync-field">
					<label class="gm-sync-label" for="gm-erp-key">
						<?php esc_html_e( 'API Key', 'gifting-marketplace' ); ?>
					</label>
					<input
						type="password"
						id="gm-erp-key"
						name="erp_key"
						class="gm-sync-input"
						placeholder="<?php esc_attr_e( 'Leave blank if not required', 'gifting-marketplace' ); ?>"
						value="<?php echo $cfg_erp_key; ?>"
						autocomplete="new-password">
				</div>
				<div class="gm-sync-field">
					<label class="gm-sync-label" for="gm-erp-auth-type">
						<?php esc_html_e( 'Authentication Type', 'gifting-marketplace' ); ?>
					</label>
					<select id="gm-erp-auth-type" name="erp_auth_type" class="gm-sync-select">
						<option value="bearer"      <?php selected( $cfg_erp_auth_type, 'bearer' ); ?>><?php esc_html_e( 'Bearer Token', 'gifting-marketplace' ); ?></option>
						<option value="basic"       <?php selected( $cfg_erp_auth_type, 'basic' ); ?>><?php esc_html_e( 'HTTP Basic Auth', 'gifting-marketplace' ); ?></option>
						<option value="query_param" <?php selected( $cfg_erp_auth_type, 'query_param' ); ?>><?php esc_html_e( 'Query Parameter (?api_key=…)', 'gifting-marketplace' ); ?></option>
						<option value="none"        <?php selected( $cfg_erp_auth_type, 'none' ); ?>><?php esc_html_e( 'None / Public', 'gifting-marketplace' ); ?></option>
					</select>
				</div>
				<div class="gm-sync-field">
					<label class="gm-sync-label" for="gm-erp-field-map">
						<?php esc_html_e( 'Field Mapping (optional JSON)', 'gifting-marketplace' ); ?>
					</label>
					<textarea
						id="gm-erp-field-map"
						name="erp_field_map"
						class="gm-sync-textarea"
						rows="6"
						placeholder='{"title":"name","price":"unit_price","sku":"product_code"}'><?php echo $cfg_erp_field_map; ?></textarea>
					<span class="gm-sync-field__hint">
						<?php esc_html_e( 'Map your API response field names to Giftelier fields. Keys = Giftelier field, values = your API field. Leave blank to use default mapping.', 'gifting-marketplace' ); ?>
					</span>
				</div>

				<!-- Sync Settings -->
				<div class="gm-sync-field gm-sync-field--sync-settings">
					<h4 class="gm-sync-field__heading"><?php esc_html_e( 'Sync Settings', 'gifting-marketplace' ); ?></h4>
					<div class="gm-sync-setting-row">
						<label class="gm-sync-label" for="gm-erp-sync-interval">
							<?php esc_html_e( 'Sync interval', 'gifting-marketplace' ); ?>
						</label>
						<select id="gm-erp-sync-interval" name="sync_interval" class="gm-sync-select">
							<option value="15"><?php esc_html_e( 'Every 15 min', 'gifting-marketplace' ); ?></option>
							<option value="30"><?php esc_html_e( 'Every 30 min', 'gifting-marketplace' ); ?></option>
							<option value="60" selected><?php esc_html_e( 'Every hour', 'gifting-marketplace' ); ?></option>
							<option value="360"><?php esc_html_e( 'Every 6 hours', 'gifting-marketplace' ); ?></option>
							<option value="1440"><?php esc_html_e( 'Daily', 'gifting-marketplace' ); ?></option>
						</select>
					</div>
					<div class="gm-sync-setting-row gm-sync-setting-row--checkbox">
						<label class="gm-sync-cb-label">
							<input type="checkbox" name="enable_webhook" class="gm-sync-checkbox" value="1">
							<span class="gm-sync-cb-label__text">
								<?php esc_html_e( 'Enable real-time webhook sync', 'gifting-marketplace' ); ?>
							</span>
						</label>
						<span class="gm-sync-field__hint">
							<?php esc_html_e( 'Get instant updates when products change on source', 'gifting-marketplace' ); ?>
						</span>
					</div>
					<p class="gm-sync-field__note">
						<?php esc_html_e( 'Products will be imported as drafts. You can publish them from your Products page.', 'gifting-marketplace' ); ?>
					</p>
				</div>

				<div class="gm-sync-form__actions">
					<button type="button" class="gm-sync-btn gm-sync-btn--secondary gm-sync-test-btn" data-test="erp">
						<?php esc_html_e( 'Test Connection', 'gifting-marketplace' ); ?>
					</button>
					<span class="gm-sync-test-result" aria-live="polite"></span>
				</div>
			</form>

			<!-- Bottom navigation for Step 2 -->
			<div class="gm-sync-pane__actions">
				<button type="button" class="gm-sync-btn gm-sync-btn--ghost gm-sync-back-btn" data-back="1">
					<span class="gm-sync-btn__arrow" aria-hidden="true">←</span>
					<?php esc_html_e( 'Back', 'gifting-marketplace' ); ?>
				</button>
				<button type="button" class="gm-sync-btn gm-sync-btn--primary" id="gm-sync-fetch-btn">
					<?php esc_html_e( 'Fetch Products', 'gifting-marketplace' ); ?>
					<span class="gm-sync-btn__arrow" aria-hidden="true">→</span>
				</button>
			</div>

		</div>
	</div><!-- step 2 pane -->


	<!-- =========================================================
	     STEP 3 — Review Products
	     ========================================================= -->
	<div class="gm-sync-pane" data-pane="3" role="tabpanel" hidden>
		<div class="gm-sync-pane__inner">

			<h3 class="gm-sync-pane__heading">
				<?php esc_html_e( 'Review Products', 'gifting-marketplace' ); ?>
			</h3>

			<!-- Loading state -->
			<div class="gm-sync-loading" id="gm-sync-review-loading" aria-live="polite">
				<span class="gm-sync-spinner" role="status" aria-label="<?php esc_attr_e( 'Fetching products…', 'gifting-marketplace' ); ?>"></span>
				<span class="gm-sync-loading__text">
					<?php esc_html_e( 'Fetching your products…', 'gifting-marketplace' ); ?>
				</span>
			</div>

			<!-- Product table (populated by JS) -->
			<div class="gm-sync-review-wrap" id="gm-sync-review-wrap" hidden>

				<div class="gm-sync-review-summary" aria-live="polite">
					<strong class="gm-sync-review-count" id="gm-sync-product-count">0</strong>
					<?php esc_html_e( 'products found from', 'gifting-marketplace' ); ?>
					<span class="gm-sync-platform-label-inline" id="gm-sync-review-platform"></span>
				</div>

				<div class="gm-sync-table-wrap">
					<table class="gm-sync-table" id="gm-sync-product-table">
						<thead>
							<tr>
								<th class="gm-sync-table__col-check">
									<label class="gm-sync-cb-label">
										<input
											type="checkbox"
											id="gm-sync-select-all"
											class="gm-sync-checkbox"
											aria-label="<?php esc_attr_e( 'Select all products', 'gifting-marketplace' ); ?>">
										<span class="gm-sync-cb-label__text"><?php esc_html_e( 'All', 'gifting-marketplace' ); ?></span>
									</label>
								</th>
								<th class="gm-sync-table__col-image"><?php esc_html_e( 'Image', 'gifting-marketplace' ); ?></th>
								<th class="gm-sync-table__col-title"><?php esc_html_e( 'Title', 'gifting-marketplace' ); ?></th>
								<th class="gm-sync-table__col-price"><?php esc_html_e( 'Price', 'gifting-marketplace' ); ?></th>
								<th class="gm-sync-table__col-sku"><?php esc_html_e( 'SKU', 'gifting-marketplace' ); ?></th>
								<th class="gm-sync-table__col-status"><?php esc_html_e( 'Status', 'gifting-marketplace' ); ?></th>
							</tr>
						</thead>
						<tbody id="gm-sync-product-tbody">
							<!-- Rows injected by gm-sync.js -->
						</tbody>
					</table>
				</div>

				<div class="gm-sync-pane__actions">
					<button type="button" class="gm-sync-btn gm-sync-btn--ghost gm-sync-back-btn" data-back="2">
						<span class="gm-sync-btn__arrow" aria-hidden="true">←</span>
						<?php esc_html_e( 'Back', 'gifting-marketplace' ); ?>
					</button>
					<button type="button" class="gm-sync-btn gm-sync-btn--primary" id="gm-sync-import-btn" disabled>
						<?php esc_html_e( 'Import Selected', 'gifting-marketplace' ); ?>
						<span class="gm-sync-import-badge" id="gm-sync-import-count" aria-live="polite">0</span>
					</button>
				</div>

			</div><!-- .gm-sync-review-wrap -->

		</div>
	</div><!-- step 3 pane -->


	<!-- =========================================================
	     STEP 4 — Sync Results
	     ========================================================= -->
	<div class="gm-sync-pane" data-pane="4" role="tabpanel" hidden>
		<div class="gm-sync-pane__inner">

			<h3 class="gm-sync-pane__heading">
				<?php esc_html_e( 'Sync Results', 'gifting-marketplace' ); ?>
			</h3>

			<!-- Progress state -->
			<div class="gm-sync-progress-wrap" id="gm-sync-progress-wrap" aria-live="polite">
				<p class="gm-sync-progress-label" id="gm-sync-progress-label">
					<?php esc_html_e( 'Importing products…', 'gifting-marketplace' ); ?>
				</p>
				<div class="gm-sync-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
					<div class="gm-sync-progress-bar__fill" id="gm-sync-progress-fill" style="width:0%"></div>
				</div>
				<p class="gm-sync-progress-fraction" id="gm-sync-progress-fraction">0 / 0</p>
			</div>

			<!-- Completion summary -->
			<div class="gm-sync-results-summary" id="gm-sync-results-summary" hidden aria-live="assertive">

				<div class="gm-sync-results-stat gm-sync-results-stat--success">
					<span class="gm-sync-results-stat__icon" aria-hidden="true">✅</span>
					<span class="gm-sync-results-stat__count" id="gm-sync-success-count">0</span>
					<span class="gm-sync-results-stat__label"><?php esc_html_e( 'products imported successfully', 'gifting-marketplace' ); ?></span>
				</div>

				<div class="gm-sync-results-stat gm-sync-results-stat--warn" id="gm-sync-fail-stat" hidden>
					<span class="gm-sync-results-stat__icon" aria-hidden="true">⚠️</span>
					<span class="gm-sync-results-stat__count" id="gm-sync-fail-count">0</span>
					<span class="gm-sync-results-stat__label"><?php esc_html_e( 'failed', 'gifting-marketplace' ); ?></span>
				</div>

				<!-- Auto-sync status notices (shown conditionally by JS based on configured settings) -->
				<div class="gm-sync-autosync-notices" id="gm-sync-autosync-notices" aria-live="polite">

					<!-- Shown when a scheduled sync interval was configured -->
					<div class="gm-sync-autosync-notice gm-sync-autosync-notice--interval" id="gm-sync-notice-interval" hidden>
						<span class="gm-sync-autosync-notice__icon" aria-hidden="true">&#x2705;</span>
						<span class="gm-sync-autosync-notice__text">
							<?php esc_html_e( 'Auto-sync is active', 'gifting-marketplace' ); ?> &mdash;
							<?php esc_html_e( 'products will update automatically every', 'gifting-marketplace' ); ?>
							<strong class="gm-sync-autosync-interval-label" id="gm-sync-interval-label"></strong>
						</span>
					</div>

					<!-- Shown when webhook sync was enabled -->
					<div class="gm-sync-autosync-notice gm-sync-autosync-notice--webhook" id="gm-sync-notice-webhook" hidden>
						<span class="gm-sync-autosync-notice__icon" aria-hidden="true">&#x1F514;</span>
						<span class="gm-sync-autosync-notice__text">
							<?php esc_html_e( 'Webhook registered', 'gifting-marketplace' ); ?> &mdash;
							<?php esc_html_e( 'changes on', 'gifting-marketplace' ); ?>
							<strong class="gm-sync-autosync-platform-label" id="gm-sync-webhook-platform-label"></strong>
							<?php esc_html_e( 'will sync in real time', 'gifting-marketplace' ); ?>
						</span>
					</div>

				</div><!-- .gm-sync-autosync-notices -->

				<!-- Failed items detail -->
				<div class="gm-sync-failed-items" id="gm-sync-failed-items" hidden>
					<h4 class="gm-sync-failed-items__heading">
						<?php esc_html_e( 'Failed imports', 'gifting-marketplace' ); ?>
					</h4>
					<ul class="gm-sync-failed-list" id="gm-sync-failed-list" aria-label="<?php esc_attr_e( 'Failed product list', 'gifting-marketplace' ); ?>">
						<!-- Populated by JS: <li><strong>Product Title</strong> — reason</li> -->
					</ul>
				</div>

				<div class="gm-sync-pane__actions">
					<a href="<?php echo esc_url( $my_products_url ); ?>" class="gm-sync-btn gm-sync-btn--primary">
						<?php esc_html_e( 'View Products', 'gifting-marketplace' ); ?>
					</a>
					<button type="button" class="gm-sync-btn gm-sync-btn--ghost" id="gm-sync-again-btn">
						<?php esc_html_e( 'Sync Again', 'gifting-marketplace' ); ?>
					</button>
				</div>

			</div><!-- .gm-sync-results-summary -->

			<!-- Sync History -->
			<div class="gm-sync-history" id="gm-sync-history">
				<h4 class="gm-sync-history__heading">
					<?php esc_html_e( 'Recent Sync History', 'gifting-marketplace' ); ?>
				</h4>

				<?php
				$sync_log = get_user_meta( $vendor_id, 'gm_sync_log', true );
				if ( ! is_array( $sync_log ) ) {
					$sync_log = array();
				}
				// Newest-first, max 5.
				$sync_log = array_reverse( array_slice( $sync_log, -5 ) );
				?>

				<?php if ( empty( $sync_log ) ) : ?>
					<p class="gm-sync-history__empty">
						<?php esc_html_e( 'No sync jobs yet.', 'gifting-marketplace' ); ?>
					</p>
				<?php else : ?>
					<div class="gm-sync-table-wrap">
						<table class="gm-sync-table gm-sync-table--history">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Date', 'gifting-marketplace' ); ?></th>
									<th><?php esc_html_e( 'Platform', 'gifting-marketplace' ); ?></th>
									<th><?php esc_html_e( 'Imported', 'gifting-marketplace' ); ?></th>
									<th><?php esc_html_e( 'Failed', 'gifting-marketplace' ); ?></th>
									<th><?php esc_html_e( 'Status', 'gifting-marketplace' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $sync_log as $job ) : ?>
									<?php
									$job_platform  = isset( $job['platform'] ) ? ucfirst( sanitize_text_field( $job['platform'] ) ) : '—';
									$job_imported  = isset( $job['imported'] ) ? absint( $job['imported'] ) : 0;
									$job_failed    = isset( $job['failed'] ) ? absint( $job['failed'] ) : 0;
									$job_status    = isset( $job['status'] ) ? sanitize_text_field( $job['status'] ) : 'unknown';
									$job_ts        = isset( $job['timestamp'] ) ? absint( $job['timestamp'] ) : 0;
									$job_date      = $job_ts ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $job_ts ) : '—';
									$status_class  = 'complete' === $job_status ? 'gm-sync-status--complete' : 'gm-sync-status--partial';
									?>
									<tr>
										<td><?php echo esc_html( $job_date ); ?></td>
										<td><?php echo esc_html( $job_platform ); ?></td>
										<td><?php echo esc_html( $job_imported ); ?></td>
										<td><?php echo esc_html( $job_failed ); ?></td>
										<td>
											<span class="gm-sync-status <?php echo esc_attr( $status_class ); ?>">
												<?php echo esc_html( ucfirst( $job_status ) ); ?>
											</span>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>

			</div><!-- .gm-sync-history -->

		</div>
	</div><!-- step 4 pane -->

	</div><!-- #gm-sync-wizard-wrap -->

</div><!-- .gm-sync-hub -->

<?php
// Localise data for the front-end JS.
wp_localize_script(
	'gm-sync-hub',
	'gmSyncData',
	array(
		'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
		'nonce'        => $nonce,
		'vendorId'     => $vendor_id,
		'savedConfig'  => array(
			'platform'     => $cfg_platform,
			'shopify_url'  => $cfg_shopify_url,
			'woo_url'      => $cfg_woo_url,
			'sheets_url'   => $cfg_sheets_url,
			'erp_endpoint' => $cfg_erp_endpoint,
			'erp_auth_type'=> $cfg_erp_auth_type,
		),
		'strings'      => array(
			'testing'        => __( 'Testing…', 'gifting-marketplace' ),
			'connected'      => __( '✅ Connected!', 'gifting-marketplace' ),
			'connectionFail' => __( '❌ Connection failed. Check your credentials.', 'gifting-marketplace' ),
			'fetching'       => __( 'Fetching your products…', 'gifting-marketplace' ),
			'importing'      => __( 'Importing products…', 'gifting-marketplace' ),
			'noProducts'     => __( 'No products were returned. Check your connection settings.', 'gifting-marketplace' ),
			'selectFile'     => __( 'No file selected', 'gifting-marketplace' ),
			'platforms'      => array(
				'shopify'     => __( 'Shopify', 'gifting-marketplace' ),
				'woocommerce' => __( 'WooCommerce', 'gifting-marketplace' ),
				'csv'         => __( 'Excel / CSV', 'gifting-marketplace' ),
				'sheets'      => __( 'Google Sheets', 'gifting-marketplace' ),
				'erp'         => __( 'Other Platform', 'gifting-marketplace' ),
				'fresh'       => __( 'Start Fresh', 'gifting-marketplace' ),
			),
		),
	)
);
