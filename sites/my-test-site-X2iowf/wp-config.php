<?php
// wp-config-template.php - Place this in control-panel/public/

/**
 * WordPress Configuration Template for Multi-tenant Sites
 * 
 * This template is used when creating new WordPress sites.
 * Placeholders will be replaced with actual values during site creation.
 */

// Database configuration
define('DB_NAME', 'wp_saas_control');
define('DB_USER', 'root');
define('DB_PASSWORD', 'Bhunee@@1315');
define('DB_HOST', '127.0.0.1');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

// Table prefix for this site
$table_prefix = 'wp_mytestsiteX2iowf_';

// URL Configuration - CRITICAL for multi-tenant
define('WP_HOME', 'http://localhost:8000/wp.php?site=my-test-site-X2iowf');
define('WP_SITEURL', 'http://localhost:8000/wp.php?site=my-test-site-X2iowf');

// Content directories
define('WP_CONTENT_DIR', __DIR__ . '/wp-content');
define('WP_CONTENT_URL', 'http://localhost:8000/wp.php?site=my-test-site-X2iowf/sites/my-test-site-X2iowf/wp-content');

// Plugin directory (optional custom path)
define('WP_PLUGIN_DIR', __DIR__ . '/wp-content/plugins');
define('WP_PLUGIN_URL', 'http://localhost:8000/wp.php?site=my-test-site-X2iowf/sites/my-test-site-X2iowf/wp-content/plugins');

// Uploads directory
define('UPLOADS', 'sites/my-test-site-X2iowf/wp-content/uploads');

// Disable file editing for security
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', false);

// Debug settings (disable in production)
define('WP_DEBUG', 'true' === 'true');
define('WP_DEBUG_LOG', 'true' === 'true');
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', false);

// Memory limits
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// Security keys - These should be unique for each site
define('AUTH_KEY',         'WOL7y1zltqzqsqr69T5DsvjPXBk2HefMLK1MuJv4NHYGH6X2FDbpgfmGGJttOCxm');
define('SECURE_AUTH_KEY',  'hXQb8Iq3lhV9oa4oQJIhgRp28DFYus0sTBLvZTOPChHFXsv9nZpbUwPgs14DXg72');
define('LOGGED_IN_KEY',    'FIR4aieFCPN1uwxQ5pP3BWXDqNqrI5Cyvj7yYGYMgZW9r3UuZ8ULzeyDe18pPJT2');
define('NONCE_KEY',        'gpFafFPASZcYZExeTPrQw2Cse0VPbNUwIY4Nb3Bl1PaDdCSPQOzpZrXHv6n7Zkcd');
define('AUTH_SALT',        'Ks8pci94NS9yjORUjQwXNkMfdNK7iadQa8o44cVPEbffHdRrhCFHqa242Bnf0igu');
define('SECURE_AUTH_SALT', '7nJi4ZBZlmR4bqm2s1tesfqUpBBTcacbz5T83hJVY0FjSaCgbQHGn2ki0oC814fc');
define('LOGGED_IN_SALT',   'molUIZ0jE45FGgP7C7F1tokXFG2EenyoQOfmwUyARSzQ1UZt178WF30LAE8X8M2h');
define('NONCE_SALT',       'SpjjNJXRAMGKX9irizficSKPfqCdJYS2wh6taDHAICAHjxzz7DnAXRCoV9iMkiIs');

// Multisite configuration (disabled for individual sites)
define('WP_ALLOW_MULTISITE', false);

// Force SSL for admin (enable in production)
// // define('FORCE_SSL_ADMIN', true);

// Performance optimizations
define('COMPRESS_CSS', true);
define('COMPRESS_SCRIPTS', true);
define('CONCATENATE_SCRIPTS', false);
define('ENFORCE_GZIP', true);

// Auto-save interval (in seconds)
define('AUTOSAVE_INTERVAL', 160);

// Post revisions (limit to save database space)
define('WP_POST_REVISIONS', 5);

// Trash settings
define('EMPTY_TRASH_DAYS', 30);

// Disable automatic updates for multi-tenant stability
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_AUTO_UPDATE_CORE', false);

// API settings
define('WP_HTTP_BLOCK_EXTERNAL', false);

// Cookie settings for multi-tenant
define('COOKIE_DOMAIN', false);
define('COOKIEPATH', '/');
define('SITECOOKIEPATH', '/');

// Custom user and usermeta tables (if needed)
// define('CUSTOM_USER_TABLE', $table_prefix . 'users');
// define('CUSTOM_USER_META_TABLE', $table_prefix . 'usermeta');

// Cron settings
define('DISABLE_WP_CRON', false);
define('ALTERNATE_WP_CRON', false);

// FTP settings (usually not needed)
define('FS_METHOD', 'direct');

/** Absolute path to the WordPress directory. */
if (!defined('ABSPATH')) {
    define('ABSPATH', '/Users/neerajbisht/Desktop/Diploy/GIT/wp-saas-platform/control-panel/../wordpress-core/');
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';