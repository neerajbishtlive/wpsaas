<?php
/**
 * SaaS Platform Integration
 */

// Disable automatic updates
add_filter('automatic_updater_disabled', '__return_true');

// Custom admin footer
add_filter('admin_footer_text', function() {
    return 'Powered by WP SaaS Platform';
});

// Security headers
add_action('send_headers', function() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
});
