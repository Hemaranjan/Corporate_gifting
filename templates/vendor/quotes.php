<?php
/**
 * Vendor dashboard — Quote Requests page.
 *
 * @package GiftingMarketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$vendor_id = get_current_user_id();
$table     = ( new GM_Quotes() )->get_table();

$quotes = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$table} WHERE vendor_id = %d ORDER BY created_at DESC",
		$vendor_id
	)
);

$pending_count = 0;
foreach ( $quotes as $q ) {
	if ( 'pending' === $q->status ) {
		$pending_count++;
	}
}
?>
<div class="gm-vdash-wrap gm-vendor-quotes">

	<div class="gm-vdash-header">
		<h2><?php esc_html_e( 'Quote Requests', 'gifting-marketplace' ); ?></h2>
		<?php if ( $pending_count > 0 ) : ?>
			<span class="gm-status-badge gm-status--pending">
				<?php echo esc_html( $pending_count ); ?> <?php esc_html_e( 'pending', 'gifting-marketplace' ); ?>
			</span>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $quotes ) ) :
		foreach ( $quotes as $quote ) :
			$product_name   = get_the_title( $quote->product_id );
			$product_url    = get_permalink( $quote->product_id );
			$customer_data  = get_userdata( $quote->customer_id );
			$customer_name  = $customer_data ? $customer_data->display_name : __( 'Customer', 'gifting-marketplace' );
			$status         = $quote->status;
			$status_label   = GM_Quotes::get_status_label( $status );
			$date_requested = date_i18n( get_option( 'date_format' ), strtotime( $quote->created_at ) );
	?>
	<div class="gm-qcard" id="gm-qcard-<?php echo esc_attr( $quote->id ); ?>">

		<div class="gm-qcard__header">
			<div>
				<div class="gm-qcard__title">
					<a href="<?php echo esc_url( $product_url ); ?>">
						<?php echo esc_html( $product_name ); ?>
					</a>
				</div>
				<div class="gm-qcard__meta">
					<span>
						<?php /* translators: %s: customer display name */
						printf( esc_html__( 'From: %s', 'gifting-marketplace' ), esc_html( $customer_name ) ); ?>
					</span>
					<span>
						<?php /* translators: %s: quantity */
						printf( esc_html__( 'Qty: %s', 'gifting-marketplace' ), esc_html( $quote->quantity ) ); ?>
					</span>
					<span>
						<?php /* translators: %s: date */
						printf( esc_html__( 'Requested: %s', 'gifting-marketplace' ), esc_html( $date_requested ) ); ?>
					</span>
				</div>
			</div>
			<span class="gm-status-badge gm-status--<?php echo esc_attr( $status ); ?> js-quote-status-<?php echo esc_attr( $quote->id ); ?>">
				<?php echo esc_html( $status_label ); ?>
			</span>
		</div>

		<?php if ( $quote->customer_message ) : ?>
		<blockquote class="gm-qcard__message">
			<?php echo esc_html( $quote->customer_message ); ?>
		</blockquote>
		<?php endif; ?>

		<?php if ( 'pending' === $status ) : ?>
			<!-- Reply form -->
			<form class="gm-reply-form" data-quote="<?php echo esc_attr( $quote->id ); ?>">
				<label for="gm-reply-amount-<?php echo esc_attr( $quote->id ); ?>">
					<?php esc_html_e( 'Your quoted price (₹)', 'gifting-marketplace' ); ?>
				</label>
				<input
					type="number"
					id="gm-reply-amount-<?php echo esc_attr( $quote->id ); ?>"
					name="gm_reply_amount"
					min="1"
					step="0.01"
					placeholder="0.00"
				/>

				<label for="gm-reply-msg-<?php echo esc_attr( $quote->id ); ?>">
					<?php esc_html_e( 'Message to customer', 'gifting-marketplace' ); ?>
				</label>
				<textarea
					id="gm-reply-msg-<?php echo esc_attr( $quote->id ); ?>"
					name="gm_reply_msg"
					rows="3"
					placeholder="<?php esc_attr_e( 'Add a note for the customer…', 'gifting-marketplace' ); ?>"
				></textarea>

				<button type="submit" class="gm-btn gm-btn--primary gm-btn--sm">
					<?php esc_html_e( 'Send Quote', 'gifting-marketplace' ); ?>
				</button>
			</form>

		<?php else : ?>
			<!-- Already quoted / accepted / rejected -->
			<div class="gm-qcard__response">
				<?php if ( $quote->quote_amount ) : ?>
					<p>
						<strong><?php esc_html_e( 'Your quoted price:', 'gifting-marketplace' ); ?></strong>
						&#8377;<?php echo esc_html( number_format( (float) $quote->quote_amount, 2 ) ); ?>
					</p>
				<?php endif; ?>
				<?php if ( $quote->vendor_message ) : ?>
					<p>
						<strong><?php esc_html_e( 'Your message:', 'gifting-marketplace' ); ?></strong>
						<?php echo esc_html( $quote->vendor_message ); ?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

	</div><!-- .gm-qcard -->
	<?php
		endforeach;
	else : ?>
		<div class="gm-empty-state">
			<div class="gm-empty-state__icon">📬</div>
			<p class="gm-empty-state__text">
				<?php esc_html_e( 'No quote requests yet.', 'gifting-marketplace' ); ?>
			</p>
		</div>
	<?php endif; ?>

</div><!-- .gm-vdash-wrap.gm-vendor-quotes -->

<script>
window.gmVendorQuotes = {
	ajaxUrl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
	nonce:   <?php echo wp_json_encode( wp_create_nonce( 'gm_nonce' ) ); ?>,
};
</script>
