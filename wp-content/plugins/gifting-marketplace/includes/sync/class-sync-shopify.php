<?php
/**
 * Shopify Connector for the Giftelier Sync Hub
 *
 * @package GiftingMarketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GM_Sync_Shopify
 *
 * Handles fetching and normalizing products from a Shopify store
 * via the Shopify Admin REST API.
 */
class GM_Sync_Shopify {

	/**
	 * The Shopify Admin API version to use.
	 */
	const API_VERSION = '2024-01';

	/**
	 * Fetch all products from a Shopify store and return them normalized.
	 *
	 * @param string $store_url    The Shopify store domain (e.g. my-store.myshopify.com).
	 * @param string $access_token The Shopify Admin API access token.
	 *
	 * @return array|WP_Error Array of normalized product arrays on success, WP_Error on failure.
	 */
	public static function fetch( string $store_url, string $access_token ) {
		$endpoint = sprintf(
			'https://%s/admin/api/%s/products.json?limit=250',
			sanitize_text_field( $store_url ),
			self::API_VERSION
		);

		$response = wp_remote_get(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== (int) $status_code ) {
			return new WP_Error(
				'shopify_fetch_failed',
				sprintf(
					/* translators: 1: HTTP status code */
					__( 'Shopify products endpoint returned HTTP %d.', 'gifting-marketplace' ),
					$status_code
				),
				array( 'status' => $status_code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['products'] ) || ! is_array( $data['products'] ) ) {
			return new WP_Error(
				'shopify_invalid_response',
				__( 'Shopify products response could not be parsed.', 'gifting-marketplace' )
			);
		}

		$normalized = array();
		foreach ( $data['products'] as $product ) {
			$normalized[] = self::normalize_product( $product );
		}

		return $normalized;
	}

	/**
	 * Test the connection to a Shopify store by calling the shop endpoint.
	 *
	 * @param string $store_url    The Shopify store domain (e.g. my-store.myshopify.com).
	 * @param string $access_token The Shopify Admin API access token.
	 *
	 * @return true|WP_Error True on successful connection, WP_Error otherwise.
	 */
	public static function test_connection( string $store_url, string $access_token ) {
		$endpoint = sprintf(
			'https://%s/admin/api/%s/shop.json',
			sanitize_text_field( $store_url ),
			self::API_VERSION
		);

		$response = wp_remote_get(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== (int) $status_code ) {
			return new WP_Error(
				'shopify_connection_failed',
				sprintf(
					/* translators: 1: HTTP status code */
					__( 'Shopify shop endpoint returned HTTP %d. Check your store URL and access token.', 'gifting-marketplace' ),
					$status_code
				),
				array( 'status' => $status_code )
			);
		}

		return true;
	}

	/**
	 * Fetch products updated since a given datetime.
	 *
	 * @param array  $credentials     Associative array with keys 'store_url' and 'access_token'.
	 * @param string $since_datetime  MySQL datetime string, e.g. '2026-01-01 12:00:00'.
	 *
	 * @return array|WP_Error Array of normalized product arrays on success, WP_Error on failure.
	 */
	public static function fetch_since( array $credentials, string $since_datetime ) {
		$store_url    = isset( $credentials['store_url'] ) ? $credentials['store_url'] : '';
		$access_token = isset( $credentials['access_token'] ) ? $credentials['access_token'] : '';

		$iso_date = date( 'c', strtotime( $since_datetime ) );

		$endpoint = sprintf(
			'https://%s/admin/api/%s/products.json?limit=250&updated_at_min=%s',
			sanitize_text_field( $store_url ),
			self::API_VERSION,
			rawurlencode( $iso_date )
		);

		$response = wp_remote_get(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== (int) $status_code ) {
			return new WP_Error(
				'shopify_fetch_since_failed',
				sprintf(
					/* translators: 1: HTTP status code */
					__( 'Shopify products endpoint returned HTTP %d.', 'gifting-marketplace' ),
					$status_code
				),
				array( 'status' => $status_code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['products'] ) || ! is_array( $data['products'] ) ) {
			return new WP_Error(
				'shopify_invalid_response',
				__( 'Shopify products response could not be parsed.', 'gifting-marketplace' )
			);
		}

		$normalized = array();
		foreach ( $data['products'] as $product ) {
			$normalized[] = self::normalize_single( $product );
		}

		return $normalized;
	}

	/**
	 * Normalize a single raw Shopify product array to the standard schema.
	 *
	 * Extracted from fetch() as a reusable public method.
	 *
	 * @param array $raw Raw product data from the Shopify API.
	 *
	 * @return array Normalized product array with source='shopify'.
	 */
	public static function normalize_single( array $raw ): array {
		return self::normalize_product( $raw );
	}

	/**
	 * Fetch a single Shopify product by ID and return it normalized.
	 *
	 * @param string $store_url    The Shopify store domain (e.g. my-store.myshopify.com).
	 * @param string $access_token The Shopify Admin API access token.
	 * @param string $product_id   The Shopify product ID.
	 *
	 * @return array|WP_Error Normalized product array on success, WP_Error on failure.
	 */
	public static function get_product( string $store_url, string $access_token, string $product_id ) {
		$endpoint = sprintf(
			'https://%s/admin/api/%s/products/%s.json',
			sanitize_text_field( $store_url ),
			self::API_VERSION,
			rawurlencode( $product_id )
		);

		$response = wp_remote_get(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== (int) $status_code ) {
			return new WP_Error(
				'shopify_get_product_failed',
				sprintf(
					/* translators: 1: HTTP status code */
					__( 'Shopify product endpoint returned HTTP %d.', 'gifting-marketplace' ),
					$status_code
				),
				array( 'status' => $status_code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['product'] ) || ! is_array( $data['product'] ) ) {
			return new WP_Error(
				'shopify_invalid_response',
				__( 'Shopify product response could not be parsed.', 'gifting-marketplace' )
			);
		}

		return self::normalize_single( $data['product'] );
	}

	/**
	 * Register products/create, products/update, and products/delete webhooks with a Shopify store.
	 *
	 * @param string $store_url    The Shopify store domain (e.g. my-store.myshopify.com).
	 * @param string $access_token The Shopify Admin API access token.
	 * @param string $callback_url The HTTPS URL Shopify will POST events to.
	 * @param string $secret       The shared secret used to sign webhook payloads.
	 *
	 * @return array|WP_Error Array of registered webhook IDs on success, WP_Error on first failure.
	 */
	public static function register_webhooks( string $store_url, string $access_token, string $callback_url, string $secret ) {
		$topics   = array( 'products/create', 'products/update', 'products/delete' );
		$endpoint = sprintf(
			'https://%s/admin/api/%s/webhooks.json',
			sanitize_text_field( $store_url ),
			self::API_VERSION
		);

		$webhook_ids = array();

		foreach ( $topics as $topic ) {
			$body = wp_json_encode(
				array(
					'webhook' => array(
						'topic'   => $topic,
						'address' => $callback_url,
						'format'  => 'json',
						'secret'  => $secret,
					),
				)
			);

			$response = wp_remote_post(
				$endpoint,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type'  => 'application/json',
					),
					'body'    => $body,
					'timeout' => 15,
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$status_code = wp_remote_retrieve_response_code( $response );

			if ( 201 !== (int) $status_code ) {
				return new WP_Error(
					'shopify_register_webhook_failed',
					sprintf(
						/* translators: 1: webhook topic 2: HTTP status code */
						__( 'Failed to register Shopify webhook "%1$s" (HTTP %2$d).', 'gifting-marketplace' ),
						$topic,
						$status_code
					),
					array(
						'topic'  => $topic,
						'status' => $status_code,
					)
				);
			}

			$response_body = wp_remote_retrieve_body( $response );
			$data          = json_decode( $response_body, true );

			if ( isset( $data['webhook']['id'] ) ) {
				$webhook_ids[ $topic ] = (string) $data['webhook']['id'];
			}
		}

		return $webhook_ids;
	}

	/**
	 * Delete a Shopify webhook by ID.
	 *
	 * @param string $store_url    The Shopify store domain (e.g. my-store.myshopify.com).
	 * @param string $access_token The Shopify Admin API access token.
	 * @param string $webhook_id   The Shopify webhook ID to delete.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function delete_webhook( string $store_url, string $access_token, string $webhook_id ) {
		$endpoint = sprintf(
			'https://%s/admin/api/%s/webhooks/%s.json',
			sanitize_text_field( $store_url ),
			self::API_VERSION,
			rawurlencode( $webhook_id )
		);

		$response = wp_remote_request(
			$endpoint,
			array(
				'method'  => 'DELETE',
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		// Shopify returns 200 on successful webhook deletion.
		if ( 200 !== (int) $status_code ) {
			return new WP_Error(
				'shopify_delete_webhook_failed',
				sprintf(
					/* translators: 1: HTTP status code */
					__( 'Failed to delete Shopify webhook (HTTP %d).', 'gifting-marketplace' ),
					$status_code
				),
				array( 'status' => $status_code )
			);
		}

