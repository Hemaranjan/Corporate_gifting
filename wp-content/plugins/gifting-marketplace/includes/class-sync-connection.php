<?php
/**
 * Giftelier Sync Connection — CRUD + Sync Logic
 *
 * Manages vendor platform connections stored in wp_gm_sync_connections,
 * the product mapping table wp_gm_sync_product_map, and orchestrates the
 * incremental pull-diff-upsert sync cycle.
 *
 * @package GiftingMarketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GM_Sync_Connection
 *
 * All public methods are static so they can be called from AJAX handlers,
 * cron callbacks, and the scheduler without instantiating the class.
 */
class GM_Sync_Connection {

	// =========================================================================
	// Connection CRUD
	// =========================================================================

	/**
	 * Create a new platform connection for a vendor.
	 *
	 * Credentials are encoded with base64_encode( json_encode( $credentials ) )
	 * for simple staging-level obfuscation. A recurring cron event is scheduled
	 * immediately after the row is inserted.
	 *
	 * @param int    $vendor_id   WP user ID of the vendor who owns this connection.
	 * @param string $platform    Platform slug (e.g. 'shopify', 'woocommerce', 'csv', 'erp').
	 * @param array  $credentials Associative array of platform-specific credentials.
	 * @param string $interval    WP cron interval key (default '1hour').
	 *
	 * @return int|WP_Error New connection ID on success, WP_Error on failure.
	 */
	public static function create( int $vendor_id, string $platform, array $credentials, string $interval = '1hour' ) {
		global $wpdb;

		$encoded = base64_encode( json_encode( $credentials ) );

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'gm_sync_connections',
			array(
				'vendor_id'   => $vendor_id,
				'platform'    => sanitize_key( $platform ),
				'credentials' => $encoded,
				'interval'    => sanitize_key( $interval ),
				'status'      => 'active',
				'last_sync'   => null,
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error(
				'gm_connection_insert_failed',
				__( 'Failed to insert connection into the database.', 'gifting-marketplace' )
			);
		}

		$connection_id = (int) $wpdb->insert_id;

		if ( class_exists( 'GM_Sync_Scheduler' ) ) {
			GM_Sync_Scheduler::schedule_connection( $connection_id, $interval );
		}

		return $connection_id;
	}

