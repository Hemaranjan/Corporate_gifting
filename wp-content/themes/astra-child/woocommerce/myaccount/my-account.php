<?php
/**
 * Giftelier — Customer My Account wrapper.
 * Overrides woocommerce/myaccount/my-account.php.
 * Nav is rendered via astra_header_after hook (full-width, outside the WC container).
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="woocommerce-MyAccount-content">
    <?php do_action( 'woocommerce_account_content' ); ?>
</div>
