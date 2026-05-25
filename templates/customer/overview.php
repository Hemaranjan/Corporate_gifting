<?php
/**
 * Customer Dashboard — Overview (includes analytics)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id    = get_current_user_id();
$user       = wp_get_current_user();
$first_name = $user->first_name ?: $user->display_name;

/* ── Orders & spend data ───────────────────────────────────────── */
$orders_year = wc_get_orders( [
    'customer'   => $user_id,
    'limit'      => -1,
    'date_after' => date( 'Y-01-01' ),
    'status'     => [ 'wc-completed', 'wc-processing' ],
] );

$total_spend  = 0;
$order_count  = count( $orders_year );
$cat_spend    = [];
$vendor_spend = [];
$month_spend  = array_fill( 1, 12, 0 );
$delivered = $shipped = 0;

foreach ( $orders_year as $order ) {
    $order_total  = (float) $order->get_total();
    $total_spend += $order_total;
    $m = (int) $order->get_date_created()->date( 'n' );
    $month_spend[ $m ] += $order_total;

    $status = $order->get_status();
    if ( $status === 'completed' ) $delivered++;
    if ( in_array( $status, [ 'processing', 'on-hold' ] ) ) $shipped++;

    foreach ( $order->get_items() as $item ) {
        $product_id = $item->get_product_id();
        $item_total = (float) $item->get_total();

        $terms = get_the_terms( $product_id, 'product_cat' );
        if ( $terms && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
                $cat_spend[ $term->name ] = ( $cat_spend[ $term->name ] ?? 0 ) + $item_total;
            }
        }

        $vendor_id = (int) get_post_field( 'post_author', $product_id );
        if ( $vendor_id ) {
            $vname = dokan_get_store_info( $vendor_id )['store_name'] ?? get_userdata( $vendor_id )->display_name ?? '';
            if ( $vname ) $vendor_spend[ $vname ] = ( $vendor_spend[ $vname ] ?? 0 ) + $item_total;
        }
    }
}

arsort( $cat_spend );
arsort( $vendor_spend );

$avg_per_order  = $order_count > 0 ? ( $total_spend / $order_count ) : 0;
$budget_yearly  = (int) get_user_meta( $user_id, 'gm_budget_yearly', true );
$budget_used    = $total_spend;
$budget_pct     = $budget_yearly > 0 ? min( 100, round( ( $budget_used / $budget_yearly ) * 100 ) ) : 0;
$recent_orders  = array_slice( $orders_year, 0, 5 );

// Upcoming Amelia bookings
$upcoming = 0;
foreach ( $orders_year as $o ) {
    foreach ( $o->get_items() as $item ) {
        if ( $item->get_meta( 'ameliaBookingId' ) || $item->get_meta( 'amelia' ) ) {
            $upcoming++;
            break;
        }
    }
}

$month_labels      = [ '', 'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec' ];
$max_month         = max( $month_spend ) ?: 1;
$bar_colors        = [ '#E8386D','#F59E0B','#7C3AED','#10B981','#3B82F6','#F97316' ];
$store_listing_id  = function_exists( 'dokan_get_option' ) ? dokan_get_option( 'store_listing', 'dokan_pages' ) : 0;
$store_listing_url = $store_listing_id ? get_permalink( (int) $store_listing_id ) : home_url( '/store-listing/' );
?>

