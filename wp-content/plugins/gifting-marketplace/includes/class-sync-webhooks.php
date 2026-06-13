<?php
/**
 * Giftelier Sync Hub — Webhook Handler
 *
 * Receives and verifies incoming real-time webhooks from Shopify and
 * WooCommerce, and provides AJAX endpoints for registering / deleting
 * webhooks with those platforms.
 *
 * @package GiftingMarketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GM_Sync_Webhooks
 *
 * Handles receiving and verifying incoming webhooks from Shopify and
 * WooCommerce, and registering webhooks with those platforms.
 */
class GM_Sync_Webhooks {

	/**
	 * Shopify Admin REST API version.
	 */
	const SHOPIFY_API_VERSION = '2024-01';

	/**
	 * Constructor — registers all WordPress hooks.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'maybe_handle_webhook' ) );
		add_action( 'wp_ajax_gm_sync_register_webhook', array( $this, 'ajax_register_webhook' ) );
		add_action( 'wp_ajax_gm_sync_delete_webhook',   array( $this, 'ajax_delete_webhook' ) );
	}

	// =========================================================================
	// Incoming webhook endpoint
	// =========================================================================

	/**
	 * Intercept requests to the webhook endpoint URL.
	 *
	 * Called on 'init'. When the query string contains gm_sync_webhook=1 the
	 * request is handled and the process exits; all other requests are ignored.
	 */
	public function maybe_handle_webhook(): void {
		if ( ! isset( $_GET['gm_sync_webhook'] ) || '1' !== $_GET['gm_sync_webhook'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$connection_id = isset( $_GET['cid'] )   ? (int) sanitize_text_field( wp_unslash( $_GET['cid'] ) )   : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$token         = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) )        : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! $connection_id || ! $token ) {
			status_header( 400 );
			exit( 'Bad Request' );
		}

		$connection = $this->get_connection( $connection_id );

		if ( ! $connection ) {
			status_header( 404 );
			exit( 'Connection not found' );
		}

		if ( ! hash_equals( (string) $connection->webhook_secret, $token ) ) {
			status_header( 403 );
			exit( 'Forbidden' );
		}

		$body = file_get_contents( 'php://input' );

		if ( false === $body ) {
			status_header( 400 );
			exit( 'Could not read request body' );
		}

		$platform = strtolower( (string) $connection->platform );

		if ( 'shopify' === $platform ) {
			$verified = $this->verify_shopify_webhook( $body, $connection->webhook_secret );
		} elseif ( 'woocommerce' === $platform ) {
			$verified = $this->verify_woocommerce_webhook( $body, $connection->webhook_secret );
		} else {
			status_header( 400 );
			exit( 'Unknown platform' );
		}

		if ( ! $verified ) {
			status_header( 401 );
			exit( 'HMAC verification failed' );
		}

		$this->process_webhook_payload( $connection, $platform, $body );

