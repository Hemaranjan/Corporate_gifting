<?php
/**
 * CSV / Excel / Google Sheets Connector
 *
 * Part of the Giftelier Sync Hub.
 *
 * @package GiftingMarketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles importing products from CSV files and Google Sheets.
 */
class GM_Sync_CSV {

	/**
	 * Maximum allowed file size: 5 MB.
	 */
	const MAX_FILE_SIZE = 5 * 1024 * 1024;

	/**
	 * Allowed file extensions.
	 */
	const ALLOWED_EXTENSIONS = array( 'csv', 'xlsx', 'xls' );

	// -------------------------------------------------------------------------
	// Column-name normalisation map.
	// Keys are lowercase source header names; values are internal field names.
	// -------------------------------------------------------------------------
	const COLUMN_MAP = array(
		'title'         => 'title',
		'name'          => 'title',
		'price'         => 'price',
		'regular_price' => 'price',
		'description'   => 'description',
		'desc'          => 'description',
		'sku'           => 'sku',
		'images'        => 'images',
		'image_url'     => 'images',
		'sale_price'    => 'sale_price',
		'stock'         => 'stock_qty',
		'stock_qty'     => 'stock_qty',
		'quantity'      => 'stock_qty',
	);

	// =========================================================================
	// PUBLIC API
	// =========================================================================

	/**
	 * Parse a locally-uploaded CSV/XLSX/XLS file.
	 *
	 * @param array $file A single entry from $_FILES (keys: name, tmp_name,
	 *                    type, error, size).
	 * @return array|WP_Error Normalised product rows on success, WP_Error on
	 *                        failure.
	 */
	public static function fetch_from_file( array $file ) {

		// ----- 1. Validate upload error code ---------------------------------
		if ( ! isset( $file['error'] ) || 0 !== (int) $file['error'] ) {
			$upload_error = isset( $file['error'] ) ? (int) $file['error'] : - 1;
			return new WP_Error(
				'upload_error',
				sprintf(
					/* translators: %d: PHP upload error code */
					__( 'File upload failed with error code %d.', 'gifting-marketplace' ),
					$upload_error
				)
			);
		}

		// ----- 2. Validate file size -----------------------------------------
		if ( ! isset( $file['size'] ) || (int) $file['size'] >= self::MAX_FILE_SIZE ) {
			return new WP_Error(
				'file_too_large',
				__( 'File exceeds the 5 MB size limit.', 'gifting-marketplace' )
			);
		}

		// ----- 3. Validate extension -----------------------------------------
		$original_name = isset( $file['name'] ) ? (string) $file['name'] : '';
		$extension     = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );

		if ( ! in_array( $extension, self::ALLOWED_EXTENSIONS, true ) ) {
			return new WP_Error(
				'invalid_extension',
				sprintf(
					/* translators: %s: file extension */
					__( 'Unsupported file type ".%s". Please upload a CSV, XLSX, or XLS file.', 'gifting-marketplace' ),
					$extension
				)
			);
		}

		// ----- 4. Route to the correct parser --------------------------------
		if ( 'csv' === $extension ) {
			return self::parse_csv_file( $file['tmp_name'], 'csv' );
		}