<div class="gm-dash">

    <!-- Welcome banner -->
    <div class="gm-dash-welcome">
        <div class="gm-dash-welcome__text">
            <h2 class="gm-dash-welcome__name">Welcome back, <?php echo esc_html( $first_name ); ?> 👋</h2>
            <p class="gm-dash-welcome__sub">Your gifting overview for <?php echo date( 'Y' ); ?>.</p>
        </div>
        <a href="<?php echo esc_url( $store_listing_url ); ?>" class="gm-btn gm-btn--ghost">
            Browse Vendors
        </a>
    </div>

    <!-- KPI stat cards -->
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
            <span class="gm-dash-stat__icon">📅</span>
            <div class="gm-dash-stat__body">
                <span class="gm-dash-stat__value"><?php echo $upcoming ?: '—'; ?></span>
                <span class="gm-dash-stat__label">Upcoming bookings</span>
            </div>
        </div>
        <div class="gm-dash-stat gm-dash-stat--green">
            <span class="gm-dash-stat__icon">🎯</span>
            <div class="gm-dash-stat__body">
                <span class="gm-dash-stat__value">
                    <?php echo $budget_yearly > 0 ? '₹' . number_format( max( 0, $budget_yearly - $budget_used ), 0 ) : '—'; ?>
                </span>
                <span class="gm-dash-stat__label">Budget remaining</span>
            </div>
        </div>
    </div>

    <?php if ( $budget_yearly > 0 ) : ?>
    <!-- Budget progress -->
    <div class="gm-dash-card">
        <div class="gm-dash-card__header">
            <h3 class="gm-dash-card__title">Annual Budget</h3>
            <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'giftelier-budget' ) ); ?>" class="gm-dash-card__action">Manage →</a>
        </div>
        <div class="gm-budget-bar">
            <div class="gm-budget-bar__fill" style="width: <?php echo $budget_pct; ?>%"></div>
        </div>
        <div class="gm-budget-bar__meta">
            <span>₹<?php echo number_format( $budget_used, 0 ); ?> used</span>
            <span><?php echo $budget_pct; ?>% of ₹<?php echo number_format( $budget_yearly, 0 ); ?></span>
            <span>₹<?php echo number_format( max( 0, $budget_yearly - $budget_used ), 0 ); ?> left</span>
        </div>
        <?php if ( $budget_pct >= 80 ) : ?>
        <div class="gm-alert gm-alert--warning">⚠️ You've used <?php echo $budget_pct; ?>% of your annual gifting budget.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

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

    <!-- Recent orders + Top categories -->
    <div class="gm-dash-two-col">

        <!-- Recent orders -->
        <div class="gm-dash-card">
            <div class="gm-dash-card__header">
                <h3 class="gm-dash-card__title">Recent Orders</h3>
                <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'giftelier-orders' ) ); ?>" class="gm-dash-card__action">View all →</a>
            </div>
            <?php if ( $recent_orders ) : ?>
            <table class="gm-dash-table">
                <thead>
                    <tr><th>Order</th><th>Date</th><th>Total</th><th>Status</th></tr>
                </thead>
                <tbody>
                <?php foreach ( $recent_orders as $order ) : ?>
                    <tr>
                        <td><a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="gm-link">#<?php echo esc_html( $order->get_order_number() ); ?></a></td>
                        <td><?php echo esc_html( $order->get_date_created()->date_i18n( 'd M Y' ) ); ?></td>
                        <td>₹<?php echo number_format( (float) $order->get_total(), 0 ); ?></td>
                        <td>
                            <span class="gm-badge gm-badge--<?php echo esc_attr( $order->get_status() ); ?>">
                                <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <p class="gm-dash-empty-text">No orders yet. <a href="<?php echo esc_url( $store_listing_url ); ?>">Browse vendors →</a></p>
            <?php endif; ?>
        </div>

        <!-- Top categories -->
        <div class="gm-dash-card">
            <div class="gm-dash-card__header">
                <h3 class="gm-dash-card__title">Spend by Category</h3>
            </div>
            <?php if ( $cat_spend ) :
                $cat_total = array_sum( $cat_spend );
                $i = 0;
                foreach ( array_slice( $cat_spend, 0, 6 ) as $cat => $amt ) :
                    $pct   = $cat_total > 0 ? round( ( $amt / $cat_total ) * 100 ) : 0;
                    $color = $bar_colors[ $i++ % count( $bar_colors ) ];
            ?>
            <div class="gm-analytics-row">
                <div class="gm-analytics-row__label">
                    <span class="gm-analytics-row__dot" style="background:<?php echo esc_attr( $color ); ?>"></span>
                    <?php echo esc_html( $cat ); ?>
                </div>
                <div class="gm-analytics-row__bar-wrap">
                    <div class="gm-analytics-row__bar" style="width:<?php echo $pct; ?>%;background:<?php echo esc_attr( $color ); ?>"></div>
                </div>
                <div class="gm-analytics-row__pct"><?php echo $pct; ?>%</div>
                <div class="gm-analytics-row__amount">₹<?php echo number_format( $amt, 0 ); ?></div>
            </div>
            <?php endforeach;
            else : ?>
            <p class="gm-dash-empty-text">Order some gifts to see category data.</p>
            <?php endif; ?>
        </div>

    </div>

    <!-- Spend by vendor + Shipment stats -->
    <div class="gm-dash-two-col">

        <!-- Spend by vendor -->
        <div class="gm-dash-card">
            <div class="gm-dash-card__header">
                <h3 class="gm-dash-card__title">Spend by Vendor</h3>
            </div>
            <?php if ( $vendor_spend ) :
                $vend_total = array_sum( $vendor_spend );
                $i = 0;
                foreach ( array_slice( $vendor_spend, 0, 6 ) as $vname => $vamt ) :
                    $pct   = $vend_total > 0 ? round( ( $vamt / $vend_total ) * 100 ) : 0;
                    $color = $bar_colors[ $i++ % count( $bar_colors ) ];
            ?>
            <div class="gm-analytics-row">
                <div class="gm-analytics-row__label">
                    <span class="gm-analytics-row__dot" style="background:<?php echo esc_attr( $color ); ?>"></span>
                    <?php echo esc_html( $vname ); ?>
                </div>
                <div class="gm-analytics-row__bar-wrap">
                    <div class="gm-analytics-row__bar" style="width:<?php echo $pct; ?>%;background:<?php echo esc_attr( $color ); ?>"></div>
                </div>
                <div class="gm-analytics-row__pct"><?php echo $pct; ?>%</div>
                <div class="gm-analytics-row__amount">₹<?php echo number_format( $vamt, 0 ); ?></div>
            </div>
            <?php endforeach;
            else : ?>
            <p class="gm-dash-empty-text">No vendor data yet.</p>
            <?php endif; ?>
        </div>

        <!-- Delivery stats -->
        <div class="gm-dash-card">
            <div class="gm-dash-card__header">
                <h3 class="gm-dash-card__title">Delivery Stats</h3>
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
                    <span class="gm-shipment-stat__icon">📊</span>
                    <span class="gm-shipment-stat__val">₹<?php echo number_format( $avg_per_order, 0 ); ?></span>
                    <span class="gm-shipment-stat__lbl">Avg per order</span>
                </div>
            </div>
        </div>

    </div>

    <!-- Quick links -->
    <div class="gm-dash-quick-links">
        <?php
        $links = [
            [ 'giftelier-calendar', '📅', 'Book an Event',  'Browse upcoming events and make a booking.' ],
            [ 'giftelier-budget',   '💰', 'Manage Budget',  'Define your yearly gifting budget and track spend.' ],
            [ 'giftelier-browse',   '🏪', 'Browse Vendors',  'Discover curated gifts from verified vendors.' ],
        ];
        foreach ( $links as [ $ep, $icon, $title, $desc ] ) :
            $quick_url = ( $ep === 'giftelier-browse' )
                ? $store_listing_url
                : wc_get_account_endpoint_url( $ep );
        ?>
        <a href="<?php echo esc_url( $quick_url ); ?>" class="gm-dash-quick-link">
            <span class="gm-dash-quick-link__icon"><?php echo $icon; ?></span>
            <strong class="gm-dash-quick-link__title"><?php echo esc_html( $title ); ?></strong>
            <span class="gm-dash-quick-link__desc"><?php echo esc_html( $desc ); ?></span>
        </a>
        <?php endforeach; ?>
    </div>

</div>
