<?php
/**
 * GM_Seed_Data — 25 vendors (5/industry) + 150 products (5/gift-type/industry).
 * Re-seed: delete option 'gm_seed_data_v6' from wp_options and reload any page.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class GM_Seed_Data {

    const OPTION   = 'gm_seed_data_v7';
    const PASSWORD = 'Vendor@Giftelier1';

    /* gift_type → gifting program, per industry */
    private static $program_map = [
        'corporate' => [
            'hamper'             => 'festival-gifting',
            'premium-gifts'      => 'employee-gifting',
            'personalized-gifts' => 'employee-gifting',
            'eco-friendly-gifts' => 'event-giveaways',
            'handcrafted-gifts'  => 'client-appreciation',
            'local-artisan-gifts'=> 'client-appreciation',
        ],
        'school' => [
            'hamper'             => 'annual-day-gifts',
            'premium-gifts'      => 'student-awards',
            'personalized-gifts' => 'student-awards',
            'eco-friendly-gifts' => 'annual-day-gifts',
            'handcrafted-gifts'  => 'staff-recognition',
            'local-artisan-gifts'=> 'graduation-gifts',
        ],
        'wedding' => [
            'hamper'             => 'wedding-hampers',
            'premium-gifts'      => 'guest-welcome-kits',
            'personalized-gifts' => 'return-gifts',
            'eco-friendly-gifts' => 'event-favours',
            'handcrafted-gifts'  => 'return-gifts',
            'local-artisan-gifts'=> 'event-favours',
        ],
        'hospitals' => [
            'hamper'             => 'patient-welcome-kits',
            'premium-gifts'      => 'doctor-appreciation',
            'personalized-gifts' => 'staff-rewards',
            'eco-friendly-gifts' => 'recovery-gifts',
            'handcrafted-gifts'  => 'doctor-appreciation',
            'local-artisan-gifts'=> 'recovery-gifts',
        ],
        'construction' => [
            'hamper'             => 'housewarming-gifts',
            'premium-gifts'      => 'customer-handover-kits',
            'personalized-gifts' => 'customer-handover-kits',
            'eco-friendly-gifts' => 'project-milestone-gifts',
            'handcrafted-gifts'  => 'partner-appreciation',
            'local-artisan-gifts'=> 'housewarming-gifts',
        ],
    ];

    private static $seg_colors = [
        'corporate'    => '5733a2',
        'school'       => '0e7490',
        'wedding'      => 'be185d',
        'hospitals'    => '0f766e',
        'construction' => 'b45309',
    ];

    /* ── Vendor definitions ──────────────────────────────────────────────── */

    private static function vendor_data() {
        return [
            'corporate' => [
                [ 'store' => 'GiftBox India',         'login' => 'giftbox_india',      'first' => 'Rahul',  'last' => 'Sharma',   'email' => 'giftbox@demo.giftelier.in',       'city' => 'Mumbai'    ],
                [ 'store' => 'Corporate Store Co',    'login' => 'corporate_store',    'first' => 'Priya',  'last' => 'Mehta',    'email' => 'corpstore@demo.giftelier.in',     'city' => 'Delhi'     ],
                [ 'store' => 'BrandKraft Gifts',      'login' => 'brandkraft_gifts',   'first' => 'Arjun',  'last' => 'Gupta',    'email' => 'brandkraft@demo.giftelier.in',    'city' => 'Bangalore' ],
                [ 'store' => 'WorkPerk Solutions',    'login' => 'workperk_solutions', 'first' => 'Neha',   'last' => 'Singh',    'email' => 'workperk@demo.giftelier.in',      'city' => 'Hyderabad' ],
                [ 'store' => 'Executive Impressions', 'login' => 'exec_impressions',   'first' => 'Vikram', 'last' => 'Patel',    'email' => 'execimpress@demo.giftelier.in',   'city' => 'Pune'      ],
            ],
            'school' => [
                [ 'store' => 'SchoolPride Gifts',  'login' => 'schoolpride_gifts',  'first' => 'Sunita', 'last' => 'Rao',    'email' => 'schoolpride@demo.giftelier.in',     'city' => 'Chennai'    ],
                [ 'store' => 'EduRewards India',   'login' => 'edurewards_india',   'first' => 'Mohan',  'last' => 'Kumar',  'email' => 'edurewards@demo.giftelier.in',      'city' => 'Kolkata'    ],
                [ 'store' => 'TrophyHouse',        'login' => 'trophyhouse_india',  'first' => 'Deepa',  'last' => 'Nair',   'email' => 'trophyhouse@demo.giftelier.in',     'city' => 'Kochi'      ],
                [ 'store' => 'GradGifts Co',       'login' => 'gradgifts_co',       'first' => 'Kiran',  'last' => 'Reddy',  'email' => 'gradgifts@demo.giftelier.in',       'city' => 'Hyderabad'  ],
                [ 'store' => 'CampusCreations',    'login' => 'campus_creations',   'first' => 'Ananya', 'last' => 'Iyer',   'email' => 'campuscreations@demo.giftelier.in', 'city' => 'Coimbatore' ],
            ],
            'wedding' => [
                [ 'store' => 'WeddingWonders India', 'login' => 'weddingwonders',    'first' => 'Pooja',  'last' => 'Sharma',   'email' => 'weddingwonders@demo.giftelier.in', 'city' => 'Jaipur'    ],
                [ 'store' => 'CelebrationBox Co',    'login' => 'celebrationbox',    'first' => 'Ravi',   'last' => 'Malhotra', 'email' => 'celebbox@demo.giftelier.in',        'city' => 'Mumbai'    ],
                [ 'store' => 'ShadiGifts',           'login' => 'shadi_gifts',       'first' => 'Kavita', 'last' => 'Joshi',    'email' => 'shadigifts@demo.giftelier.in',      'city' => 'Udaipur'   ],
                [ 'store' => 'EventMementos',        'login' => 'event_mementos',    'first' => 'Suresh', 'last' => 'Verma',    'email' => 'eventmemo@demo.giftelier.in',       'city' => 'Delhi'     ],
                [ 'store' => 'HamperCraft India',    'login' => 'hampercraft_india', 'first' => 'Meena',  'last' => 'Pillai',   'email' => 'hampercraft@demo.giftelier.in',     'city' => 'Bangalore' ],
            ],
            'hospitals' => [
                [ 'store' => 'CareGifts India',    'login' => 'caregifts_india',    'first' => 'Amit',   'last' => 'Shah',  'email' => 'caregifts@demo.giftelier.in',    'city' => 'Mumbai'    ],
                [ 'store' => 'MedToken Gifts',     'login' => 'medtoken_gifts',     'first' => 'Shweta', 'last' => 'Bose',  'email' => 'medtoken@demo.giftelier.in',     'city' => 'Delhi'     ],
                [ 'store' => 'WellnessBox India',  'login' => 'wellnessbox_india',  'first' => 'Rajesh', 'last' => 'Nair',  'email' => 'wellnessbox@demo.giftelier.in',  'city' => 'Bangalore' ],
                [ 'store' => 'HealingTouch Gifts', 'login' => 'healingtouch_gifts', 'first' => 'Priti',  'last' => 'Desai', 'email' => 'healingtouch@demo.giftelier.in', 'city' => 'Pune'      ],
                [ 'store' => 'HospitalCare Gifts', 'login' => 'hospitalcare_gifts', 'first' => 'Sanjay', 'last' => 'Menon', 'email' => 'hospcare@demo.giftelier.in',     'city' => 'Chennai'   ],
            ],
            'construction' => [
                [ 'store' => 'HomeMilestone Gifts', 'login' => 'homemilestone',    'first' => 'Anil',   'last' => 'Kapoor', 'email' => 'homemilestone@demo.giftelier.in', 'city' => 'Mumbai'    ],
                [ 'store' => 'PropertyGifts India', 'login' => 'propertygifts',    'first' => 'Ritu',   'last' => 'Saxena', 'email' => 'propertygifts@demo.giftelier.in', 'city' => 'Noida'     ],
                [ 'store' => 'BuilderChoice Gifts', 'login' => 'builderchoice',    'first' => 'Sunil',  'last' => 'Kumar',  'email' => 'builderchoice@demo.giftelier.in', 'city' => 'Pune'      ],
                [ 'store' => 'KeysAndGifts',        'login' => 'keys_and_gifts',   'first' => 'Namita', 'last' => 'Roy',    'email' => 'keysandgifts@demo.giftelier.in',  'city' => 'Kolkata'   ],
                [ 'store' => 'SiteComplete Co',     'login' => 'sitecomplete_co',  'first' => 'Dinesh', 'last' => 'Pandey', 'email' => 'sitecomplete@demo.giftelier.in',  'city' => 'Ahmedabad' ],
            ],
        ];
    }

    /* ── Product definitions: 5 per gift-type × 6 types × 5 industries = 150 ── */

    private static function img( $seed ) {
        return 'https://picsum.photos/seed/' . $seed . '/600/600';
    }

    private static function product_data() {
        return [

/* ════════════════════════════════════════════════════════════════════
   CORPORATE GIFTING
   ═══════════════════════════════════════════════════════════════════ */
'corporate' => [

    /* — Hamper — */
    [ 'gift_type'=>'hamper', 'price'=>2499, 'img'=>self::img('gm-c-h1'),
      'name'=>'Executive Wellness Hamper',
      'short'=>'Artisan snacks, premium loose-leaf tea, soy candle and leather desk journal.',
      'desc'=>'Curated for impact — single-origin artisan snacks, premium Darjeeling loose-leaf tea, a hand-poured soy-wax candle, and a leather-bound desk journal. Fully customisable with your company branding. Perfect for onboarding and milestones.' ],
    [ 'gift_type'=>'hamper', 'price'=>3499, 'img'=>self::img('gm-c-h2'),
      'name'=>'Client Appreciation Hamper',
      'short'=>'Belgian chocolates, gourmet crackers, single-estate coffee and branded ribbon.',
      'desc'=>'Thank your most valued clients with this curated luxury hamper. Contains Belgian chocolate truffles, artisan cheese crackers, single-estate Coorg coffee, premium dry fruits, and a personalised ribbon-tied message card — all in a silk-lined box.' ],
    [ 'gift_type'=>'hamper', 'price'=>1999, 'img'=>self::img('gm-c-h3'),
      'name'=>'Festive Corporate Gift Hamper',
      'short'=>'Diwali-ready box with premium mithai, dry fruits, a brass diya and festive ribbon.',
      'desc'=>'Light up every festival for your team and clients. Contains premium kaju katli, assorted dry fruits, a hand-painted brass diya, artisan chocolates, and a personalised festive band — in a branded kraft hamper box with gold ribbon.' ],
    [ 'gift_type'=>'hamper', 'price'=>2999, 'img'=>self::img('gm-c-h4'),
      'name'=>'New Joiner Welcome Hamper',
      'short'=>'Branded tote, gourmet snacks, Darjeeling tea, scented candle and welcome note.',
      'desc'=>'Make every new hire feel valued from day one. This onboarding hamper includes a branded canvas tote, curated gourmet snacks, premium Darjeeling tea, a scented candle, and a personalised welcome note — perfect for remote and in-office teams.' ],
    [ 'gift_type'=>'hamper', 'price'=>4499, 'img'=>self::img('gm-c-h5'),
      'name'=>'Chairman\'s Selection Hamper',
      'short'=>'Ultra-premium whisky miniature, truffle salt, gold-foil chocolates and leather keepsake.',
      'desc'=>'Reserved for your most distinguished relationships. A single-malt whisky miniature, hand-harvested truffle salt, gold-foil Belgian chocolates, a premium soy candle in a crystal jar, and a leather keepsake card holder — in a black linen gift chest.' ],

    /* — Premium — */
    [ 'gift_type'=>'premium-gifts', 'price'=>4999, 'img'=>self::img('gm-c-p1'),
      'name'=>'Monogrammed Leather Desk Set',
      'short'=>'Full-grain leather desk mat, pen holder and card tray with laser-engraved monogram.',
      'desc'=>'A refined executive workspace statement. Full-grain leather desk mat, matching pen holder, and card tray — all laser-engraved with the recipient\'s monogram and company logo. Presented in a premium black gift box.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>6499, 'img'=>self::img('gm-c-p2'),
      'name'=>'Executive Gold Pen & Notebook Set',
      'short'=>'18-carat gold-finish rollerball pen and A5 hardbound notebook in a velvet gift box.',
      'desc'=>'The ultimate desk companion for senior leaders. An 18-carat gold-finish rollerball pen with refillable cartridge and an A5 full-grain leather hardbound notebook, presented in a velvet-lined gift box with a personalised engraved nameplate.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>5499, 'img'=>self::img('gm-c-p3'),
      'name'=>'Premium Leather Padfolio Set',
      'short'=>'Full-grain leather padfolio with A4 notepad, business card holder and metal pen.',
      'desc'=>'Impress clients in every boardroom. Full-grain genuine leather padfolio with a zip-close compartment, integrated A4 notepad, business card slots, and a matching stainless-steel pen — embossed with the recipient\'s initials.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>7999, 'img'=>self::img('gm-c-p4'),
      'name'=>'Smart Watch Corporate Gift Set',
      'short'=>'Premium smartwatch with fitness tracking in a luxury branded gift box.',
      'desc'=>'The ultimate performance reward. A premium smartwatch featuring fitness tracking, sleep monitoring, and notification management, presented in a branded luxury gift box with a personalised achievement card. Customise with your company logo.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>3999, 'img'=>self::img('gm-c-p5'),
      'name'=>'Crystal Paperweight Desk Trophy',
      'short'=>'Optically clear crystal globe laser-engraved with company logo and recipient name.',
      'desc'=>'A timeless desk statement that doubles as recognition. Optically clear lead-free crystal globe laser-engraved with the recipient\'s name, designation, and company crest — presented in a velvet-lined black gift box.' ],

    /* — Personalized — */
    [ 'gift_type'=>'personalized-gifts', 'price'=>1999, 'img'=>self::img('gm-c-per1'),
      'name'=>'Custom Crystal Trophy Set',
      'short'=>'Optically clear crystal trophy laser-engraved with name, designation and logo.',
      'desc'=>'Make every recognition moment memorable. Lead-free crystal trophy with deep laser engraving — name, designation, achievement, and company logo. Arrives in a velvet-lined gift box.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>2499, 'img'=>self::img('gm-c-per2'),
      'name'=>'Personalised Brass Nameplate',
      'short'=>'Solid brass desk nameplate laser-engraved with name, designation and company logo.',
      'desc'=>'A classic, permanent desk statement. Solid polished brass nameplate (20×7 cm) with deep laser engraving of the employee\'s name, designation, and company logo — on a solid rosewood base. A gift that stays on the desk for years.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>1499, 'img'=>self::img('gm-c-per3'),
      'name'=>'Engraved Achievement Plaque',
      'short'=>'Sheesham wood plaque with polished brass plate, laser-engraved with name and award.',
      'desc'=>'Make recognition permanent. This solid sheesham wood plaque with a polished brass accent plate is laser-engraved with the employee\'s name, award category, and year. Includes a wall-mounting kit and velvet-lined gift box.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>3499, 'img'=>self::img('gm-c-per4'),
      'name'=>'Custom Leather Business Card Holder',
      'short'=>'Full-grain leather card holder engraved with recipient\'s name and company logo.',
      'desc'=>'A sophisticated everyday carry that reflects corporate excellence. Full-grain leather card holder with magnetic clasp, accordion pockets, and laser-engraved initials — presented in a branded black gift box with tissue wrap.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>1299, 'img'=>self::img('gm-c-per5'),
      'name'=>'Personalised Corporate Mug Set',
      'short'=>'Premium ceramic mug printed with employee name, years of service and company logo.',
      'desc'=>'A warm daily reminder of appreciation. Premium ceramic mug (350ml) with full-colour logo and name print, paired with a branded coaster and a curated pack of premium teas — presented in a personalised kraft gift box.' ],

    /* — Eco-Friendly — */
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>1499, 'img'=>self::img('gm-c-e1'),
      'name'=>'Bamboo Corporate Stationery Kit',
      'short'=>'FSC-certified bamboo pen, recycled notebook, seed notecard and cork coaster.',
      'desc'=>'Zero plastic, full impact. FSC-certified bamboo ballpen, 100% recycled-paper A5 notebook, a plantable seed-paper thank-you card, and a natural cork coaster — packaged in recycled kraft. 100% biodegradable.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>999, 'img'=>self::img('gm-c-e2'),
      'name'=>'Seed Paper Corporate Kit',
      'short'=>'Plantable seed-paper notepad, bamboo pen and cork coaster in a recycled kraft box.',
      'desc'=>'Business gifting that gives back to the earth. A seed-paper spiral notepad (plant after use), a FSC-certified bamboo ballpen, and a natural cork coaster set — all packaged in a recycled kraft gift box with soy-ink printing.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>2499, 'img'=>self::img('gm-c-e3'),
      'name'=>'Organic Wellness Corporate Box',
      'short'=>'Certified-organic teas, beeswax candle, bamboo cup and eco-friendly tote.',
      'desc'=>'Wellness and sustainability in one gift. Six certified-organic herbal teas, a hand-poured beeswax candle, a reusable bamboo travel cup, and a jute tote bag — all packaged in a recycled cardboard gift box with zero plastic.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>1799, 'img'=>self::img('gm-c-e4'),
      'name'=>'Recycled Cork & Bamboo Desk Set',
      'short'=>'Recycled cork desk pad, bamboo pen holder and bamboo stationery organiser.',
      'desc'=>'A beautifully sustainable workspace upgrade. Recycled cork desk pad (40×30 cm), FSC-certified bamboo pen holder, and a bamboo stationery organiser — all made with zero virgin plastic. Ships in a recycled corrugated box.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>1299, 'img'=>self::img('gm-c-e5'),
      'name'=>'Eco Gratitude Gift Set',
      'short'=>'Seed packet, plantable card, beeswax wrap, herbal tea and organic chocolate.',
      'desc'=>'A gift that grows — literally. A wildflower seed packet, handwritten plantable thank-you card, beeswax food wrap, a curated herbal tea sachet, and a square of single-origin organic dark chocolate. All packaging is compostable.' ],

    /* — Handcrafted — */
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>2999, 'img'=>self::img('gm-c-cr1'),
      'name'=>'Hand-Hammered Brass Desk Organiser',
      'short'=>'Artisan-crafted solid brass desk organiser with hand-hammered finish.',
      'desc'=>'Crafted by master metalworkers in Moradabad, this solid brass desk organiser features a hand-hammered texture with three compartments for pens, cards, and notes. Presented in a handmade jute-wrapped box.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>3499, 'img'=>self::img('gm-c-cr2'),
      'name'=>'Hand-Stitched Leather Journal',
      'short'=>'Saddle-stitched full-grain leather journal with handmade cotton paper.',
      'desc'=>'A handcrafted journal that improves with age. Full-grain vegetable-tanned leather cover, hand-saddle-stitched by artisans in Kanpur, with 240 pages of handmade cotton paper. Brass clasp and matching leather bookmark.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>2499, 'img'=>self::img('gm-c-cr3'),
      'name'=>'Hand-Thrown Ceramic Coffee Set',
      'short'=>'Wheel-thrown ceramic coffee mug and saucer set hand-glazed by Khurja artisans.',
      'desc'=>'No two pieces are identical. This coffee mug and saucer set is wheel-thrown and hand-glazed by pottery artisans from Khurja, UP, in a rich corporate navy glaze. Perfect for the corner-office coffee ritual.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>4499, 'img'=>self::img('gm-c-cr4'),
      'name'=>'Handwoven Silk Tie Gift Box',
      'short'=>'Pure Banarasi silk tie hand-woven by Varanasi weavers, in a premium gift box.',
      'desc'=>'A timeless executive accessory from India\'s most celebrated weavers. Pure Banarasi silk tie with a 8 cm blade, hand-woven on a traditional loom by artisans in Varanasi — in a choice of three classic patterns. Boxed in premium silk-lined packaging.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>1999, 'img'=>self::img('gm-c-cr5'),
      'name'=>'Hand-Painted Madhubani Coaster Set',
      'short'=>'Set of 6 hand-painted Madhubani art coasters on natural wood base.',
      'desc'=>'Add a cultural touch to the executive desk. Six natural wood coasters hand-painted by certified Madhubani artisans from Mithila, Bihar, in traditional motifs. Presented in a handmade mango-wood box. No two sets are identical.' ],

    /* — Local Artisan — */
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>1799, 'img'=>self::img('gm-c-a1'),
      'name'=>'Madhubani Art Corporate Frame',
      'short'=>'Original hand-painted Madhubani artwork on handmade cotton paper, natural wood frame.',
      'desc'=>'Each frame features an original hand-painted Madhubani artwork by certified artisans from Mithila, Bihar, mounted on handmade cotton paper and framed in natural mango wood. No two pieces are identical.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>2499, 'img'=>self::img('gm-c-a2'),
      'name'=>'Pattachitra Corporate Gift Panel',
      'short'=>'Traditional Pattachitra hand-painted panel from Odisha artisans in a carved frame.',
      'desc'=>'A living piece of Odisha\'s 12th-century painting tradition. Hand-painted Pattachitra panel on treated palm leaf, depicting the Dashavatara (ten avatars of Vishnu), framed in hand-carved sheesham wood.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>1499, 'img'=>self::img('gm-c-a3'),
      'name'=>'Kalamkari Art Office Panel',
      'short'=>'Hand-drawn Kalamkari artwork on natural cotton fabric in a bamboo frame.',
      'desc'=>'Kalamkari — the ancient art of drawing with a kalam (pen). This panel is hand-drawn with vegetable dyes on natural cotton fabric by artisans from Srikalahasti, Andhra Pradesh, and framed in FSC-certified bamboo.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>3299, 'img'=>self::img('gm-c-a4'),
      'name'=>'Bidriware Pen Stand',
      'short'=>'Hand-inlaid Bidriware desk pen stand from Bidar artisans — silver on black alloy.',
      'desc'=>'A 500-year-old craft on the executive desk. This Bidriware pen stand is hand-crafted by artisans from Bidar, Karnataka, with pure silver wire inlaid into oxidised zinc-alloy in traditional floral patterns. Comes with a certificate of craft origin.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>1299, 'img'=>self::img('gm-c-a5'),
      'name'=>'Blue Pottery Corporate Mug Set',
      'short'=>'Set of 2 hand-painted blue pottery mugs from Jaipur artisans in a gift box.',
      'desc'=>'Jaipur\'s signature blue pottery in a corporate gift set. Two hand-painted blue pottery mugs by artisans from Jaipur, Rajasthan, in classic floral motifs — packaged in a kraft box lined with hand-block-printed fabric.' ],
], /* end corporate */

