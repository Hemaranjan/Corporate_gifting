<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page = 20;

$orders = wc_get_orders( [
    'limit'   => $per_page,
    'paged'   => $paged,
    'orderby' => 'date',
    'order'   => 'DESC',
] );

$total_orders = (int) count( wc_get_orders( [ 'limit' => -1, 'return' => 'ids' ] ) );
$total_pages  = ceil( $total_orders / $per_page );
?>

<h1 class="gm-section-title">Orders <span style="font-size:14px;font-weight:500;color:#8a8f9c;">(<?php echo esc_html( number_format( $total_orders ) ); ?> total)</span></h1>

<div class="gm-table-wrap">
    <table>
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Status</th>
                <th>Items</th>
                <th>Total</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $orders ) :
                foreach ( $orders as $order ) :
                    $name   = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
                    $status = $order->get_status();
            ?>
            <tr>
                <td><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></td>
                <td><?php echo esc_html( $name ?: '—' ); ?></td>
                <td><?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd M Y' ) : '—' ); ?></td>
                <td>
                    <span class="gm-status-badge gm-status-<?php echo esc_attr( $status ); ?>">
                        <?php echo esc_html( wc_get_order_status_name( $status ) ); ?>
                    </span>
                </td>
                <td><?php echo esc_html( $order->get_item_count() ); ?></td>
                <td><?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></td>
                <td><a href="<?php echo esc_url( get_edit_post_link( $order->get_id() ) ); ?>">View</a></td>
            </tr>
            <?php endforeach;
            else : ?>
            <tr><td colspan="7" style="text-align:center;padding:24px;color:#a0a4b0;">No orders found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ( $total_pages > 1 ) : ?>
<div style="margin-top:16px;display:flex;gap:8px;align-items:center;">
    <?php for ( $i = 1; $i <= $total_pages; $i++ ) :
        $url   = add_query_arg( [ 'page' => 'giftelier-admin', 'section' => 'orders', 'paged' => $i ], admin_url( 'admin.php' ) );
        $class = ( $i === $paged ) ? 'button button-primary' : 'button';
    ?>
        <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $i ); ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<style>
.gm-status-badge{display:inline-block;padding:3px 9px;border-radius:20px;font-size:12px;font-weight:600}
.gm-status-completed{background:#d1fae5;color:#065f46}
.gm-status-processing{background:#dbeafe;color:#1e40af}
.gm-status-pending{background:#fef3c7;color:#92400e}
.gm-status-on-hold{background:#ede9fe;color:#4c1d95}
.gm-status-cancelled{background:#fee2e2;color:#991b1b}
.gm-status-refunded{background:#f3f4f6;color:#4b5563}
</style>
