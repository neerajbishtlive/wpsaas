<?php
/**
 * Force WordPress URLs
 * This must-use plugin ensures WordPress uses the correct URLs
 */

// Force the site URL
add_filter('option_siteurl', function($value) {
    return 'http://localhost:8000/sites/test-5nCJo7';
});

add_filter('option_home', function($value) {
    return 'http://localhost:8000/sites/test-5nCJo7';
});

// Prevent WordPress from redirecting to install
add_filter('wp_redirect', function($location) {
    if (strpos($location, 'setup-config.php') !== false) {
        return home_url('/');
    }
    return $location;
}, 1);

// Force correct URLs in admin
if (is_admin()) {
    add_filter('admin_url', function($url) {
        return str_replace('http://localhost/', 'http://localhost:8000/', $url);
    });
}
