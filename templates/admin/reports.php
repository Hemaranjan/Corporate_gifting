<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

/* Top-selling products (last 30 days) */
$top_products = wc_get_orders( [
    'limit'        => -1,
    'status'       => [ 'wc-completed', 'wc-processing' ],
    'date_created' => date( 'Y-m-d', strtotime( '-30 days' ) ) . '...' . date( 'Y-m-d' ),
] );

$product_sales = [];
foreach ( $top_products as $order ) {
    foreach ( $order->get_items() as $item ) {
        $pid = $item->get_product_id();
        if ( ! isset( $product_sales[ $pid ] ) ) {
            $product_sales[ $pid ] = [ 'name' => $item->get_name(), 'qty' => 0, 'revenue' => 0 ];
        }
        $product_sales[ $pid ]['qty']     += (int) $item->get_quantity();
        $product_sales[ $pid ]['revenue'] += (float) $item->get_total();
    }
}
usort( $product_sales, fn( $a, $b ) => $b['qty'] - $a['qty'] );
$top_products_data = array_slice( $product_sales, 0, 10 );

/* Top vendors by revenue */
$vendor_revenue = [];
$all_orders = wc_get_orders( [
    'limit'  => -1,
    'status' => [ 'wc-completed', 'wc-processing' ],
] );
foreach ( $all_orders as $order ) {
    foreach ( $order->get_items() as $item ) {
        $product = $item->get_product();
        if ( ! $product ) continue;
        $author = get_post_field( 'post_author', $product->get_id() );
        if ( ! $author ) continue;
        if ( ! isset( $vendor_revenue[ $author ] ) ) {
            $store_info = function_exists( 'dokan_get_store_info' ) ? dokan_get_store_info( $author ) : [];
            $vendor_revenue[ $author ] = [
                'name'    => $store_info['store_name'] ?? get_the_author_meta( 'display_name', $author ),
                'revenue' => 0,
                'orders'  => 0,
            ];
        }
        $vendor_revenue[ $author ]['revenue'] += (float) $item->get_total();
        $vendor_revenue[ $author ]['orders']++;
    }
}
usort( $vendor_revenue, fn( $a, $b ) => $b['revenue'] <=> $a['revenue'] );
$top_vendors = array_slice( $vendor_revenue, 0, 10 );
?>

<h1 class="gm-section-title">Reports</h1>

<!-- Top Products (30 days) -->
<h2 class="gm-section-title" style="font-size:15px;">Top Products — Last 30 Days</h2>
<div class="gm-table-wrap" style="margin-bottom:28px;">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>Units Sold</th>
                <th>Revenue</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $top_products_data ) :
                foreach ( $top_products_data as $i => $p ) :
            ?>
            <tr>
                <td><?php echo esc_html( $i + 1 ); ?></td>
                <td><?php echo esc_html( $p['name'] ); ?></td>
                <td><?php echo esc_html( number_format( $p['qty'] ) ); ?></td>
                <td><?php echo wp_kses_post( wc_price( $p['revenue'] ) ); ?></td>
            </tr>
            <?php endforeach;
            else : ?>
            <tr><td colspan="4" style="text-align:center;padding:24px;color:#a0a4b0;">No sales data yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Top Vendors by Revenue -->
<h2 class="gm-section-title" style="font-size:15px;">Top Vendors by Revenue</h2>
<div class="gm-table-wrap">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Vendor / Store</th>
                <th>Items Sold</th>
                <th>Revenue</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $top_vendors ) :
                foreach ( $top_vendors as $i => $v ) :
            ?>
            <tr>
                <td><?php echo esc_html( $i + 1 ); ?></td>
                <td><?php echo esc_html( $v['name'] ); ?></td>
                <td><?php echo esc_html( number_format( $v['orders'] ) ); ?></td>
                <td><?php echo wp_kses_post( wc_price( $v['revenue'] ) ); ?></td>
            </tr>
            <?php endforeach;
            else : ?>
            <tr><td colspan="4" style="text-align:center;padding:24px;color:#a0a4b0;">No vendor data yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
