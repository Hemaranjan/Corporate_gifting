<?php
/**
 * Customer dashboard — Quotes page.
 *
 * @package GiftingMarketplace
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$customer_id = get_current_user_id();
$table       = ( new GM_Quotes() )->get_table();

$quotes = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$table} WHERE customer_id = %d ORDER BY created_at DESC",
		$customer_id
	)
);
?>
<div class="gm-dash gm-quotes-page">

	<!-- Welcome banner -->
	<div class="gm-dash__banner">
		<h1 class="gm-dash__banner-title">Your Quotes 📋</h1>
		<p class="gm-dash__banner-subtitle">Track quote requests and accept vendor offers.</p>
	</div>

	<!-- Quotes card -->
	<div class="gm-dash__card">
		<?php if ( ! empty( $quotes ) ) : ?>
			<table class="gm-quotes-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'gifting-marketplace' ); ?></th>
						<th><?php esc_html_e( 'Vendor', 'gifting-marketplace' ); ?></th>
						<th><?php esc_html_e( 'Qty', 'gifting-marketplace' ); ?></th>
						<th><?php esc_html_e( 'Requested', 'gifting-marketplace' ); ?></th>
						<th><?php esc_html_e( 'Status', 'gifting-marketplace' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'gifting-marketplace' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'gifting-marketplace' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $quotes as $quote ) :
						$product_name  = get_the_title( $quote->product_id );
						$product_url   = get_permalink( $quote->product_id );
						$thumb_url     = get_the_post_thumbnail_url( $quote->product_id, 'thumbnail' );
						$store_info    = function_exists( 'dokan_get_store_info' ) ? dokan_get_store_info( $quote->vendor_id ) : [];
						$store_name    = ! empty( $store_info['store_name'] ) ? $store_info['store_name'] : __( 'Vendor', 'gifting-marketplace' );
						$status        = $quote->status;
						$status_label  = GM_Quotes::get_status_label( $status );
						$time_diff     = human_time_diff( strtotime( $quote->created_at ), current_time( 'timestamp' ) );
					?>
					<tr data-quote-id="<?php echo esc_attr( $quote->id ); ?>">

						<!-- Product -->
						<td>
							<div class="gm-quotes-product-cell">
								<?php if ( $thumb_url ) : ?>
									<img
										src="<?php echo esc_url( $thumb_url ); ?>"
										alt="<?php echo esc_attr( $product_name ); ?>"
										class="gm-product-thumb"
									/>
								<?php endif; ?>
								<a href="<?php echo esc_url( $product_url ); ?>">
									<?php echo esc_html( $product_name ); ?>
								</a>
							</div>
						</td>

						<!-- Vendor -->
						<td><?php echo esc_html( $store_name ); ?></td>

						<!-- Qty -->
						<td><?php echo esc_html( $quote->quantity ); ?></td>

						<!-- Date -->
						<td><?php /* translators: %s: human-readable time diff */
							printf( esc_html__( '%s ago', 'gifting-marketplace' ), esc_html( $time_diff ) ); ?></td>

						<!-- Status -->
						<td>
							<span class="gm-status-badge gm-status--<?php echo esc_attr( $status ); ?>">
								<?php echo esc_html( $status_label ); ?>
							</span>
						</td>

						<!-- Amount -->
						<td>
							<?php if ( 'pending' === $status ) : ?>
								&mdash;
							<?php else : ?>
								&#8377;<?php echo esc_html( number_format( (float) $quote->quote_amount, 0 ) ); ?>
							<?php endif; ?>
						</td>

						<!-- Actions -->
						<td class="gm-quote-actions">
							<?php if ( 'quoted' === $status ) : ?>
								<button
									class="gm-btn gm-btn--primary gm-btn--sm gm-accept-quote"
									data-id="<?php echo esc_attr( $quote->id ); ?>"
									data-amount="<?php echo esc_attr( $quote->quote_amount ); ?>"
								>
									<?php esc_html_e( 'Accept', 'gifting-marketplace' ); ?>
								</button>
								<button
									class="gm-btn gm-btn--ghost-rose gm-btn--sm gm-reject-quote"
									data-id="<?php echo esc_attr( $quote->id ); ?>"
								>
									<?php esc_html_e( 'Reject', 'gifting-marketplace' ); ?>
								</button>
							<?php elseif ( 'accepted' === $status ) : ?>
								<span style="color:#10B981;font-weight:700;">&#10003; <?php esc_html_e( 'Order Created', 'gifting-marketplace' ); ?></span>
							<?php elseif ( 'pending' === $status ) : ?>
								<span class="gm-quote-muted"><?php esc_html_e( 'Awaiting vendor…', 'gifting-marketplace' ); ?></span>
							<?php endif; ?>
						</td>

					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<div class="gm-empty-state">
				<div class="gm-empty-state__icon">📋</div>
				<p class="gm-empty-state__text">
					<?php esc_html_e( "No quotes yet. Browse vendors and click 'Request a Quote' on any product.", 'gifting-marketplace' ); ?>
				</p>
			</div>
		<?php endif; ?>
	</div><!-- .gm-dash__card -->

</div><!-- .gm-dash.gm-quotes-page -->

<script>
window.gmQuotesPage = {
	ajaxUrl:     <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
	nonce:       <?php echo wp_json_encode( wp_create_nonce( 'gm_nonce' ) ); ?>,
	redirecting: <?php echo wp_json_encode( __( 'Redirecting to checkout…', 'gifting-marketplace' ) ); ?>,
};
</script>