/* ════════════════════════════════════════════════════════════════════
   SCHOOLS & EDUCATION
   ═══════════════════════════════════════════════════════════════════ */
'school' => [

    /* — Hamper — */
    [ 'gift_type'=>'hamper', 'price'=>999, 'img'=>self::img('gm-s-h1'),
      'name'=>'Student Achievement Hamper',
      'short'=>'Stationery essentials, healthy snacks, inspirational book and congratulations card.',
      'desc'=>'A thoughtful celebration of student success. Premium stationery essentials, healthy trail-mix snacks, a short inspirational book, and a personalised congratulations card from the school — all in a bright ribbon-tied gift box.' ],
    [ 'gift_type'=>'hamper', 'price'=>1299, 'img'=>self::img('gm-s-h2'),
      'name'=>'Annual Day Merit Hamper',
      'short'=>'Premium notepad set, colour pens, healthy snacks and a merit ribbon.',
      'desc'=>'Make annual day unforgettable. Premium stationery set (notepad, ruler, set squares), a pack of 12 colour pens, healthy trail-mix snacks, and a personalised merit ribbon — packaged in a school-branded gift box with tissue.' ],
    [ 'gift_type'=>'hamper', 'price'=>1799, 'img'=>self::img('gm-s-h3'),
      'name'=>'Teacher Appreciation Hamper',
      'short'=>'Darjeeling tea, Belgian chocolates, a scented candle and a personalised card.',
      'desc'=>'Show educators they are truly valued. Premium Darjeeling first-flush tea, Belgian chocolate truffles, a jasmine-scented soy candle, and a personalised appreciation note from students and management — in a silk-ribbon-tied box.' ],
    [ 'gift_type'=>'hamper', 'price'=>699, 'img'=>self::img('gm-s-h4'),
      'name'=>'Graduation Celebration Hamper',
      'short'=>'Mini hamper with artisan chocolates, a journal, gold pen and a graduation card.',
      'desc'=>'Launch your graduates in style. Contains artisan chocolates, a hardbound journal, a gold-finish pen, premium snacks, and a personalised graduation card — in a ribbon-tied kraft gift box with graduation motif print.' ],
    [ 'gift_type'=>'hamper', 'price'=>2499, 'img'=>self::img('gm-s-h5'),
      'name'=>'Scholar\'s Excellence Hamper',
      'short'=>'Premium stationery, book voucher, healthy snacks and a gold-embossed certificate.',
      'desc'=>'The definitive academic achievement gift. Premium pen set, leather-finish A5 notebook, a bookstore gift voucher, a pack of healthy snacks, and a gold-embossed personalised achievement certificate — in a presentation box.' ],

    /* — Premium — */
    [ 'gift_type'=>'premium-gifts', 'price'=>2499, 'img'=>self::img('gm-s-p1'),
      'name'=>'Premium Scholar Award Set',
      'short'=>'Optical crystal award plaque, velvet box and leather-finish certificate holder.',
      'desc'=>'The definitive recognition for academic achievement. Optical crystal plaque with deep laser engraving (name, achievement, year) plus a velvet-lined box and leather-finish certificate holder.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>3499, 'img'=>self::img('gm-s-p2'),
      'name'=>'Gold-Finish Academic Trophy',
      'short'=>'Die-cast zinc-alloy trophy with gold plating and personalised nameplate.',
      'desc'=>'A prestigious trophy worthy of every podium moment. Die-cast zinc-alloy trophy with a polished gold-plate finish, personalised brass nameplate, and a velvet-lined gift box — available in three sizes for tiered award ceremonies.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>1999, 'img'=>self::img('gm-s-p3'),
      'name'=>'Principal\'s Choice Gift Set',
      'short'=>'Premium leather agenda, gold pen and personalised school-crest paperweight.',
      'desc'=>'A distinguished gift for a distinguished educator. Full-grain leather desktop agenda, gold-finish rollerball pen, and a crystal paperweight laser-engraved with the school\'s crest — all in a brushed black luxury gift box.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>4499, 'img'=>self::img('gm-s-p4'),
      'name'=>'Champion\'s Medal Set',
      'short'=>'Set of 10 premium brass medals (gold/silver/bronze) with full-colour ribbons.',
      'desc'=>'Honour every position of merit. A set of 10 premium brass medals — 3 gold, 3 silver, 4 bronze — with full-colour printed ribbons. Each medal is 5 cm in diameter and can be individually engraved on the reverse.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>2999, 'img'=>self::img('gm-s-p5'),
      'name'=>'Graduation Day Luxury Set',
      'short'=>'Crystal keepsake, leather certificate folder and a personalised school memento.',
      'desc'=>'Make graduation day legendary. A crystal house-shaped keepsake laser-engraved with the student\'s name and year, matched with a premium leather certificate folder and a personalised message from the principal.' ],

    /* — Personalized — */
    [ 'gift_type'=>'personalized-gifts', 'price'=>1499, 'img'=>self::img('gm-s-per1'),
      'name'=>'Engraved Merit Shield',
      'short'=>'Solid sheesham wood shield with laser-engraved student name, achievement and school crest.',
      'desc'=>'Display-worthy and meaningful. Solid sheesham wood shield with laser-engraved personalisation — student name, achievement category, academic year, and school crest. Includes wall-mounting kit.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>999, 'img'=>self::img('gm-s-per2'),
      'name'=>'Personalised Academic Excellence Medal',
      'short'=>'Gold-finish brass medal engraved with student name and subject on velvet ribbon.',
      'desc'=>'A medal that students will treasure for years. Cast in solid brass with a gold finish, laser-engraved with the student\'s name and subject on the reverse — presented in a velvet pouch with a personalised congratulations note.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>1999, 'img'=>self::img('gm-s-per3'),
      'name'=>'Custom Graduation Photo Frame',
      'short'=>'Dual-compartment frame for graduation photo and certificate, engraved with name and year.',
      'desc'=>'Display the graduation milestone forever. Premium dual-compartment frame (holds A4 certificate + 8×10 photo) with laser-engraved name and graduation year on a matte black frame. Wall-mountable with included kit.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>799, 'img'=>self::img('gm-s-per4'),
      'name'=>'Personalised School Yearbook Mug',
      'short'=>'Premium ceramic mug printed with student name, year and school crest.',
      'desc'=>'A daily keepsake of school memories. Premium ceramic mug (350ml) with full-colour print — student name, graduation year, and school crest on one side, and a motivational quote on the other. Presented in a personalised kraft gift box.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>2499, 'img'=>self::img('gm-s-per5'),
      'name'=>'Engraved Service Plaque for Teachers',
      'short'=>'Wood-and-metal plaque engraved with teacher name, years of service and school crest.',
      'desc'=>'Recognise the loyalty of long-serving educators. This premium sheesham wood plaque with polished brass accent plate is laser-engraved with the teacher\'s name, service years ("10 Years of Dedicated Service"), and the school\'s crest.' ],

    /* — Eco-Friendly — */
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>799, 'img'=>self::img('gm-s-e1'),
      'name'=>'Seed Paper Award Kit',
      'short'=>'Award certificate on plantable seed paper with a mini terracotta pot and organic soil.',
      'desc'=>'Achievements that bloom. Award certificate printed on 100% plantable seed paper embedded with wildflower seeds. Comes with a hand-painted mini terracotta pot and organic potting soil. Zero plastic, entirely biodegradable.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>599, 'img'=>self::img('gm-s-e2'),
      'name'=>'Eco Scholar Kit',
      'short'=>'Recycled notebook, bamboo pencil set and a plantable bookmark.',
      'desc'=>'A school kit that teaches sustainability by example. 100% recycled-paper spiral notebook (A5), a set of 6 FSC-certified bamboo pencils, and a wildflower seed-paper bookmark — all in a recycled kraft pouch.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>1299, 'img'=>self::img('gm-s-e3'),
      'name'=>'Green Campus Gift Box',
      'short'=>'Jute pencil case, seed paper notepad, bamboo ruler and organic colour pencils.',
      'desc'=>'Inspire the next generation of eco-citizens. Handwoven jute pencil case, seed-paper notepad, FSC-certified bamboo ruler, and a set of water-based colour pencils in recycled cardboard — packaged in a compostable gift box.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>1799, 'img'=>self::img('gm-s-e4'),
      'name'=>'Teacher Wellness Eco Box',
      'short'=>'Organic herbal teas, beeswax candle, seed paper card and a bamboo tumbler.',
      'desc'=>'A teacher\'s self-care gift that aligns with their values. Six certified-organic herbal teas, a hand-poured beeswax pillar candle, a wildflower seed-paper gratitude card, and a leak-proof bamboo travel tumbler — in a recycled kraft box.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>499, 'img'=>self::img('gm-s-e5'),
      'name'=>'Mini Nature Grow Kit',
      'short'=>'Hand-painted terracotta pot, organic cress seeds and a care card.',
      'desc'=>'Watch your achievement grow! A hand-painted mini terracotta pot by Nizamabad artisans, organic cress seeds that sprout in 5 days, a pebble layer, and a printed care card — the perfect eco gift for young students.' ],

    /* — Handcrafted — */
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>1299, 'img'=>self::img('gm-s-cr1'),
      'name'=>'Hand-Painted Ceramic Merit Trophy',
      'short'=>'Wheel-thrown ceramic trophy hand-painted with motifs of growth and achievement.',
      'desc'=>'Each trophy is thrown on a potter\'s wheel and hand-painted by artisans from Khurja, UP. The student\'s name is hand-inscribed on the base. Every trophy is one of a kind — a true collectors\' piece.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>999, 'img'=>self::img('gm-s-cr2'),
      'name'=>'Hand-Stitched Student Portfolio',
      'short'=>'Hand-stitched natural canvas portfolio with custom name embroidery.',
      'desc'=>'A portfolio that grows with the student. Sturdy natural canvas art portfolio (A3) with hand-stitched edge binding and the student\'s name hand-embroidered on the front by artisans from Kutch, Gujarat.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>1799, 'img'=>self::img('gm-s-cr3'),
      'name'=>'Handloom Teacher\'s Tote',
      'short'=>'Pure khadi handloom tote bag with hand-block-printed inspirational quote.',
      'desc'=>'A meaningful daily companion for educators. Pure khadi handloom natural tote bag hand-block-printed by artisans from Ajrakhpur, Gujarat, with the quote "A good teacher is like a candle — it consumes itself to light the way for others."' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>2499, 'img'=>self::img('gm-s-cr4'),
      'name'=>'Hand-Crafted Brass Bell Trophy',
      'short'=>'Hand-cast solid brass school bell trophy engraved with winner name and year.',
      'desc'=>'Inspired by the school bell — the call to knowledge. This solid brass school-bell-shaped trophy is hand-cast by artisans in Moradabad with a personalised engraved nameplate at the base. Presented in a velvet-lined box.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>699, 'img'=>self::img('gm-s-cr5'),
      'name'=>'Hand-Printed Fabric Pencil Pouch',
      'short'=>'Block-printed natural cotton pencil pouch with zip and personalised name tag.',
      'desc'=>'A handcrafted everyday essential for students. Natural cotton pencil pouch with hand-block printing in Bagru vegetable dyes, a brass zip fastener, and a personalised leather name tag hand-stitched to the strap.' ],

    /* — Local Artisan — */
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>899, 'img'=>self::img('gm-s-a1'),
      'name'=>'Warli Art Inspiration Frame',
      'short'=>'Hand-painted Warli tribal art on textured canvas depicting children learning.',
      'desc'=>'A beautiful classroom addition. Original hand-painted Warli tribal artwork by artisans from Maharashtra, depicting children gathering under a banyan tree — a symbol of knowledge. Mounted on textured canvas in a natural wood frame.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>1299, 'img'=>self::img('gm-s-a2'),
      'name'=>'Gond Art Butterfly Panel',
      'short'=>'Original Gond tribal painting of a tree of life on handmade canvas.',
      'desc'=>'Gond art from the forests of Madhya Pradesh. Each panel is an original painting by Gond tribal artists from Dindori, MP, depicting the "Tree of Life" — a symbol of growth and knowledge — in vibrant natural pigments on handmade canvas.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>799, 'img'=>self::img('gm-s-a3'),
      'name'=>'Dokra Owl Figurine',
      'short'=>'Lost-wax cast Dhokra brass owl figurine from Bastar artisans — symbol of knowledge.',
      'desc'=>'The owl — ancient symbol of wisdom and knowledge. This Dhokra brass owl figurine is individually cast by tribal artisans from Bastar, Chhattisgarh, using the 4,000-year-old lost-wax casting technique. Comes with authenticity card.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>1799, 'img'=>self::img('gm-s-a4'),
      'name'=>'Phulkari Embroidered Gift Bag',
      'short'=>'Hand-embroidered Punjab Phulkari fabric gift bag with silk thread detailing.',
      'desc'=>'A gift in itself. This gift bag is crafted from hand-embroidered Phulkari fabric by artisans from Patiala, Punjab, featuring the traditional "flowers" motif in vivid silk thread colours. Reusable and collectible.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>599, 'img'=>self::img('gm-s-a5'),
      'name'=>'Terracotta Learning Figurine Set',
      'short'=>'Set of 3 hand-painted terracotta figurines depicting student, teacher and school.',
      'desc'=>'A charming classroom keepsake. Three hand-painted terracotta figurines — a student, a teacher, and a school building — crafted by potters from Kutch, Gujarat. Each figure is unique and depicts rural Indian school life in vivid natural pigments.' ],
], /* end school */

