<?php
/**
 * Giftelier — Custom Login / Sign Up page.
 * Overrides woocommerce/myaccount/form-login.php.
 *
 * After login the woocommerce_login_redirect filter (functions.php) routes:
 *   Vendors   → Dokan vendor dashboard
 *   Customers → /my-account/giftelier-calendar/
 */
if ( ! defined( 'ABSPATH' ) ) exit;

do_action( 'woocommerce_before_customer_login_form' );

$show_registration = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );
$active_tab        = isset( $_GET['tab'] ) && $_GET['tab'] === 'register' ? 'register' : 'login';
?>

<div class="gm-login-wrap">

    <!-- Role chooser cards -->
    <div class="gm-login-roles">
        <div class="gm-login-role gm-login-role--customer">
            <div class="gm-login-role__icon">🎁</div>
            <h3>I'm a Customer</h3>
            <ul>
                <li>Gifting dashboard</li>
                <li>Budget &amp; calendar</li>
                <li>Browse gift stores</li>
                <li>Track orders</li>
            </ul>
        </div>
        <div class="gm-login-role__sep">
            <span>or</span>
        </div>
        <div class="gm-login-role gm-login-role--vendor">
            <div class="gm-login-role__icon">🏪</div>
            <h3>I'm a Vendor</h3>
            <ul>
                <li>Vendor dashboard</li>
                <li>Manage products</li>
                <li>Track sales</li>
                <li>Withdraw earnings</li>
            </ul>
        </div>
    </div>

    <p class="gm-login-note">Use the same login below — you'll be directed to your dashboard automatically.</p>

    <?php if ( $show_registration ) : ?>
    <!-- Tabs -->
    <div class="gm-login-tabs">
        <a href="<?php echo esc_url( add_query_arg( 'tab', 'login', wc_get_page_permalink( 'myaccount' ) ) ); ?>"
           class="gm-login-tab <?php echo $active_tab === 'login' ? 'is-active' : ''; ?>">
            Sign In
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'tab', 'register', wc_get_page_permalink( 'myaccount' ) ) ); ?>"
           class="gm-login-tab <?php echo $active_tab === 'register' ? 'is-active' : ''; ?>">
            Create Account
        </a>
    </div>
    <?php endif; ?>

    <!-- Login form -->
    <div class="gm-login-panel <?php echo ( ! $show_registration || $active_tab === 'login' ) ? 'is-visible' : ''; ?>" id="gm-panel-login">
        <form class="woocommerce-form woocommerce-form-login login" method="post" novalidate>

            <?php do_action( 'woocommerce_login_form_start' ); ?>

            <div class="gm-field">
                <label for="username"><?php esc_html_e( 'Email address or username', 'woocommerce' ); ?></label>
                <input type="text" name="username" id="username" autocomplete="username"
                       value="<?php echo ( ! empty( $_POST['username'] ) && is_string( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>"
                       required aria-required="true" />
            </div>

            <div class="gm-field">
                <label for="password"><?php esc_html_e( 'Password', 'woocommerce' ); ?></label>
                <input type="password" name="password" id="password" autocomplete="current-password" required aria-required="true" />
            </div>

            <?php do_action( 'woocommerce_login_form' ); ?>

            <div class="gm-field-row">
                <label class="gm-checkbox">
                    <input type="checkbox" name="rememberme" id="rememberme" value="forever" />
                    <span><?php esc_html_e( 'Keep me signed in', 'woocommerce' ); ?></span>
                </label>
                <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="gm-lost-password">
                    <?php esc_html_e( 'Forgot password?', 'woocommerce' ); ?>
                </a>
            </div>

            <?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>

            <button type="submit" class="gm-btn-submit" name="login" value="<?php esc_attr_e( 'Log in', 'woocommerce' ); ?>">
                Sign In to Giftelier
            </button>

            <?php do_action( 'woocommerce_login_form_end' ); ?>

        </form>

        <?php if ( $show_registration ) : ?>
        <p class="gm-switch-tab">
            New here? <a href="<?php echo esc_url( add_query_arg( 'tab', 'register', wc_get_page_permalink( 'myaccount' ) ) ); ?>">Create a free account</a>
        </p>
        <?php endif; ?>
    </div>

    <?php if ( $show_registration ) : ?>
    <!-- Register form -->
    <div class="gm-login-panel <?php echo $active_tab === 'register' ? 'is-visible' : ''; ?>" id="gm-panel-register">

        <form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?>>

            <?php do_action( 'woocommerce_register_form_start' ); ?>

            <?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>
            <div class="gm-field">
                <label for="reg_username"><?php esc_html_e( 'Username', 'woocommerce' ); ?></label>
                <input type="text" name="username" id="reg_username" autocomplete="username"
                       value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>"
                       required aria-required="true" />
            </div>
            <?php endif; ?>

            <div class="gm-field">
                <label for="reg_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?></label>
                <input type="email" name="email" id="reg_email" autocomplete="email"
                       value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>"
                       required aria-required="true" />
            </div>

            <?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>
            <div class="gm-field">
                <label for="reg_password"><?php esc_html_e( 'Password', 'woocommerce' ); ?></label>
                <input type="password" name="password" id="reg_password" autocomplete="new-password" required aria-required="true" />
            </div>
            <?php else : ?>
            <p class="gm-hint"><?php esc_html_e( 'A link to set a new password will be sent to your email address.', 'woocommerce' ); ?></p>
            <?php endif; ?>

            <?php do_action( 'woocommerce_register_form' ); ?>

            <?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>

            <button type="submit" class="gm-btn-submit" name="register" value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>">
                Create My Account
            </button>

            <?php do_action( 'woocommerce_register_form_end' ); ?>

        </form>

        <p class="gm-switch-tab">
            Already have an account? <a href="<?php echo esc_url( add_query_arg( 'tab', 'login', wc_get_page_permalink( 'myaccount' ) ) ); ?>">Sign in</a>
        </p>
    </div>
    <?php endif; ?>

</div><!-- .gm-login-wrap -->

<?php do_action( 'woocommerce_after_customer_login_form' ); ?>
