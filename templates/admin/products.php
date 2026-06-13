<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

$paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page = 20;

$products = wc_get_products( [
    'status'  => 'publish',
    'limit'   => $per_page,
    'page'    => $paged,
    'orderby' => 'date',
    'order'   => 'DESC',
] );

$total_products = (int) wp_count_posts( 'product' )->publish;
$total_pages    = ceil( $total_products / $per_page );
?>

<h1 class="gm-section-title">Products <span style="font-size:14px;font-weight:500;color:#8a8f9c;">(<?php echo esc_html( number_format( $total_products ) ); ?> published)</span></h1>

<div class="gm-table-wrap">
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th>Vendor</th>
                <th>Price</th>
                <th>Stock</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $products ) :
                foreach ( $products as $product ) :
                    $author     = get_post_field( 'post_author', $product->get_id() );
                    $store_info = function_exists( 'dokan_get_store_info' ) ? dokan_get_store_info( $author ) : [];
                    $store_name = $store_info['store_name'] ?? get_the_author_meta( 'display_name', $author );
            ?>
            <tr>
                <td>
                    <strong><?php echo esc_html( $product->get_name() ); ?></strong>
                </td>
                <td><?php echo esc_html( $product->get_sku() ?: '—' ); ?></td>
                <td><?php echo esc_html( $store_name ); ?></td>
                <td><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></td>
                <td>
                    <?php if ( $product->managing_stock() ) :
                        echo esc_html( $product->get_stock_quantity() . ' in stock' );
                    elseif ( $product->is_in_stock() ) :
                        echo 'In stock';
                    else :
                        echo '<span style="color:#dc2626;">Out of stock</span>';
                    endif; ?>
                </td>
                <td><a href="<?php echo esc_url( get_edit_post_link( $product->get_id() ) ); ?>">Edit</a></td>
            </tr>
            <?php endforeach;
            else : ?>
            <tr><td colspan="6" style="text-align:center;padding:24px;color:#a0a4b0;">No products found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ( $total_pages > 1 ) : ?>
<div style="margin-top:16px;display:flex;gap:8px;align-items:center;">
    <?php for ( $i = 1; $i <= $total_pages; $i++ ) :
        $url   = add_query_arg( [ 'page' => 'giftelier-admin', 'section' => 'products', 'paged' => $i ], admin_url( 'admin.php' ) );
        $class = ( $i === $paged ) ? 'button button-primary' : 'button';
    ?>
        <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $i ); ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
