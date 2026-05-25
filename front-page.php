<?php
/**
 * Front Page — Giftelier marketing landing page.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$is_logged_in = is_user_logged_in();

// Vendors get sent to their dashboard; everyone else sees the homepage
if ( $is_logged_in ) {
    $user = wp_get_current_user();
    if ( ! current_user_can( 'manage_options' )
         && function_exists( 'dokan_is_user_seller' )
         && dokan_is_user_seller( $user->ID ) ) {
        wp_safe_redirect( dokan_get_navigation_url() ); exit;
    }
}

$login_url = function_exists( 'wc_get_page_permalink' )
    ? wc_get_page_permalink( 'myaccount' )
    : wp_login_url();

// All homepage CTAs always point to the login/signup page.
// After login, functions.php redirect filters route vendors → Dokan, customers → calendar.
$cta_url = $login_url;

// Store listing page URL (Dokan).
$store_listing_id  = function_exists( 'dokan_get_option' ) ? dokan_get_option( 'store_listing', 'dokan_pages' ) : 0;
$store_listing_url = $store_listing_id ? get_permalink( (int) $store_listing_id ) : home_url( '/store-listing/' );

// Products for the browse section
$products = wc_get_products( [
    'status' => 'publish',
    'limit'  => 8,
    'orderby'=> 'rand',
] );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>

<body <?php body_class( 'gm-front-page' ); ?>>
<?php wp_body_open(); ?>

<!-- ── HEADER ──────────────────────────────────────────────────────── -->
<header class="gmp-header">
    <div class="gmp-container gmp-header__inner">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="gmp-logo">
            <span class="gmp-logo__icon">🎁</span>
            <span class="gmp-logo__name">Giftelier</span>
        </a>
        <nav class="gmp-nav">
            <a href="#gmp-how">How it works</a>
            <a href="#gmp-gifts">Browse Gifts</a>
        </nav>
        <a href="<?php echo esc_url( $cta_url ); ?>" class="gmp-btn gmp-btn--primary gmp-btn--sm">
            Login / Sign Up
        </a>
    </div>
</header>

<!-- ── HERO ────────────────────────────────────────────────────────── -->
<section class="gmp-hero">
    <div class="gmp-hero__bg" aria-hidden="true">
        <div class="gmp-hero__orb gmp-hero__orb--1"></div>
        <div class="gmp-hero__orb gmp-hero__orb--2"></div>
        <div class="gmp-hero__orb gmp-hero__orb--3"></div>
    </div>
    <div class="gmp-container gmp-hero__inner">
        <div class="gmp-hero__content">
            <div class="gmp-hero__eyebrow">
                <span class="gmp-hero__eyebrow-dot"></span>
                India's B2B Corporate Gifting Platform
            </div>
            <h1 class="gmp-hero__title">
                Gifting That Builds<br>
                <span class="gmp-hero__accent">Lasting Bonds</span>
            </h1>
            <p class="gmp-hero__sub">
                Source curated corporate gifts, manage bulk orders, and delight your clients and employees — all from one platform, delivered on time.
            </p>
            <div class="gmp-hero__actions">
                <a href="<?php echo esc_url( $cta_url ); ?>" class="gmp-btn gmp-btn--primary gmp-btn--lg">
                    Start Gifting Free
                </a>
                <a href="#gmp-how" class="gmp-btn gmp-btn--ghost gmp-btn--lg">How It Works</a>
            </div>
            <div class="gmp-hero__proof">
                <div class="gmp-hero__avatars">
                    <span>🏢</span><span>🏭</span><span>🏥</span><span>🏗️</span><span>🎓</span>
                </div>
                <span>Trusted by <strong>500+</strong> businesses across India</span>
            </div>
        </div>
        <div class="gmp-hero__visual" aria-hidden="true">
            <div class="gmp-float-card gmp-float-card--a">
                <div class="gmp-float-card__icon">🏢</div>
                <div class="gmp-float-card__body">
                    <strong>Diwali Corporate Order</strong>
                    <span>250 units · Dispatched</span>
                </div>
                <span class="gmp-badge gmp-badge--green">Delivered</span>
            </div>
            <div class="gmp-float-card gmp-float-card--b">
                <div class="gmp-float-card__icon">📦</div>
                <div class="gmp-float-card__body">
                    <strong>Employee Gift Set</strong>
                    <span>Bulk order · 80 units</span>
                </div>
                <span class="gmp-badge gmp-badge--amber">In Transit</span>
            </div>
            <div class="gmp-float-card gmp-float-card--c">
                <div class="gmp-float-card__stars">★★★★★</div>
                <div class="gmp-float-card__body">
                    <strong>"Perfect for bulk gifting!"</strong>
                    <span>— HR Manager, Infosys</span>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── STATS ────────────────────────────────────────────────────────── -->
<div class="gmp-stats">
    <div class="gmp-container">
        <div class="gmp-stats__inner">
            <div class="gmp-stat"><strong>50+</strong><span>Verified B2B Vendors</span></div>
            <div class="gmp-stat__sep"></div>
            <div class="gmp-stat"><strong>10K+</strong><span>Corporate Orders Fulfilled</span></div>
            <div class="gmp-stat__sep"></div>
            <div class="gmp-stat"><strong>26K+</strong><span>Pincodes Covered</span></div>
            <div class="gmp-stat__sep"></div>
            <div class="gmp-stat"><strong>4.9 ★</strong><span>Client Satisfaction</span></div>
        </div>
    </div>
</div>

<!-- ── HOW IT WORKS ─────────────────────────────────────────────────── -->
<section class="gmp-section gmp-section--how" id="gmp-how">
    <div class="gmp-container">
        <div class="gmp-section-intro">
            <span class="gmp-pill gmp-pill--dark">Simple Process</span>
            <h2>How Giftelier works</h2>
            <p>From onboarding to bulk delivery in four easy steps.</p>
        </div>
        <div class="gmp-steps">
            <div class="gmp-step">
                <div class="gmp-step__num">01</div>
                <div class="gmp-step__icon">🔐</div>
                <h3>Create Your Account</h3>
                <p>Sign up free and set up your company gifting profile in minutes.</p>
            </div>
            <div class="gmp-step__arrow">→</div>
            <div class="gmp-step">
                <div class="gmp-step__num">02</div>
                <div class="gmp-step__icon">📅</div>
                <h3>Plan Your Occasions</h3>
                <p>Add corporate occasions — Diwali, Employee Day, client anniversaries — to your gifting calendar.</p>
            </div>
            <div class="gmp-step__arrow">→</div>
            <div class="gmp-step">
                <div class="gmp-step__num">03</div>
                <div class="gmp-step__icon">🎁</div>
                <h3>Source &amp; Quote</h3>
                <p>Browse curated B2B gift catalogues and request bulk quotes directly from verified vendors.</p>
            </div>
            <div class="gmp-step__arrow">→</div>
            <div class="gmp-step">
                <div class="gmp-step__num">04</div>
                <div class="gmp-step__icon">🚚</div>
                <h3>We Deliver, You Impress</h3>
                <p>Bulk orders are packed and shipped pan-India via Shiprocket — tracked to every recipient.</p>
            </div>
        </div>
        <div style="text-align:center;margin-top:40px">
            <a href="<?php echo esc_url( $cta_url ); ?>" class="gmp-btn gmp-btn--primary gmp-btn--lg">
                Start Corporate Gifting →
            </a>
        </div>
    </div>
</section>

<!-- ── BROWSE GIFTS ─────────────────────────────────────────────────── -->
<section class="gmp-section gmp-section--gifts" id="gmp-gifts">
    <div class="gmp-container">
        <div class="gmp-section-intro">
            <span class="gmp-pill gmp-pill--rose">Curated B2B Collection</span>
            <h2>Corporate gifts your recipients will love</h2>
            <p>Hand-picked products from verified vendors — suitable for bulk ordering across India.</p>
        </div>

        <div class="gmp-gift-grid">
            <?php foreach ( $products as $product ) :
                $img_id  = $product->get_image_id();
                $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'woocommerce_thumbnail' ) : '';
                $cats    = wp_get_post_terms( $product->get_id(), 'product_cat', [ 'fields' => 'names' ] );
                $cat     = ! empty( $cats ) && ! is_wp_error( $cats ) ? $cats[0] : '';
                $vendor_id   = (int) get_post_field( 'post_author', $product->get_id() );
                $vendor_info = function_exists( 'dokan_get_store_info' ) ? dokan_get_store_info( $vendor_id ) : [];
                $vendor_name = $vendor_info['store_name'] ?? '';
            ?>
            <div class="gmp-gift-card">
                <a href="<?php echo esc_url( $cta_url ); ?>" class="gmp-gift-card__img-wrap">
                    <?php if ( $img_url ) : ?>
                        <img src="<?php echo esc_url( $img_url ); ?>"
                             alt="<?php echo esc_attr( $product->get_name() ); ?>"
                             class="gmp-gift-card__img" loading="lazy" />
                    <?php else : ?>
                        <div class="gmp-gift-card__img gmp-gift-card__img--placeholder">🎁</div>
                    <?php endif; ?>
                    <?php if ( $cat ) : ?>
                    <span class="gmp-gift-card__cat"><?php echo esc_html( $cat ); ?></span>
                    <?php endif; ?>
                </a>
                <div class="gmp-gift-card__body">
                    <?php if ( $vendor_name ) : ?>
                    <span class="gmp-gift-card__vendor"><?php echo esc_html( $vendor_name ); ?></span>
                    <?php endif; ?>
                    <h3 class="gmp-gift-card__name"><?php echo esc_html( $product->get_name() ); ?></h3>
                    <div class="gmp-gift-card__footer">
                        <span class="gmp-gift-card__price">₹<?php echo number_format( (float) $product->get_price(), 0 ); ?></span>
                        <a href="<?php echo esc_url( $cta_url ); ?>" class="gmp-btn gmp-btn--outline gmp-btn--sm">
                            Sign in to Buy
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="gmp-gifts-more">
            <a href="<?php echo esc_url( $store_listing_url ); ?>" class="gmp-btn gmp-btn--outline gmp-btn--lg">
                View all gifts →
            </a>
        </div>
    </div>
</section>

<!-- ── WHY GIFTELIER ─────────────────────────────────────────────────── -->
<section class="gmp-section gmp-section--why">
    <div class="gmp-container">
        <div class="gmp-section-intro">
            <span class="gmp-pill gmp-pill--purple">Why us</span>
            <h2>Built for corporate gifting at scale</h2>
        </div>
        <div class="gmp-why-grid">
            <div class="gmp-why-card">
                <div class="gmp-why-card__icon">📋</div>
                <h3>Bulk Quote Requests</h3>
                <p>Get custom pricing for large orders directly from vendors through our built-in quote system — no cold emails needed.</p>
            </div>
            <div class="gmp-why-card">
                <div class="gmp-why-card__icon">📅</div>
                <h3>Occasion-Aware Delivery</h3>
                <p>Map gifts to corporate occasions — Diwali, Employee Day, client milestones — and we ensure delivery arrives on time.</p>
            </div>
            <div class="gmp-why-card">
                <div class="gmp-why-card__icon">🚚</div>
                <h3>Pan-India Tracked Shipping</h3>
                <p>Powered by Shiprocket — 15+ courier partners, 26,000+ pincodes. Every shipment tracked end to end.</p>
            </div>
            <div class="gmp-why-card">
                <div class="gmp-why-card__icon">🏪</div>
                <h3>Curated B2B Vendors</h3>
                <p>Every vendor is manually vetted for quality and reliability in corporate gifting — not generic marketplace listings.</p>
            </div>
        </div>
    </div>
</section>

<!-- ── FINAL CTA ─────────────────────────────────────────────────────── -->
<section class="gmp-cta-banner">
    <div class="gmp-container gmp-cta-banner__inner">
        <h2>Elevate your corporate gifting strategy</h2>
        <p>Join hundreds of businesses gifting smarter — clients, employees, and partners across India.</p>
        <a href="<?php echo esc_url( $cta_url ); ?>" class="gmp-btn gmp-btn--white gmp-btn--lg">
            Get Started Free →
        </a>
    </div>
</section>

<!-- ── FOOTER ─────────────────────────────────────────────────────────── -->
<footer class="gmp-footer">
    <div class="gmp-container gmp-footer__inner">
        <div class="gmp-logo">
            <span class="gmp-logo__icon">🎁</span>
            <span class="gmp-logo__name" style="color:#fff">Giftelier</span>
        </div>
        <p class="gmp-footer__copy">© <?php echo date('Y'); ?> Giftelier. All rights reserved.</p>
        <div class="gmp-footer__links">
            <a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">Privacy</a>
            <a href="<?php echo esc_url( home_url('/refund-returns/') ); ?>">Returns</a>
            <a href="<?php echo esc_url( $login_url ); ?>">Sign In</a>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