		status_header( 200 );
		header( 'Content-Type: text/plain' );
		exit( 'OK' );
	}

	// =========================================================================
	// HMAC verification
	// =========================================================================

	/**
	 * Verify an incoming Shopify webhook using HMAC-SHA256.
	 *
	 * Shopify sends the signature as a base64-encoded HMAC in the
	 * X-Shopify-Hmac-Sha256 header.
	 *
	 * @param string $body   Raw request body.
	 * @param string $secret Shared secret stored for this connection.
	 *
	 * @return bool True when the signature is valid.
	 */
	public function verify_shopify_webhook( string $body, string $secret ): bool {
		$header_name = 'HTTP_X_SHOPIFY_HMAC_SHA256';

		if ( empty( $_SERVER[ $header_name ] ) ) {
			return false;
		}

		$received_hash = sanitize_text_field( wp_unslash( $_SERVER[ $header_name ] ) );
		$computed_hash = base64_encode( hash_hmac( 'sha256', $body, $secret, true ) );

		return hash_equals( $computed_hash, $received_hash );
	}

	/**
	 * Verify an incoming WooCommerce webhook using HMAC-SHA256.
	 *
	 * WooCommerce sends the signature as a base64-encoded HMAC in the
	 * X-WC-Webhook-Signature header.
	 *
	 * @param string $body   Raw request body.
	 * @param string $secret Shared secret stored for this connection.
	 *
	 * @return bool True when the signature is valid.
	 */
	public function verify_woocommerce_webhook( string $body, string $secret ): bool {
		$header_name = 'HTTP_X_WC_WEBHOOK_SIGNATURE';

		if ( empty( $_SERVER[ $header_name ] ) ) {
			return false;
		}

		$received_hash = sanitize_text_field( wp_unslash( $_SERVER[ $header_name ] ) );
		$computed_hash = base64_encode( hash_hmac( 'sha256', $body, $secret, true ) );

		return hash_equals( $computed_hash, $received_hash );
	}

	// =========================================================================
	// Payload routing
	// =========================================================================

	/**
	 * Decode and route a verified webhook payload.
	 *
	 * For Shopify, reads the topic from X-Shopify-Topic.
	 * For WooCommerce, reads the topic from X-WC-Webhook-Topic.
	 *
	 * @param object $connection The connection row from the database.
	 * @param string $platform   'shopify' or 'woocommerce'.
	 * @param string $body       Raw JSON body.
	 */
	public function process_webhook_payload( object $connection, string $platform, string $body ): void {
		$payload = json_decode( $body, true );

		if ( ! is_array( $payload ) ) {
			return;
		}

		if ( 'shopify' === $platform ) {
			$topic_header = 'HTTP_X_SHOPIFY_TOPIC';
			$topic        = isset( $_SERVER[ $topic_header ] )
				? sanitize_text_field( wp_unslash( $_SERVER[ $topic_header ] ) )
				: '';

			switch ( $topic ) {
				case 'products/create':
				case 'products/update':
					$this->normalize_and_upsert( $connection, $payload );
					break;

				case 'products/delete':
					if ( isset( $payload['id'] ) ) {
						$this->delete_mapped_product( (int) $connection->id, (string) $payload['id'] );
					}
					break;
			}
		} elseif ( 'woocommerce' === $platform ) {
			$topic_header = 'HTTP_X_WC_WEBHOOK_TOPIC';
			$topic        = isset( $_SERVER[ $topic_header ] )
				? sanitize_text_field( wp_unslash( $_SERVER[ $topic_header ] ) )
				: '';

			switch ( $topic ) {
				case 'product.created':
				case 'product.updated':
					$this->normalize_and_upsert( $connection, $payload );
					break;

				case 'product.deleted':
					if ( isset( $payload['id'] ) ) {
						$this->delete_mapped_product( (int) $connection->id, (string) $payload['id'] );
					}
					break;
			}
		}
	}

	// =========================================================================
	// Product upsert / delete
	// =========================================================================

	/**
	 * Normalize a raw product payload and create or update the local WC product.
	 *
	 * Checks wp_gm_sync_product_map for an existing mapping keyed on
	 * connection_id + external_id. Updates the WC product when a mapping exists;
	 * creates a new WC product and inserts a map row when it does not.
	 *
	 * @param object $connection  Connection row from the database.
	 * @param array  $raw_product Raw product payload from the platform.
	 */
	public function normalize_and_upsert( object $connection, array $raw_product ): void {
		$platform = strtolower( (string) $connection->platform );

		if ( 'shopify' === $platform ) {
			$normalized = $this->normalize_shopify_product( $raw_product );
		} elseif ( 'woocommerce' === $platform ) {
			$normalized = $this->normalize_woocommerce_product( $raw_product );
		} else {
			return;
		}

		if ( empty( $normalized ) ) {
			return;
		}

		global $wpdb;

		$map_table   = $wpdb->prefix . 'gm_sync_product_map';
		$external_id = (string) $normalized['external_id'];

		$map_row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$map_table} WHERE connection_id = %d AND external_id = %s LIMIT 1",
				(int) $connection->id,
				$external_id
			)
		);

		$checksum = md5( wp_json_encode( $normalized ) );
		$now      = current_time( 'mysql', true );

		if ( $map_row ) {
			$wc_product_id = (int) $map_row->wc_product_id;
			$wc_product    = wc_get_product( $wc_product_id );

			if ( $wc_product ) {
				$wc_product->set_name( sanitize_text_field( $normalized['title'] ?? '' ) );
				$wc_product->set_regular_price( (string) ( $normalized['price'] ?? 0 ) );

				if ( ! empty( $normalized['description'] ) ) {
					$wc_product->set_description( wp_kses_post( $normalized['description'] ) );
				}

				if ( ! empty( $normalized['short_description'] ) ) {
					$wc_product->set_short_description( wp_kses_post( $normalized['short_description'] ) );
				}

				$stock_qty = $normalized['stock_qty'] ?? null;
				if ( null !== $stock_qty ) {
					$wc_product->set_manage_stock( true );
					$wc_product->set_stock_quantity( (int) $stock_qty );
					$wc_product->set_stock_status( (int) $stock_qty > 0 ? 'instock' : 'outofstock' );
				}

				$wc_product->save();
			}

			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$map_table,
				array(
					'last_synced' => $now,
					'checksum'    => $checksum,
				),
				array(
					'connection_id' => (int) $connection->id,
					'external_id'   => $external_id,
				),
				array( '%s', '%s' ),
				array( '%d', '%s' )
			);
		} else {
			$wc_product = new WC_Product_Simple();
			$wc_product->set_name( sanitize_text_field( $normalized['title'] ?? '' ) );
			$wc_product->set_status( 'draft' );
			$wc_product->set_catalog_visibility( 'visible' );
			$wc_product->set_regular_price( (string) ( $normalized['price'] ?? 0 ) );

			if ( ! empty( $normalized['description'] ) ) {
				$wc_product->set_description( wp_kses_post( $normalized['description'] ) );
			}

			if ( ! empty( $normalized['short_description'] ) ) {
				$wc_product->set_short_description( wp_kses_post( $normalized['short_description'] ) );
			}

			if ( ! empty( $normalized['sku'] ) ) {
				$wc_product->set_sku( sanitize_text_field( $normalized['sku'] ) );
			}

			$stock_qty = $normalized['stock_qty'] ?? null;
			if ( null !== $stock_qty ) {
				$wc_product->set_manage_stock( true );
				$wc_product->set_stock_quantity( (int) $stock_qty );
				$wc_product->set_stock_status( (int) $stock_qty > 0 ? 'instock' : 'outofstock' );
			}

			$wc_product_id = $wc_product->save();

			if ( $wc_product_id ) {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$map_table,
					array(
						'connection_id' => (int) $connection->id,
						'external_id'   => $external_id,
						'wc_product_id' => $wc_product_id,
						'last_synced'   => $now,
						'checksum'      => $checksum,
					),
					array( '%d', '%s', '%d', '%s', '%s' )
				);
			}
		}
	}

	/**
	 * Trash the local WC product that corresponds to a deleted remote product
	 * and remove its row from the product map.
	 *
	 * @param int    $connection_id ID of the sync connection.
	 * @param string $external_id   External platform product ID.
	 */
	public function delete_mapped_product( int $connection_id, string $external_id ): void {
		global $wpdb;

		$map_table = $wpdb->prefix . 'gm_sync_product_map';

		$map_row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$map_table} WHERE connection_id = %d AND external_id = %s LIMIT 1",
				$connection_id,
				$external_id
			)
		);

		if ( ! $map_row ) {
			return;
		}

		$wc_product_id = (int) $map_row->wc_product_id;

		if ( $wc_product_id ) {
			wp_trash_post( $wc_product_id );
		}

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$map_table,
			array(
				'connection_id' => $connection_id,
				'external_id'   => $external_id,
			),
			array( '%d', '%s' )
		);
	}

	// =========================================================================
	// AJAX: register webhook
	// =========================================================================

	/**
	 * AJAX handler — register webhooks with the remote platform and persist the
	 * resulting webhook_id and secret to the connection row.
	 *
	 * Expected POST fields:
	 *   connection_id  int
	 *   platform       string  'shopify' or 'woocommerce'
	 *   store_url      string
	 *   access_token   string  (Shopify only)
	 *   consumer_key   string  (WooCommerce only)
	 *   consumer_secret string (WooCommerce only)
	 */
	public function ajax_register_webhook(): void {
		check_ajax_referer( 'gm_sync_hub', 'nonce' );

		if ( ! dokan_is_user_seller( get_current_user_id() ) ) {
			wp_send_json_error( __( 'You must be a registered vendor to register webhooks.', 'gifting-marketplace' ) );
			return;
		}

		$connection_id = isset( $_POST['connection_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['connection_id'] ) ) : 0;
		$platform      = isset( $_POST['platform'] )      ? sanitize_key( wp_unslash( $_POST['platform'] ) )                   : '';
		$store_url     = isset( $_POST['store_url'] )     ? sanitize_text_field( wp_unslash( $_POST['store_url'] ) )           : '';

		if ( ! $connection_id || ! $platform || ! $store_url ) {
			wp_send_json_error( __( 'Missing required fields.', 'gifting-marketplace' ) );
			return;
		}

		if ( 'shopify' === $platform ) {
			$access_token = isset( $_POST['access_token'] ) ? sanitize_text_field( wp_unslash( $_POST['access_token'] ) ) : '';

			if ( ! $access_token ) {
				wp_send_json_error( __( 'access_token is required for Shopify.', 'gifting-marketplace' ) );
				return;
			}

			$result = $this->register_shopify_webhook( $connection_id, $store_url, $access_token );
		} elseif ( 'woocommerce' === $platform ) {
			$consumer_key    = isset( $_POST['consumer_key'] )    ? sanitize_text_field( wp_unslash( $_POST['consumer_key'] ) )    : '';
			$consumer_secret = isset( $_POST['consumer_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['consumer_secret'] ) ) : '';

			if ( ! $consumer_key || ! $consumer_secret ) {
				wp_send_json_error( __( 'consumer_key and consumer_secret are required for WooCommerce.', 'gifting-marketplace' ) );
				return;
			}

			$result = $this->register_woocommerce_webhook( $connection_id, $store_url, $consumer_key, $consumer_secret );
		} else {
			wp_send_json_error( __( 'Unsupported platform.', 'gifting-marketplace' ) );
			return;
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
			return;
		}

		$this->save_webhook_to_connection( $connection_id, $result['webhook_id'], $result['secret'] );

		wp_send_json_success(
			array(
				'webhook_id' => $result['webhook_id'],
				'status'     => 'registered',
			)
		);
	}

	// =========================================================================
	// AJAX: delete webhook
	// =========================================================================

	/**
	 * AJAX handler — delete a previously registered webhook from the remote
	 * platform and clear the stored webhook_id from the connection row.
	 *
	 * Expected POST fields:
	 *   connection_id  int
	 *   platform       string  'shopify' or 'woocommerce'
	 *   store_url      string
	 *   access_token   string  (Shopify only)
	 *   consumer_key   string  (WooCommerce only)
	 *   consumer_secret string (WooCommerce only)
	 *   webhook_id     int|string
	 */
	public function ajax_delete_webhook(): void {
		check_ajax_referer( 'gm_sync_hub', 'nonce' );

		if ( ! dokan_is_user_seller( get_current_user_id() ) ) {
			wp_send_json_error( __( 'You must be a registered vendor to delete webhooks.', 'gifting-marketplace' ) );
			return;
		}

		$connection_id = isset( $_POST['connection_id'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['connection_id'] ) ) : 0;
		$platform      = isset( $_POST['platform'] )      ? sanitize_key( wp_unslash( $_POST['platform'] ) )                   : '';
		$store_url     = isset( $_POST['store_url'] )     ? sanitize_text_field( wp_unslash( $_POST['store_url'] ) )           : '';
		$webhook_id    = isset( $_POST['webhook_id'] )    ? sanitize_text_field( wp_unslash( $_POST['webhook_id'] ) )          : '';

		if ( ! $connection_id || ! $platform || ! $store_url || ! $webhook_id ) {
			wp_send_json_error( __( 'Missing required fields.', 'gifting-marketplace' ) );
			return;
		}

		if ( 'shopify' === $platform ) {
			$access_token = isset( $_POST['access_token'] ) ? sanitize_text_field( wp_unslash( $_POST['access_token'] ) ) : '';

			if ( ! $access_token ) {
				wp_send_json_error( __( 'access_token is required for Shopify.', 'gifting-marketplace' ) );
				return;
			}

			$result = $this->delete_shopify_webhook( $store_url, $access_token, $webhook_id );
		} elseif ( 'woocommerce' === $platform ) {
			$consumer_key    = isset( $_POST['consumer_key'] )    ? sanitize_text_field( wp_unslash( $_POST['consumer_key'] ) )    : '';
			$consumer_secret = isset( $_POST['consumer_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['consumer_secret'] ) ) : '';

			if ( ! $consumer_key || ! $consumer_secret ) {
				wp_send_json_error( __( 'consumer_key and consumer_secret are required for WooCommerce.', 'gifting-marketplace' ) );
				return;
			}

			$result = $this->delete_woocommerce_webhook( $store_url, $consumer_key, $consumer_secret, $webhook_id );
		} else {
			wp_send_json_error( __( 'Unsupported platform.', 'gifting-marketplace' ) );
			return;
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
			return;
		}

		$this->clear_webhook_from_connection( $connection_id );

		wp_send_json_success( array( 'deleted' => true ) );
	}

	// =========================================================================
	// Platform webhook registration — Shopify
	// =========================================================================

	/**
	 * Register three Shopify webhooks (create, update, delete) for products.
	 *
	 * Creates one webhook per topic. Returns the ID and shared secret from the
	 * first successful registration; the same secret is reused for all three.
	 *
	 * @param int    $connection_id Sync connection identifier.
	 * @param string $store_url     Shopify store domain (e.g. my-store.myshopify.com).
	 * @param string $access_token  Shopify Admin API access token.
	 *
	 * @return array|WP_Error Array with 'webhook_id' and 'secret' on success.
	 */
	public function register_shopify_webhook( int $connection_id, string $store_url, string $access_token ) {
		$secret      = $this->generate_secret();
		$callback    = home_url( '/?gm_sync_webhook=1&cid=' . $connection_id . '&token=' . rawurlencode( $secret ) );
		$endpoint    = sprintf(
			'https://%s/admin/api/%s/webhooks.json',
			$store_url,
			self::SHOPIFY_API_VERSION
		);

		$topics = array( 'products/create', 'products/update', 'products/delete' );

		$first_webhook_id = null;

		foreach ( $topics as $topic ) {
			$body = wp_json_encode(
				array(
					'webhook' => array(
						'topic'   => $topic,
						'address' => $callback,
						'format'  => 'json',
					),
				)
			);

			$response = wp_remote_post(
				$endpoint,
				array(
					'timeout' => 20,
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type'  => 'application/json',
					),
					'body'    => $body,
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$status_code = wp_remote_retrieve_response_code( $response );

			if ( $status_code < 200 || $status_code >= 300 ) {
				return new WP_Error(
					'shopify_webhook_register_failed',
					sprintf(
						/* translators: 1: HTTP status code, 2: topic */
						__( 'Shopify webhook registration for topic %2$s returned HTTP %1$d.', 'gifting-marketplace' ),
						$status_code,
						$topic
					),
					array( 'status' => $status_code )
				);
			}

			$data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( null === $first_webhook_id && isset( $data['webhook']['id'] ) ) {
				$first_webhook_id = $data['webhook']['id'];
			}
		}

		return array(
			'webhook_id' => $first_webhook_id,
			'secret'     => $secret,
		);
	}

	/**
	 * Delete a Shopify webhook by its ID.
	 *
	 * @param string $store_url    Shopify store domain.
	 * @param string $access_token Shopify Admin API access token.
	 * @param string $webhook_id   Shopify webhook ID.
	 *
	 * @return true|WP_Error True on success.
	 */
	private function delete_shopify_webhook( string $store_url, string $access_token, string $webhook_id ) {
		$endpoint = sprintf(
			'https://%s/admin/api/%s/webhooks/%s.json',
			$store_url,
			self::SHOPIFY_API_VERSION,
			$webhook_id
		);

		$response = wp_remote_request(
			$endpoint,
			array(
				'method'  => 'DELETE',
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error(
				'shopify_webhook_delete_failed',
				sprintf(
					/* translators: 1: HTTP status code */
					__( 'Shopify webhook deletion returned HTTP %d.', 'gifting-marketplace' ),
					$status_code
				),
				array( 'status' => $status_code )
			);
		}

		return true;
	}

	// =========================================================================
	// Platform webhook registration — WooCommerce
	// =========================================================================

	/**
	 * Register three WooCommerce webhooks (created, updated, deleted) for products.
	 *
	 * @param int    $connection_id   Sync connection identifier.
	 * @param string $store_url       Base URL of the remote WooCommerce store.
	 * @param string $consumer_key    WC REST API consumer key.
	 * @param string $consumer_secret WC REST API consumer secret.
	 *
	 * @return array|WP_Error Array with 'webhook_id' and 'secret' on success.
	 */
	public function register_woocommerce_webhook( int $connection_id, string $store_url, string $consumer_key, string $consumer_secret ) {
		$secret   = $this->generate_secret();
		$callback = home_url( '/?gm_sync_webhook=1&cid=' . $connection_id . '&token=' . rawurlencode( $secret ) );
		$endpoint = trailingslashit( $store_url ) . 'wp-json/wc/v3/webhooks';
		$headers  = array(
			'Authorization' => 'Basic ' . base64_encode( $consumer_key . ':' . $consumer_secret ),
			'Content-Type'  => 'application/json',
		);

		$topics = array( 'product.created', 'product.updated', 'product.deleted' );

		$first_webhook_id = null;

		foreach ( $topics as $topic ) {
			$body = wp_json_encode(
				array(
					'name'         => 'Giftelier Sync — ' . $topic,
					'status'       => 'active',
					'topic'        => $topic,
					'delivery_url' => $callback,
					'secret'       => $secret,
				)
			);

			$response = wp_remote_post(
				$endpoint,
				array(
					'timeout' => 20,
					'headers' => $headers,
					'body'    => $body,
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$status_code = wp_remote_retrieve_response_code( $response );

			if ( $status_code < 200 || $status_code >= 300 ) {
				return new WP_Error(
					'wc_webhook_register_failed',
					sprintf(
						/* translators: 1: HTTP status code, 2: topic */
						__( 'WooCommerce webhook registration for topic %2$s returned HTTP %1$d.', 'gifting-marketplace' ),
						$status_code,
						$topic
					),
					array( 'status' => $status_code )
				);
			}

			$data = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( null === $first_webhook_id && isset( $data['id'] ) ) {
				$first_webhook_id = $data['id'];
			}
		}

		return array(
			'webhook_id' => $first_webhook_id,
			'secret'     => $secret,
		);
	}

	/**
	 * Delete a WooCommerce webhook by its ID.
	 *
	 * @param string $store_url       Base URL of the remote WooCommerce store.
	 * @param string $consumer_key    WC REST API consumer key.
	 * @param string $consumer_secret WC REST API consumer secret.
	 * @param string $webhook_id      WooCommerce webhook ID.
	 *
	 * @return true|WP_Error True on success.
	 */
	private function delete_woocommerce_webhook( string $store_url, string $consumer_key, string $consumer_secret, string $webhook_id ) {
		$endpoint = trailingslashit( $store_url ) . 'wp-json/wc/v3/webhooks/' . $webhook_id . '?force=true';

		$response = wp_remote_request(
			$endpoint,
			array(
				'method'  => 'DELETE',
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $consumer_key . ':' . $consumer_secret ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error(
				'wc_webhook_delete_failed',
				sprintf(
					/* translators: 1: HTTP status code */
					__( 'WooCommerce webhook deletion returned HTTP %d.', 'gifting-marketplace' ),
					$status_code
				),
				array( 'status' => $status_code )
			);
		}

		return true;
	}

	// =========================================================================
	// Normalizers
	// =========================================================================

	/**
	 * Normalize a raw Shopify product payload to the Giftelier sync schema.
	 *
	 * @param array $product Raw product array from the Shopify API or webhook body.
	 *
	 * @return array Normalized product array.
	 */
	private function normalize_shopify_product( array $product ): array {
		$variant = isset( $product['variants'][0] ) ? $product['variants'][0] : array();

		$raw_body    = isset( $product['body_html'] ) ? (string) $product['body_html'] : '';
		$description = wp_strip_all_tags( $raw_body );
		$description = preg_replace( '/\s+/', ' ', $description );
		$description = trim( $description );

		if ( mb_strlen( $description ) > 500 ) {
			$description = mb_substr( $description, 0, 500 );
		}

		$short_description = mb_substr( $description, 0, 150 );

		$price = isset( $variant['price'] ) ? (float) $variant['price'] : 0.0;

		$sale_price = null;
		if ( ! empty( $variant['compare_at_price'] ) ) {
			$compare_at = (float) $variant['compare_at_price'];
			if ( $compare_at > $price ) {
				$sale_price = $compare_at;
			}
		}

		$sku = isset( $variant['sku'] ) ? (string) $variant['sku'] : '';

		$images = array();
		if ( ! empty( $product['images'] ) && is_array( $product['images'] ) ) {
			foreach ( $product['images'] as $image ) {
				if ( ! empty( $image['src'] ) ) {
					$images[] = (string) $image['src'];
				}
			}
		}

		$stock_qty = null;
		if ( isset( $variant['inventory_quantity'] ) ) {
			$stock_qty = (int) $variant['inventory_quantity'];
		}

		return array(
			'external_id'       => (string) ( isset( $product['id'] ) ? $product['id'] : '' ),
			'title'             => (string) ( isset( $product['title'] ) ? $product['title'] : '' ),
			'description'       => $description,
			'short_description' => $short_description,
			'price'             => $price,
			'sale_price'        => $sale_price,
			'sku'               => $sku,
			'images'            => $images,
			'stock_qty'         => $stock_qty,
			'status'            => 'draft',
			'source'            => 'shopify',
		);
	}

	/**
	 * Normalize a raw WooCommerce product payload to the Giftelier sync schema.
	 *
	 * @param array $raw Raw product array from the WooCommerce REST API or webhook body.
	 *
	 * @return array Normalized product array.
	 */
	private function normalize_woocommerce_product( array $raw ): array {
		$description       = $this->plain_text( $raw['description'] ?? '', 500 );
		$short_description = $this->plain_text( $raw['short_description'] ?? '', 150 );

		$regular_price = isset( $raw['regular_price'] ) && '' !== $raw['regular_price']
			? (float) $raw['regular_price']
			: 0.0;

		$sale_price = isset( $raw['sale_price'] ) && '' !== $raw['sale_price']
			? (float) $raw['sale_price']
			: null;

		$images = array();
		if ( ! empty( $raw['images'] ) && is_array( $raw['images'] ) ) {
			foreach ( $raw['images'] as $img ) {
				if ( ! empty( $img['src'] ) ) {
					$images[] = esc_url_raw( $img['src'] );
				}
			}
		}

		$stock_qty = isset( $raw['stock_quantity'] ) && null !== $raw['stock_quantity']
			? (int) $raw['stock_quantity']
			: 0;

		$sku = isset( $raw['sku'] ) ? sanitize_text_field( $raw['sku'] ) : '';

		return array(
			'external_id'       => (string) ( (int) ( $raw['id'] ?? 0 ) ),
			'title'             => sanitize_text_field( $raw['name'] ?? '' ),
			'description'       => $description,
			'short_description' => $short_description,
			'price'             => $regular_price,
			'sale_price'        => $sale_price,
			'sku'               => $sku,
			'images'            => $images,
			'stock_qty'         => $stock_qty,
			'status'            => 'draft',
			'source'            => 'woocommerce',
		);
	}

	/**
	 * Strip HTML tags from a string and truncate to a maximum character length.
	 *
	 * @param string $html    Raw HTML string.
	 * @param int    $max_len Maximum number of characters.
	 *
	 * @return string Plain-text string, truncated if necessary.
	 */
	private function plain_text( string $html, int $max_len ): string {
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( mb_strlen( $text ) > $max_len ) {
			$text = mb_substr( $text, 0, $max_len - 1 ) . '…';
		}

		return $text;
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * Load a single connection row from the database by its ID.
	 *
	 * @param int $connection_id The connection ID.
	 *
	 * @return object|null Database row object, or null when not found.
	 */
	private function get_connection( int $connection_id ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . 'gm_sync_connections';

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$connection_id
			)
		) ?: null;
	}

	/**
	 * Persist webhook_id and webhook_secret to a connection row.
	 *
	 * @param int    $connection_id The connection ID.
	 * @param mixed  $webhook_id    Webhook ID returned by the remote platform.
	 * @param string $secret        Shared HMAC secret.
	 */
	private function save_webhook_to_connection( int $connection_id, $webhook_id, string $secret ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'gm_sync_connections';

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'webhook_id'     => (string) $webhook_id,
				'webhook_secret' => $secret,
			),
			array( 'id' => $connection_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Clear the webhook_id and webhook_secret fields on a connection row.
	 *
	 * @param int $connection_id The connection ID.
	 */
	private function clear_webhook_from_connection( int $connection_id ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'gm_sync_connections';

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'webhook_id'     => null,
				'webhook_secret' => null,
			),
			array( 'id' => $connection_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Generate a cryptographically random 32-character hexadecimal secret.
	 *
	 * @return string 32-character hex string.
	 */
	private function generate_secret(): string {
		return bin2hex( random_bytes( 16 ) );
	}
}
