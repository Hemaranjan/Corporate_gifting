<?php
/**
 * Giftelier Sync Hub — Main Orchestrator Class
 *
 * Registers Dokan dashboard nav, AJAX handlers, and the sync job database table.
 * Routes fetch / import requests to the appropriate connector.
 *
 * @package GiftingMarketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once GM_PATH . 'includes/sync/class-sync-shopify.php';
require_once GM_PATH . 'includes/sync/class-sync-woocommerce.php';
require_once GM_PATH . 'includes/sync/class-sync-csv.php';
require_once GM_PATH . 'includes/sync/class-sync-erp.php';

/**
 * Class GM_Sync_Hub
 *
 * Coordinates multi-platform product importing from the Dokan vendor dashboard.
 */
class GM_Sync_Hub {

	/**
	 * Dokan query-var key for this dashboard page.
	 */
	const QUERY_VAR = 'gm-sync-hub';

	/**
	 * Constructor — registers all hooks.
	 */
	public function __construct() {
		// Dokan dashboard integration.
		add_filter( 'dokan_query_var_filter',   array( $this, 'add_query_var'      ) );
		add_filter( 'dokan_get_dashboard_nav',  array( $this, 'add_nav_item'       ) );
		add_action( 'dokan_load_custom_template', array( $this, 'load_template'    ) );

		// Assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX — logged-in only (sellers are always logged in).
		add_action( 'wp_ajax_gm_sync_test',        array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_gm_sync_fetch',        array( $this, 'ajax_fetch_products' ) );
		add_action( 'wp_ajax_gm_sync_import',       array( $this, 'ajax_import_products' ) );
		add_action( 'wp_ajax_gm_sync_history',      array( $this, 'ajax_get_history' ) );
		add_action( 'wp_ajax_gm_sync_save_config',  array( $this, 'ajax_save_config' ) );

		// Connection management AJAX handlers.
		add_action( 'wp_ajax_gm_sync_save_connection',   array( $this, 'ajax_save_connection' ) );
		add_action( 'wp_ajax_gm_sync_delete_connection',  array( $this, 'ajax_delete_connection' ) );
		add_action( 'wp_ajax_gm_sync_toggle_connection',  array( $this, 'ajax_toggle_connection' ) );
		add_action( 'wp_ajax_gm_sync_now',               array( $this, 'ajax_sync_now' ) );
		add_action( 'wp_ajax_gm_sync_get_connections',    array( $this, 'ajax_get_connections' ) );
		add_action( 'wp_ajax_gm_sync_register_webhook',   array( $this, 'ajax_register_webhook_proxy' ) );

		// Sub-system classes.
		require_once GM_PATH . 'includes/class-sync-scheduler.php';
		require_once GM_PATH . 'includes/class-sync-webhooks.php';
		require_once GM_PATH . 'includes/class-sync-connection.php';
		new GM_Sync_Scheduler();
		new GM_Sync_Webhooks();
	}

	// =========================================================================
	// Dokan dashboard integration
	// =========================================================================

	/**
	 * Register the gm-sync-hub query var with Dokan.
	 *
	 * @param array $vars Existing Dokan query vars.
	 * @return array
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Inject the Sync Hub nav item into the Dokan vendor dashboard menu.
	 *
	 * @param array $urls Existing nav items.
	 * @return array
	 */
	public function add_nav_item( array $urls ): array {
		$urls[ self::QUERY_VAR ] = array(
			'title' => __( 'Sync Hub', 'gifting-marketplace' ),
			'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>',
			'url'   => dokan_get_navigation_url( self::QUERY_VAR ),
			'pos'   => 45,
		);
		return $urls;
	}

	/**
	 * Load the Sync Hub template when the query var is active.
	 *
	 * @param array $query_vars Active Dokan query vars.
	 */
	public function load_template( array $query_vars ): void {
		if ( isset( $query_vars[ self::QUERY_VAR ] ) ) {
			$template = GM_PATH . 'templates/vendor/sync-hub.php';
			if ( file_exists( $template ) ) {
				include $template;
			}
		}
	}

	// =========================================================================
	// Asset enqueuing
	// =========================================================================

	/**
	 * Enqueue Sync Hub CSS and JS on the Dokan seller dashboard only.
	 */
	public function enqueue_assets(): void {
		if ( ! function_exists( 'dokan_is_seller_dashboard' ) || ! dokan_is_seller_dashboard() ) {
			return;
		}

		$css_path = GM_PATH . 'assets/css/sync-hub.css';
		$js_path  = GM_PATH . 'assets/js/sync-hub.js';
		$css_ver  = file_exists( $css_path ) ? filemtime( $css_path ) : '1.0.0';
		$js_ver   = file_exists( $js_path )  ? filemtime( $js_path )  : '1.0.0';

		wp_enqueue_style(
			'gm-sync-hub',
			GM_URL . 'assets/css/sync-hub.css',
			array(),
			$css_ver
		);

		wp_enqueue_script(
			'gm-sync-hub',
			GM_URL . 'assets/js/sync-hub.js',
			array( 'jquery' ),
			$js_ver,
			true
		);

		wp_localize_script(
			'gm-sync-hub',
			'gmSyncHub',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'gm_sync_hub' ),
				'vendorId'      => get_current_user_id(),
				'syncIntervals' => array(
					'15min'  => __( 'Every 15 minutes', 'gifting-marketplace' ),
					'30min'  => __( 'Every 30 minutes', 'gifting-marketplace' ),
					'1hour'  => __( 'Every hour', 'gifting-marketplace' ),
					'6hours' => __( 'Every 6 hours', 'gifting-marketplace' ),
					'daily'  => __( 'Daily', 'gifting-marketplace' ),
				),
			)
		);
	}

	// =========================================================================
	// Database
	// =========================================================================

	/**
	 * Create the wp_gm_sync_jobs, wp_gm_sync_connections, and wp_gm_sync_product_map
	 * tables using dbDelta.
	 *
	 * Called on plugin activation and lazily on first init.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Sync jobs table.
		$jobs_table = $wpdb->prefix . 'gm_sync_jobs';
		$sql_jobs   = "CREATE TABLE {$jobs_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			vendor_id bigint(20) UNSIGNED NOT NULL,
			source_type varchar(30) NOT NULL,
			source_config longtext DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			total int(11) NOT NULL DEFAULT 0,
			synced int(11) NOT NULL DEFAULT 0,
			failed int(11) NOT NULL DEFAULT 0,
			log longtext DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			completed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY vendor (vendor_id)
		) {$charset};";

		dbDelta( $sql_jobs );

		// Sync connections table.
		$conn_table = $wpdb->prefix . 'gm_sync_connections';
		$sql_conn   = "CREATE TABLE {$conn_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			vendor_id bigint(20) UNSIGNED NOT NULL,
			platform varchar(30) NOT NULL,
			credentials longtext DEFAULT NULL,
			webhook_id varchar(100) DEFAULT NULL,
			webhook_secret varchar(100) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			sync_interval varchar(20) NOT NULL DEFAULT '1hour',
			last_sync datetime DEFAULT NULL,
			next_sync datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY vendor (vendor_id)
		) {$charset};";

		dbDelta( $sql_conn );

		// Sync product map table.
		$map_table = $wpdb->prefix . 'gm_sync_product_map';
		$sql_map   = "CREATE TABLE {$map_table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			connection_id bigint(20) UNSIGNED NOT NULL,
			external_id varchar(100) NOT NULL,
			wc_product_id bigint(20) UNSIGNED NOT NULL,
			checksum varchar(32) DEFAULT NULL,
			last_synced datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY conn_ext (connection_id, external_id),
			KEY wc_product (wc_product_id)
		) {$charset};";

		dbDelta( $sql_map );
	}

	// =========================================================================
	// AJAX: test connection
	// =========================================================================

	/**
	 * AJAX handler — test whether the supplied credentials can reach the source.
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'gm_sync_hub', 'nonce' );

		if ( ! dokan_is_user_seller( get_current_user_id() ) ) {
			wp_send_json_error( __( 'You must be a registered vendor to use the Sync Hub.', 'gifting-marketplace' ) );
			return;
		}

		$source_type = sanitize_key( wp_unslash( $_POST['source_type'] ?? '' ) );
		$raw_creds   = wp_unslash( $_POST['credentials'] ?? '{}' );
		$credentials = json_decode( $raw_creds, true );

		if ( ! is_array( $credentials ) ) {
			$credentials = array();
		}

		$result = $this->route_test( $source_type, $credentials );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
			return;
		}

		wp_send_json_success( array( 'ok' => true ) );
	}

	// =========================================================================
	// AJAX: fetch products (preview)
	// =========================================================================

	/**
	 * AJAX handler — fetch and return normalised products from the source without importing.
	 */
	public function ajax_fetch_products(): void {
		check_ajax_referer( 'gm_sync_hub', 'nonce' );

		if ( ! dokan_is_user_seller( get_current_user_id() ) ) {
			wp_send_json_error( __( 'You must be a registered vendor to use the Sync Hub.', 'gifting-marketplace' ) );
			return;
		}

		$source_type = sanitize_key( wp_unslash( $_POST['source_type'] ?? '' ) );
		$raw_creds   = wp_unslash( $_POST['credentials'] ?? '{}' );
		$credentials = json_decode( $raw_creds, true );

		if ( ! is_array( $credentials ) ) {
			$credentials = array();
		}

		$file     = isset( $_FILES['file'] ) ? $_FILES['file'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$products = $this->route_fetch( $source_type, $credentials, $file );

		if ( is_wp_error( $products ) ) {
			wp_send_json_error( $products->get_error_message() );
			return;
		}

		wp_send_json_success(
			array(
				'products' => $products,
				'total'    => count( $products ),
			)
		);
	}

	// =========================================================================
	// AJAX: import products
	// =========================================================================

	/**
	 * AJAX handler — import a set of normalised products as WooCommerce draft listings.
	 */
	public function ajax_import_products(): void {
		check_ajax_referer( 'gm_sync_hub', 'nonce' );

		$vendor_id = get_current_user_id();

		if ( ! dokan_is_user_seller( $vendor_id ) ) {
			wp_send_json_error( __( 'You must be a registered vendor to use the Sync Hub.', 'gifting-marketplace' ) );
			return;
		}

		$source_type  = sanitize_key( wp_unslash( $_POST['source_type'] ?? '' ) );
		$raw_products = wp_unslash( $_POST['products'] ?? '[]' );
		$products     = json_decode( $raw_products, true );

		if ( ! is_array( $products ) || empty( $products ) ) {
			wp_send_json_error( __( 'No products supplied for import.', 'gifting-marketplace' ) );
			return;
		}

		$job_id = $this->create_sync_job( $vendor_id, $source_type, count( $products ) );

		$synced  = 0;
		$failed  = 0;
		$log     = array();

		// Ensure media_sideload_image is available outside the admin context.
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		foreach ( $products as $product ) {
			$result = $this->import_single_product( $product, $vendor_id );

			if ( is_wp_error( $result ) ) {
				$failed++;
				$log[] = array(
					'title'  => sanitize_text_field( $product['title'] ?? '' ),
					'status' => 'failed',
					'error'  => $result->get_error_message(),
				);
			} else {
				$synced++;
				$log[] = array(
					'title'      => sanitize_text_field( $product['title'] ?? '' ),
					'status'     => 'success',
					'product_id' => $result,
				);
			}
		}

		$this->update_sync_job( $job_id, $synced, $failed, $log );

		wp_send_json_success(
			array(
				'synced' => $synced,
				'failed' => $failed,
				'job_id' => $job_id,
			)
		);
	}

	// =========================================================================
	// AJAX: sync history
	// =========================================================================

	/**
	 * AJAX handler — return the last 10 sync jobs for the current vendor.
	 */
	public function ajax_get_history(): void {
		check_ajax_referer( 'gm_sync_hub', 'nonce' );

		$vendor_id = get_current_user_id();

		if ( ! dokan_is_user_seller( $vendor_id ) ) {
			wp_send_json_error( __( 'You must be a registered vendor to use the Sync Hub.', 'gifting-marketplace' ) );
			return;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'gm_sync_jobs';
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT id, source_type, status, total, synced, failed, created_at, completed_at
				 FROM {$table}
				 WHERE vendor_id = %d
				 ORDER BY created_at DESC
				 LIMIT 10",
				$vendor_id
			),
			ARRAY_A
		);

		wp_send_json_success( array( 'jobs' => $rows ? $rows : array() ) );
	}

	// =========================================================================
	// AJAX: save config
	// =========================================================================

	/**
	 * AJAX handler — persist source type and masked credentials to vendor user meta.
	 */
	public function ajax_save_config(): void {
		check_ajax_referer( 'gm_sync_hub', 'nonce' );

		$vendor_id = get_current_user_id();

		if ( ! dokan_is_user_seller( $vendor_id ) ) {
			wp_send_json_error( __( 'You must be a registered vendor to use the Sync Hub.', 'gifting-marketplace' ) );
			return;
		}

		$source_type = sanitize_key( wp_unslash( $_POST['source_type'] ?? '' ) );
		$raw_creds   = wp_unslash( $_POST['credentials'] ?? '{}' );
		$credentials = json_decode( $raw_creds, true );

		if ( ! is_array( $credentials ) ) {
			$credentials = array();
		}

		$masked = array();
		foreach ( $credentials as $key => $value ) {
			$masked[ sanitize_key( $key ) ] = $this->mask_credential( (string) $value );
		}

		update_user_meta(
			$vendor_id,
			'gm_sync_config',
			array(
				'source_type'  => $source_type,
				'credentials'  => $masked,
				'saved_at'     => current_time( 'mysql' ),
			)
		);

		wp_send_json_success( array( 'saved' => true ) );
	}

	// =========================================================================
	// AJAX: save connection
	// =========================================================================

	/**
	 * AJAX handler — create a new sync connection and schedule automatic syncing.
	 */
	public function ajax_save_connection(): void {
		check_ajax_referer( 'gm_sync_hub', 'nonce' );

		$vendor_id = get_current_user_id();

		if ( ! dokan_is_user_seller( $vendor_id ) ) {
			wp_send_json_error( __( 'You must be a registered vendor to use the Sync Hub.', 'gifting-marketplace' ) );
			return;
		}

		$platform    = sanitize_key( wp_unslash( $_POST['platform'] ?? '' ) );
		$raw_creds   = wp_unslash( $_POST['credentials'] ?? '{}' );
		$credentials = json_decode( $raw_creds, true );
		$interval    = sanitize_key( wp_unslash( $_POST['interval'] ?? '1hour' ) );

		if ( ! is_array( $credentials ) ) {
			$credentials = array();
		}

		$id = GM_Sync_Connection::create( $vendor_id, $platform, $credentials, $interval );

		if ( is_wp_error( $id ) ) {
			wp_send_json_error( $id->get_error_message() );
			return;
		}

		wp_send_json_success(
			array(
				'connection_id' => $id,
				'message'       => __( 'Connection saved and sync scheduled', 'gifting-marketplace' ),
			)
		);
	}

	// =========================================================================
	// AJAX: delete connection
	// =========================================================================

	/**
	 * AJAX handler — delete an existing sync connection owned by this vendor.
	 */
	public function ajax_delete_connection(): void {
		check_ajax_referer( 'gm_sync_hub', 'nonce' );

		$vendor_id     = get_current_user_id();
		$connection_id = (int) ( $_POST['connection_id'] ?? 0 );

		if ( ! dokan_is_user_seller( $vendor_id ) ) {
			wp_send_json_error( __( 'You must be a registered vendor to use the Sync Hub.', 'gifting-marketplace' ) );
			return;
		}

		if ( ! $connection_id || ! $this->vendor_owns_connection( $vendor_id, $connection_id ) ) {
			wp_send_json_error( __( 'Connection not found or access denied.', 'gifting-marketplace' ) );
			return;
		}

		GM_Sync_Connection::delete( $connection_id );

		wp_send_json_success();
	}

	// =========================================================================
	// AJAX: toggle connection status
	// =========================================================================

	/**
	 * AJAX handler — toggle a connection between active and paused states.
	 */
	public function ajax_toggle_connection(): void {
		check_ajax_referer( 'gm_sync_hub', 'nonce' );

		$vendor_id     = get_current_user_id();
		$connection_id = (int) ( $_POST['connection_id'] ?? 0 );
		$status        = sanitize_key( wp_unslash( $_POST['status'] ?? 'active' ) );

		if ( ! dokan_is_user_seller( $vendor_id ) ) {
			wp_send_json_error( __( 'You must be a registered vendor to use the Sync Hub.', 'gifting-marketplace' ) );
			return;
		}

		if ( ! $connection_id || ! $this->vendor_owns_connection( $vendor_id, $connection_id ) ) {
			wp_send_json_error( __( 'Connection not found or access denied.', 'gifting-marketplace' ) );
			return;
		}

		if ( ! in_array( $status, array( 'active', 'paused' ), true ) ) {
			wp_send_json_error( __( 'Invalid status value. Use "active" or "paused".', 'gifting-marketplace' ) );
			return;
		}

		GM_Sync_Connection::toggle_status( $connection_id, $status );

		wp_send_json_success();
	}

	// =========================================================================
	// AJAX: sync now
	// =========================================================================

	/**
	 * AJAX handler — trigger an immediate sync for the given connection.
	 */
	public function ajax_sync_now(): void {
		check_ajax_referer( 'gm_sync_hub', 'nonce' );

		$vendor_id     = get_current_user_id();
		$connection_id = (int) ( $_POST['connection_id'] ?? 0 );

		if ( ! dokan_is_user_seller( $vendor_id ) ) {
			wp_send_json_error( __( 'You must be a registered vendor to use the Sync Hub.', 'gifting-marketplace' ) );
			return;
		}

		if ( ! $connection_id || ! $this->vendor_owns_connection( $vendor_id, $connection_id ) ) {
			wp_send_json_error( __( 'Connection not found or access denied.', 'gifting-marketplace' ) );
			return;
		}

		$result = GM_Sync_Connection::run_sync( $connection_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
			return;
		}

		wp_send_json_success( $result );
	}

	// =========================================================================
	// AJAX: get connections
	// =========================================================================

	/**
	 * AJAX handler — return all sync connections for the current vendor.
	 */
	public function ajax_get_connections(): void {
		check_ajax_referer( 'gm_sync_hub', 'nonce' );

		$vendor_id = get_current_user_id();

		if ( ! dokan_is_user_seller( $vendor_id ) ) {
			wp_send_json_error( __( 'You must be a registered vendor to use the Sync Hub.', 'gifting-marketplace' ) );
			return;
		}

		$rows = GM_Sync_Connection::get_for_vendor( $vendor_id );

		wp_send_json_success( array( 'connections' => $rows ) );
	}

	// =========================================================================
	// AJAX: register webhook proxy
	// =========================================================================

	/**
	 * AJAX handler — proxy the webhook registration request to GM_Sync_Webhooks.
	 */
	public function ajax_register_webhook_proxy(): void {
		( new GM_Sync_Webhooks() )->ajax_register_webhook();
	}

	// =========================================================================
	// Private: connector routing
	// =========================================================================

	/**
	 * Route a connection test to the correct connector class.
	 *
	 * @param string $source_type Source identifier.
	 * @param array  $credentials Decrypted / raw credentials array.
	 * @return true|WP_Error
	 */
	private function route_test( string $source_type, array $credentials ) {
		switch ( $source_type ) {
			case 'shopify':
				$store_url    = sanitize_text_field( $credentials['store_url']    ?? '' );
				$access_token = sanitize_text_field( $credentials['access_token'] ?? '' );
				if ( ! $store_url || ! $access_token ) {
					return new WP_Error( 'missing_credentials', __( 'Shopify requires store_url and access_token.', 'gifting-marketplace' ) );
				}
				return GM_Sync_Shopify::test_connection( $store_url, $access_token );

			case 'woocommerce':
				$store_url       = sanitize_text_field( $credentials['store_url']        ?? '' );
				$consumer_key    = sanitize_text_field( $credentials['consumer_key']     ?? '' );
				$consumer_secret = sanitize_text_field( $credentials['consumer_secret']  ?? '' );
				if ( ! $store_url || ! $consumer_key || ! $consumer_secret ) {
					return new WP_Error( 'missing_credentials', __( 'WooCommerce requires store_url, consumer_key, and consumer_secret.', 'gifting-marketplace' ) );
				}
				return GM_Sync_WooCommerce::test_connection( $store_url, $consumer_key, $consumer_secret );

			case 'erp':
				$api_url   = esc_url_raw( $credentials['api_url']   ?? '' );
				$api_key   = sanitize_text_field( $credentials['api_key']   ?? '' );
				$auth_type = sanitize_key( $credentials['auth_type'] ?? 'bearer' );
				if ( ! $api_url ) {
					return new WP_Error( 'missing_credentials', __( 'ERP requires api_url.', 'gifting-marketplace' ) );
				}
				return GM_Sync_ERP::test_connection( $api_url, $api_key, $auth_type );

			case 'csv':
			case 'google_sheets':
				return true;

			default:
				return new WP_Error( 'unknown_source', sprintf(
					/* translators: %s: source type slug */
					__( 'Unknown source type: %s', 'gifting-marketplace' ),
					$source_type
				) );
		}
	}

	/**
	 * Route a product fetch to the correct connector class.
	 *
	 * @param string     $source_type Source identifier.
	 * @param array      $credentials Credentials array.
	 * @param array|null $file        Entry from $_FILES, if applicable.
	 * @return array|WP_Error
	 */
	private function route_fetch( string $source_type, array $credentials, $file ) {
		switch ( $source_type ) {
			case 'shopify':
				$store_url    = sanitize_text_field( $credentials['store_url']    ?? '' );
				$access_token = sanitize_text_field( $credentials['access_token'] ?? '' );
				if ( ! $store_url || ! $access_token ) {
					return new WP_Error( 'missing_credentials', __( 'Shopify requires store_url and access_token.', 'gifting-marketplace' ) );
				}
				return GM_Sync_Shopify::fetch( $store_url, $access_token );

			case 'woocommerce':
				$store_url       = sanitize_text_field( $credentials['store_url']        ?? '' );
				$consumer_key    = sanitize_text_field( $credentials['consumer_key']     ?? '' );
				$consumer_secret = sanitize_text_field( $credentials['consumer_secret']  ?? '' );
				if ( ! $store_url || ! $consumer_key || ! $consumer_secret ) {
					return new WP_Error( 'missing_credentials', __( 'WooCommerce requires store_url, consumer_key, and consumer_secret.', 'gifting-marketplace' ) );
				}
				return GM_Sync_WooCommerce::fetch( $store_url, $consumer_key, $consumer_secret );

			case 'csv':
				if ( ! $file ) {
					return new WP_Error( 'no_file', __( 'No CSV file was uploaded.', 'gifting-marketplace' ) );
				}
				return GM_Sync_CSV::fetch_from_file( $file );

			case 'google_sheets':
				$sheets_url = esc_url_raw( $credentials['sheets_url'] ?? '' );
				if ( ! $sheets_url ) {
					return new WP_Error( 'missing_credentials', __( 'Google Sheets requires a sheets_url.', 'gifting-marketplace' ) );
				}
				return GM_Sync_CSV::fetch_from_sheets( $sheets_url );

			case 'erp':
				$api_url   = esc_url_raw( $credentials['api_url']   ?? '' );
				$api_key   = sanitize_text_field( $credentials['api_key']   ?? '' );
				$auth_type = sanitize_key( $credentials['auth_type'] ?? 'bearer' );
				$field_map = isset( $credentials['field_map'] ) && is_array( $credentials['field_map'] )
					? $credentials['field_map']
					: array();
				if ( ! $api_url ) {
					return new WP_Error( 'missing_credentials', __( 'ERP requires api_url.', 'gifting-marketplace' ) );
				}
				return GM_Sync_ERP::fetch( $api_url, $api_key, $auth_type, $field_map );

			default:
				return new WP_Error( 'unknown_source', sprintf(
					/* translators: %s: source type slug */
					__( 'Unknown source type: %s', 'gifting-marketplace' ),
					$source_type
				) );
		}
	}

	// =========================================================================
	// Private: product creation
	// =========================================================================

	/**
	 * Create a single WooCommerce product post from a normalised product array.
	 *
	 * @param array $product   Normalised product data.
	 * @param int   $vendor_id Dokan vendor (WP user) ID.
	 * @return int|WP_Error New product post ID on success, WP_Error on failure.
	 */
	private function import_single_product( array $product, int $vendor_id ) {
		$title   = sanitize_text_field( $product['title']             ?? '' );
		$content = wp_kses_post( $product['description']              ?? '' );
		$excerpt = wp_kses_post( $product['short_description']        ?? '' );

		if ( '' === $title ) {
			return new WP_Error( 'missing_title', __( 'Product title is required.', 'gifting-marketplace' ) );
		}

		$post_data = array(
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $excerpt,
			'post_status'  => 'draft',
			'post_author'  => $vendor_id,
			'post_type'    => 'product',
		);

		$product_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $product_id ) ) {
			return $product_id;
		}

		// Core WooCommerce meta.
		$price         = isset( $product['price'] )      ? (float) $product['price']      : 0.0;
		$sale_price    = isset( $product['sale_price'] ) ? (float) $product['sale_price'] : null;
		$sku           = sanitize_text_field( $product['sku']       ?? '' );
		$stock_qty     = isset( $product['stock_qty'] )  ? (int) $product['stock_qty']    : 0;
		$manage_stock  = ( $stock_qty > 0 ) ? 'yes' : 'no';
		$stock_status  = ( $stock_qty > 0 ) ? 'instock' : 'outofstock';

		update_post_meta( $product_id, '_regular_price', $price );
		update_post_meta( $product_id, '_price',         ( $sale_price !== null && $sale_price < $price ) ? $sale_price : $price );

		if ( $sale_price !== null ) {
			update_post_meta( $product_id, '_sale_price', $sale_price );
		}

		if ( $sku !== '' ) {
			update_post_meta( $product_id, '_sku', $sku );
		}

		update_post_meta( $product_id, '_stock',        $stock_qty );
		update_post_meta( $product_id, '_stock_status', $stock_status );
		update_post_meta( $product_id, '_manage_stock', $manage_stock );

		// WooCommerce product type taxonomy.
		wp_set_object_terms( $product_id, 'simple', 'product_type' );

		// Featured image — sideload the first image URL if available.
		$images = isset( $product['images'] ) && is_array( $product['images'] ) ? $product['images'] : array();
		if ( ! empty( $images[0] ) ) {
			$image_url = esc_url_raw( (string) $images[0] );
			if ( $image_url ) {
				$attachment_id = media_sideload_image( $image_url, $product_id, $title, 'id' );
				if ( ! is_wp_error( $attachment_id ) && is_int( $attachment_id ) ) {
					set_post_thumbnail( $product_id, $attachment_id );
				}
			}
		}

		return $product_id;
	}

	// =========================================================================
	// Private: sync job helpers
	// =========================================================================

	/**
	 * Insert a new sync job record with status = running.
	 *
	 * @param int    $vendor_id   Vendor user ID.
	 * @param string $source_type Source type slug.
	 * @param int    $total       Total number of products to import.
	 * @return int Inserted row ID.
	 */
	private function create_sync_job( int $vendor_id, string $source_type, int $total ): int {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'gm_sync_jobs',
			array(
				'vendor_id'   => $vendor_id,
				'source_type' => $source_type,
				'status'      => 'running',
				'total'       => $total,
				'synced'      => 0,
				'failed'      => 0,
				'log'         => '[]',
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update a sync job to completed with final counts and log.
	 *
	 * @param int   $job_id  Job row ID.
	 * @param int   $synced  Number of successfully imported products.
	 * @param int   $failed  Number of failed products.
	 * @param array $log     Per-product result log.
	 */
	private function update_sync_job( int $job_id, int $synced, int $failed, array $log ): void {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'gm_sync_jobs',
			array(
				'status'       => 'completed',
				'synced'       => $synced,
				'failed'       => $failed,
				'log'          => wp_json_encode( $log ),
				'completed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $job_id ),
			array( '%s', '%d', '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	// =========================================================================
	// Private: ownership check
	// =========================================================================

	/**
	 * Verify that a given connection belongs to the specified vendor.
	 *
	 * @param int $vendor_id     The vendor's WP user ID.
	 * @param int $connection_id The connection row ID.
	 * @return bool True if the connection belongs to this vendor.
	 */
	private function vendor_owns_connection( int $vendor_id, int $connection_id ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'gm_sync_connections';
		$owner = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT vendor_id FROM {$table} WHERE id = %d LIMIT 1",
				$connection_id
			)
		);

		return (int) $owner === $vendor_id;
	}

	// =========================================================================
	// Private: credential masking
	// =========================================================================

	/**
	 * Mask a credential string: keep the first 4 characters and last 4 characters,
	 * replace everything in between with asterisks.
	 *
	 * Strings shorter than 9 characters are fully masked.
	 *
	 * @param string $value Raw credential value.
	 * @return string Masked credential.
	 */
	private function mask_credential( string $value ): string {
		$len = mb_strlen( $value );

		if ( $len <= 8 ) {
			return str_repeat( '*', $len );
		}

		$prefix = mb_substr( $value, 0, 4 );
		$suffix = mb_substr( $value, -4 );
		$mask   = str_repeat( '*', $len - 8 );

		return $prefix . $mask . $suffix;
	}
}
