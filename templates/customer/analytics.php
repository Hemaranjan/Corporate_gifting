<?php
/**
 * Customer Dashboard — Spend Analytics
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id = get_current_user_id();

// All completed orders this year
$orders_year = wc_get_orders( [
    'customer'   => $user_id,
    'limit'      => -1,
    'date_after' => date( 'Y-01-01' ),
    'status'     => [ 'wc-completed', 'wc-processing' ],
] );

$total_spend   = 0;
$order_count   = count( $orders_year );
$cat_spend     = [];
$vendor_spend  = [];
$month_spend   = array_fill( 1, 12, 0 );

foreach ( $orders_year as $order ) {
    $order_total  = (float) $order->get_total();
    $total_spend += $order_total;
    $m = (int) $order->get_date_created()->date( 'n' );
    $month_spend[ $m ] += $order_total;

    foreach ( $order->get_items() as $item ) {
        $product_id = $item->get_product_id();
        $item_total = (float) $item->get_total();

        // Category breakdown
        $terms = get_the_terms( $product_id, 'product_cat' );
        if ( $terms && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $cat_spend[ $term->name ] = ( $cat_spend[ $term->name ] ?? 0 ) + $item_total;
            }
        }

        // Vendor breakdown
        $vendor_id = (int) get_post_field( 'post_author', $product_id );
        if ( $vendor_id ) {
            $vendor_name = dokan_get_store_info( $vendor_id )['store_name'] ?? get_userdata( $vendor_id )->display_name;
            $vendor_spend[ $vendor_name ] = ( $vendor_spend[ $vendor_name ] ?? 0 ) + $item_total;
        }
    }
}

arsort( $cat_spend );
arsort( $vendor_spend );
$avg_per_order = $order_count > 0 ? ( $total_spend / $order_count ) : 0;

// Shipment stats from order meta
$shipped = $delivered = 0;
foreach ( $orders_year as $order ) {
    $status = $order->get_status();
    if ( $status === 'completed' ) $delivered++;
    if ( in_array( $status, [ 'processing', 'on-hold' ] ) ) $shipped++;
}

$month_labels = [ '', 'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec' ];
$max_month    = max( $month_spend ) ?: 1;
?>
<div class="gm-dash">
    <div class="gm-dash-page-header">
        <h2 class="gm-dash-page-title">Spend Analytics</h2>
        <p class="gm-dash-page-sub">Your gifting spend overview for <?php echo date( 'Y' ); ?>.</p>
    </div>

    <!-- KPI row -->
    <div class="gm-dash-stats">
        <div class="gm-dash-stat gm-dash-stat--rose">
            <span class="gm-dash-stat__icon">💰</span>
            <div class="gm-dash-stat__body">
                <span class="gm-dash-stat__value">₹<?php echo number_format( $total_spend, 0 ); ?></span>
                <span class="gm-dash-stat__label">Total spend <?php echo date( 'Y' ); ?></span>
            </div>
        </div>
        <div class="gm-dash-stat gm-dash-stat--amber">
            <span class="gm-dash-stat__icon">🛒</span>
            <div class="gm-dash-stat__body">
                <span class="gm-dash-stat__value"><?php echo $order_count; ?></span>
                <span class="gm-dash-stat__label">Orders placed</span>
            </div>
        </div>
        <div class="gm-dash-stat gm-dash-stat--purple">
            <span class="gm-dash-stat__icon">📊</span>
            <div class="gm-dash-stat__body">
                <span class="gm-dash-stat__value">₹<?php echo number_format( $avg_per_order, 0 ); ?></span>
                <span class="gm-dash-stat__label">Avg per order</span>
            </div>
        </div>
        <div class="gm-dash-stat gm-dash-stat--green">
            <span class="gm-dash-stat__icon">🚚</span>
            <div class="gm-dash-stat__body">
                <span class="gm-dash-stat__value"><?php echo $delivered; ?></span>
                <span class="gm-dash-stat__label">Delivered</span>
            </div>
        </div>
    </div>

    <!-- Monthly spend chart -->
    <div class="gm-dash-card">
        <div class="gm-dash-card__header">
            <h3 class="gm-dash-card__title">Monthly Spend</h3>
            <span class="gm-dash-card__badge"><?php echo date( 'Y' ); ?></span>
        </div>
        <div class="gm-bar-chart">
            <?php for ( $m = 1; $m <= 12; $m++ ) :
                $h   = $max_month > 0 ? round( ( $month_spend[ $m ] / $max_month ) * 100 ) : 0;
                $cur = ( $m == (int) date( 'n' ) );
            ?>
            <div class="gm-bar-chart__col <?php echo $cur ? 'gm-bar-chart__col--current' : ''; ?>">
                <span class="gm-bar-chart__val">
                    <?php echo $month_spend[ $m ] > 0 ? '₹' . number_format( $month_spend[ $m ], 0 ) : ''; ?>
                </span>
                <div class="gm-bar-chart__bar" style="height: <?php echo max( 4, $h ); ?>%"></div>
                <span class="gm-bar-chart__label"><?php echo $month_labels[ $m ]; ?></span>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <div class="gm-dash-two-col">
        <!-- Top categories -->
        <div class="gm-dash-card">
            <div class="gm-dash-card__header">
                <h3 class="gm-dash-card__title">Top Categories</h3>
            </div>
            <?php if ( $cat_spend ) :
                $cat_total = array_sum( $cat_spend );
                $colors = [ '#E8386D','#F59E0B','#7C3AED','#10B981','#3B82F6','#F97316' ];
                $i = 0;
                foreach ( array_slice( $cat_spend, 0, 6 ) as $cat => $amt ) :
                    $pct   = $cat_total > 0 ? round( ( $amt / $cat_total ) * 100 ) : 0;
                    $color = $colors[ $i++ % count( $colors ) ];
            ?>
            <div class="gm-analytics-row">
                <div class="gm-analytics-row__label">
                    <span class="gm-analytics-row__dot" style="background: <?php echo esc_attr( $color ); ?>"></span>
                    <?php echo esc_html( $cat ); ?>
                </div>
                <div class="gm-analytics-row__bar-wrap">
                    <div class="gm-analytics-row__bar" style="width: <?php echo $pct; ?>%; background: <?php echo esc_attr( $color ); ?>"></div>
                </div>
                <div class="gm-analytics-row__pct"><?php echo $pct; ?>%</div>
                <div class="gm-analytics-row__amount">₹<?php echo number_format( $amt, 0 ); ?></div>
            </div>
            <?php endforeach;
            else : ?>
            <p class="gm-dash-empty-text">No category data yet.</p>
            <?php endif; ?>
        </div>

        <!-- Top vendors -->
        <div class="gm-dash-card">
            <div class="gm-dash-card__header">
                <h3 class="gm-dash-card__title">Spend by Vendor</h3>
            </div>
            <?php if ( $vendor_spend ) :
                $vend_total = array_sum( $vendor_spend );
                $i = 0;
                foreach ( array_slice( $vendor_spend, 0, 6 ) as $vname => $vamt ) :
                    $pct   = $vend_total > 0 ? round( ( $vamt / $vend_total ) * 100 ) : 0;
                    $color = $colors[ $i++ % count( $colors ) ];
            ?>
            <div class="gm-analytics-row">
                <div class="gm-analytics-row__label">
                    <span class="gm-analytics-row__dot" style="background: <?php echo esc_attr( $color ); ?>"></span>
                    <?php echo esc_html( $vname ); ?>
                </div>
                <div class="gm-analytics-row__bar-wrap">
                    <div class="gm-analytics-row__bar" style="width: <?php echo $pct; ?>%; background: <?php echo esc_attr( $color ); ?>"></div>
                </div>
                <div class="gm-analytics-row__pct"><?php echo $pct; ?>%</div>
                <div class="gm-analytics-row__amount">₹<?php echo number_format( $vamt, 0 ); ?></div>
            </div>
            <?php endforeach;
            else : ?>
            <p class="gm-dash-empty-text">No vendor data yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Shipment stats -->
    <div class="gm-dash-card">
        <div class="gm-dash-card__header">
            <h3 class="gm-dash-card__title">Shipment & Delivery Stats</h3>
        </div>
        <div class="gm-shipment-stats">
            <div class="gm-shipment-stat">
                <span class="gm-shipment-stat__icon">📦</span>
                <span class="gm-shipment-stat__val"><?php echo $order_count; ?></span>
                <span class="gm-shipment-stat__lbl">Total orders</span>
            </div>
            <div class="gm-shipment-stat">
                <span class="gm-shipment-stat__icon">🚚</span>
                <span class="gm-shipment-stat__val"><?php echo $shipped; ?></span>
                <span class="gm-shipment-stat__lbl">In transit</span>
            </div>
            <div class="gm-shipment-stat">
                <span class="gm-shipment-stat__icon">✅</span>
                <span class="gm-shipment-stat__val"><?php echo $delivered; ?></span>
                <span class="gm-shipment-stat__lbl">Delivered</span>
            </div>
            <div class="gm-shipment-stat">
                <span class="gm-shipment-stat__icon">🎯</span>
                <span class="gm-shipment-stat__val">
                    <?php echo $order_count > 0 ? round( ( $delivered / $order_count ) * 100 ) . '%' : '—'; ?>
                </span>
                <span class="gm-shipment-stat__lbl">Delivery rate</span>
            </div>
        </div>
    </div>
</div>
