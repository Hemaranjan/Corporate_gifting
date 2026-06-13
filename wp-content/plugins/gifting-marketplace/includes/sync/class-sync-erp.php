<?php
/**
 * Giftelier Sync Hub — ERP / POS / REST API Connector
 *
 * Fetches products from an arbitrary REST API endpoint, applies a caller-supplied
 * field map, and returns a normalised array compatible with the Giftelier Sync Hub
 * product schema (source = 'erp').
 *
 * @package GiftingMarketplace\Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GM_Sync_ERP
 *
 * Static connector for ERP / POS / generic REST API sources.
 */
class GM_Sync_ERP {

	/**
	 * Keys that a remote API might use as the top-level wrapper around the
	 * products array.  Checked in order; root-level array is tried first.
	 */
	private static $WRAPPER_KEYS = array( 'products', 'data', 'items', 'results' );

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Fetch and normalise products from a remote REST API.
	 *
	 * @param string $api_url   Full URL of the products endpoint.
	 * @param string $api_key   Credential (token, base64 user:pass string, or
	 *                          raw key — depends on auth_type).
	 * @param string $auth_type One of: 'bearer', 'basic', 'query_param', 'none'.
	 * @param array  $field_map Map of remote field names to normalised field
	 *                          names, e.g. [ 'name' => 'title', 'cost' => 'price' ].
	 *                          Direction: remote key => our key.
	 *
	 * @return array|WP_Error Normalised product array on success, WP_Error on failure.
	 */
	public static function fetch( string $api_url, string $api_key, string $auth_type, array $field_map ) {

		$request_args = self::build_request_args( $api_key, $auth_type, 'GET' );
		if ( is_wp_error( $request_args ) ) {
			return $request_args;
		}

		$url      = self::apply_query_param_auth( $api_url, $api_key, $auth_type );
		$response = wp_remote_get( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'gm_erp_request_failed',
				sprintf( __( 'ERP API request failed: %s', 'gifting-marketplace' ), $response->get_error_message() )
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return new WP_Error(
				'gm_erp_bad_response',
				sprintf(
					__( 'ERP API returned HTTP %d for URL: %s', 'gifting-marketplace' ),
					$status_code,
					$url
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'gm_erp_json_error',
				sprintf(
					__( 'ERP API response is not valid JSON: %s', 'gifting-marketplace' ),
					json_last_error_msg()
				)
			);
		}

		$raw_products = self::extract_products_array( $data );

		if ( is_wp_error( $raw_products ) ) {
			return $raw_products;
		}

		return self::normalise_products( $raw_products, $field_map );
	}

	/**
	 * Test connectivity to the remote API endpoint.
	 *
	 * Attempts a HEAD request first; falls back to GET if HEAD is not supported
	 * (i.e. the server returns a non-2xx status for HEAD).
	 *
	 * @param string $api_url   Full URL of the endpoint to probe.
	 * @param string $api_key   Credential string.
	 * @param string $auth_type One of: 'bearer', 'basic', 'query_param', 'none'.
	 *
	 * @return bool|WP_Error True if the endpoint responds with HTTP 2xx,
	 *                        WP_Error on transport or authentication failure.
	 */
	public static function test_connection( string $api_url, string $api_key, string $auth_type ) {

		$url = self::apply_query_param_auth( $api_url, $api_key, $auth_type );

		// Try HEAD first (cheap, no body transfer).
		$head_args = self::build_request_args( $api_key, $auth_type, 'HEAD' );
		if ( is_wp_error( $head_args ) ) {
			return $head_args;
		}

		$head_response = wp_remote_head( $url, $head_args );

		if ( ! is_wp_error( $head_response ) ) {
			$status = (int) wp_remote_retrieve_response_code( $head_response );
			if ( $status >= 200 && $status < 300 ) {
				return true;
			}
		}

		// Fallback: GET request.
		$get_args = self::build_request_args( $api_key, $auth_type, 'GET' );
		if ( is_wp_error( $get_args ) ) {
			return $get_args;
		}

		$get_response = wp_remote_get( $url, $get_args );

		if ( is_wp_error( $get_response ) ) {
			return new WP_Error(
				'gm_erp_connection_failed',
				sprintf(
					__( 'ERP API connection test failed: %s', 'gifting-marketplace' ),
					$get_response->get_error_message()
				)
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $get_response );

		if ( $status >= 200 && $status < 300 ) {
			return true;
		}

		return new WP_Error(
			'gm_erp_connection_failed',
			sprintf(
				__( 'ERP API connection test returned HTTP %d for URL: %s', 'gifting-marketplace' ),
				$status,
				$url
			)
		);
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Build the wp_remote_* $args array including headers and timeout.
	 *
	 * @param string $api_key   Credential.
	 * @param string $auth_type 'bearer' | 'basic' | 'query_param' | 'none'.
	 * @param string $method    HTTP method string (used for sslverify context only).
	 *
	 * @return array|WP_Error
	 */
	private static function build_request_args( string $api_key, string $auth_type, string $method ) {

		$headers = array(
			'Accept'     => 'application/json',
			'User-Agent' => 'Giftelier-Sync-Hub/1.0 (WordPress/' . get_bloginfo( 'version' ) . ')',
		);

		switch ( $auth_type ) {
			case 'bearer':
				if ( '' === $api_key ) {
					return new WP_Error(
						'gm_erp_missing_key',
						__( 'ERP API key is required for bearer auth.', 'gifting-marketplace' )
					);
				}
				$headers['Authorization'] = 'Bearer ' . $api_key;
				break;

			case 'basic':
				if ( '' === $api_key ) {
					return new WP_Error(
						'gm_erp_missing_key',
						__( 'ERP API key is required for basic auth.', 'gifting-marketplace' )
					);
				}
				$headers['Authorization'] = 'Basic ' . base64_encode( $api_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				break;

			case 'query_param':
			case 'none':
				// No Authorization header; query_param is applied to the URL separately.
				break;

			default:
				return new WP_Error(
					'gm_erp_invalid_auth_type',
					sprintf(
						__( 'Unknown ERP auth_type "%s". Allowed: bearer, basic, query_param, none.', 'gifting-marketplace' ),
						$auth_type
					)
				);
		}

		return array(
			'timeout'   => 15,
			'headers'   => $headers,
			'sslverify' => apply_filters( 'gm_erp_sslverify', true ),
		);
	}

	/**
	 * Append the api_key query parameter when auth_type is 'query_param'.
	 *
	 * @param string $url       Original URL.
	 * @param string $api_key   Credential.
	 * @param string $auth_type Auth type string.
	 *
	 * @return string Modified URL.
	 */
	private static function apply_query_param_auth( string $url, string $api_key, string $auth_type ): string {

		if ( 'query_param' !== $auth_type || '' === $api_key ) {
			return $url;
		}

		return add_query_arg( 'api_key', rawurlencode( $api_key ), $url );
	}

	/**
	 * Detect and extract the products array from a decoded JSON payload.
	 *
	 * Tries root-level array first, then checks each key in self::$WRAPPER_KEYS.
	 *
	 * @param mixed $data Decoded JSON (array or object turned array via true flag).
	 *
	 * @return array|WP_Error
	 */
	private static function extract_products_array( $data ) {

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'gm_erp_unexpected_payload',
				__( 'ERP API response is not a JSON object or array.', 'gifting-marketplace' )
			);
		}

		// Root-level indexed array — treat the whole response as the product list.
		if ( isset( $data[0] ) || ( count( $data ) > 0 && array_keys( $data ) === range( 0, count( $data ) - 1 ) ) ) {
			return $data;
		}

		// Check for common wrapper keys.
		foreach ( self::$WRAPPER_KEYS as $key ) {
			if ( isset( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
				return $data[ $key ];
			}
		}

		return new WP_Error(
			'gm_erp_no_products_key',
			sprintf(
				__( 'ERP API response did not contain a recognisable products array. Checked keys: %s', 'gifting-marketplace' ),
				implode( ', ', self::$WRAPPER_KEYS )
			)
		);
	}

	/**
	 * Apply the field map to each raw product and attach source metadata.
	 *
	 * The field_map direction is: remote key => our (normalised) key.
	 * Any remote field not present in the map is carried through under its
	 * original key inside the 'meta' sub-array so no data is silently discarded.
	 *
	 * Normalised schema fields (all optional unless the map provides them):
	 *   id, title, description, price, sku, stock, image_url, categories,
	 *   brand, weight, currency, source, raw_id, meta.
	 *
	 * @param array $raw_products Indexed array of raw product arrays from the API.
	 * @param array $field_map    [ 'remote_key' => 'our_key', ... ]
	 *
	 * @return array Normalised product records.
	 */
	private static function normalise_products( array $raw_products, array $field_map ): array {

		$normalised = array();

		foreach ( $raw_products as $index => $raw ) {

			if ( ! is_array( $raw ) ) {
				continue;
			}

			$product = array(
				'source' => 'erp',
				'meta'   => array(),
			);

			foreach ( $raw as $remote_key => $value ) {
				if ( isset( $field_map[ $remote_key ] ) ) {
					$our_key            = $field_map[ $remote_key ];
					$product[ $our_key ] = self::cast_field( $our_key, $value );
				} else {
					// Preserve unmapped fields without polluting the top-level schema.
					$product['meta'][ $remote_key ] = $value;
				}
			}

			// Store the remote's own identifier separately so it can be used for
			// de-duplication without colliding with our internal 'id' field.
			if ( isset( $raw['id'] ) && ! isset( $product['raw_id'] ) ) {
				$product['raw_id'] = $raw['id'];
			}

			$normalised[] = $product;
		}

		return $normalised;
	}

	/**
	 * Lightly coerce a field value to the expected type for known schema fields.
	 *
	 * @param string $field Our normalised field name.
	 * @param mixed  $value Raw value from the API.
	 *
	 * @return mixed Coerced value.
	 */
	private static function cast_field( string $field, $value ) {

		switch ( $field ) {
			case 'price':
			case 'weight':
				return is_numeric( $value ) ? (float) $value : $value;

			case 'stock':
				return is_numeric( $value ) ? (int) $value : $value;

			case 'categories':
				if ( is_string( $value ) ) {
					// Accept comma-separated strings as a convenience.
					return array_map( 'trim', explode( ',', $value ) );
				}
				return is_array( $value ) ? $value : array( $value );

			case 'title':
			case 'description':
			case 'sku':
			case 'image_url':
			case 'brand':
			case 'currency':
				return is_string( $value ) ? sanitize_text_field( $value ) : (string) $value;

			default:
				return $value;
		}
	}
}