/* ════════════════════════════════════════════════════════════════════
   WEDDINGS & EVENTS
   ═══════════════════════════════════════════════════════════════════ */
'wedding' => [

    /* — Hamper — */
    [ 'gift_type'=>'hamper', 'price'=>1999, 'img'=>self::img('gm-w-h1'),
      'name'=>'Bridal Welcome Hamper',
      'short'=>'Rose water, herbal tea, premium dates, saffron and a hand-embroidered keepsake pouch.',
      'desc'=>'A luxurious welcome for the bridal party. Bulgarian rose water, wellness herbal teas, premium Medjool dates, pure Kashmiri saffron, and a hand-embroidered silk keepsake pouch — all in a blush-pink gift box.' ],
    [ 'gift_type'=>'hamper', 'price'=>999, 'img'=>self::img('gm-w-h2'),
      'name'=>'Sweet Return Gift Box',
      'short'=>'250g premium Indian mithai, a scented tea-light, and a personalised wedding tag.',
      'desc'=>'A sweet farewell for every wedding guest. 250g of premium Indian mithai (kaju katli, ladoo, peda) in a custom-printed box with the couple\'s names and date, a soy wax tea-light candle, and a personalised gold-foil gift tag.' ],
    [ 'gift_type'=>'hamper', 'price'=>2999, 'img'=>self::img('gm-w-h3'),
      'name'=>'Honeymoon Essentials Hamper',
      'short'=>'Travel skincare kit, premium snacks, a couple\'s journal and a Polaroid surprise.',
      'desc'=>'Send them off in style. This honeymoon hamper contains a couple\'s travel skincare kit, premium snack packs, a matching journal set, a Polaroid camera with film, and a sealed "open on honeymoon" surprise envelope — in a silk-ribbon box.' ],
    [ 'gift_type'=>'hamper', 'price'=>3999, 'img'=>self::img('gm-w-h4'),
      'name'=>'Heritage Wedding Blessing Hamper',
      'short'=>'Silver diyas, saffron, dry fruits, puja items and a gold-embossed blessings card.',
      'desc'=>'Begin the new journey with tradition. A pair of silver-plated diyas, pure Kashmiri saffron, 200g premium dry fruits, a puja thali set, and a gold-embossed blessings card — packaged in a red-and-gold gift box.' ],
    [ 'gift_type'=>'hamper', 'price'=>1499, 'img'=>self::img('gm-w-h5'),
      'name'=>'VIP Guest Welcome Hamper',
      'short'=>'Luxury welcome box with Belgian chocolates, travel perfume, silk scarf and welcome note.',
      'desc'=>'Roll out the red carpet for your most special guests. A luxury black gift box containing Belgian chocolate truffles, a travel-size luxury perfume, a hand-painted silk stole, and a handwritten personal note from the bride and groom.' ],

    /* — Premium — */
    [ 'gift_type'=>'premium-gifts', 'price'=>3999, 'img'=>self::img('gm-w-p1'),
      'name'=>'24K Gold-Dipped Rose Keepsake',
      'short'=>'A real rose preserved with 24K gold, displayed in a glass dome on velvet base.',
      'desc'=>'A forever gift for a once-in-a-lifetime occasion. A real rose preserved through 24K gold electroforming, displayed under a hand-blown glass dome on a velvet base. Laser-engraved with the couple\'s names and wedding date.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>5999, 'img'=>self::img('gm-w-p2'),
      'name'=>'Couple\'s Luxury Spa Hamper',
      'short'=>'Champagne, Belgian chocolates, couples\' spa kit and a personalised keepsake card.',
      'desc'=>'The ultimate wedding gift for the happy couple. Premium sparkling wine, Belgian chocolate truffles, a couples\' spa kit (massage oil, bath salts, face masks), and a personalised keepsake card — all in a silk-lined hamper box.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>7999, 'img'=>self::img('gm-w-p3'),
      'name'=>'Precious Metal Photo Frame',
      'short'=>'Silver-plated 8×10 photo frame with vine-and-flower motif and couple name engraving.',
      'desc'=>'A classic keepsake that will last a lifetime. Hand-crafted silver-plated photo frame with a vine-and-flower motif, laser-engraved with the couple\'s names and wedding date, for an 8×10 photograph. Presented in a velvet-lined luxury gift box.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>4499, 'img'=>self::img('gm-w-p4'),
      'name'=>'Premium Silk Saree Gift Set',
      'short'=>'Pure Banarasi silk saree with matching blouse piece, in a luxury gift box.',
      'desc'=>'The most treasured wedding gift in India. A pure Banarasi silk saree with intricate zari work and a matching blouse piece, hand-woven by master weavers in Varanasi — presented in a luxurious silk-lined keepsake box.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>3499, 'img'=>self::img('gm-w-p5'),
      'name'=>'Crystal Couple\'s Wine Set',
      'short'=>'Two hand-blown crystal wine glasses engraved with couple\'s names in a velvet box.',
      'desc'=>'Toast to forever. Two hand-blown lead-free crystal wine glasses, laser-engraved with the couple\'s names and wedding date, presented in a velvet-lined gift box with a small bottle of premium sparkling fruit wine.' ],

    /* — Personalized — */
    [ 'gift_type'=>'personalized-gifts', 'price'=>1499, 'img'=>self::img('gm-w-per1'),
      'name'=>'Engraved Couple Photo Frame',
      'short'=>'Double-sided acrylic frame laser-engraved with names and wedding date for two 4×6 photos.',
      'desc'=>'A personalised keepsake that guests will display for years. Double-sided acrylic frame with laser-engraved names and wedding date. Presented in a velvet pouch with a note from the couple.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>2499, 'img'=>self::img('gm-w-per2'),
      'name'=>'Custom Wooden Serving Board',
      'short'=>'Solid teak serving board laser-engraved with couple\'s names, wedding date and motif.',
      'desc'=>'A personalised gift for a couple\'s first kitchen. Solid teak cheese and charcuterie board laser-engraved with the couple\'s names, wedding date, and a floral motif — with a matching groove for crackers and a cheese knife set.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>1999, 'img'=>self::img('gm-w-per3'),
      'name'=>'Personalised Wedding Keepsake Box',
      'short'=>'Engraved mango-wood keepsake box for wedding mementos, with lock and key.',
      'desc'=>'A treasure chest for wedding memories. Handcrafted mango-wood keepsake box, laser-engraved with the couple\'s names and wedding date on the lid, with a brass lock and key — spacious enough for cards, rings, and small mementos.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>999, 'img'=>self::img('gm-w-per4'),
      'name'=>'Custom Wedding Candle Set',
      'short'=>'Set of two pillar candles with personalised labels — couple\'s names and wedding date.',
      'desc'=>'Light up the celebration. Two hand-poured soy-wax pillar candles in soft rose and ivory, each with a custom-printed label bearing the couple\'s names and wedding date — presented in a sheer organza bag tied with satin ribbon.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>3499, 'img'=>self::img('gm-w-per5'),
      'name'=>'Personalised Family Portrait Sketch',
      'short'=>'Custom hand-drawn charcoal portrait of the couple from a reference photo, in a wood frame.',
      'desc'=>'A one-of-a-kind wedding gift. A custom hand-drawn charcoal portrait of the couple by a certified portrait artist from Kolkata — based on the reference photo provided — framed in a natural wood frame with a personalised plaque.' ],

    /* — Eco-Friendly — */
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>699, 'img'=>self::img('gm-w-e1'),
      'name'=>'Plantable Seed Return Gift Kit',
      'short'=>'Handmade seed-paper card with wildflower seeds, a terracotta pot and organic soil.',
      'desc'=>'Give guests a gift that blooms. Handmade seed-paper card embedded with wildflower seeds, a hand-painted mini terracotta pot, and organic potting soil. Zero plastic, completely biodegradable. The card itself becomes a flower.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>499, 'img'=>self::img('gm-w-e2'),
      'name'=>'Eco Wedding Favour Bag',
      'short'=>'Organic cotton muslin bag with lavender sachets, seed paper and a recycled ribbon.',
      'desc'=>'A sustainable wedding favour guests will actually keep. Natural muslin drawstring bag filled with two dried lavender sachets, a wildflower seed heart, and a handwritten thank-you card on seed paper — tied with a recycled cotton ribbon.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>1299, 'img'=>self::img('gm-w-e3'),
      'name'=>'Green Wedding Welcome Kit',
      'short'=>'Bamboo water bottle, organic soap, seed paper programme and beeswax candle.',
      'desc'=>'Welcome guests sustainably. A reusable bamboo water bottle, a bar of organic handmade soap, the wedding programme printed on seed paper (plant it after!), and a beeswax tea-light candle — all in a natural jute tote.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>999, 'img'=>self::img('gm-w-e4'),
      'name'=>'Upcycled Sari Potli Return Gift',
      'short'=>'Hand-stitched potli bag from upcycled vintage sari fabric filled with dry rose petals.',
      'desc'=>'Zero waste, full beauty. Each potli bag is hand-stitched by artisans from upcycled vintage sari fabric from Gujarat, filled with fragrant dried rose petals and a personalised thank-you note printed on seed paper.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>1799, 'img'=>self::img('gm-w-e5'),
      'name'=>'Eco Couple\'s Home Starter Kit',
      'short'=>'Beeswax candles, bamboo utensils, organic coffee and a herb grow kit.',
      'desc'=>'Start the new home sustainably. Two hand-poured beeswax pillar candles, FSC-certified bamboo kitchen utensils, single-origin organic coffee, and an organic herb grow kit with three seed sachets (basil, coriander, mint) — all in a recycled gift box.' ],

    /* — Handcrafted — */
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>999, 'img'=>self::img('gm-w-cr1'),
      'name'=>'Hand-Block Printed Potli Gift Set',
      'short'=>'Block-printed cotton potli bags filled with fragrant dry flowers and artisan chocolates.',
      'desc'=>'Traditional, reusable, and beautiful. Each potli bag is hand-block-printed by artisans from Bagru, Rajasthan, in vegetable dyes. Filled with fragrant dried rose petals and a mini box of artisan dark chocolates.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>2499, 'img'=>self::img('gm-w-cr2'),
      'name'=>'Hand-Embroidered Silk Keepsake Box',
      'short'=>'Hand-embroidered silk-covered jewellery box with brass fittings from Lucknow artisans.',
      'desc'=>'A keepsake to treasure for generations. This jewellery box is covered in hand-embroidered Chikankari silk fabric by artisans from Lucknow, UP, with brass hinges and a mirror inside. The embroidery depicts traditional wedding motifs.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>1499, 'img'=>self::img('gm-w-cr3'),
      'name'=>'Handwoven Guest Favour Basket',
      'short'=>'Handwoven natural grass basket filled with mithai, petals and a personalised note.',
      'desc'=>'Artisan baskets for every wedding table. Hand-woven natural grass baskets by artisans from Assam, filled with two pieces of premium mithai, dried rose petals, and a personalised thank-you note from the couple — tied with a silk ribbon.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>3499, 'img'=>self::img('gm-w-cr4'),
      'name'=>'Hand-Hammered Copper Pitcher Set',
      'short'=>'Artisan hand-hammered copper pitcher and two tumblers from Moradabad metalworkers.',
      'desc'=>'A wedding gift that enriches every home. Hand-hammered pure copper pitcher (1.5L) and two matching tumblers, crafted by master metalworkers in Moradabad — the city famous for brassware — presented in a handmade jute-wrapped box.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>799, 'img'=>self::img('gm-w-cr5'),
      'name'=>'Handmade Macramé Couple Frame',
      'short'=>'Hand-knotted natural cotton macramé photo frame for a 4×6 wedding photo.',
      'desc'=>'Boho-beautiful and handmade with love. Natural cotton macramé photo frame hand-knotted by artisans from Jaipur, for a 4×6 photograph. Each frame features a unique knotting pattern with a natural wood dowel and a jute hanging loop.' ],

    /* — Local Artisan — */
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>1799, 'img'=>self::img('gm-w-a1'),
      'name'=>'Dhokra Art Home Décor Piece',
      'short'=>'Lost-wax cast Dhokra brass dancing peacock figurine from Chhattisgarh artisans.',
      'desc'=>'Rooted in 4,000 years of Indian craft tradition. Each Dhokra figurine is individually cast by tribal artisans from Bastar, Chhattisgarh. A pair of dancing peacocks symbolising love — comes with a certificate of artisan authenticity.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>2499, 'img'=>self::img('gm-w-a2'),
      'name'=>'Pichwai Painting Wedding Gift',
      'short'=>'Hand-painted Pichwai of Radha-Krishna on canvas from Nathdwara artisans, in carved frame.',
      'desc'=>'A timeless wedding blessing. Hand-painted Pichwai artwork by artisans from Nathdwara, Rajasthan, depicting Radha and Krishna — the eternal symbol of divine love — in natural stone pigments on canvas, framed in hand-carved sheesham wood.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>1299, 'img'=>self::img('gm-w-a3'),
      'name'=>'Lac Bangle Gift Box',
      'short'=>'Set of 6 hand-crafted lac bangles with mirror work from Jaipur artisans.',
      'desc'=>'A gift rooted in Indian bridal tradition. A set of 6 hand-crafted lac bangles with traditional mirror and thread work by artisans from Jaipur, Rajasthan — in bridal reds and golds — presented in a silk-lined keepsake box.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>3299, 'img'=>self::img('gm-w-a4'),
      'name'=>'Kashmiri Papier-Mâché Décor Set',
      'short'=>'Hand-painted Kashmiri papier-mâché vase and decorative box set from Srinagar artisans.',
      'desc'=>'The jewel of Kashmir\'s craft heritage. Hand-painted papier-mâché vase (20 cm) and decorative box by artisans from Srinagar, in traditional chinar leaf and lotus motifs in pure gold and lacquer colours — each piece takes two days to paint.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>999, 'img'=>self::img('gm-w-a5'),
      'name'=>'Madhubani Wedding Card Frame',
      'short'=>'Original Madhubani painting of wedding scene on handmade cotton paper, mango wood frame.',
      'desc'=>'Preserve the wedding memory in art. Original hand-painted Madhubani artwork by artisans from Madhubani, Bihar, depicting a traditional Indian wedding procession — on handmade cotton paper framed in natural mango wood. Certificate of origin included.' ],
], /* end wedding */

