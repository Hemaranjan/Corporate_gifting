<?php
/**
 * Giftelier Admin Panel — custom wp-admin interface for the Giftelier team.
 *
 * Registers a top-level admin menu and renders a full-page panel with a
 * horizontal nav: Dashboard | Customers | Vendors | Orders | Products |
 * Payments | Reports | Settings.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class GM_Admin_Panel {

    const SLUG = 'giftelier-admin';

    const SECTIONS = [
        'dashboard' => [ 'label' => 'Dashboard',  'icon' => 'dashicons-chart-area'   ],
        'customers' => [ 'label' => 'Customers',   'icon' => 'dashicons-groups'       ],
        'vendors'   => [ 'label' => 'Vendors',      'icon' => 'dashicons-store'        ],
        'orders'    => [ 'label' => 'Orders',        'icon' => 'dashicons-cart'         ],
        'products'  => [ 'label' => 'Products',     'icon' => 'dashicons-products'     ],
        'payments'  => [ 'label' => 'Payments',     'icon' => 'dashicons-money-alt'    ],
        'reports'   => [ 'label' => 'Reports',      'icon' => 'dashicons-analytics'    ],
        'settings'  => [ 'label' => 'Settings',     'icon' => 'dashicons-admin-generic' ],
    ];

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menu'  ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    /* ── Menu registration ──────────────────────────────────────────── */

    public function register_menu() {
        add_menu_page(
            __( 'Giftelier Admin', 'gifting-marketplace' ),
            __( 'Giftelier Admin', 'gifting-marketplace' ),
            'manage_options',
            self::SLUG,
            [ $this, 'render_page' ],
            'dashicons-gift',
            3
        );
    }

    /* ── Assets ─────────────────────────────────────────────────────── */

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, self::SLUG ) === false ) return;

        wp_enqueue_style(
            'gm-admin-panel',
            GM_URL . 'assets/css/admin-panel.css',
            [ 'dashicons' ],
            filemtime( GM_PATH . 'assets/css/admin-panel.css' ) ?: '1.0.0'
        );

        wp_enqueue_style(
            'gm-admin-google-fonts',
            'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
            [],
            null
        );
    }

    /* ── Page renderer ──────────────────────────────────────────────── */

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'gifting-marketplace' ) );
        }

        $active = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'dashboard';
        if ( ! array_key_exists( $active, self::SECTIONS ) ) {
            $active = 'dashboard';
        }

        $tpl = GM_PATH . "templates/admin/{$active}.php";
        ?>
        <div class="gm-admin-wrap">

            <!-- Top navigation bar -->
            <header class="gm-admin-header">
                <div class="gm-admin-brand">
                    <span class="dashicons dashicons-gift"></span>
                    <strong><?php esc_html_e( 'Giftelier Admin', 'gifting-marketplace' ); ?></strong>
                </div>

                <nav class="gm-admin-nav" role="navigation" aria-label="<?php esc_attr_e( 'Giftelier Admin Navigation', 'gifting-marketplace' ); ?>">
                    <?php foreach ( self::SECTIONS as $key => $cfg ) :
                        $url   = add_query_arg( [ 'page' => self::SLUG, 'section' => $key ], admin_url( 'admin.php' ) );
                        $class = ( $key === $active ) ? 'gm-nav-item gm-nav-item--active' : 'gm-nav-item';
                    ?>
                        <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>">
                            <?php echo esc_html( $cfg['label'] ); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <div class="gm-admin-user">
                    <?php
                    $user   = wp_get_current_user();
                    $avatar = get_avatar_url( $user->ID, [ 'size' => 32 ] );
                    ?>
                    <img src="<?php echo esc_url( $avatar ); ?>"
                         alt="<?php echo esc_attr( $user->display_name ); ?>"
                         class="gm-admin-avatar"
                         title="<?php echo esc_attr( $user->display_name ); ?>" />
                </div>
            </header>

            <!-- Section content -->
            <main class="gm-admin-content">
                <?php if ( file_exists( $tpl ) ) {
                    include $tpl;
                } else {
                    $this->render_placeholder( $active );
                } ?>
            </main>

        </div>
        <?php
    }

    /* ── Placeholder for unbuilt sections ───────────────────────────── */

    private function render_placeholder( $section ) {
        $label = self::SECTIONS[ $section ]['label'] ?? ucfirst( $section );
        ?>
        <div class="gm-placeholder">
            <span class="dashicons <?php echo esc_attr( self::SECTIONS[ $section ]['icon'] ?? 'dashicons-admin-generic' ); ?>"></span>
            <h2><?php echo esc_html( $label ); ?></h2>
            <p><?php esc_html_e( 'This section is coming soon.', 'gifting-marketplace' ); ?></p>
        </div>
        <?php
    }
}
