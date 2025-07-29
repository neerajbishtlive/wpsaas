<?php
/**
 * WordPress Configuration File
 * This configuration prevents double-loading issues
 */

// Prevent double execution
if (defined('WP_CONFIG_LOADED')) {
    return;
}
define('WP_CONFIG_LOADED', true);

// Database configuration
define('DB_NAME', 'wp_saas_control');
define('DB_USER', 'root');
define('DB_PASSWORD', 'Bhunee@@1315');
define('DB_HOST', '127.0.0.1');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

// Table prefix for this site
$table_prefix = 'wp_finaltestSAkZEH_';

// WordPress Memory
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// URLs - Set to base URL to prevent redirects
define('WP_HOME', 'http://localhost:8000');
define('WP_SITEURL', 'http://localhost:8000');

// Content Directory
define('WP_CONTENT_DIR', dirname(__FILE__) . '/wp-content');
define('WP_CONTENT_URL', 'http://localhost:8000/sites/test-5nCJo7/wp-content');

// Disable redirects
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
if (!defined('REDIRECT_CANONICAL')) {
    define('REDIRECT_CANONICAL', false);
}

// Debug settings
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', false);

// Disable file editing
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', false);

// Authentication Keys and Salts
define('AUTH_KEY',         'put your unique phrase here');
define('SECURE_AUTH_KEY',  'put your unique phrase here');
define('LOGGED_IN_KEY',    'put your unique phrase here');
define('NONCE_KEY',        'put your unique phrase here');
define('AUTH_SALT',        'put your unique phrase here');
define('SECURE_AUTH_SALT', 'put your unique phrase here');
define('LOGGED_IN_SALT',   'put your unique phrase here');
define('NONCE_SALT',       'put your unique phrase here');

// Absolute path to WordPress
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__, 3) . '/wordpress-core/');
}

// Only load wp-settings if it hasn't been loaded yet
if (!defined('WPINC')) {
    require_once ABSPATH . 'wp-settings.php';
}