/* ════════════════════════════════════════════════════════════════════
   HEALTHCARE
   ═══════════════════════════════════════════════════════════════════ */
'hospitals' => [

    /* — Hamper — */
    [ 'gift_type'=>'hamper', 'price'=>1799, 'img'=>self::img('gm-h-h1'),
      'name'=>'Wellness & Recovery Hamper',
      'short'=>'Organic herbal teas, lavender stress-ball, immunity supplement kit and wellness card.',
      'desc'=>'A thoughtful care package for patients and healthcare staff. Six certified-organic herbal teas, a lavender stress-relief squeeze ball, an immunity-boost supplement kit, and a personalised wellness card — in a natural cotton tote.' ],
    [ 'gift_type'=>'hamper', 'price'=>1299, 'img'=>self::img('gm-h-h2'),
      'name'=>'Doctor\'s Day Indulgence Hamper',
      'short'=>'Single-origin coffee, artisan dark chocolate, trail mix and a personalised card.',
      'desc'=>'A warm thank-you for the doctors who give everything. Single-origin ground coffee, artisan dark chocolate, premium trail-mix snacks, a hand-massager stress ball, and a personalised appreciation card from the hospital management.' ],
    [ 'gift_type'=>'hamper', 'price'=>999, 'img'=>self::img('gm-h-h3'),
      'name'=>'Patient Welcome Kit',
      'short'=>'Herbal chamomile tea, travel toiletries, notepad and a comforting welcome card.',
      'desc'=>'Make patients feel cared for from their first day. Travel-size toiletries, a herbal chamomile tea sachet, a small notepad and pen, and a comforting welcome card from the hospital — in a natural cotton tote with the hospital logo.' ],
    [ 'gift_type'=>'hamper', 'price'=>2499, 'img'=>self::img('gm-h-h4'),
      'name'=>'Staff Nurse Appreciation Hamper',
      'short'=>'Premium compression socks, herbal teas, a scented candle and a gratitude note.',
      'desc'=>'Because nurses carry the heart of every hospital. Premium compression socks (ideal for long shifts), six certified-organic herbal teas, an aromatherapy soy candle, and a personalised gratitude note from hospital management — in a kraft hamper box.' ],
    [ 'gift_type'=>'hamper', 'price'=>3499, 'img'=>self::img('gm-h-h5'),
      'name'=>'New Mother Care Hamper',
      'short'=>'Organic postnatal supplements, coconut oil, muslin wrap and baby footprint kit.',
      'desc'=>'Welcome a new mother with warmth and care. Certified-organic postnatal supplements, organic coconut oil, a handmade muslin baby wrap, a baby footprint impression kit, and a congratulations card from the maternity team — in a gift basket.' ],

    /* — Premium — */
    [ 'gift_type'=>'premium-gifts', 'price'=>3499, 'img'=>self::img('gm-h-p1'),
      'name'=>'Premium Doctor Appreciation Set',
      'short'=>'Leather agenda, gold-finish pen set and personalised stethoscope tag in luxury box.',
      'desc'=>'A distinguished thank-you for healthcare heroes. Full-grain leather desktop agenda, gold-finish rollerball pen set, and a laser-engraved personalised stethoscope ID tag — all in a brushed black luxury gift box.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>4999, 'img'=>self::img('gm-h-p2'),
      'name'=>'Healthcare Leadership Gift Set',
      'short'=>'Premium crystal award, leather portfolio and gold pen for hospital leadership.',
      'desc'=>'For those who lead with purpose. Optical crystal tower award laser-engraved with the recipient\'s name and leadership title, matched with a full-grain leather portfolio and a gold-finish rollerball pen — in a luxury gift box.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>2999, 'img'=>self::img('gm-h-p3'),
      'name'=>'Luxury Wellness Spa Gift Set',
      'short'=>'Premium aromatherapy diffuser, essential oil set and natural bath salts.',
      'desc'=>'The self-care gift healthcare professionals deserve. A premium ultrasonic aroma diffuser, a set of five therapeutic essential oils (lavender, eucalyptus, peppermint, frankincense, lemon), and 500g of Himalayan pink bath salts — in a luxury white box.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>5999, 'img'=>self::img('gm-h-p4'),
      'name'=>'Premium Smartwatch for Healthcare',
      'short'=>'Medical-grade smartwatch with heart rate, SpO2 and stress monitoring.',
      'desc'=>'A health-focused gift for health professionals. Medical-grade smartwatch with continuous heart rate monitoring, blood oxygen (SpO2) measurement, stress index, and sleep tracking — in a premium branded gift box with a personalised achievement card.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>2499, 'img'=>self::img('gm-h-p5'),
      'name'=>'Doctor\'s Desk Organiser Gift Set',
      'short'=>'Solid brass desk organiser, pen set and personalised nameplate for the doctor\'s desk.',
      'desc'=>'Upgrade the doctor\'s workspace. A premium solid brass desktop organiser (pen holders, card slot, clip tray), a precision metal pen set, and a personalised brushed-metal nameplate — all in a branded gift box.' ],

    /* — Personalized — */
    [ 'gift_type'=>'personalized-gifts', 'price'=>1299, 'img'=>self::img('gm-h-per1'),
      'name'=>'Personalised Stethoscope Tag Set',
      'short'=>'Laser-engraved stainless-steel stethoscope tag and keychain with doctor\'s name.',
      'desc'=>'Practical and memorable. Laser-engraved 316L stainless-steel stethoscope ID tag with secure silicone grip and a matching keychain, personalised with the doctor\'s name and designation. Packaged in a velvet gift pouch.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>2499, 'img'=>self::img('gm-h-per2'),
      'name'=>'Engraved Doctor\'s Appreciation Plaque',
      'short'=>'Crystal plaque with caduceus motif engraved with doctor\'s name and dedication.',
      'desc'=>'Honour the dedication of your doctors. Optical crystal desk plaque with caduceus (medical symbol) motif, laser-engraved with the doctor\'s name, specialty, and a heartfelt appreciation message — in a velvet-lined presentation box.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>999, 'img'=>self::img('gm-h-per3'),
      'name'=>'Personalised Doctor\'s Day Mug',
      'short'=>'Premium ceramic mug printed with doctor\'s name, designation and hospital logo.',
      'desc'=>'A warm daily reminder of appreciation. Premium ceramic mug (400ml) with full-colour print of the doctor\'s name, designation, and a personalised quote — paired with a curated selection of premium teas in a kraft gift box.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>1799, 'img'=>self::img('gm-h-per4'),
      'name'=>'Custom Healthcare Hero Award',
      'short'=>'Crystal award with caduceus motif, engraved with staff name and "Healthcare Hero".',
      'desc'=>'Celebrate the healthcare heroes on your team. Crystal award with medical symbol motif, laser-engraved with the staff member\'s name, designation, and "Healthcare Hero" — presented in a premium gift box.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>3499, 'img'=>self::img('gm-h-per5'),
      'name'=>'Custom Nursing Station Name Board',
      'short'=>'Hand-painted wood name board personalised with ward name and department details.',
      'desc'=>'Identity and pride for every ward. Solid teak wood board (30×20 cm) hand-painted with the ward name, department, and hospital logo in premium enamel colours — with wall-mounting hardware included.' ],

    /* — Eco-Friendly — */
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>999, 'img'=>self::img('gm-h-e1'),
      'name'=>'Organic Tea & Wellness Kit',
      'short'=>'Six certified-organic herbal teas in a sustainable bamboo tray.',
      'desc'=>'Wellness gifted thoughtfully. Six individually-wrapped certified-organic herbal teas (immunity, relaxation, detox, and more) arranged in a reusable bamboo tray with a handwritten note. No plastic packaging.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>1499, 'img'=>self::img('gm-h-e2'),
      'name'=>'Hospital Green Gifting Kit',
      'short'=>'Recycled notebook, bamboo pen, organic hand sanitiser and seed paper card.',
      'desc'=>'Sustainable gifting for a sustainable healthcare future. Recycled-paper notebook (A5), FSC-certified bamboo ballpen, an organic aloe vera hand sanitiser, and a wildflower seed-paper gratitude card — all in a recycled kraft box.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>799, 'img'=>self::img('gm-h-e3'),
      'name'=>'Patient Discharge Grow Kit',
      'short'=>'Hand-painted terracotta pot, organic herb seeds and a get-well seed paper card.',
      'desc'=>'Send patients home with a living memory of their care. A hand-painted mini terracotta pot by Nizamabad artisans, organic tulsi seed sachet, organic potting soil, and a get-well message on seed paper — fully biodegradable.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>2199, 'img'=>self::img('gm-h-e4'),
      'name'=>'Eco Wellness Spa Box',
      'short'=>'Organic soap, beeswax candle, herbal tea, lavender eye mask and reusable tote.',
      'desc'=>'A restorative self-care gift made entirely from natural materials. Organic handmade soap, a hand-poured beeswax aromatherapy candle, premium chamomile tea, a lavender-filled eye mask, and a natural cotton tote — all certified organic.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>599, 'img'=>self::img('gm-h-e5'),
      'name'=>'Bamboo Health Care Kit',
      'short'=>'Bamboo toothbrush, charcoal face bar, organic hand cream and a seed paper note.',
      'desc'=>'Sustainable wellness essentials. An FSC-certified bamboo toothbrush, a charcoal and tea tree organic face bar, a lavender organic hand cream, and a wildflower seed-paper appreciation note — all in a cotton drawstring bag.' ],

    /* — Handcrafted — */
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>2199, 'img'=>self::img('gm-h-cr1'),
      'name'=>'Handcrafted Aroma Diffuser Set',
      'short'=>'Hand-turned neem wood diffuser with three therapeutic essential oil blends.',
      'desc'=>'Bring calm to the doctor\'s lounge. Hand-turned neem wood diffuser crafted by artisans in Saharanpur, UP, with three therapeutic blends — lavender, eucalyptus, and peppermint. Set includes bamboo tray and dropper.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>1499, 'img'=>self::img('gm-h-cr2'),
      'name'=>'Hand-Spun Khadi Wellness Tote',
      'short'=>'Hand-spun pure khadi tote with block-printed wellness quote from Wardha artisans.',
      'desc'=>'A daily companion crafted with care. Pure hand-spun khadi natural tote bag from Wardha, Maharashtra, hand-block-printed with the Hippocratic quote "First, do no harm" in organic vegetable dyes. Pairs with the wellness kit inside.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>2999, 'img'=>self::img('gm-h-cr3'),
      'name'=>'Hand-Forged Copper Water Bottle',
      'short'=>'Artisan hand-hammered pure copper water bottle with Ayurvedic benefits.',
      'desc'=>'Water stored in copper has been prescribed in Ayurveda for thousands of years. This 1-litre pure copper bottle is hand-hammered by artisans in Moradabad, with a leak-proof lid and natural patina exterior. Comes in a handmade jute pouch.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>1799, 'img'=>self::img('gm-h-cr4'),
      'name'=>'Handwoven Meditation Basket',
      'short'=>'Hand-woven natural grass meditation kit basket with lavender sachet and mala beads.',
      'desc'=>'A mindfulness gift for healthcare staff. Hand-woven natural grass basket (Assam craft) containing a dried lavender sachet, a sandalwood mala bead necklace, a palm-sized crystal worry stone, and a printed mindfulness card.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>999, 'img'=>self::img('gm-h-cr5'),
      'name'=>'Hand-Painted Ayurvedic Herb Pots',
      'short'=>'Three hand-painted terracotta pots with tulsi, ashwagandha and brahmi seeds.',
      'desc'=>'The gift of Ayurvedic wellness. Three hand-painted terracotta pots by artisans from Kutch, each with an organic seed sachet — tulsi (immunity), ashwagandha (stress relief), and brahmi (cognitive health). Includes a care guide card.' ],

    /* — Local Artisan — */
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>1199, 'img'=>self::img('gm-h-a1'),
      'name'=>'Terracotta Herbal Planter Gift Set',
      'short'=>'Three hand-painted terracotta pots with organic herb seeds — tulsi, pudina and methi.',
      'desc'=>'A living, growing gift from local pottery artisans. Three hand-painted terracotta pots made by potters from Nizamabad, UP, each with an organic herb seed sachet — tulsi, pudina, and methi. Includes a care guide card.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>1799, 'img'=>self::img('gm-h-a2'),
      'name'=>'Dokra Healing Figurine',
      'short'=>'Lost-wax cast Dhokra brass Dhanvantari (god of medicine) figurine from Bastar artisans.',
      'desc'=>'Dhanvantari — the Hindu god of medicine and Ayurveda. This Dhokra brass figurine of Dhanvantari is individually cast by tribal artisans from Bastar, Chhattisgarh, in the 4,000-year-old lost-wax tradition. A meaningful desk piece for healthcare professionals.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>999, 'img'=>self::img('gm-h-a3'),
      'name'=>'Handloom Wellness Scarf',
      'short'=>'Pure handloom cotton scarf with natural block-print floral motifs from Andhra artisans.',
      'desc'=>'Crafted on traditional pit looms in Chirala, Andhra Pradesh. A pure handloom cotton scarf (180×45 cm) with hand-block-printed flower motifs in natural vegetable dyes — soft, breathable, and uniquely patterned. Packaged in recycled silk paper.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>2499, 'img'=>self::img('gm-h-a4'),
      'name'=>'Sanjhi Art Wellness Wall Panel',
      'short'=>'Paper-cut Sanjhi art depicting the lotus (healing symbol) from Mathura artisans.',
      'desc'=>'Sanjhi — the ancient paper-cutting art from the temples of Vrindavan. Intricate paper-cut panel depicting a blooming lotus (universal symbol of healing and purity) by artisans from Mathura, UP — framed in natural bamboo. Each piece takes 3 days to cut.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>1499, 'img'=>self::img('gm-h-a5'),
      'name'=>'Blue Pottery Herb Planter Set',
      'short'=>'Set of 3 hand-painted Jaipur blue pottery planters with organic herb seed sachets.',
      'desc'=>'Jaipur\'s iconic blue pottery meets Ayurvedic wellness. Three hand-painted blue pottery planters by artisans from Jaipur, in the signature turquoise-and-white floral style, each with an organic seed sachet — tulsi, mint, and marigold.' ],
], /* end hospitals */

