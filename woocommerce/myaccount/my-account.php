<?php
/**
 * Giftelier — Customer My Account wrapper.
 * Overrides woocommerce/myaccount/my-account.php.
 * Nav is now a full-width top bar; no sidebar column needed.
 */
defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_account_navigation' );
?>
<div class="woocommerce-MyAccount-content">
    <?php do_action( 'woocommerce_account_content' ); ?>
</div>
