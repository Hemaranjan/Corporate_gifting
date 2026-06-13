<?php
/**
 * GM_Quotes — manages the gm_quotes custom DB table and all quote AJAX handlers.
 *
 * @package GiftingMarketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GM_Quotes {

	public function __construct() {
		add_action( 'init', [ $this, 'maybe_create_table' ] );

		// Product page quote button — priority 31: right after Add to Cart (30), before third-party plugins.
		add_action( 'woocommerce_single_product_summary', [ $this, 'render_quote_button' ], 31 );

		// AJAX handlers.
		add_action( 'wp_ajax_gm_request_quote',  [ $this, 'ajax_request_quote' ] );
		add_action( 'wp_ajax_gm_vendor_reply',   [ $this, 'ajax_vendor_reply' ] );
		add_action( 'wp_ajax_gm_accept_quote',   [ $this, 'ajax_accept_quote' ] );
		add_action( 'wp_ajax_gm_reject_quote',   [ $this, 'ajax_reject_quote' ] );

		// Dokan vendor dashboard endpoint.
		add_filter( 'dokan_query_var_filter',   [ $this, 'add_vendor_qv' ] );
		add_action( 'dokan_load_custom_template', [ $this, 'load_vendor_quotes' ] );

		// Cart / order hooks for quoted prices.
		add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply_quoted_price' ], 99 );
		add_filter( 'woocommerce_get_cart_item_from_session', [ $this, 'restore_quoted_price' ], 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_quote_id_to_order_item' ], 10, 3 );
	}

	// -------------------------------------------------------------------------
	// Table management
	// -------------------------------------------------------------------------

	/**
	 * Create the gm_quotes table once via dbDelta.
	 */
	public function maybe_create_table() {
		if ( get_option( 'gm_quotes_db_v' ) === '1.0' ) {
			return;
		}

		global $wpdb;

		$table      = $this->get_table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id bigint(20) UNSIGNED NOT NULL,
			vendor_id bigint(20) UNSIGNED NOT NULL,
			customer_id bigint(20) UNSIGNED NOT NULL,
			quantity int(11) NOT NULL DEFAULT 1,
			customer_message text NOT NULL,
			quote_amount decimal(10,2) DEFAULT NULL,
			vendor_message text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'gm_quotes_db_v', '1.0' );
	}

	/**
	 * Return the fully-qualified table name.
	 *
	 * @return string
	 */
	public function get_table() {
		global $wpdb;
		return $wpdb->prefix . 'gm_quotes';
	}

	// -------------------------------------------------------------------------
	// Front-end: quote button & modal
	// -------------------------------------------------------------------------

	/**
	 * Render "Request a Quote" button + modal on single product pages.
	 */
	public function render_quote_button() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$uid = get_current_user_id();
		// Hide for non-admin vendors only; admins and customers always see the button.
		if ( ! current_user_can( 'manage_options' )
		     && function_exists( 'dokan_is_user_seller' )
		     && dokan_is_user_seller( $uid ) ) {
			return;
		}

		$product_id   = get_the_ID();
		$product_name = get_the_title();
		$vendor_id    = (int) get_post_field( 'post_author', $product_id );
		?>
		<div class="gm-quote-wrap">
			<button
				class="gm-quote-btn"
				data-product="<?php echo esc_attr( $product_id ); ?>"
				data-product-name="<?php echo esc_attr( $product_name ); ?>"
				data-vendor="<?php echo esc_attr( $vendor_id ); ?>"
			>
				<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
				<?php esc_html_e( 'Request a Quote', 'gifting-marketplace' ); ?>
			</button>
			<p class="gm-quote-hint">
				<?php esc_html_e( 'Need a custom price for bulk or special orders?', 'gifting-marketplace' ); ?>
			</p>
		</div>

		<div id="gm-quote-modal" class="gm-quote-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="gm-quote-modal-title">
			<div class="gm-quote-modal">
				<div class="gm-quote-modal__header">
					<div>
						<h3 id="gm-quote-modal-title" class="gm-quote-modal__title">
							<?php esc_html_e( 'Request a Quote', 'gifting-marketplace' ); ?>
						</h3>
						<p class="gm-quote-modal__product-name"></p>
					</div>
					<button type="button" id="gm-quote-cancel" class="gm-quote-modal__close" aria-label="<?php esc_attr_e( 'Close', 'gifting-marketplace' ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
					</button>
				</div>
				<div class="gm-quote-modal__body">
					<label for="gm-quote-qty">
						<?php esc_html_e( 'Quantity', 'gifting-marketplace' ); ?>
					</label>
					<input
						type="number"
						id="gm-quote-qty"
						name="gm_quote_qty"
						min="1"
						max="999"
						value="1"
					/>

					<label for="gm-quote-msg">
						<?php esc_html_e( 'Your Message', 'gifting-marketplace' ); ?>
					</label>
					<textarea
						id="gm-quote-msg"
						name="gm_quote_msg"
						rows="4"
						placeholder="<?php esc_attr_e( 'Describe your requirements, quantities, delivery timeline…', 'gifting-marketplace' ); ?>"
					></textarea>

					<input type="hidden" id="gm-quote-product-id" name="product_id" value="" />
					<input type="hidden" id="gm-quote-vendor-id"  name="vendor_id"  value="" />
				</div>
				<div class="gm-quote-modal__footer">
					<button type="button" class="gm-btn gm-btn--sm gm-btn--ghost gm-quote-dismiss">
						<?php esc_html_e( 'Cancel', 'gifting-marketplace' ); ?>
					</button>
					<button type="button" id="gm-quote-submit" class="gm-btn gm-btn--sm gm-btn--primary">
						<?php esc_html_e( 'Send Request', 'gifting-marketplace' ); ?>
					</button>
				</div>
			</div>
		</div>

		<script>
		window.gmQuoteNonce = <?php echo wp_json_encode( wp_create_nonce( 'gm_nonce' ) ); ?>;
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// AJAX: customer requests a quote
	// -------------------------------------------------------------------------

	public function ajax_request_quote() {
		check_ajax_referer( 'gm_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'gifting-marketplace' ) ] );
		}

		$product_id  = absint( $_POST['product_id'] ?? 0 );
		$vendor_id   = absint( $_POST['vendor_id']  ?? 0 );
		$quantity    = max( 1, absint( $_POST['quantity'] ?? 1 ) );
		$message     = sanitize_textarea_field( $_POST['message'] ?? '' );
		$customer_id = get_current_user_id();

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product.', 'gifting-marketplace' ) ] );
		}

		global $wpdb;
		$inserted = $wpdb->insert(
			$this->get_table(),
			[
				'product_id'       => $product_id,
				'vendor_id'        => $vendor_id,
				'customer_id'      => $customer_id,
				'quantity'         => $quantity,
				'customer_message' => $message,
				'status'           => 'pending',
				'created_at'       => current_time( 'mysql' ),
				'updated_at'       => current_time( 'mysql' ),
			],
			[ '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' ]
		);

		if ( ! $inserted ) {
			wp_send_json_error( [ 'message' => __( 'Could not save quote request.', 'gifting-marketplace' ) ] );
		}

		$quote_id = $wpdb->insert_id;

		// Notify vendor.
		$vendor       = get_userdata( $vendor_id );
		$customer     = get_userdata( $customer_id );
		$product_name = $product->get_name();

		if ( $vendor && $vendor->user_email ) {
			$subject = sprintf( __( 'New Quote Request for %s', 'gifting-marketplace' ), $product_name );
			$body    = sprintf(
				/* translators: 1: customer name, 2: product name, 3: quantity, 4: message */
				__(
					"Hello,\n\nYou have received a new quote request.\n\nCustomer: %1\$s\nProduct: %2\$s\nQuantity: %3\$d\nMessage:\n%4\$s\n\nPlease log in to your vendor dashboard to respond.",
					'gifting-marketplace'
				),
				$customer ? $customer->display_name : __( 'A customer', 'gifting-marketplace' ),
				$product_name,
				$quantity,
				$message
			);
			wp_mail( $vendor->user_email, $subject, $body );
		}

		wp_send_json_success( [ 'quote_id' => $quote_id ] );
	}

	// -------------------------------------------------------------------------
	// AJAX: vendor replies with a price
	// -------------------------------------------------------------------------

	public function ajax_vendor_reply() {
		check_ajax_referer( 'gm_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'gifting-marketplace' ) ] );
		}

		$quote_id = absint( $_POST['quote_id'] ?? 0 );
		$amount   = floatval( $_POST['amount']  ?? 0 );
		$msg      = sanitize_textarea_field( $_POST['message'] ?? '' );

		if ( $amount <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Please enter a valid amount greater than 0.', 'gifting-marketplace' ) ] );
		}

		global $wpdb;
		$quote = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->get_table()} WHERE id = %d", $quote_id ) );

		if ( ! $quote ) {
			wp_send_json_error( [ 'message' => __( 'Quote not found.', 'gifting-marketplace' ) ] );
		}

		if ( (int) $quote->vendor_id !== get_current_user_id() ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'gifting-marketplace' ) ] );
		}

		$wpdb->update(
			$this->get_table(),
			[
				'status'         => 'quoted',
				'quote_amount'   => $amount,
				'vendor_message' => $msg,
				'updated_at'     => current_time( 'mysql' ),
			],
			[ 'id' => $quote_id ],
			[ '%s', '%f', '%s', '%s' ],
			[ '%d' ]
		);

		// Notify customer.
		$customer = get_userdata( $quote->customer_id );
		$product  = wc_get_product( $quote->product_id );
		if ( $customer && $customer->user_email ) {
			$subject = sprintf( __( 'Quote Received for %s', 'gifting-marketplace' ), $product ? $product->get_name() : __( 'your product', 'gifting-marketplace' ) );
			$body    = sprintf(
				/* translators: 1: amount, 2: message */
				__(
					"Good news! You have received a quote.\n\nQuoted Price: ₹%1\$s\nVendor Message:\n%2\$s\n\nLog in to your account to accept or reject this quote.",
					'gifting-marketplace'
				),
				number_format( $amount, 2 ),
				$msg
			);
			wp_mail( $customer->user_email, $subject, $body );
		}

		wp_send_json_success( [ 'quote_id' => $quote_id, 'amount' => $amount ] );
	}

	// -------------------------------------------------------------------------
	// AJAX: customer accepts a quote → creates WC order
	// -------------------------------------------------------------------------

	public function ajax_accept_quote() {
		check_ajax_referer( 'gm_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'gifting-marketplace' ) ] );
		}

		$quote_id    = absint( $_POST['quote_id'] ?? 0 );
		$customer_id = get_current_user_id();

		global $wpdb;
		$quote = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->get_table()} WHERE id = %d", $quote_id ) );

		if ( ! $quote ) {
			wp_send_json_error( [ 'message' => __( 'Quote not found.', 'gifting-marketplace' ) ] );
		}

		if ( (int) $quote->customer_id !== $customer_id ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'gifting-marketplace' ) ] );
		}

		if ( $quote->status !== 'quoted' ) {
			wp_send_json_error( [ 'message' => __( 'This quote cannot be accepted in its current state.', 'gifting-marketplace' ) ] );
		}

		// Update status.
		$wpdb->update(
			$this->get_table(),
			[ 'status' => 'accepted', 'updated_at' => current_time( 'mysql' ) ],
			[ 'id' => $quote_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		// Create WooCommerce order.
		$order = wc_create_order( [ 'customer_id' => $customer_id ] );

		$product = wc_get_product( $quote->product_id );
		if ( $product ) {
			$item = new WC_Order_Item_Product();
			$item->set_product( $product );
			$item->set_quantity( $quote->quantity );
			$item->set_subtotal( (float) $quote->quote_amount * (int) $quote->quantity );
			$item->set_total( (float) $quote->quote_amount * (int) $quote->quantity );
			$item->add_meta_data( 'gm_quote_id', $quote_id, true );
			$order->add_item( $item );
		}

		$order->calculate_totals();
		$order->set_status( 'pending-payment' );
		$order->save();

		wp_send_json_success( [ 'redirect' => $order->get_checkout_payment_url() ] );
	}

	// -------------------------------------------------------------------------
	// AJAX: customer rejects a quote
	// -------------------------------------------------------------------------

	public function ajax_reject_quote() {
		check_ajax_referer( 'gm_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'You must be logged in.', 'gifting-marketplace' ) ] );
		}

		$quote_id    = absint( $_POST['quote_id'] ?? 0 );
		$customer_id = get_current_user_id();

		global $wpdb;
		$quote = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->get_table()} WHERE id = %d", $quote_id ) );

		if ( ! $quote ) {
			wp_send_json_error( [ 'message' => __( 'Quote not found.', 'gifting-marketplace' ) ] );
		}

		if ( (int) $quote->customer_id !== $customer_id ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'gifting-marketplace' ) ] );
		}

		$wpdb->update(
			$this->get_table(),
			[ 'status' => 'rejected', 'updated_at' => current_time( 'mysql' ) ],
			[ 'id' => $quote_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		wp_send_json_success( [ 'quote_id' => $quote_id ] );
	}

	// -------------------------------------------------------------------------
	// Dokan vendor dashboard endpoint
	// -------------------------------------------------------------------------

	/**
	 * Register the 'gm-quotes' query var with Dokan.
	 *
	 * @param array $qvars
	 * @return array
	 */
	public function add_vendor_qv( $qvars ) {
		$qvars['gm-quotes'] = 'gm-quotes';
		return $qvars;
	}

	/**
	 * Load the vendor quotes template when the endpoint is active.
	 *
	 * @param array $qv
	 */
	public function load_vendor_quotes( $qv ) {
		if ( ! isset( $qv['gm-quotes'] ) ) {
			return;
		}
		// Wrap with the same structure all Dokan templates use so the nav renders.
		do_action( 'dokan_dashboard_wrap_start' );
		echo '<div class="dokan-dashboard-wrap">';
		do_action( 'dokan_dashboard_content_before' );
		echo '<div class="dokan-dashboard-content">';
		do_action( 'dokan_dashboard_content_inside_before' );
		include GM_PATH . 'templates/vendor/quotes.php';
		do_action( 'dokan_dashboard_content_inside_after' );
		echo '</div>';
		do_action( 'dokan_dashboard_content_after' );
		echo '</div>';
		do_action( 'dokan_dashboard_wrap_end' );
	}

	// -------------------------------------------------------------------------
	// Cart / order: apply quoted price
	// -------------------------------------------------------------------------

	/**
	 * Overwrite the cart item price with the agreed quote amount.
	 *
	 * @param WC_Cart $cart
	 */
	public function apply_quoted_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $item ) {
			if ( isset( $item['gm_quoted_price'] ) && $item['gm_quoted_price'] > 0 ) {
				$item['data']->set_price( (float) $item['gm_quoted_price'] );
			}
		}
	}

	/**
	 * Restore quoted price meta from session data into the cart item array.
	 *
	 * @param array $cart_item
	 * @param array $values  Session values.
	 * @return array
	 */
	public function restore_quoted_price( $cart_item, $values ) {
		if ( isset( $values['gm_quoted_price'] ) ) {
			$cart_item['gm_quoted_price'] = $values['gm_quoted_price'];
		}
		if ( isset( $values['gm_quote_id'] ) ) {
			$cart_item['gm_quote_id'] = $values['gm_quote_id'];
		}
		return $cart_item;
	}

	/**
	 * Persist the quote ID as order item meta on checkout.
	 *
	 * @param WC_Order_Item_Product $item
	 * @param string                $cart_item_key
	 * @param array                 $values
	 */
	public function save_quote_id_to_order_item( $item, $cart_item_key, $values ) {
		if ( isset( $values['gm_quote_id'] ) ) {
			$item->add_meta_data( 'gm_quote_id', (int) $values['gm_quote_id'], true );
		}
	}

	// -------------------------------------------------------------------------
	// Static helpers
	// -------------------------------------------------------------------------

	/**
	 * Human-readable label for a quote status.
	 *
	 * @param string $status
	 * @return string
	 */
	public static function get_status_label( $status ) {
		$labels = [
			'pending'  => __( 'Awaiting Response', 'gifting-marketplace' ),
			'quoted'   => __( 'Quote Received',    'gifting-marketplace' ),
			'accepted' => __( 'Accepted',          'gifting-marketplace' ),
			'rejected' => __( 'Rejected',          'gifting-marketplace' ),
		];
		return $labels[ $status ] ?? ucfirst( $status );
	}
}
