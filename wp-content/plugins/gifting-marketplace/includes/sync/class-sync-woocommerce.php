<?php
/**
 * Giftelier Sync Hub — WooCommerce Connector
 *
 * @package GiftingMarketplace\Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches and normalises products from a remote WooCommerce store via the
 * WC REST API v3.
 */
class GM_Sync_WooCommerce {

	/**
	 * Maximum number of pages to retrieve in a single fetch run.
	 */
	const MAX_PAGES = 5;

	/**
	 * Number of products requested per page.
	 */
	const PER_PAGE = 100;

	/**
	 * Fetch all published products from a remote WooCommerce store.
	 *
	 * Follows pagination via the Link: <url>; rel="next" header up to
	 * MAX_PAGES pages.  Each product is normalised to the Giftelier schema
	 * before being appended to the result array.
	 *
	 * @param string $store_url       Base URL of the remote store (no trailing slash).
	 * @param string $consumer_key    WooCommerce REST API consumer key.
	 * @param string $consumer_secret WooCommerce REST API consumer secret.
	 *
	 * @return array|WP_Error Flat array of normalised product arrays, or WP_Error on failure.
	 */
	public static function fetch( string $store_url, string $consumer_key, string $consumer_secret ) {
		$products   = array();
		$next_url   = trailingslashit( $store_url ) . 'wp-json/wc/v3/products?per_page=' . self::PER_PAGE . '&status=publish';
		$pages_done = 0;

		while ( $next_url && $pages_done < self::MAX_PAGES ) {
			$response = self::remote_get( $next_url, $consumer_key, $consumer_secret );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			if ( $status_code !== 200 ) {
				return new WP_Error(
					'gm_wc_http_error',
					sprintf(
						/* translators: 1: HTTP status code */
						__( 'WooCommerce API returned HTTP %d.', 'gifting-marketplace' ),
						$status_code
					),
					array( 'status' => $status_code )
				);
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( ! is_array( $data ) ) {
				return new WP_Error(
					'gm_wc_invalid_json',
					__( 'WooCommerce API returned invalid JSON.', 'gifting-marketplace' )
				);
			}

			foreach ( $data as $raw_product ) {
				$products[] = self::normalise( $raw_product );
			}

			$pages_done++;
			$next_url = self::extract_next_link( wp_remote_retrieve_header( $response, 'link' ) );
		}

		return $products;
	}

	/**
	 * Verify that the API credentials are valid by requesting a single product.
	 *
	 * @param string $store_url       Base URL of the remote store.
	 * @param string $consumer_key    WooCommerce REST API consumer key.
	 * @param string $consumer_secret WooCommerce REST API consumer secret.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function test_connection( string $store_url, string $consumer_key, string $consumer_secret ) {
		$url      = trailingslashit( $store_url ) . 'wp-json/wc/v3/products?per_page=1&status=publish';
		$response = self::remote_get( $url, $consumer_key, $consumer_secret );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code === 200 ) {
			return true;
		}

		if ( $status_code === 401 ) {
			return new WP_Error(
				'gm_wc_unauthorized',
				__( 'WooCommerce API authentication failed. Check your consumer key and secret.', 'gifting-marketplace' ),
				array( 'status' => 401 )
			);
		}

		return new WP_Error(
			'gm_wc_connection_failed',
			sprintf(
				/* translators: 1: HTTP status code */
				__( 'WooCommerce connection test failed with HTTP %d.', 'gifting-marketplace' ),
				$status_code
			),
			array( 'status' => $status_code )
		);
	}

	/**
	 * Fetch products modified since a given datetime from a remote WooCommerce store.
	 *
	 * Uses the WooCommerce REST API `modified_after` filter to retrieve only
	 * products that have changed since the supplied timestamp.  Follows
	 * pagination up to MAX_PAGES pages.  Each product is normalised to the
	 * Giftelier schema before being appended to the result array.
	 *
	 * @param array  $credentials {
	 *     Associative array of API credentials.
	 *
	 *     @type string $store_url       Base URL of the remote store (no trailing slash).
	 *     @type string $consumer_key    WooCommerce REST API consumer key.
	 *     @type string $consumer_secret WooCommerce REST API consumer secret.
	 * }
	 * @param string $since_datetime Date/time string (any format recognised by
	 *                               DateTime) representing the lower-bound of
	 *                               the modification window.
	 *
	 * @return array|WP_Error Flat array of normalised product arrays, or WP_Error on failure.
	 */
	public static function fetch_since( array $credentials, string $since_datetime ) {
		$store_url       = $credentials['store_url'] ?? '';
		$consumer_key    = $credentials['consumer_key'] ?? '';
		$consumer_secret = $credentials['consumer_secret'] ?? '';

		if ( empty( $store_url ) || empty( $consumer_key ) || empty( $consumer_secret ) ) {
			return new WP_Error(
				'gm_wc_missing_credentials',
				__( 'store_url, consumer_key, and consumer_secret are all required.', 'gifting-marketplace' )
			);
		}

		// Convert the supplied datetime string to a strict ISO 8601 value.
		try {
			$dt      = new DateTime( $since_datetime );
			$iso     = $dt->format( DateTime::ATOM ); // e.g. 2024-01-15T10:30:00+00:00
		} catch ( Exception $e ) {
			return new WP_Error(
				'gm_wc_invalid_date',
				sprintf(
					/* translators: 1: the invalid date string */
					__( 'Invalid since_datetime value: %s', 'gifting-marketplace' ),
					$since_datetime
				)
			);
		}

		$products   = array();
		$next_url   = trailingslashit( $store_url )
			. 'wp-json/wc/v3/products?per_page=' . self::PER_PAGE
			. '&modified_after=' . rawurlencode( $iso );
		$pages_done = 0;

		while ( $next_url && $pages_done < self::MAX_PAGES ) {
			$response = self::remote_get( $next_url, $consumer_key, $consumer_secret );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			if ( $status_code !== 200 ) {
				return new WP_Error(
					'gm_wc_http_error',
					sprintf(
						/* translators: 1: HTTP status code */
						__( 'WooCommerce API returned HTTP %d.', 'gifting-marketplace' ),
						$status_code
					),
					array( 'status' => $status_code )
				);
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( ! is_array( $data ) ) {
				return new WP_Error(
					'gm_wc_invalid_json',
					__( 'WooCommerce API returned invalid JSON.', 'gifting-marketplace' )
				);
			}

			foreach ( $data as $raw_product ) {
				$products[] = self::normalise( $raw_product );
			}

			$pages_done++;
			$next_url = self::extract_next_link( wp_remote_retrieve_header( $response, 'link' ) );
		}

		return $products;
	}

	/**
	 * Normalise a single raw WooCommerce product to the Giftelier schema.
	 *
	 * This is the public counterpart of the private normalise() method and is
	 * intended for use by webhook handlers that receive a single product
	 * payload from WooCommerce.
	 *
	 * @param array $raw Raw product data from the WooCommerce REST API.
	 *
	 * @return array Normalised product array with source='woocommerce'.
	 */
	public static function normalize_single( array $raw ): array {
		return self::normalise( $raw );
	}

	/**
	 * Register WooCommerce webhooks for product lifecycle events.
	 *
	 * Creates three webhooks on the remote store (product.created,
	 * product.updated, product.deleted), all pointing to $callback_url and
	 * signed with $secret.
	 *
	 * @param string $store_url       Base URL of the remote store (no trailing slash).
	 * @param string $consumer_key    WooCommerce REST API consumer key.
	 * @param string $consumer_secret WooCommerce REST API consumer secret.
	 * @param string $callback_url    URL that WooCommerce will POST payloads to.
	 * @param string $secret          Shared secret used by WooCommerce to sign payloads.
	 *
	 * @return array|WP_Error Associative array of topic => webhook_id on success,
	 *                        or WP_Error on failure.
	 */
	public static function register_webhooks(
		string $store_url,
		string $consumer_key,
		string $consumer_secret,
		string $callback_url,
		string $secret
	) {
		$topics   = array( 'product.created', 'product.updated', 'product.deleted' );
		$endpoint = trailingslashit( $store_url ) . 'wp-json/wc/v3/webhooks';
		$results  = array();

		foreach ( $topics as $topic ) {
			$body = wp_json_encode(
				array(
					'name'         => 'Giftelier Sync',
					'topic'        => $topic,
					'delivery_url' => $callback_url,
					'secret'       => $secret,
					'status'       => 'active',
				)
			);

			$response = self::remote_post( $endpoint, $consumer_key, $consumer_secret, $body );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			if ( $status_code !== 201 ) {
				return new WP_Error(
					'gm_wc_webhook_create_failed',
					sprintf(
						/* translators: 1: webhook topic, 2: HTTP status code */
						__( 'Failed to create webhook for %1$s (HTTP %2$d).', 'gifting-marketplace' ),
						$topic,
						$status_code
					),
					array( 'status' => $status_code, 'topic' => $topic )
				);
			}

			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $data['id'] ) ) {
				return new WP_Error(
					'gm_wc_webhook_invalid_response',
					sprintf(
						/* translators: 1: webhook topic */
						__( 'WooCommerce returned an unexpected response when creating webhook for %s.', 'gifting-marketplace' ),
						$topic
					)
				);
			}

			$results[ $topic ] = (int) $data['id'];
		}

		return $results;
	}

	/**
	 * Delete a WooCommerce webhook by ID.
	 *
	 * Sends a DELETE request to the WooCommerce webhooks endpoint for the
	 * given webhook ID.  WooCommerce requires the `force=true` query parameter
	 * to permanently remove a webhook (without it the endpoint returns 405).
	 *
	 * @param string $store_url       Base URL of the remote store (no trailing slash).
	 * @param string $consumer_key    WooCommerce REST API consumer key.
	 * @param string $consumer_secret WooCommerce REST API consumer secret.
	 * @param string $webhook_id      Numeric ID of the webhook to delete.
	 *
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function delete_webhook(
		string $store_url,
		string $consumer_key,
		string $consumer_secret,
		string $webhook_id
	) {
		$url      = trailingslashit( $store_url )
			. 'wp-json/wc/v3/webhooks/' . rawurlencode( $webhook_id )
			. '?force=true';
		$response = self::remote_delete( $url, $consumer_key, $consumer_secret );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		// WooCommerce returns 200 for a successful deletion.
		if ( $status_code === 200 ) {
			return true;
		}

		if ( $status_code === 404 ) {
			return new WP_Error(
				'gm_wc_webhook_not_found',
				sprintf(
					/* translators: 1: webhook ID */
					__( 'Webhook %s not found on the remote store.', 'gifting-marketplace' ),
					$webhook_id
				),
				array( 'status' => 404 )
			);
		}

		return new WP_Error(
			'gm_wc_webhook_delete_failed',
			sprintf(
				/* translators: 1: webhook ID, 2: HTTP status code */
				__( 'Failed to delete webhook %1$s (HTTP %2$d).', 'gifting-marketplace' ),
				$webhook_id,
				$status_code
			),
			array( 'status' => $status_code )
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Perform an authenticated GET request to the WooCommerce API.
	 *
	 * @param string $url             Full request URL.
	 * @param string $consumer_key    API consumer key.
	 * @param string $consumer_secret API consumer secret.
	 *
	 * @return array|WP_Error wp_remote_get() return value.
	 */
	private static function remote_get( string $url, string $consumer_key, string $consumer_secret ) {
		$credentials = base64_encode( $consumer_key . ':' . $consumer_secret );

		return wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Basic ' . $credentials,
					'Accept'        => 'application/json',
				),
			)
		);
	}

	/**
	 * Perform an authenticated POST request to the WooCommerce API.
	 *
	 * @param string $url             Full request URL.
	 * @param string $consumer_key    API consumer key.
	 * @param string $consumer_secret API consumer secret.
	 * @param string $body            JSON-encoded request body.
	 *
	 * @return array|WP_Error wp_remote_post() return value.
	 */
	private static function remote_post( string $url, string $consumer_key, string $consumer_secret, string $body ) {
		$credentials = base64_encode( $consumer_key . ':' . $consumer_secret );

		return wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Basic ' . $credentials,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => $body,
			)
		);
	}

	/**
	 * Perform an authenticated DELETE request to the WooCommerce API.
	 *
	 * @param string $url             Full request URL.
	 * @param string $consumer_key    API consumer key.
	 * @param string $consumer_secret API consumer secret.
	 *
	 * @return array|WP_Error wp_remote_request() return value.
	 */
	private static function remote_delete( string $url, string $consumer_key, string $consumer_secret ) {
		$credentials = base64_encode( $consumer_key . ':' . $consumer_secret );

		return wp_remote_request(
			$url,
			array(
				'method'  => 'DELETE',
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Basic ' . $credentials,
					'Accept'        => 'application/json',
				),
			)
		);
	}

	/**
	 * Parse the rel="next" URL out of a Link response header string.
	 *
	 * The WooCommerce REST API uses RFC 5988 link headers such as:
	 *   <https://example.com/wp-json/wc/v3/products?page=2>; rel="next"
	 *
	 * @param string $link_header Value of the Link response header.
	 *
	 * @return string|null Next page URL, or null when there is no next page.
	 */
	private static function extract_next_link( string $link_header ): ?string {
		if ( empty( $link_header ) ) {
			return null;
		}

		// A Link header may contain multiple comma-separated entries.
		$parts = explode( ',', $link_header );

		foreach ( $parts as $part ) {
			$segments = explode( ';', trim( $part ) );

			if ( count( $segments ) < 2 ) {
				continue;
			}

			$url_segment = trim( $segments[0] );
			$rel_segment = trim( $segments[1] );

			if ( strpos( $rel_segment, 'rel="next"' ) === false ) {
				continue;
			}

			// Strip the surrounding angle brackets from <url>.
			if ( preg_match( '/^<(.+)>$/', $url_segment, $matches ) ) {
				return $matches[1];
			}
		}

		return null;
	}

	/**
	 * Normalise a raw WooCommerce product array to the Giftelier schema.
	 *
	 * @param array $raw Raw product data from the WooCommerce REST API.
	 *
	 * @return array Normalised product array.
	 */
	private static function normalise( array $raw ): array {
		$description       = self::plain_text( $raw['description'] ?? '', 500 );
		$short_description = self::plain_text( $raw['short_description'] ?? '', 150 );

		$regular_price = isset( $raw['regular_price'] ) && $raw['regular_price'] !== ''
			? (float) $raw['regular_price']
			: 0.0;

		$sale_price = isset( $raw['sale_price'] ) && $raw['sale_price'] !== ''
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

		$stock_qty = isset( $raw['stock_quantity'] ) && $raw['stock_quantity'] !== null
			? (int) $raw['stock_quantity']
			: 0;

		$sku = isset( $raw['sku'] ) ? sanitize_text_field( $raw['sku'] ) : '';

		return array(
			'external_id'       => (int) ( $raw['id'] ?? 0 ),
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
	 * Strip HTML tags from a string and truncate it to a maximum character length.
	 *
	 * A trailing ellipsis is appended when the string is truncated.
	 *
	 * @param string $html     Raw HTML string from the API.
	 * @param int    $max_len  Maximum number of characters in the returned string.
	 *
	 * @return string Plain-text string, truncated if necessary.
	 */
	private static function plain_text( string $html, int $max_len ): string {
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		if ( mb_strlen( $text ) > $max_len ) {
			$text = rtrim( mb_substr( $text, 0, $max_len - 1 ) ) . "\u{2026}";
		}

		return $text;
	}
}