/* ════════════════════════════════════════════════════════════════════
   CONSTRUCTION & REAL ESTATE
   ═══════════════════════════════════════════════════════════════════ */
'construction' => [

    /* — Hamper — */
    [ 'gift_type'=>'hamper', 'price'=>2999, 'img'=>self::img('gm-co-h1'),
      'name'=>'Housewarming Luxury Hamper',
      'short'=>'Gourmet spices, handcrafted diya, aromatic incense, artisan tea and lucky-home charm.',
      'desc'=>'A premium blessing for a new home. Single-origin gourmet spices, a hand-painted brass diya, premium Darjeeling first-flush tea, hand-rolled agarbatti, and a traditional brass lucky-home charm — all in a silk-lined hamper box.' ],
    [ 'gift_type'=>'hamper', 'price'=>1999, 'img'=>self::img('gm-co-h2'),
      'name'=>'New Home Celebration Hamper',
      'short'=>'Premium sweets, sparkling wine, chocolates, artisan crackers and a congratulations card.',
      'desc'=>'Celebrate the milestone of a new home. A bottle of premium sparkling fruit wine, Belgian chocolates, Indian sweets (kaju barfi, ladoo), artisan cheese crackers, and a signed congratulations card from the builder — in a luxury hamper box.' ],
    [ 'gift_type'=>'hamper', 'price'=>4499, 'img'=>self::img('gm-co-h3'),
      'name'=>'Channel Partner Excellence Hamper',
      'short'=>'Single-malt whisky, gourmet foods, personalised appreciation letter and premium chocolates.',
      'desc'=>'A top-tier gift for top-tier partners. Single-malt whisky miniature, Belgian chocolate truffles, artisan crackers and cheese, premium coffee, and a personalised appreciation letter from the Managing Director — in a black linen gift chest.' ],
    [ 'gift_type'=>'hamper', 'price'=>1499, 'img'=>self::img('gm-co-h4'),
      'name'=>'Project Handover Celebration Box',
      'short'=>'Mini celebration hamper with sparkling juice, artisan chocolates and branded ribbon.',
      'desc'=>'Mark the project milestone with a celebration. A branded mini celebration box containing a bottle of premium sparkling fruit juice, a box of artisan chocolates, a personalised handover card, and a confetti popper — for the project completion party.' ],
    [ 'gift_type'=>'hamper', 'price'=>3499, 'img'=>self::img('gm-co-h5'),
      'name'=>'Vastu Blessing Hamper',
      'short'=>'Brass Ganesha, camphor, incense sticks, rock salt lamp and organic ghee.',
      'desc'=>'A traditional home-blessing hamper rooted in Vastu Shastra. Solid brass Ganesha figurine, hand-rolled camphor tablets, premium incense sticks, a small Himalayan rock salt lamp, and organic desi ghee — in a red-and-gold silk gift box.' ],

    /* — Premium — */
    [ 'gift_type'=>'premium-gifts', 'price'=>4499, 'img'=>self::img('gm-co-p1'),
      'name'=>'Premium Home Décor Welcome Set',
      'short'=>'Crystal vase, scented pillar candle and brass Ganesha figurine in a velvet-lined box.',
      'desc'=>'An aspirational welcome to a new home. Hand-blown crystal vase, luxury amber-and-oud scented pillar candle (50-hour burn), and a solid brass Ganesha figurine with intricate filigree detail — in a brushed black velvet-lined gift box.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>6999, 'img'=>self::img('gm-co-p2'),
      'name'=>'Luxury Home Entertaining Set',
      'short'=>'Hand-blown crystal decanter set with four whisky glasses in a velvet case.',
      'desc'=>'The definitive housewarming luxury gift. Hand-blown lead-free crystal whisky decanter (750ml) and four matching crystal rocks glasses, laser-engraved with the family name — in a premium velvet-lined case with a personalised keepsake card.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>4999, 'img'=>self::img('gm-co-p3'),
      'name'=>'Partner Excellence Award Set',
      'short'=>'Crystal tower award engraved with partner name, company and achievement milestone.',
      'desc'=>'Recognise your top channel partners. This crystal tower award (25 cm) features deep laser engraving with the partner\'s name, company, achievement (e.g. "Top Sales Partner 2025"), and your builder brand logo — in a luxury presentation box.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>3499, 'img'=>self::img('gm-co-p4'),
      'name'=>'Premium Leather Document Folder',
      'short'=>'Full-grain leather home-documents folder embossed with builder logo and family name.',
      'desc'=>'A professional and prestigious handover gift. Full-grain leather document folder (fits A4 documents) with zip closure, embossed with the builder\'s logo and the homeowner\'s family name — perfect for organising sale deeds, registration papers, and warranties.' ],
    [ 'gift_type'=>'premium-gifts', 'price'=>8999, 'img'=>self::img('gm-co-p5'),
      'name'=>'Smart Home Starter Kit',
      'short'=>'Smart LED bulb set, smart plug, and branded welcome card in a premium gift box.',
      'desc'=>'Welcome them to a smarter home. A set of four smart LED colour-changing bulbs, a smart power strip with app control, and a personalised "Welcome to Your Smart Home" card — all branded with the developer\'s logo in a premium gift box.' ],

    /* — Personalized — */
    [ 'gift_type'=>'personalized-gifts', 'price'=>1599, 'img'=>self::img('gm-co-per1'),
      'name'=>'Custom Address Nameplate',
      'short'=>'Solid teak nameplate laser-engraved with family name, house number and decorative border.',
      'desc'=>'A meaningful and lasting welcome gift. Sustainably sourced teak wood (1.5 cm thick), laser-engraved with the family name, house number, and a decorative floral border. UV-coated for outdoor use with wall-mounting kit included.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>2999, 'img'=>self::img('gm-co-per2'),
      'name'=>'Family Name Art Print',
      'short'=>'Custom illustrated family name art print with significant dates in a frame.',
      'desc'=>'A personalised home centrepiece. Custom-designed art print featuring the family surname in large type, surrounded by significant dates (move-in date, anniversaries, birthdays) in a classic typographic layout — framed in natural wood, ready to hang.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>1999, 'img'=>self::img('gm-co-per3'),
      'name'=>'Engraved Home Keys Keepsake Box',
      'short'=>'Handcrafted mango-wood box engraved with family name and "Home Sweet Home" for the keys.',
      'desc'=>'A beautiful home for your home\'s keys. Handcrafted mango-wood keepsake box with a hook-rack interior for hanging keys, laser-engraved with the family name and "Home Sweet Home" — presented in a branded gift box at the handover ceremony.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>3499, 'img'=>self::img('gm-co-per4'),
      'name'=>'Custom Home Coordinate Plaque',
      'short'=>'Brushed stainless steel plaque engraved with GPS coordinates and address of the new home.',
      'desc'=>'A modern, meaningful housewarming piece. Brushed stainless steel wall plaque (20×15 cm) laser-engraved with the GPS coordinates of the new home, the full address, and the handover date — mounted on a natural wood backing.' ],
    [ 'gift_type'=>'personalized-gifts', 'price'=>1299, 'img'=>self::img('gm-co-per5'),
      'name'=>'Personalised Welcome Doormat',
      'short'=>'Coir doormat with family name printed in bold with a floral border.',
      'desc'=>'The very first step into their new home. Natural coir doormat (60×40 cm) with the family name printed in bold with a hand-painted floral border — UV-treated for outdoor durability. Personalised and shipped within 3 days.' ],

    /* — Eco-Friendly — */
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>1299, 'img'=>self::img('gm-co-e1'),
      'name'=>'Eco Home Starter Kit',
      'short'=>'Beeswax candles, bamboo kitchen utensils, jute tote and organic home garden seed kit.',
      'desc'=>'Help them start their new home the sustainable way. Two hand-poured beeswax candles, FSC-certified bamboo kitchen utensils, a reusable jute shopping tote, and an organic home garden seed kit (tomato, basil, coriander).' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>1799, 'img'=>self::img('gm-co-e2'),
      'name'=>'Zero Waste Kitchen Gift Set',
      'short'=>'Beeswax food wraps, bamboo scrub brush, jute produce bags and organic dish soap.',
      'desc'=>'A sustainable kitchen for a new beginning. A set of three beeswax food wraps (replacing cling film), a bamboo dish scrub brush, four reusable jute produce bags, and an organic castile dish soap bar — all in a recycled cardboard box.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>999, 'img'=>self::img('gm-co-e3'),
      'name'=>'Organic Home Garden Kit',
      'short'=>'Five organic seed sachets, biodegradable pots and organic potting mix for balcony garden.',
      'desc'=>'Grow your own on the new balcony. Five organic vegetable and herb seed sachets (tomato, chilli, coriander, spinach, basil), five biodegradable coconut coir pots, and an organic potting mix block — in a printed seed packet box.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>2499, 'img'=>self::img('gm-co-e4'),
      'name'=>'Eco Housewarming Bundle',
      'short'=>'Solar jar lights, bamboo plant pots, seed paper wish cards and organic candles.',
      'desc'=>'An entirely eco-friendly housewarming gift. Two solar-powered glass jar fairy lights for the balcony, two FSC-certified bamboo plant pots, three seed-paper wish cards (guests write wishes and plant them), and two beeswax pillar candles.' ],
    [ 'gift_type'=>'eco-friendly-gifts', 'price'=>599, 'img'=>self::img('gm-co-e5'),
      'name'=>'Plantable Housewarming Card Set',
      'short'=>'Set of 5 handmade seed-paper housewarming cards that bloom into wildflowers.',
      'desc'=>'Cards that don\'t get thrown away. A set of five handmade seed-paper housewarming cards embedded with wildflower seeds — write your message, give to guests, they plant and grow flowers. Each card is individually hand-pressed by artisans in Pune.' ],

    /* — Handcrafted — */
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>3499, 'img'=>self::img('gm-co-cr1'),
      'name'=>'Hand-Forged Brass Door Knocker',
      'short'=>'Artisan hand-forged solid brass door knocker in traditional elephant design.',
      'desc'=>'An heirloom-quality welcome gift. Solid brass door knocker hand-forged by master metalworkers in Moradabad, in a traditional elephant design symbolising good fortune. Includes wall fixings and a certificate of craft origin.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>2499, 'img'=>self::img('gm-co-cr2'),
      'name'=>'Hand-Hammered Copper Décor Tray',
      'short'=>'Artisan hand-hammered pure copper serving tray with engraved border from Moradabad.',
      'desc'=>'A centrepiece worthy of the new home. Hand-hammered pure copper serving tray (30×20 cm) with an engraved floral border, crafted by metalworkers in Moradabad — useful for serving and beautiful as wall décor.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>4999, 'img'=>self::img('gm-co-cr3'),
      'name'=>'Hand-Crafted Brass Diya Set',
      'short'=>'Set of 5 hand-cast brass oil diyas with intricate peacock motif.',
      'desc'=>'Illuminate the new home with tradition. Five hand-cast solid brass oil diyas in a graduating size set, each with an intricate peacock motif hand-engraved by brass artisans in Vrindavan, UP — presented on a handmade mango-wood tray.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>1999, 'img'=>self::img('gm-co-cr4'),
      'name'=>'Hand-Woven Jute Welcome Mat',
      'short'=>'Handwoven natural jute and cotton door mat with traditional geometric pattern.',
      'desc'=>'The most welcoming welcome mat. Hand-woven natural jute and cotton blend mat (60×40 cm) by artisans from Kolkata, in a traditional geometric diamond pattern — natural, non-slip, and biodegradable.' ],
    [ 'gift_type'=>'handcrafted-gifts', 'price'=>2999, 'img'=>self::img('gm-co-cr5'),
      'name'=>'Handcrafted Ceramic Home Set',
      'short'=>'Hand-thrown ceramic planter, bowl and mug set hand-glazed in earthy tones.',
      'desc'=>'A cohesive artisan home set. Wheel-thrown ceramic planter (15 cm), cereal bowl, and coffee mug — hand-glazed in warm terracotta and forest-green tones by artisans from Khurja, UP. Each piece is unique and food-safe.' ],

    /* — Local Artisan — */
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>2499, 'img'=>self::img('gm-co-a1'),
      'name'=>'Bidriware Home Accent Piece',
      'short'=>'Hand-inlaid Bidriware tray from Bidar artisans — silver floral patterns on black zinc-alloy.',
      'desc'=>'A UNESCO-heritage craft keepsake for a new home. Bidriware tray hand-crafted by artisans from Bidar, Karnataka, inlaid with pure silver wire into oxidised zinc-alloy — a 500-year-old art form. Comes with certificate of authenticity.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>1999, 'img'=>self::img('gm-co-a2'),
      'name'=>'Madhubani Home Blessing Panel',
      'short'=>'Original Madhubani painting of the home deity Griha Lakshmi in a mango wood frame.',
      'desc'=>'Bless the new home with art. Original hand-painted Madhubani artwork depicting Griha Lakshmi (the goddess of the home) by certified artisans from Madhubani, Bihar — mounted on handmade cotton paper in a natural mango-wood frame.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>3299, 'img'=>self::img('gm-co-a3'),
      'name'=>'Channapatna Wooden Toy Décor Set',
      'short'=>'Set of 3 hand-turned Channapatna lacquer wood decorative figurines.',
      'desc'=>'Channapatna — the "toy town" of Karnataka. Three hand-turned and natural lacquer-finished wooden figurines in the iconic Channapatna style (elephant, peacock, horse) crafted by artisans from Channapatna, Karnataka. Comes with a craft heritage card.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>1499, 'img'=>self::img('gm-co-a4'),
      'name'=>'Rajasthani Blue Pottery Home Set',
      'short'=>'Hand-painted blue pottery flower vase and fruit bowl set from Jaipur artisans.',
      'desc'=>'Jaipur\'s iconic blue pottery for the new home. A hand-painted flower vase (20 cm) and fruit bowl pair by artisans from Jaipur, Rajasthan, in the signature quartz-and-glass-powder blue pottery technique with floral motifs.' ],
    [ 'gift_type'=>'local-artisan-gifts', 'price'=>2799, 'img'=>self::img('gm-co-a5'),
      'name'=>'Kondapalli Village Home Décor Set',
      'short'=>'Set of 4 hand-painted Kondapalli wooden figurines depicting home and family.',
      'desc'=>'Kondapalli — the living craft village of Andhra Pradesh. Four hand-painted wooden figurines (couple, home, tulsi plant, welcome lamp) crafted by artisans from Kondapalli village — made from soft tella poniki wood and painted in vegetable pigments.' ],
], /* end construction */

        ]; /* end product_data return */
    }

    /* ── Bootstrap ───────────────────────────────────────────────────── */

    private $segment_vendor_ids = [];

    public function __construct() {
        add_action( 'init', [ $this, 'maybe_seed' ], 99 );
    }

    public function maybe_seed() {
        if ( get_option( self::OPTION ) ) return;
        if ( ! taxonomy_exists( 'product_cat' ) ) return;
        if ( ! class_exists( 'WooCommerce' ) ) return;

        @set_time_limit( 0 );
        @ignore_user_abort( true );

        $this->cleanup_old_seeded_data();
        $this->seed_vendors();
        $this->seed_products();

        update_option( self::OPTION, true );
    }

    /* ── Step 0: clean up old seeded data ────────────────────────────── */

    private function cleanup_old_seeded_data() {
        global $wpdb;

        $old_ids = $wpdb->get_col(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_gm_seeded' AND meta_value='1'"
        );
        foreach ( $old_ids as $pid ) {
            wp_delete_post( (int) $pid, true );
        }

        $old_users = get_users( [
            'meta_key'   => 'gm_seeded',
            'meta_value' => '1',
            'fields'     => 'ID',
        ] );
        foreach ( $old_users as $uid ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            wp_delete_user( (int) $uid );
        }
    }

    /* ── Step 1: create vendors ──────────────────────────────────────── */

    private function seed_vendors() {
        foreach ( self::vendor_data() as $segment => $vendors ) {
            $this->segment_vendor_ids[ $segment ] = [];

            foreach ( $vendors as $idx => $v ) {
                $existing = get_user_by( 'email', $v['email'] );
                if ( $existing ) {
                    $this->segment_vendor_ids[ $segment ][] = $existing->ID;
                    continue;
                }

                $uid = wp_insert_user( [
                    'user_login'   => $v['login'],
                    'user_pass'    => self::PASSWORD,
                    'user_email'   => $v['email'],
                    'first_name'   => $v['first'],
                    'last_name'    => $v['last'],
                    'display_name' => $v['store'],
                    'role'         => 'seller',
                ] );

                if ( is_wp_error( $uid ) ) continue;

                $color = self::$seg_colors[ $segment ] ?? '5733a2';

                /* Avatar */
                $avatar_url = 'https://ui-avatars.com/api/?name=' . rawurlencode( $v['store'] )
                            . '&size=300&background=' . $color . '&color=fff&format=png&bold=true';
                $avatar_id  = $this->sideload_image( $avatar_url, 0, $v['store'] . ' Avatar' );

                /* Banner — unique picsum photo per store */
                $banner_url = 'https://picsum.photos/seed/gm-banner-' . $v['login'] . '/1200/400';
                $banner_id  = $this->sideload_image( $banner_url, 0, $v['store'] . ' Banner' );

                update_user_meta( $uid, 'dokan_profile_settings', [
                    'store_name' => $v['store'],
                    'social'     => [],
                    'payment'    => [],
                    'phone'      => '9876543210',
                    'show_email' => 'no',
                    'address'    => [
                        'street_1' => $v['city'],
                        'city'     => $v['city'],
                        'state'    => 'MH',
                        'country'  => 'IN',
                        'zip'      => '400001',
                    ],
                    'location'       => $v['city'] . ', India',
                    'dokan_category' => '',
                    'banner'         => $banner_id ?: 0,
                    'gravatar'       => $avatar_id  ?: 0,
                    'icon'           => $avatar_id  ?: 0,
                ] );

                update_user_meta( $uid, 'dokan_enable_selling', 'yes' );
                update_user_meta( $uid, 'dokan_publishing',     'yes' );
                update_user_meta( $uid, 'gm_vendor_segment',    [ $segment ] );
                update_user_meta( $uid, 'gm_seeded',            '1' );

                $this->segment_vendor_ids[ $segment ][] = $uid;
            }
        }
    }

    /* ── Step 2: create products ─────────────────────────────────────── */

    private function seed_products() {
        foreach ( self::product_data() as $segment => $products ) {
            $vendor_ids = $this->segment_vendor_ids[ $segment ] ?? [];
            if ( empty( $vendor_ids ) ) continue;

            foreach ( $products as $idx => $p ) {
                $vendor_id = $vendor_ids[ $idx % count( $vendor_ids ) ];
                $gift_type = $p['gift_type'];

                $product = new WC_Product_Simple();
                $product->set_name( $p['name'] );
                $product->set_short_description( $p['short'] );
                $product->set_description( $p['desc'] );
                $product->set_regular_price( (string) $p['price'] );
                $product->set_status( 'publish' );
                $product->set_catalog_visibility( 'visible' );
                $product->set_manage_stock( false );
                $product->save();

                $pid = $product->get_id();
                if ( ! $pid ) continue;

                /* Featured image */
                if ( ! empty( $p['img'] ) ) {
                    $img_id = $this->sideload_image( $p['img'], $pid, $p['name'] );
                    if ( $img_id ) {
                        $product->set_image_id( $img_id );
                        $product->save();
                    }
                }

                /* Assign to vendor */
                wp_update_post( [ 'ID' => $pid, 'post_author' => $vendor_id ] );

                /* Gift-type WooCommerce category */
                wp_set_object_terms( $pid, [ $gift_type ], 'product_cat', false );

                /* Industry + gifting-program WooCommerce product tags */
                $program = self::$program_map[ $segment ][ $gift_type ] ?? '';
                $tags    = $program ? [ $segment, $program ] : [ $segment ];
                wp_set_object_terms( $pid, $tags, 'product_tag', false );

                /* Meta */
                update_post_meta( $pid, '_gm_seeded',          '1' );
                update_post_meta( $pid, '_gm_industry',        $segment );
                update_post_meta( $pid, '_gm_gift_type',       $gift_type );
                update_post_meta( $pid, '_gm_gifting_program', $program );
            }
        }
    }

    /* ── Helper: sideload image from URL ─────────────────────────────── */

    private function sideload_image( $url, $post_id, $desc ) {
        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        $result = media_sideload_image( $url, $post_id, $desc, 'id' );
        return is_wp_error( $result ) ? 0 : (int) $result;
    }
}
