<?php
/**
 * DineMate Landing - Configuration File
 * 
 * This file contains configuration settings for the landing website.
 * Update these values to customize your landing pages.
 */

// ============================================
// SITE CONFIGURATION
// ============================================

// Brand Information
define('SITE_NAME', 'DineMate');
define('SITE_TAGLINE', 'The World\'s Leading Restaurant Reservation Platform');
define('SITE_DESCRIPTION', 'Discover, book, and experience exceptional dining with DineMate.');

// URLs
define('SITE_URL', 'http://localhost/Dinemate/Dinemate-Landing');
define('APP_URL', 'http://localhost/Dinemate');

// Contact Information
define('SUPPORT_EMAIL', 'support@dinemate.com');
define('PARTNERS_EMAIL', 'partners@dinemate.com');
define('BUSINESS_EMAIL', 'business@dinemate.com');

// Office Information
define('OFFICE_ADDRESS', '123 Innovation Street, San Francisco, CA 94107, USA');
define('PHONE', '+1 (555) 123-4567');

// ============================================
// STATISTICS & METRICS
// ============================================

$stats = [
    'restaurants' => '50,000+',
    'diners' => '5,000,000+',
    'monthly_reservations' => '1,000,000+',
    'countries' => '50+',
];

// ============================================
// PRICING CONFIGURATION
// ============================================

$diner_plans = [
    'explorer' => [
        'name' => 'Explorer',
        'price' => 'Free',
        'period' => 'Forever',
        'features' => [
            'Unlimited restaurant searches',
            'Real-time availability',
            'Instant booking confirmation',
            'Basic email notifications',
            'Mobile app access',
            '1 saved favorite list',
        ]
    ],
    'premium' => [
        'name' => 'Premium',
        'price' => '$9.99',
        'period' => 'per month',
        'badge' => 'Most Popular',
        'features' => [
            'Everything in Explorer',
            'Priority reservation access',
            'Exclusive member deals',
            'Points & rewards program',
            'Dedicated support',
            'Unlimited favorite lists',
        ]
    ],
    'vip' => [
        'name' => 'VIP',
        'price' => '$19.99',
        'period' => 'per month',
        'features' => [
            'Everything in Premium',
            'VIP table placement',
            'Premium points multiplier',
            'Concierge service',
            'Exclusive event invitations',
            'Free gift card value',
        ]
    ],
];

$restaurant_plans = [
    'starter' => [
        'name' => 'Starter',
        'price' => '$99',
        'period' => 'per month',
        'features' => [
            'Up to 50 monthly reservations',
            'Basic analytics dashboard',
            'Email & SMS confirmations',
            'Single location support',
            'Standard customer support',
            'Basic menu management',
        ]
    ],
    'growth' => [
        'name' => 'Growth',
        'price' => '$299',
        'period' => 'per month',
        'badge' => 'Most Popular',
        'features' => [
            'Unlimited reservations',
            'Advanced analytics & insights',
            'Marketing automation',
            'Multi-location support',
            'Priority support',
            'Custom branding options',
        ]
    ],
    'enterprise' => [
        'name' => 'Enterprise',
        'price' => 'Custom',
        'period' => 'Contact us',
        'features' => [
            'Everything in Growth',
            'API access & integrations',
            'Custom development',
            'Dedicated account manager',
            'Training & onboarding',
        ]
    ],
];

// ============================================
// TESTIMONIALS
// ============================================

$testimonials = [
    'diners' => [
        [
            'avatar' => 'RJ',
            'name' => 'Raj Patel',
            'role' => 'Food Enthusiast',
            'rating' => 5,
            'text' => 'DineMate has completely changed how I discover and book restaurants. It\'s so easy and the reservations are always confirmed. Best app ever!'
        ],
        [
            'avatar' => 'SC',
            'name' => 'Sarah Chen',
            'role' => 'Event Organizer',
            'rating' => 5,
            'text' => 'Managing reservations for large groups used to be a nightmare. DineMate makes it seamless. Highly recommended for anyone who loves dining out.'
        ],
    ],
    'restaurants' => [
        [
            'avatar' => 'LP',
            'name' => 'La Piazza',
            'role' => 'Fine Dining Italian',
            'rating' => 5,
            'text' => 'DineMate increased our bookings by 40% in the first month. The management system is intuitive and our no-shows dropped dramatically.'
        ],
        [
            'avatar' => 'TM',
            'name' => 'The Modern Kitchen',
            'role' => 'Contemporary Cuisine',
            'rating' => 5,
            'text' => 'The analytics dashboard gives us insights we never had before. We\'ve optimized our seating and increased revenue by 25%.'
        ],
    ]
];

// ============================================
// SOCIAL MEDIA
// ============================================

