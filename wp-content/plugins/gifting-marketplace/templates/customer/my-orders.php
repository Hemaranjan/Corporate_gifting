<?php
/**
 * Customer Dashboard — Order & Booking Management
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$user_id = get_current_user_id();

// Tab: active filter
$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'all';

$status_map = [
    'all'       => [ 'wc-pending','wc-on-hold','wc-processing','wc-completed','wc-cancelled','wc-refunded' ],
    'active'    => [ 'wc-pending','wc-on-hold','wc-processing' ],
    'completed' => [ 'wc-completed' ],
    'cancelled' => [ 'wc-cancelled','wc-refunded' ],
];

$statuses = $status_map[ $tab ] ?? $status_map['all'];

$orders = wc_get_orders( [
    'customer' => $user_id,
    'limit'    => 20,
    'status'   => $statuses,
    'orderby'  => 'date',
    'order'    => 'DESC',
] );

$orders_url = wc_get_account_endpoint_url( 'giftelier-orders' );

// Badge class map
$badge_class = [
    'pending'    => 'warning',
    'on-hold'    => 'warning',
    'processing' => 'info',
    'completed'  => 'success',
    'cancelled'  => 'danger',
    'refunded'   => 'danger',
];
?>
<div class="gm-dash">
    <div class="gm-dash-page-header">
        <h2 class="gm-dash-page-title">My Orders & Bookings</h2>
        <p class="gm-dash-page-sub">Track your gifts from order to delivery.</p>
    </div>

    <!-- Tabs -->
    <div class="gm-tabs">
        <?php
        $tabs = [ 'all' => 'All Orders', 'active' => 'Active', 'completed' => 'Delivered', 'cancelled' => 'Cancelled' ];
        foreach ( $tabs as $key => $label ) :
        ?>
        <a href="<?php echo esc_url( add_query_arg( 'tab', $key, $orders_url ) ); ?>"
           class="gm-tab <?php echo $tab === $key ? 'gm-tab--active' : ''; ?>">
            <?php echo esc_html( $label ); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ( $orders ) : ?>
    <div class="gm-orders-list">
    <?php foreach ( $orders as $order ) :
        $status      = $order->get_status();
        $badge_key   = $badge_class[ $status ] ?? 'info';
        $date        = $order->get_date_created()->date_i18n( 'd M Y' );
        $items       = $order->get_items();
        $item_count  = count( $items );
        $has_amelia  = false;
        $event_date  = '';

        // Check for Amelia booking
        foreach ( $items as $item ) {
            if ( $item->get_meta( 'ameliaBookingId' ) || $item->get_meta( 'amelia' ) ) {
                $has_amelia = true;
                $event_date = $item->get_meta( 'amelia_event_date' );
                break;
            }
        }

        // Shiprocket tracking
        $tracking_id  = get_post_meta( $order->get_id(), '_shiprocket_tracking_id', true );
        $tracking_url = get_post_meta( $order->get_id(), '_shiprocket_tracking_url', true );

        // Delivery-before-event indicator
        $event_ts        = $event_date ? strtotime( $event_date ) : null;
        $today_ts        = current_time( 'timestamp' );
        $event_diff      = $event_ts ? ( $event_ts - $today_ts ) : null;
        $delivery_ok     = $status === 'completed' || ( $event_ts && $event_diff > 0 );
        $delivery_urgent = $event_ts && $event_diff !== null && $event_diff > 0 && $event_diff < 3 * DAY_IN_SECONDS && $status !== 'completed';
    ?>
    <div class="gm-order-card <?php echo $has_amelia ? 'gm-order-card--has-event' : ''; ?>">
        <div class="gm-order-card__header">
            <div class="gm-order-card__meta">
                <strong class="gm-order-card__number">#<?php echo esc_html( $order->get_order_number() ); ?></strong>
                <span class="gm-order-card__date"><?php echo esc_html( $date ); ?></span>
                <span class="gm-badge gm-badge--<?php echo esc_attr( $badge_key ); ?>">
                    <?php echo esc_html( wc_get_order_status_name( $status ) ); ?>
                </span>
                <?php if ( $has_amelia ) : ?>
                <span class="gm-badge gm-badge--event">📅 Event Booking</span>
                <?php endif; ?>
            </div>
            <div class="gm-order-card__total">
                ₹<?php echo number_format( (float) $order->get_total(), 0 ); ?>
            </div>
        </div>

        <!-- Items summary -->
        <div class="gm-order-card__items">
            <?php foreach ( array_slice( $items, 0, 3 ) as $item ) :
                $product = $item->get_product();
                $thumb   = $product ? get_the_post_thumbnail_url( $item->get_product_id(), 'thumbnail' ) : '';
            ?>
            <div class="gm-order-item-thumb">
                <?php if ( $thumb ) : ?>
                <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $item->get_name() ); ?>" />
                <?php else : ?>
                <div class="gm-order-item-thumb__placeholder">🎁</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if ( $item_count > 3 ) : ?>
            <div class="gm-order-item-thumb gm-order-item-thumb--more">+<?php echo $item_count - 3; ?></div>
            <?php endif; ?>
            <span class="gm-order-card__item-names">
                <?php
                $names = array_map( fn( $i ) => $i->get_name(), array_slice( $items, 0, 2 ) );
                echo esc_html( implode( ', ', $names ) . ( $item_count > 2 ? ' +' . ( $item_count - 2 ) . ' more' : '' ) );
                ?>
            </span>
        </div>

        <!-- Event date + delivery indicator -->
        <?php if ( $has_amelia && $event_date ) : ?>
        <div class="gm-order-card__event-row">
            <span class="gm-order-card__event-label">📅 Event:</span>
            <strong><?php echo esc_html( date( 'd M Y', strtotime( $event_date ) ) ); ?></strong>
            <?php if ( $delivery_urgent ) : ?>
            <span class="gm-delivery-indicator gm-delivery-indicator--urgent">⚠️ Delivery urgent</span>
            <?php elseif ( $delivery_ok && $status !== 'completed' ) : ?>
            <span class="gm-delivery-indicator gm-delivery-indicator--ok">✅ On track</span>
            <?php elseif ( $status === 'completed' ) : ?>
            <span class="gm-delivery-indicator gm-delivery-indicator--delivered">✅ Delivered</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Tracking -->
        <?php if ( $tracking_id || $tracking_url ) : ?>
        <div class="gm-order-card__tracking">
            <span>🚚 Tracking:</span>
            <?php if ( $tracking_url ) : ?>
            <a href="<?php echo esc_url( $tracking_url ); ?>" target="_blank" class="gm-link">
                <?php echo esc_html( $tracking_id ?: 'Track Shipment' ); ?>
            </a>
            <?php else : ?>
            <span><?php echo esc_html( $tracking_id ); ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="gm-order-card__footer">
            <a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="gm-btn gm-btn--outline gm-btn--sm">
                View Order
            </a>
            <?php if ( $status === 'completed' ) : ?>
            <form method="POST" action="<?php echo esc_url( wc_get_cart_url() ); ?>" class="gm-reorder-form">
                <?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
                <?php foreach ( $items as $item ) : ?>
                <input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $item->get_product_id() ); ?>" />
                <?php endforeach; ?>
                <button type="submit" class="gm-btn gm-btn--primary gm-btn--sm">🔁 Reorder</button>
            </form>
            <?php endif; ?>
            <?php if ( in_array( $status, [ 'pending', 'on-hold' ] ) ) : ?>
            <a href="<?php echo esc_url( $order->get_cancel_order_url() ); ?>"
               class="gm-btn gm-btn--danger gm-btn--sm"
               onclick="return confirm('Cancel this order?')">Cancel</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>

    <?php else : ?>
    <div class="gm-dash-card gm-dash-empty">
        <span class="gm-dash-empty__icon">📭</span>
        <p>No orders found.
            <?php if ( $tab !== 'all' ) : ?>
            <a href="<?php echo esc_url( $orders_url ); ?>">View all orders</a> or
            <?php endif; ?>
            <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'giftelier-browse' ) ); ?>">browse gifts</a>.
        </p>
    </div>
    <?php endif; ?>
</div>
