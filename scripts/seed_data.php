<?php
/**
 * Corporate Gifting Platform — Sample Data Seeder
 * Seeds: 2 Vendors, 2 Customers, Sample Products, Categories
 *
 * Verified against Bagisto v2.4.1 schema.
 */

define('LARAVEL_START', microtime(true));
chdir('/var/www/html');
require '/var/www/html/vendor/autoload.php';

$app = require_once '/var/www/html/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "\n--- Seeding Corporate Gifting Sample Data ---\n\n";

// ─────────────────────────────────────────────
// 1. UPDATE ADMIN CREDENTIALS
// ─────────────────────────────────────────────
echo "[Admin] Updating admin credentials...\n";
// Remove the default example admin if our corporate admin already exists
$corporateAdmin = DB::table('admins')->where('email', 'admin@corporate.com')->first();
if ($corporateAdmin) {
    // Just update password to ensure it's correct
    DB::table('admins')->where('email', 'admin@corporate.com')->update([
        'name'       => 'Platform Admin',
        'password'   => Hash::make('Admin@123'),
        'status'     => 1,
        'updated_at' => now(),
    ]);
    // Remove the default example.com admin if it still exists
    DB::table('admins')->where('email', 'admin@example.com')->delete();
} else {
    // Rename example.com to corporate.com
    DB::table('admins')->where('email', 'admin@example.com')->update([
        'name'       => 'Platform Admin',
        'email'      => 'admin@corporate.com',
        'password'   => Hash::make('Admin@123'),
        'status'     => 1,
        'updated_at' => now(),
    ]);
}
echo "   Email   : admin@corporate.com\n";
echo "   Password: Admin@123\n\n";

// ─────────────────────────────────────────────
// 2. CHANNEL & ROOT CATEGORY
// ─────────────────────────────────────────────
$channel    = DB::table('channels')->first();
$channelId  = $channel->id;          // 1
$rootCatId  = $channel->root_category_id;  // 1

// ─────────────────────────────────────────────
// 3. CREATE CATEGORIES (children of root)
// ─────────────────────────────────────────────
echo "[Categories] Creating corporate gift categories...\n";

function createCategory(string $name, string $description, string $slug, int $parentId): int {
    $existing = DB::table('category_translations')->where('slug', $slug)->first();
    if ($existing) {
        echo "   [skip] $name\n";
        return (int) $existing->category_id;
    }

    // Use NestedSet: fetch parent lft/rgt to compute positions
    $parent = DB::table('categories')->where('id', $parentId)->first();
    $parentRgt = $parent->_rgt;

    // Shift existing nodes to make room
    DB::table('categories')->where('_rgt', '>=', $parentRgt)
        ->increment('_rgt', 2);
    DB::table('categories')->where('_lft', '>', $parentRgt)
        ->increment('_lft', 2);

    $catId = DB::table('categories')->insertGetId([
        'position'     => rand(2, 9),
        'status'       => 1,
        'display_mode' => 'products_and_description',
        'parent_id'    => $parentId,
        '_lft'         => $parentRgt,
        '_rgt'         => $parentRgt + 1,
        'created_at'   => now(),
        'updated_at'   => now(),
    ]);

    DB::table('category_translations')->insert([
        'category_id'      => $catId,
        'locale'           => 'en',
        'name'             => $name,
        'description'      => $description,
        'slug'             => $slug,
        'meta_title'       => $name,
        'meta_description' => $description,
        'meta_keywords'    => strtolower(str_replace(' ', ',', $name)),
    ]);

    echo "   Created : $name (ID: $catId)\n";
    return $catId;
}

$luxuryId  = createCategory('Luxury Gift Hampers',     'Premium corporate gift hampers for executives',           'luxury-gift-hampers',      $rootCatId);
$brandedId = createCategory('Branded Corporate Gifts', 'Logo-branded merchandise and gifts for corporate events', 'branded-corporate-gifts',  $rootCatId);
$festiveId = createCategory('Festive Gift Sets',       'Seasonal and festival corporate gift sets in bulk',       'festive-gift-sets',        $rootCatId);
echo "\n";