$social_links = [
    'facebook' => '#',
    'twitter' => '#',
    'instagram' => '#',
    'linkedin' => '#',
];

// ============================================
// FEATURES
// ============================================

$features = [
    'diners' => [
        'instant_reservations' => [
            'title' => 'Instant Reservations',
            'description' => 'Book tables in seconds with real-time availability across thousands of restaurants.'
        ],
        'location_discovery' => [
            'title' => 'Location Discovery',
            'description' => 'Find the best restaurants near you with detailed information and customer reviews.'
        ],
        'smart_notifications' => [
            'title' => 'Smart Notifications',
            'description' => 'Get timely reminders and updates about your reservations and special offers.'
        ],
    ],
    'restaurants' => [
        'reach_diners' => [
            'title' => 'Reach Millions of Diners',
            'description' => 'Get your restaurant in front of 5+ million active diners.'
        ],
        'streamline_management' => [
            'title' => 'Streamline Reservation Management',
            'description' => 'Manage all bookings from one intuitive dashboard.'
        ],
        'reduce_no_shows' => [
            'title' => 'Reduce No-Shows',
            'description' => 'Automated reminders reduce no-shows by up to 85%.'
        ],
    ]
];

// ============================================
// TEAM MEMBERS
// ============================================

$team = [
    [
        'name' => 'Michael Chen',
        'role' => 'CEO & Co-Founder',
        'bio' => 'Former product leader at leading food platforms. Passionate about UX and restaurant technology.',
        'avatar' => '👨‍💼'
    ],
    [
        'name' => 'Sarah Williams',
        'role' => 'CTO & Co-Founder',
        'bio' => 'Tech visionary with 15+ years in SaaS. Expert in scalable platforms and data engineering.',
        'avatar' => '👩‍💼'
    ],
    [
        'name' => 'James Rivera',
        'role' => 'VP Partnerships',
        'bio' => 'Hospitality veteran with deep relationships in the restaurant industry worldwide.',
        'avatar' => '👨‍🍳'
    ],
];

// ============================================
// NAVIGATION ITEMS
// ============================================

$nav_items = [
    ['label' => 'Home', 'url' => '/index.php'],
    ['label' => 'For Diners', 'url' => '/pages/for-diners.php'],
    ['label' => 'For Restaurants', 'url' => '/pages/for-restaurants.php'],
    ['label' => 'Pricing', 'url' => '/pages/pricing.php'],
    ['label' => 'About', 'url' => '/pages/about.php'],
    ['label' => 'Contact', 'url' => '/pages/contact.php'],
];

// ============================================
// FOOTER LINKS
// ============================================

$footer_links = [
    'For Diners' => [
        ['label' => 'Browse Restaurants', 'url' => '#'],
        ['label' => 'How It Works', 'url' => '#'],
        ['label' => 'Gift Cards', 'url' => '#'],
        ['label' => 'Mobile App', 'url' => '#'],
    ],
    'For Restaurants' => [
        ['label' => 'Partner With Us', 'url' => '/pages/for-restaurants.php'],
        ['label' => 'Pricing', 'url' => '/pages/pricing.php'],
        ['label' => 'Restaurant Admin', 'url' => '#'],
        ['label' => 'Support', 'url' => '#'],
    ],
    'Company' => [
        ['label' => 'About Us', 'url' => '/pages/about.php'],
        ['label' => 'Blog', 'url' => '#'],
        ['label' => 'Press', 'url' => '#'],
        ['label' => 'Contact', 'url' => '/pages/contact.php'],
    ],
    'Legal' => [
        ['label' => 'Privacy Policy', 'url' => '#'],
        ['label' => 'Terms of Service', 'url' => '#'],
        ['label' => 'Cookie Policy', 'url' => '#'],
        ['label' => 'Accessibility', 'url' => '#'],
    ],
];

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Get feature by key
 */
function get_feature($type, $key) {
    global $features;
    return $features[$type][$key] ?? [];
}

/**
 * Get pricing plan
 */
function get_plan($type, $plan) {
    if ($type === 'diner') {
        global $diner_plans;
        return $diner_plans[$plan] ?? [];
    } else {
        global $restaurant_plans;
        return $restaurant_plans[$plan] ?? [];
    }
}

/**
 * Get all plans by type
 */
function get_all_plans($type) {
    if ($type === 'diner') {
        global $diner_plans;
        return $diner_plans;
    } else {
        global $restaurant_plans;
        return $restaurant_plans;
    }
}

/**
 * Format currency
 */
function format_currency($value) {
    return $value; // Already formatted in config
}

/**
 * Get stat value
 */
function get_stat($key) {
    global $stats;
    return $stats[$key] ?? 'N/A';
}

?>