	/**
	 * Retrieve a single connection row with decoded credentials.
	 *
	 * @param int $connection_id Primary key of the connection row.
	 *
	 * @return object|null Connection object with a decoded 'credentials' array property,
	 *                     or null when the row does not exist.
	 */
	public static function get( int $connection_id ): ?object {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gm_sync_connections WHERE id = %d LIMIT 1",
				$connection_id
			)
		);

		if ( ! $row ) {
			return null;
		}

		$row->credentials = self::decode_credentials( $row->credentials );

		return $row;
	}

	/**
	 * Retrieve all connections for a given vendor with decoded credentials and
	 * next scheduled sync time.
	 *
	 * @param int $vendor_id WP user ID of the vendor.
	 *
	 * @return array Array of connection objects (may be empty).
	 */
	public static function get_for_vendor( int $vendor_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gm_sync_connections WHERE vendor_id = %d ORDER BY created_at DESC",
				$vendor_id
			)
		);

		if ( ! $rows ) {
			return array();
		}

		foreach ( $rows as $row ) {
			$row->credentials = self::decode_credentials( $row->credentials );

			$hook      = 'gm_sync_run_' . (int) $row->id;
			$next_run  = wp_next_scheduled( $hook );
			$row->next_sync = $next_run ? gmdate( 'Y-m-d H:i:s', $next_run ) : null;
		}

		return $rows;
	}

	/**
	 * Update the stored credentials for an existing connection.
	 *
	 * @param int   $connection_id Primary key of the connection row.
	 * @param array $credentials   New credentials array to store.
	 *
	 * @return void
	 */
	public static function update_credentials( int $connection_id, array $credentials ): void {
		global $wpdb;

		$encoded = base64_encode( json_encode( $credentials ) );

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'gm_sync_connections',
			array( 'credentials' => $encoded ),
			array( 'id' => $connection_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete a connection and all related data.
	 *
	 * Removes the connection row, all product map entries, the scheduled cron
	 * event, and any registered webhooks for this connection.
	 *
	 * @param int $connection_id Primary key of the connection row.
	 *
	 * @return void
	 */
	public static function delete( int $connection_id ): void {
		global $wpdb;

		$connection = self::get( $connection_id );

		if ( $connection && class_exists( 'GM_Sync_Scheduler' ) ) {
			GM_Sync_Scheduler::unschedule_connection( $connection_id );
		}

		if ( $connection ) {
			self::delete_webhooks( $connection );
		}

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'gm_sync_product_map',
			array( 'connection_id' => $connection_id ),
			array( '%d' )
		);

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'gm_sync_connections',
			array( 'id' => $connection_id ),
			array( '%d' )
		);
	}

	/**
	 * Set the status of a connection to 'active' or 'paused'.
	 *
	 * When activating, a cron event is (re)scheduled. When pausing, the cron
	 * event is removed.
	 *
	 * @param int    $connection_id Primary key of the connection row.
	 * @param string $status        Either 'active' or 'paused'.
	 *
	 * @return void
	 */
	public static function toggle_status( int $connection_id, string $status ): void {
		global $wpdb;

		$status = in_array( $status, array( 'active', 'paused' ), true ) ? $status : 'paused';

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'gm_sync_connections',
			array( 'status' => $status ),
			array( 'id' => $connection_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( ! class_exists( 'GM_Sync_Scheduler' ) ) {
			return;
		}

		if ( 'active' === $status ) {
			$connection = self::get( $connection_id );
			$interval   = $connection ? $connection->interval : '1hour';
			GM_Sync_Scheduler::schedule_connection( $connection_id, $interval );
		} else {
			GM_Sync_Scheduler::unschedule_connection( $connection_id );
		}
	}

	// =========================================================================
	// Sync logic
	// =========================================================================

	/**
	 * Execute an incremental sync for a connection.
	 *
	 * Fetches products modified since the connection's last_sync timestamp,
	 * diffs against the stored product map using MD5 checksums, and upserts
	 * changed or new products into WooCommerce. A job row is recorded in
	 * wp_gm_sync_jobs on completion.
	 *
	 * @param int $connection_id Primary key of the connection to sync.
	 *
	 * @return array {
	 *     @type int $synced  Number of products created or updated.
	 *     @type int $skipped Number of products with no change.
	 *     @type int $failed  Number of products that could not be upserted.
	 * }
	 */
	public static function run_sync( int $connection_id ): array {
		$connection = self::get( $connection_id );

		if ( ! $connection ) {
			return array( 'synced' => 0, 'skipped' => 0, 'failed' => 0 );
		}

		$credentials = $connection->credentials;
		$last_sync   = $connection->last_sync;
		$platform    = $connection->platform;

		$products = self::fetch_since( $platform, $credentials, $last_sync );

		$synced  = 0;
		$skipped = 0;
		$failed  = 0;

		if ( ! is_wp_error( $products ) && is_array( $products ) ) {
			foreach ( $products as $normalized ) {
				$external_id = (string) ( $normalized['external_id'] ?? '' );
				if ( '' === $external_id ) {
					$failed++;
					continue;
				}

				$checksum = md5( json_encode( $normalized ) );

				global $wpdb;

				$map_row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}gm_sync_product_map
						 WHERE connection_id = %d AND external_id = %s
						 LIMIT 1",
						$connection_id,
						$external_id
					)
				);

				if ( $map_row && $map_row->checksum === $checksum ) {
					$skipped++;
					continue;
				}

				$result = self::upsert_product( $connection, $normalized );

				if ( is_wp_error( $result ) ) {
					$failed++;
				} else {
					$synced++;
				}
			}
		}

		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'gm_sync_connections',
			array( 'last_sync' => current_time( 'mysql' ) ),
			array( 'id' => $connection_id ),
			array( '%s' ),
			array( '%d' )
		);

		$total = $synced + $skipped + $failed;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'gm_sync_jobs',
			array(
				'vendor_id'    => (int) $connection->vendor_id,
				'source_type'  => $platform,
				'source_config' => wp_json_encode( array( 'connection_id' => $connection_id ) ),
				'status'       => 'completed',
				'total'        => $total,
				'synced'       => $synced,
				'failed'       => $failed,
				'log'          => wp_json_encode( array( 'skipped' => $skipped ) ),
				'created_at'   => current_time( 'mysql' ),
				'completed_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		return array(
			'synced'  => $synced,
			'skipped' => $skipped,
			'failed'  => $failed,
		);
	}

	/**
	 * Create or update a WooCommerce product from a normalised product array.
	 *
	 * When a product map entry exists, the WC post and its meta are updated and
	 * the map checksum is refreshed. When no map entry exists, a new WC product
	 * post is created as a draft and a new map row is inserted.
	 *
	 * @param object $connection Connection row object (must have id, vendor_id, platform).
	 * @param array  $normalized Normalised product array with keys: external_id, title,
	 *                           description, short_description, price, sale_price,
	 *                           sku, stock_qty, images.
	 *
	 * @return int|WP_Error WooCommerce product post ID on success, WP_Error on failure.
	 */
	public static function upsert_product( object $connection, array $normalized ) {
		global $wpdb;

		$connection_id = (int) $connection->id;
		$vendor_id     = (int) $connection->vendor_id;
		$external_id   = (string) ( $normalized['external_id'] ?? '' );
		$checksum      = md5( json_encode( $normalized ) );

		$title   = sanitize_text_field( $normalized['title']             ?? '' );
		$content = wp_kses_post( $normalized['description']              ?? '' );
		$excerpt = wp_kses_post( $normalized['short_description']        ?? '' );

		$price      = isset( $normalized['price'] )      ? (float) $normalized['price']      : 0.0;
		$sale_price = isset( $normalized['sale_price'] ) ? (float) $normalized['sale_price'] : null;
		$sku        = sanitize_text_field( $normalized['sku']            ?? '' );
		$stock_qty  = isset( $normalized['stock_qty'] )  ? (int) $normalized['stock_qty']    : 0;

		$stock_status = $stock_qty > 0 ? 'instock' : 'outofstock';
		$manage_stock = $stock_qty > 0 ? 'yes' : 'no';

		$map_row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gm_sync_product_map
				 WHERE connection_id = %d AND external_id = %s
				 LIMIT 1",
				$connection_id,
				$external_id
			)
		);

		if ( $map_row ) {
			$wc_product_id = (int) $map_row->wc_product_id;

			$update_result = wp_update_post(
				array(
					'ID'           => $wc_product_id,
					'post_title'   => $title,
					'post_content' => $content,
					'post_excerpt' => $excerpt,
				),
				true
			);

			if ( is_wp_error( $update_result ) ) {
				return $update_result;
			}

			self::set_wc_meta( $wc_product_id, $price, $sale_price, $sku, $stock_qty, $stock_status, $manage_stock );

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prefix . 'gm_sync_product_map',
				array(
					'checksum'    => $checksum,
					'last_synced' => current_time( 'mysql' ),
				),
				array( 'id' => (int) $map_row->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			return $wc_product_id;
		}

		if ( '' === $title ) {
			return new WP_Error( 'gm_missing_title', __( 'Product title is required.', 'gifting-marketplace' ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $content,
				'post_excerpt' => $excerpt,
				'post_status'  => 'draft',
				'post_author'  => $vendor_id,
				'post_type'    => 'product',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		wp_set_object_terms( $post_id, 'simple', 'product_type' );
		self::set_wc_meta( $post_id, $price, $sale_price, $sku, $stock_qty, $stock_status, $manage_stock );

		$images = isset( $normalized['images'] ) && is_array( $normalized['images'] ) ? $normalized['images'] : array();
		if ( ! empty( $images[0] ) ) {
			if ( ! function_exists( 'media_sideload_image' ) ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			$image_url     = esc_url_raw( (string) $images[0] );
			$attachment_id = media_sideload_image( $image_url, $post_id, $title, 'id' );

			if ( ! is_wp_error( $attachment_id ) && is_int( $attachment_id ) ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'gm_sync_product_map',
			array(
				'connection_id' => $connection_id,
				'external_id'   => $external_id,
				'wc_product_id' => $post_id,
				'checksum'      => $checksum,
				'last_synced'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s' )
		);

		return $post_id;
	}

	// =========================================================================
	// Product map helpers
	// =========================================================================

	/**
	 * Return all product map rows for a given connection.
	 *
	 * @param int $connection_id Primary key of the connection.
	 *
	 * @return array Array of row objects from wp_gm_sync_product_map.
	 */
	public static function get_product_map( int $connection_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gm_sync_product_map WHERE connection_id = %d ORDER BY last_synced DESC",
				$connection_id
			)
		);

		return $rows ? $rows : array();
	}

	/**
	 * Return the count of products that have been synced for a connection.
	 *
	 * @param int $connection_id Primary key of the connection.
	 *
	 * @return int Number of mapped products.
	 */
	public static function count_synced_products( int $connection_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}gm_sync_product_map WHERE connection_id = %d",
				$connection_id
			)
		);
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * Decode a base64-encoded JSON credentials string into an associative array.
	 *
	 * Returns an empty array when the value cannot be decoded.
	 *
	 * @param string $encoded Encoded credentials string from the database.
	 *
	 * @return array Decoded credentials array.
	 */
	private static function decode_credentials( string $encoded ): array {
		if ( '' === $encoded ) {
			return array();
		}

		$json = base64_decode( $encoded, true );

		if ( false === $json ) {
			return array();
		}

		$decoded = json_decode( $json, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Route an incremental fetch to the correct connector class.
	 *
	 * Connectors that implement fetch_since() receive the decoded credentials
	 * and last_sync timestamp. Connectors that do not implement fetch_since()
	 * fall back to a full fetch().
	 *
	 * @param string      $platform    Platform slug.
	 * @param array       $credentials Decoded credentials array.
	 * @param string|null $last_sync   MySQL datetime string of the last successful sync,
	 *                                 or null for the first ever run.
	 *
	 * @return array|WP_Error Normalised product array on success, WP_Error on failure.
	 */
	private static function fetch_since( string $platform, array $credentials, ?string $last_sync ) {
		switch ( $platform ) {
			case 'shopify':
				$store_url    = sanitize_text_field( $credentials['store_url']    ?? '' );
				$access_token = sanitize_text_field( $credentials['access_token'] ?? '' );

				if ( ! $store_url || ! $access_token ) {
					return new WP_Error(
						'gm_missing_credentials',
						__( 'Shopify requires store_url and access_token.', 'gifting-marketplace' )
					);
				}

				if ( method_exists( 'GM_Sync_Shopify', 'fetch_since' ) ) {
					return GM_Sync_Shopify::fetch_since( $store_url, $access_token, $last_sync );
				}

				return GM_Sync_Shopify::fetch( $store_url, $access_token );

			case 'woocommerce':
				$store_url       = sanitize_text_field( $credentials['store_url']       ?? '' );
				$consumer_key    = sanitize_text_field( $credentials['consumer_key']    ?? '' );
				$consumer_secret = sanitize_text_field( $credentials['consumer_secret'] ?? '' );

				if ( ! $store_url || ! $consumer_key || ! $consumer_secret ) {
					return new WP_Error(
						'gm_missing_credentials',
						__( 'WooCommerce requires store_url, consumer_key, and consumer_secret.', 'gifting-marketplace' )
					);
				}

				if ( method_exists( 'GM_Sync_WooCommerce', 'fetch_since' ) ) {
					return GM_Sync_WooCommerce::fetch_since( $store_url, $consumer_key, $consumer_secret, $last_sync );
				}

				return GM_Sync_WooCommerce::fetch( $store_url, $consumer_key, $consumer_secret );

			case 'erp':
				$api_url   = esc_url_raw( $credentials['api_url']   ?? '' );
				$api_key   = sanitize_text_field( $credentials['api_key']   ?? '' );
				$auth_type = sanitize_key( $credentials['auth_type'] ?? 'bearer' );
				$field_map = isset( $credentials['field_map'] ) && is_array( $credentials['field_map'] )
					? $credentials['field_map']
					: array();

				if ( ! $api_url ) {
					return new WP_Error(
						'gm_missing_credentials',
						__( 'ERP requires api_url.', 'gifting-marketplace' )
					);
				}

				if ( method_exists( 'GM_Sync_ERP', 'fetch_since' ) ) {
					return GM_Sync_ERP::fetch_since( $api_url, $api_key, $auth_type, $field_map, $last_sync );
				}

				return GM_Sync_ERP::fetch( $api_url, $api_key, $auth_type, $field_map );

			case 'csv':
			case 'google_sheets':
				return new WP_Error(
					'gm_incremental_unsupported',
					__( 'CSV and Google Sheets connections do not support automatic incremental sync.', 'gifting-marketplace' )
				);

			default:
				return new WP_Error(
					'gm_unknown_platform',
					sprintf(
						/* translators: %s: platform slug */
						__( 'Unknown platform: %s', 'gifting-marketplace' ),
						$platform
					)
				);
		}
	}

	/**
	 * Write WooCommerce price, SKU, and stock post meta for a product.
	 *
	 * @param int         $product_id   WC product post ID.
	 * @param float       $price        Regular price.
	 * @param float|null  $sale_price   Sale price, or null if not on sale.
	 * @param string      $sku          Product SKU.
	 * @param int         $stock_qty    Stock quantity.
	 * @param string      $stock_status 'instock' or 'outofstock'.
	 * @param string      $manage_stock 'yes' or 'no'.
	 *
	 * @return void
	 */
	private static function set_wc_meta(
		int $product_id,
		float $price,
		?float $sale_price,
		string $sku,
		int $stock_qty,
		string $stock_status,
		string $manage_stock
	): void {
		update_post_meta( $product_id, '_regular_price', $price );

		if ( null !== $sale_price && $sale_price < $price ) {
			update_post_meta( $product_id, '_sale_price', $sale_price );
			update_post_meta( $product_id, '_price', $sale_price );
		} else {
			delete_post_meta( $product_id, '_sale_price' );
			update_post_meta( $product_id, '_price', $price );
		}

		if ( '' !== $sku ) {
			update_post_meta( $product_id, '_sku', $sku );
		}

		update_post_meta( $product_id, '_stock',        $stock_qty );
		update_post_meta( $product_id, '_stock_status', $stock_status );
		update_post_meta( $product_id, '_manage_stock', $manage_stock );
	}

	/**
	 * Remove any platform webhooks registered for this connection.
	 *
	 * Delegates to the appropriate connector if it implements delete_webhook().
	 * Silently returns when the connector does not support webhooks.
	 *
	 * @param object $connection Connection row object with platform and credentials.
	 *
	 * @return void
	 */
	private static function delete_webhooks( object $connection ): void {
		$platform    = $connection->platform;
		$credentials = $connection->credentials;

		switch ( $platform ) {
			case 'shopify':
				$webhook_id = get_option( 'gm_webhook_shopify_' . (int) $connection->id );
				if ( $webhook_id && method_exists( 'GM_Sync_Shopify', 'delete_webhook' ) ) {
					$store_url    = sanitize_text_field( $credentials['store_url']    ?? '' );
					$access_token = sanitize_text_field( $credentials['access_token'] ?? '' );
					GM_Sync_Shopify::delete_webhook( $store_url, $access_token, $webhook_id );
				}
				delete_option( 'gm_webhook_shopify_' . (int) $connection->id );
				break;

			case 'woocommerce':
				$webhook_id = get_option( 'gm_webhook_woocommerce_' . (int) $connection->id );
				if ( $webhook_id && method_exists( 'GM_Sync_WooCommerce', 'delete_webhook' ) ) {
					$store_url       = sanitize_text_field( $credentials['store_url']       ?? '' );
					$consumer_key    = sanitize_text_field( $credentials['consumer_key']    ?? '' );
					$consumer_secret = sanitize_text_field( $credentials['consumer_secret'] ?? '' );
					GM_Sync_WooCommerce::delete_webhook( $store_url, $consumer_key, $consumer_secret, $webhook_id );
				}
				delete_option( 'gm_webhook_woocommerce_' . (int) $connection->id );
				break;

			default:
				break;
		}
	}
}