// ─────────────────────────────────────────────
// 4. VENDOR ACCOUNTS (vendor customer group)
// ─────────────────────────────────────────────
echo "[Vendors] Creating vendor accounts...\n";

$vendorGroupId = DB::table('customer_groups')->where('code', 'vendor')->value('id');
if (!$vendorGroupId) {
    $vendorGroupId = DB::table('customer_groups')->insertGetId([
        'code'            => 'vendor',
        'name'            => 'Vendor',
        'is_user_defined' => 1,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
    echo "   Created vendor group.\n";
}

$vendors = [
    [
        'first_name' => 'GiftCraft',
        'last_name'  => 'Solutions',
        'email'      => 'vendor@giftcraft.com',
        'password'   => 'Vendor@123',
        'phone'      => '+919876543210',
        'store'      => 'GiftCraft Store',
        'company'    => 'GiftCraft Solutions Pvt Ltd',
    ],
    [
        'first_name' => 'BrandBox',
        'last_name'  => 'Gifts',
        'email'      => 'vendor@brandboxgifts.com',
        'password'   => 'Vendor@123',
        'phone'      => '+919876543211',
        'store'      => 'BrandBox Store',
        'company'    => 'BrandBox Gifts Pvt Ltd',
    ],
];

foreach ($vendors as $v) {
    if (DB::table('customers')->where('email', $v['email'])->exists()) {
        echo "   [skip] {$v['email']}\n";
        continue;
    }
    $vid = DB::table('customers')->insertGetId([
        'first_name'        => $v['first_name'],
        'last_name'         => $v['last_name'],
        'email'             => $v['email'],
        'password'          => Hash::make($v['password']),
        'customer_group_id' => $vendorGroupId,
        'channel_id'        => $channelId,
        'status'            => 1,
        'is_verified'       => 1,
        'phone'             => $v['phone'],
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);
    DB::table('customer_notes')->insert([
        'customer_id'   => $vid,
        'note'          => "Vendor store: {$v['store']} | Company: {$v['company']}",
        'customer_notified' => 0,
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);
    echo "   Vendor : {$v['first_name']} {$v['last_name']}\n";
    echo "   Email  : {$v['email']}\n";
    echo "   Pass   : {$v['password']}\n";
    echo "   Store  : {$v['store']}\n\n";
}

// ─────────────────────────────────────────────
// 5. CUSTOMER ACCOUNTS
// ─────────────────────────────────────────────
echo "[Customers] Creating customer accounts...\n";

$generalGroupId = DB::table('customer_groups')->where('code', 'general')->value('id') ?? 2;

$customers = [
    [
        'first_name' => 'Aanya',
        'last_name'  => 'Mehta',
        'email'      => 'aanya.mehta@techcorp.com',
        'password'   => 'Customer@123',
        'phone'      => '+919988776655',
        'company'    => 'TechCorp India Ltd',
        'note'       => 'Bulk customer — 200+ gift sets per quarter for employee appreciation.',
    ],
    [
        'first_name' => 'Rohan',
        'last_name'  => 'Sharma',
        'email'      => 'rohan.sharma@financeplus.in',
        'password'   => 'Customer@123',
        'phone'      => '+919988776644',
        'company'    => 'FinancePlus Advisory',
        'note'       => 'Premium customer — luxury hampers for client gifting, Diwali & New Year.',
    ],
];

foreach ($customers as $c) {
    if (DB::table('customers')->where('email', $c['email'])->exists()) {
        echo "   [skip] {$c['email']}\n";
        continue;
    }
    $cid = DB::table('customers')->insertGetId([
        'first_name'        => $c['first_name'],
        'last_name'         => $c['last_name'],
        'email'             => $c['email'],
        'password'          => Hash::make($c['password']),
        'customer_group_id' => $generalGroupId,
        'channel_id'        => $channelId,
        'status'            => 1,
        'is_verified'       => 1,
        'phone'             => $c['phone'],
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);
    DB::table('addresses')->insert([
        'address_type'   => 'customer',
        'customer_id'    => $cid,
        'company_name'   => $c['company'],
        'first_name'     => $c['first_name'],
        'last_name'      => $c['last_name'],
        'email'          => $c['email'],
        'phone'          => $c['phone'],
        'address'        => '101 Business Park, Andheri East',
        'city'           => 'Mumbai',
        'state'          => 'Maharashtra',
        'country'        => 'IN',
        'postcode'       => '400069',
        'default_address'=> 1,
        'created_at'     => now(),
        'updated_at'     => now(),
    ]);
    DB::table('customer_notes')->insert([
        'customer_id'       => $cid,
        'note'              => $c['note'],
        'customer_notified' => 0,
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);
    echo "   Customer : {$c['first_name']} {$c['last_name']}\n";
    echo "   Email    : {$c['email']}\n";
    echo "   Password : {$c['password']}\n";
    echo "   Company  : {$c['company']}\n\n";
}

// ─────────────────────────────────────────────
// 6. SAMPLE PRODUCTS
// ─────────────────────────────────────────────
echo "[Products] Creating sample corporate gift products...\n";

$attrFamilyId    = DB::table('attribute_families')->first()->id;
$inventorySource = DB::table('inventory_sources')->first();

// Attribute IDs (verified from live DB)
$ATTR = [
    'name'                 => 2,
    'url_key'              => 3,
    'new'                  => 5,
    'featured'             => 6,
    'visible_individually' => 7,
    'status'               => 8,
    'short_description'    => 9,
    'description'          => 10,
    'price'                => 11,
    'weight'               => 22,
];

$products = [
    [
        'sku'         => 'CGP-LUXURY-001',
        'name'        => 'Executive Luxury Hamper',
        'description' => 'A premium gift hamper featuring artisan chocolates, premium dry fruits, scented candles, and a personalized leather journal. Perfect for C-suite gifting and client appreciation.',
        'price'       => 4999.00,
        'weight'      => '2.5',
        'category_id' => $luxuryId,
        'url_key'     => 'executive-luxury-hamper',
    ],
    [
        'sku'         => 'CGP-BRANDED-001',
        'name'        => 'Branded Corporate Kit',
        'description' => 'Complete branded merchandise kit with custom logo printed pen set, diary, USB hub, and tote bag. Minimum order 50 units. Lead time 7 business days.',
        'price'       => 1299.00,
        'weight'      => '1.2',
        'category_id' => $brandedId,
        'url_key'     => 'branded-corporate-kit',
    ],
    [
        'sku'         => 'CGP-FESTIVE-001',
        'name'        => 'Diwali Festival Gift Box',
        'description' => 'Beautifully curated Diwali gift box with premium sweets, dry fruits, diyas, and festive decorations. Available in bulk for corporate orders from 25 units.',
        'price'       => 2499.00,
        'weight'      => '1.8',
        'category_id' => $festiveId,
        'url_key'     => 'diwali-festival-gift-box',
    ],
    [
        'sku'         => 'CGP-WELLNESS-001',
        'name'        => 'Employee Wellness Gift Set',
        'description' => 'Curated wellness gift set featuring herbal teas, aromatherapy oils, bamboo desk organizer, and a handcrafted notebook. Ideal for employee appreciation and onboarding kits.',
        'price'       => 3499.00,
        'weight'      => '1.5',
        'category_id' => $luxuryId,
        'url_key'     => 'employee-wellness-gift-set',
    ],
];

foreach ($products as $p) {
    if (DB::table('products')->where('sku', $p['sku'])->exists()) {
        echo "   [skip] {$p['sku']}\n";
        continue;
    }

    $pid = DB::table('products')->insertGetId([
        'type'               => 'simple',
        'attribute_family_id'=> $attrFamilyId,
        'sku'                => $p['sku'],
        'created_at'         => now(),
        'updated_at'         => now(),
    ]);

    // Product flat row (for storefront listing/search)
    DB::table('product_flat')->insert([
        'product_id'           => $pid,
        'channel'              => 'default',
        'locale'               => 'en',
        'name'                 => $p['name'],
        'description'          => $p['description'],
        'short_description'    => substr($p['description'], 0, 150),
        'url_key'              => $p['url_key'],
        'sku'                  => $p['sku'],
        'new'                  => 1,
        'featured'             => 1,
        'status'               => 1,
        'visible_individually' => 1,
        'price'                => $p['price'],
        'weight'               => $p['weight'],
        'attribute_family_id'  => $attrFamilyId,
        'created_at'           => now(),
        'updated_at'           => now(),
    ]);

    // Product attribute values
    $attrValues = [
        // text attributes (locale + channel-aware)
        ['attribute_id' => $ATTR['name'],              'text_value'    => $p['name'],              'channel' => 'default', 'locale' => 'en'],
        ['attribute_id' => $ATTR['url_key'],           'text_value'    => $p['url_key'],           'channel' => null,      'locale' => 'en'],
        ['attribute_id' => $ATTR['short_description'], 'text_value'    => substr($p['description'], 0, 150), 'channel' => null, 'locale' => 'en'],
        ['attribute_id' => $ATTR['description'],       'text_value'    => $p['description'],       'channel' => null,      'locale' => 'en'],
        ['attribute_id' => $ATTR['weight'],            'text_value'    => $p['weight'],            'channel' => null,      'locale' => null],
        // price
        ['attribute_id' => $ATTR['price'],             'float_value'   => $p['price'],             'channel' => null,      'locale' => null],
        // booleans
        ['attribute_id' => $ATTR['status'],            'boolean_value' => 1,                       'channel' => 'default', 'locale' => null],
        ['attribute_id' => $ATTR['new'],               'boolean_value' => 1,                       'channel' => null,      'locale' => null],
        ['attribute_id' => $ATTR['featured'],          'boolean_value' => 1,                       'channel' => null,      'locale' => null],
        ['attribute_id' => $ATTR['visible_individually'], 'boolean_value' => 1,                    'channel' => null,      'locale' => null],
    ];

    foreach ($attrValues as $av) {
        $row = ['product_id' => $pid, 'attribute_id' => $av['attribute_id'], 'channel' => $av['channel'] ?? null, 'locale' => $av['locale'] ?? null];
        if (isset($av['text_value']))    $row['text_value']    = $av['text_value'];
        if (isset($av['float_value']))   $row['float_value']   = $av['float_value'];
        if (isset($av['boolean_value'])) $row['boolean_value'] = $av['boolean_value'];
        DB::table('product_attribute_values')->insert($row);
    }

    // Category assignment
    DB::table('product_categories')->insertOrIgnore(['product_id' => $pid, 'category_id' => $p['category_id']]);

    // Channel assignment
    DB::table('product_channels')->insertOrIgnore(['product_id' => $pid, 'channel_id' => $channelId]);

    // Inventory (500 units)
    if ($inventorySource) {
        DB::table('product_inventories')->insert([
            'product_id'          => $pid,
            'inventory_source_id' => $inventorySource->id,
            'vendor_id'           => 0,
            'qty'                 => 500,
        ]);
    }

    echo "   {$p['name']} — INR {$p['price']}\n";
}

// ─────────────────────────────────────────────
// SUMMARY
// ─────────────────────────────────────────────
echo "\n";
echo "============================================\n";
echo "        PLATFORM CREDENTIALS SUMMARY        \n";
echo "============================================\n\n";
echo "ADMIN PANEL   : http://localhost:8080/admin\n";
echo "  Email       : admin@corporate.com\n";
echo "  Password    : Admin@123\n\n";
echo "VENDOR 1\n";
echo "  Email       : vendor@giftcraft.com\n";
echo "  Password    : Vendor@123\n";
echo "  Store       : GiftCraft Store\n\n";
echo "VENDOR 2\n";
echo "  Email       : vendor@brandboxgifts.com\n";
echo "  Password    : Vendor@123\n";
echo "  Store       : BrandBox Store\n\n";
echo "CUSTOMER 1\n";
echo "  Name        : Aanya Mehta (TechCorp India)\n";
echo "  Email       : aanya.mehta@techcorp.com\n";
echo "  Password    : Customer@123\n\n";
echo "CUSTOMER 2\n";
echo "  Name        : Rohan Sharma (FinancePlus Advisory)\n";
echo "  Email       : rohan.sharma@financeplus.in\n";
echo "  Password    : Customer@123\n\n";
echo "STOREFRONT    : http://localhost:8080\n\n";
echo "============================================\n";
echo "  Seeding Complete!\n";
echo "============================================\n";
