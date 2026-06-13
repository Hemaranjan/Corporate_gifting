<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

/* Summarise revenue by month for the last 6 months */
$months = [];
for ( $i = 5; $i >= 0; $i-- ) {
    $start = date( 'Y-m-01', strtotime( "-{$i} months" ) );
    $end   = date( 'Y-m-t',  strtotime( "-{$i} months" ) );
    $label = date( 'M Y',    strtotime( "-{$i} months" ) );

    $m_orders = wc_get_orders( [
        'limit'        => -1,
        'status'       => [ 'wc-completed', 'wc-processing' ],
        'date_created' => $start . '...' . $end,
    ] );
    $total = 0;
    foreach ( $m_orders as $o ) {
        $total += (float) $o->get_total();
    }
    $months[] = [ 'label' => $label, 'total' => $total, 'count' => count( $m_orders ) ];
}

/* Recent paid orders */
$paid_orders = wc_get_orders( [
    'limit'   => 15,
    'status'  => [ 'wc-completed', 'wc-processing' ],
    'orderby' => 'date',
    'order'   => 'DESC',
] );
?>

<h1 class="gm-section-title">Payments</h1>

<!-- Monthly revenue summary -->
<h2 class="gm-section-title" style="font-size:15px;">Revenue — Last 6 Months</h2>
<div class="gm-stat-grid" style="margin-bottom:28px;">
    <?php foreach ( $months as $m ) : ?>
    <div class="gm-stat-card">
        <div class="gm-stat-card__label"><?php echo esc_html( $m['label'] ); ?></div>
        <div class="gm-stat-card__value"><?php echo wp_kses_post( wc_price( $m['total'] ) ); ?></div>
        <div class="gm-stat-card__sub"><?php echo esc_html( $m['count'] ); ?> paid orders</div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Recent paid orders -->
<h2 class="gm-section-title" style="font-size:15px;">Recent Paid Orders</h2>
<div class="gm-table-wrap">
    <table>
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Method</th>
                <th>Status</th>
                <th>Total</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $paid_orders ) :
                foreach ( $paid_orders as $order ) :
                    $name   = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
                    $method = $order->get_payment_method_title() ?: '—';
                    $status = $order->get_status();
            ?>
            <tr>
                <td><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></td>
                <td><?php echo esc_html( $name ?: '—' ); ?></td>
                <td><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd M Y' ) : '—' ); ?></td>
                <td><?php echo esc_html( $method ); ?></td>
                <td><span class="gm-status-badge gm-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( wc_get_order_status_name( $status ) ); ?></span></td>
                <td><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></td>
                <td><a href="<?php echo esc_url( get_edit_post_link( $order->get_id() ) ); ?>">View</a></td>
            </tr>
            <?php endforeach;
            else : ?>
            <tr><td colspan="7" style="text-align:center;padding:24px;color:#a0a4b0;">No paid orders yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<style>
.gm-status-badge{display:inline-block;padding:3px 9px;border-radius:20px;font-size:12px;font-weight:600}
.gm-status-completed{background:#d1fae5;color:#065f46}
.gm-status-processing{background:#dbeafe;color:#1e40af}
</style>
