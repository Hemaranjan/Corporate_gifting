<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page = 20;

$query = new WP_User_Query( [
    'role__in'    => [ 'customer', 'subscriber' ],
    'number'      => $per_page,
    'offset'      => ( $paged - 1 ) * $per_page,
    'orderby'     => 'registered',
    'order'       => 'DESC',
    'count_total' => true,
] );

$users       = $query->get_results();
$total_users = (int) $query->get_total();
$total_pages = ceil( $total_users / $per_page );
?>

<h1 class="gm-section-title">Customers <span style="font-size:14px;font-weight:500;color:#8a8f9c;">(<?php echo esc_html( number_format( $total_users ) ); ?> total)</span></h1>

<div class="gm-table-wrap">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Registered</th>
                <th>Orders</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $users ) :
                foreach ( $users as $user ) :
                    $order_count = wc_get_customer_order_count( $user->ID );
            ?>
            <tr>
                <td><strong><?php echo esc_html( $user->display_name ); ?></strong></td>
                <td><?php echo esc_html( $user->user_email ); ?></td>
                <td><?php echo esc_html( date_i18n( 'd M Y', strtotime( $user->user_registered ) ) ); ?></td>
                <td><?php echo esc_html( $order_count ); ?></td>
                <td><a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>">Edit</a></td>
            </tr>
            <?php endforeach;
            else : ?>
            <tr><td colspan="5" style="text-align:center;padding:24px;color:#a0a4b0;">No customers found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ( $total_pages > 1 ) : ?>
<div style="margin-top:16px;display:flex;gap:8px;align-items:center;">
    <?php for ( $i = 1; $i <= $total_pages; $i++ ) :
        $url   = add_query_arg( [ 'page' => 'giftelier-admin', 'section' => 'customers', 'paged' => $i ], admin_url( 'admin.php' ) );
        $class = ( $i === $paged ) ? 'button button-primary' : 'button';
    ?>
        <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $i ); ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
