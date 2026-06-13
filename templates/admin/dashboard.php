<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

/* ── Pull live counts ─────────────────────────────────────────────── */

$total_customers = (int) ( new WP_User_Query( [
    'role'        => 'customer',
    'count_total' => true,
    'number'      => 0,
] ) )->get_total();

$total_vendors = function_exists( 'dokan_get_sellers' )
    ? (int) dokan_get_sellers( [ 'number' => 0, 'status' => 'approved' ] )['count']
    : (int) ( new WP_User_Query( [ 'role' => 'seller', 'count_total' => true, 'number' => 0 ] ) )->get_total();

$order_counts = wc_get_order_statuses();
$total_orders = (int) ( function_exists( 'wc_get_orders' )
    ? count( wc_get_orders( [ 'limit' => -1, 'return' => 'ids' ] ) )
    : 0 );

$total_products = (int) wp_count_posts( 'product' )->publish;

/* Revenue this month */
$month_start = date( 'Y-m-01' );
$month_end   = date( 'Y-m-t' );
$revenue_orders = wc_get_orders( [
    'limit'        => -1,
    'status'       => [ 'wc-completed', 'wc-processing' ],
    'date_created' => $month_start . '...' . $month_end,
] );
$revenue_month = 0;
foreach ( $revenue_orders as $o ) {
    $revenue_month += (float) $o->get_total();
}

/* Recent orders (last 5) */
$recent_orders = wc_get_orders( [
    'limit'   => 5,
    'orderby' => 'date',
    'order'   => 'DESC',
] );
?>

<h1 class="gm-section-title">Dashboard</h1>

<!-- Stat cards -->
<div class="gm-stat-grid">
    <div class="gm-stat-card">
        <div class="gm-stat-card__label">Customers</div>
        <div class="gm-stat-card__value"><?php echo esc_html( number_format( $total_customers ) ); ?></div>
        <div class="gm-stat-card__sub">Registered accounts</div>
    </div>
    <div class="gm-stat-card">
        <div class="gm-stat-card__label">Vendors</div>
        <div class="gm-stat-card__value"><?php echo esc_html( number_format( $total_vendors ) ); ?></div>
        <div class="gm-stat-card__sub">Approved vendors</div>
    </div>
    <div class="gm-stat-card">
        <div class="gm-stat-card__label">Total Orders</div>
        <div class="gm-stat-card__value"><?php echo esc_html( number_format( $total_orders ) ); ?></div>
        <div class="gm-stat-card__sub">All time</div>
    </div>
    <div class="gm-stat-card">
        <div class="gm-stat-card__label">Products</div>
        <div class="gm-stat-card__value"><?php echo esc_html( number_format( $total_products ) ); ?></div>
        <div class="gm-stat-card__sub">Published</div>
    </div>
    <div class="gm-stat-card">
        <div class="gm-stat-card__label">Revenue (this month)</div>
        <div class="gm-stat-card__value"><?php echo wp_kses_post( wc_price( $revenue_month ) ); ?></div>
        <div class="gm-stat-card__sub"><?php echo esc_html( date( 'F Y' ) ); ?></div>
    </div>
</div>

<!-- Recent orders -->
<h2 class="gm-section-title" style="font-size:16px;">Recent Orders</h2>
<div class="gm-table-wrap">
    <table>
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Status</th>
                <th>Total</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $recent_orders ) :
                foreach ( $recent_orders as $order ) :
                    $billing_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
                    $status_label = wc_get_order_status_name( $order->get_status() );
            ?>
            <tr>
                <td><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></td>
                <td><?php echo esc_html( $billing_name ?: '—' ); ?></td>
                <td><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd M Y' ) : '—' ); ?></td>
                <td><span class="gm-status-badge gm-status-<?php echo esc_attr( $order->get_status() ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
                <td><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></td>
                <td><a href="<?php echo esc_url( get_edit_post_link( $order->get_id() ) ); ?>">View</a></td>
            </tr>
            <?php endforeach;
            else : ?>
            <tr><td colspan="6" style="text-align:center;padding:24px;color:#a0a4b0;">No orders yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.gm-status-badge {
    display: inline-block;
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
.gm-status-completed   { background: #d1fae5; color: #065f46; }
.gm-status-processing  { background: #dbeafe; color: #1e40af; }
.gm-status-pending     { background: #fef3c7; color: #92400e; }
.gm-status-on-hold     { background: #ede9fe; color: #4c1d95; }
.gm-status-cancelled   { background: #fee2e2; color: #991b1b; }
.gm-status-refunded    { background: #f3f4f6; color: #4b5563; }
</style>