		// xlsx / xls
		return new WP_Error(
			'xlsx_not_supported',
			__( 'XLSX not supported yet, please export as CSV.', 'gifting-marketplace' )
		);
	}

	/**
	 * Fetch products from a Google Sheets share URL or published CSV URL.
	 *
	 * Supported URL forms:
	 *   - https://docs.google.com/spreadsheets/d/{ID}/edit#gid=…
	 *   - https://docs.google.com/spreadsheets/d/{ID}/pub?…&output=csv
	 *
	 * @param string $sheets_url The URL supplied by the user.
	 * @return array|WP_Error Normalised product rows on success, WP_Error on
	 *                        failure.
	 */
	public static function fetch_from_sheets( string $sheets_url ) {

		$csv_url = self::convert_sheets_url( $sheets_url );

		if ( is_wp_error( $csv_url ) ) {
			return $csv_url;
		}

		// ----- Fetch remote CSV ----------------------------------------------
		$response = wp_remote_get(
			$csv_url,
			array(
				'timeout'    => 30,
				'user-agent' => 'GiftingMarketplace-SyncHub/1.0',
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'remote_fetch_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Could not retrieve Google Sheet: %s', 'gifting-marketplace' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $status_code ) {
			return new WP_Error(
				'remote_fetch_http_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Google Sheets returned HTTP %d. Ensure the sheet is publicly shared.', 'gifting-marketplace' ),
					$status_code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			return new WP_Error(
				'empty_response',
				__( 'Google Sheets returned an empty response.', 'gifting-marketplace' )
			);
		}

		return self::parse_csv_string( $body, 'google_sheets' );
	}

	// =========================================================================
	// PRIVATE HELPERS
	// =========================================================================

	/**
	 * Convert a Google Sheets user-facing URL to a direct CSV export URL.
	 *
	 * @param string $url Raw URL.
	 * @return string|WP_Error CSV export URL, or WP_Error if the URL is not
	 *                          recognisable.
	 */
	private static function convert_sheets_url( string $url ) {

		$url = trim( $url );

		if ( empty( $url ) ) {
			return new WP_Error(
				'empty_url',
				__( 'Please provide a Google Sheets URL.', 'gifting-marketplace' )
			);
		}

		// Already a published CSV URL (/pub?…&output=csv).
		if ( false !== strpos( $url, '/pub' ) && false !== strpos( $url, 'output=csv' ) ) {
			return $url;
		}

		// Published URL without output=csv – add it.
		if ( false !== strpos( $url, '/pub' ) ) {
			$separator = ( false !== strpos( $url, '?' ) ) ? '&' : '?';
			return $url . $separator . 'output=csv';
		}

		// Standard share/edit URL: strip everything from /edit or /view onwards
		// and replace with /export?format=csv.
		if ( preg_match( '#^(https://docs\.google\.com/spreadsheets/d/[^/]+)#i', $url, $matches ) ) {
			return $matches[1] . '/export?format=csv';
		}

		return new WP_Error(
			'invalid_sheets_url',
			__( 'Could not parse the Google Sheets URL. Please use a standard share or published URL.', 'gifting-marketplace' )
		);
	}

	/**
	 * Open a local file path and parse it as CSV.
	 *
	 * @param string $tmp_path  Path to the temporary file.
	 * @param string $source    Source label to embed in each row.
	 * @return array|WP_Error
	 */
	private static function parse_csv_file( string $tmp_path, string $source ) {

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $tmp_path, 'r' );

		if ( false === $handle ) {
			return new WP_Error(
				'file_open_failed',
				__( 'Could not open the uploaded file for reading.', 'gifting-marketplace' )
			);
		}

		$rows    = array();
		$headers = null;

		// phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
		while ( false !== ( $row = fgetcsv( $handle ) ) ) {
			if ( null === $headers ) {
				// First row: treat as headers.
				$headers = array_map( 'trim', $row );
				continue;
			}
			$rows[] = $row;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( null === $headers ) {
			return new WP_Error(
				'empty_file',
				__( 'The CSV file appears to be empty.', 'gifting-marketplace' )
			);
		}

		return self::normalise_rows( $headers, $rows, $source );
	}

	/**
	 * Parse a CSV string (e.g. a remote response body) line by line.
	 *
	 * @param string $csv_string Raw CSV content.
	 * @param string $source     Source label to embed in each row.
	 * @return array|WP_Error
	 */
	private static function parse_csv_string( string $csv_string, string $source ) {

		// Normalise line endings to \n before splitting.
		$csv_string = str_replace( array( "\r\n", "\r" ), "\n", $csv_string );
		$lines      = explode( "\n", $csv_string );

		$headers = null;
		$rows    = array();

		foreach ( $lines as $line ) {
			// Skip completely blank lines.
			if ( '' === trim( $line ) ) {
				continue;
			}

			$fields = str_getcsv( $line );

			if ( null === $headers ) {
				$headers = array_map( 'trim', $fields );
				continue;
			}

			$rows[] = $fields;
		}

		if ( null === $headers ) {
			return new WP_Error(
				'empty_sheet',
				__( 'The Google Sheet appears to be empty.', 'gifting-marketplace' )
			);
		}

		return self::normalise_rows( $headers, $rows, $source );
	}

	/**
	 * Map raw header/value pairs to the internal product schema.
	 *
	 * @param string[] $headers  First-row header names.
	 * @param array[]  $rows     Subsequent data rows (indexed arrays).
	 * @param string   $source   'csv' or 'google_sheets'.
	 * @return array Normalised product rows.
	 */
	private static function normalise_rows( array $headers, array $rows, string $source ): array {

		// Build a map: column-index → internal field name (or null to skip).
		$index_map = array();
		foreach ( $headers as $idx => $header ) {
			$key              = strtolower( trim( $header ) );
			$index_map[ $idx ] = isset( self::COLUMN_MAP[ $key ] ) ? self::COLUMN_MAP[ $key ] : null;
		}

		$products = array();

		foreach ( $rows as $row ) {
			$product = array(
				'title'       => '',
				'price'       => '',
				'description' => '',
				'sku'         => '',
				'images'      => array(),
				'sale_price'  => '',
				'stock_qty'   => '',
				'source'      => $source,
			);

			foreach ( $index_map as $idx => $field ) {
				if ( null === $field ) {
					continue;
				}

				$value = isset( $row[ $idx ] ) ? trim( (string) $row[ $idx ] ) : '';

				if ( 'images' === $field ) {
					// Split comma-separated image URLs into an array.
					if ( '' !== $value ) {
						$image_parts = array_map( 'trim', explode( ',', $value ) );
						$product['images'] = array_filter( $image_parts, 'strlen' );
						$product['images'] = array_values( $product['images'] );
					}
				} else {
					// For scalar fields, only overwrite if not already set by a
					// higher-priority alias (e.g. 'title' wins over 'name').
					// We achieve this by always writing; since COLUMN_MAP lists
					// the preferred alias first in PHP iteration order, the last
					// write wins — acceptable for this use-case, but we
					// protect 'title'/'price' by only writing non-empty values
					// when the field already has content.
					if ( '' === $product[ $field ] || '' !== $value ) {
						$product[ $field ] = $value;
					}
				}
			}

			// Skip rows with no title.
			if ( '' === $product['title'] ) {
				continue;
			}

			$products[] = $product;
		}

		return $products;
	}
}