		return true;
	}

	/**
	 * Normalize a raw Shopify product array to the Giftelier sync schema.
	 *
	 * @param array $product Raw product data from the Shopify API.
	 *
	 * @return array Normalized product array.
	 */
	private static function normalize_product( array $product ): array {
		$variant = isset( $product['variants'][0] ) ? $product['variants'][0] : array();

		// Description: strip HTML, collapse whitespace, cap at 500 chars.
		$raw_body    = isset( $product['body_html'] ) ? (string) $product['body_html'] : '';
		$description = wp_strip_all_tags( $raw_body );
		$description = preg_replace( '/\s+/', ' ', $description );
		$description = trim( $description );
		if ( mb_strlen( $description ) > 500 ) {
			$description = mb_substr( $description, 0, 500 );
		}

		// Short description: first 150 chars of the cleaned description.
		$short_description = mb_substr( $description, 0, 150 );

		// Price from first variant.
		$price = isset( $variant['price'] ) ? (float) $variant['price'] : 0.0;

		// Sale price: compare_at_price is the "was" price in Shopify; it is the
		// higher value. We expose it only when it is strictly greater than price.
		$sale_price = null;
		if ( ! empty( $variant['compare_at_price'] ) ) {
			$compare_at = (float) $variant['compare_at_price'];
			if ( $compare_at > $price ) {
				$sale_price = $compare_at;
			}
		}

		// SKU.
		$sku = isset( $variant['sku'] ) ? (string) $variant['sku'] : '';

		// Images: collect the src from each image object.
		$images = array();
		if ( ! empty( $product['images'] ) && is_array( $product['images'] ) ) {
			foreach ( $product['images'] as $image ) {
				if ( ! empty( $image['src'] ) ) {
					$images[] = (string) $image['src'];
				}
			}
		}

		// Stock quantity from first variant.
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
}
