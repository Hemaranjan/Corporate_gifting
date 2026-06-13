<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page = 20;

$query = new WP_User_Query( [
    'role__in'    => [ 'seller', 'vendor' ],
    'number'      => $per_page,
    'offset'      => ( $paged - 1 ) * $per_page,
    'orderby'     => 'registered',
    'order'       => 'DESC',
    'count_total' => true,
] );

$vendors     = $query->get_results();
$total       = (int) $query->get_total();
$total_pages = ceil( $total / $per_page );
?>

<h1 class="gm-section-title">Vendors <span style="font-size:14px;font-weight:500;color:#8a8f9c;">(<?php echo esc_html( number_format( $total ) ); ?> total)</span></h1>

<div class="gm-table-wrap">
    <table>
        <thead>
            <tr>
                <th>Store Name</th>
                <th>Owner</th>
                <th>Email</th>
                <th>Registered</th>
                <th>Products</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $vendors ) :
                foreach ( $vendors as $vendor ) :
                    $store_info = function_exists( 'dokan_get_store_info' ) ? dokan_get_store_info( $vendor->ID ) : [];
                    $store_name = $store_info['store_name'] ?? $vendor->display_name;
                    $product_count = count( wc_get_products( [ 'author' => $vendor->ID, 'limit' => -1, 'return' => 'ids' ] ) );
            ?>
            <tr>
                <td><strong><?php echo esc_html( $store_name ); ?></strong></td>
                <td><?php echo esc_html( $vendor->display_name ); ?></td>
                <td><?php echo esc_html( $vendor->user_email ); ?></td>
                <td><?php echo esc_html( date_i18n( 'd M Y', strtotime( $vendor->user_registered ) ) ); ?></td>
                <td><?php echo esc_html( $product_count ); ?></td>
                <td><a href="<?php echo esc_url( get_edit_user_link( $vendor->ID ) ); ?>">Edit</a></td>
            </tr>
            <?php endforeach;
            else : ?>
            <tr><td colspan="6" style="text-align:center;padding:24px;color:#a0a4b0;">No vendors found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ( $total_pages > 1 ) : ?>
<div style="margin-top:16px;display:flex;gap:8px;align-items:center;">
    <?php for ( $i = 1; $i <= $total_pages; $i++ ) :
        $url   = add_query_arg( [ 'page' => 'giftelier-admin', 'section' => 'vendors', 'paged' => $i ], admin_url( 'admin.php' ) );
        $class = ( $i === $paged ) ? 'button button-primary' : 'button';
    ?>
        <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $i ); ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
