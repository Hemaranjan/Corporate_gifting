<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

/* Handle save */
$saved = false;
if (
    isset( $_POST['gm_settings_nonce'] ) &&
    wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gm_settings_nonce'] ) ), 'gm_save_settings' )
) {
    update_option( 'gm_brand_name',          sanitize_text_field( $_POST['gm_brand_name']          ?? '' ) );
    update_option( 'gm_support_email',       sanitize_email(      $_POST['gm_support_email']       ?? '' ) );
    update_option( 'gm_commission_rate',     absint(              $_POST['gm_commission_rate']     ?? 10  ) );
    update_option( 'gm_enable_event_banner', isset( $_POST['gm_enable_event_banner'] ) ? '1' : '0' );
    $saved = true;
}

$brand_name     = get_option( 'gm_brand_name',          'Giftelier' );
$support_email  = get_option( 'gm_support_email',       '' );
$commission     = (int) get_option( 'gm_commission_rate', 10 );
$event_banner   = get_option( 'gm_enable_event_banner', '1' ) === '1';
?>

<h1 class="gm-section-title">Settings</h1>

<?php if ( $saved ) : ?>
<div class="notice notice-success is-dismissible" style="margin-bottom:20px;">
    <p><?php esc_html_e( 'Settings saved.', 'gifting-marketplace' ); ?></p>
</div>
<?php endif; ?>

<div style="max-width:640px;background:#fff;border:1px solid #eaecf0;border-radius:10px;padding:28px;">
    <form method="post">
        <?php wp_nonce_field( 'gm_save_settings', 'gm_settings_nonce' ); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="gm_brand_name"><?php esc_html_e( 'Brand Name', 'gifting-marketplace' ); ?></label></th>
                <td>
                    <input type="text" id="gm_brand_name" name="gm_brand_name"
                           value="<?php echo esc_attr( $brand_name ); ?>"
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="gm_support_email"><?php esc_html_e( 'Support Email', 'gifting-marketplace' ); ?></label></th>
                <td>
                    <input type="email" id="gm_support_email" name="gm_support_email"
                           value="<?php echo esc_attr( $support_email ); ?>"
                           class="regular-text" />
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="gm_commission_rate"><?php esc_html_e( 'Vendor Commission (%)', 'gifting-marketplace' ); ?></label></th>
                <td>
                    <input type="number" id="gm_commission_rate" name="gm_commission_rate"
                           value="<?php echo esc_attr( $commission ); ?>"
                           min="0" max="100" step="1" class="small-text" /> %
                    <p class="description"><?php esc_html_e( 'Platform commission deducted from each vendor sale.', 'gifting-marketplace' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Cart Event Banner', 'gifting-marketplace' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="gm_enable_event_banner" value="1"
                               <?php checked( $event_banner ); ?> />
                        <?php esc_html_e( 'Show event booking reminder banner on cart page', 'gifting-marketplace' ); ?>
                    </label>
                </td>
            </tr>
        </table>

        <p style="margin-top:20px;">
            <?php submit_button( __( 'Save Settings', 'gifting-marketplace' ), 'primary', 'submit', false ); ?>
        </p>
    </form>
</div>
