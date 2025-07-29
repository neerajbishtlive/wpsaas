<?php
/**
 * WordPress Configuration Template for Multi-tenant Sites
 * 
 * This template is used when creating new WordPress sites.
 * Placeholders will be replaced with actual values during site creation.
 * 
 * IMPORTANT: This is a TEMPLATE file. The placeholders like {{DB_NAME}} 
 * will be replaced with actual values when a new site is created.
 */

// Database configuration
define('DB_NAME', '{{DB_NAME}}');
define('DB_USER', '{{DB_USER}}');
define('DB_PASSWORD', '{{DB_PASSWORD}}');
define('DB_HOST', '{{DB_HOST}}');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

// Table prefix for this site
$table_prefix = '{{TABLE_PREFIX}}';

// URL Configuration - CRITICAL for multi-tenant
define('WP_HOME', '{{SITE_URL}}');
define('WP_SITEURL', '{{SITE_URL}}');

// Content directories
define('WP_CONTENT_DIR', __DIR__ . '/wp-content');
define('WP_CONTENT_URL', '{{SITE_URL}}/sites/{{SITE_SUBDOMAIN}}/wp-content');

// Plugin directory (optional custom path)
define('WP_PLUGIN_DIR', __DIR__ . '/wp-content/plugins');
define('WP_PLUGIN_URL', '{{SITE_URL}}/sites/{{SITE_SUBDOMAIN}}/wp-content/plugins');

// Uploads directory
define('UPLOADS', 'sites/{{SITE_SUBDOMAIN}}/wp-content/uploads');

// Disable file editing for security
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', false);

// Debug settings (disable in production)
// {{WP_DEBUG}} will be replaced with true or false
define('WP_DEBUG', '{{WP_DEBUG}}');
define('WP_DEBUG_LOG', {{WP_DEBUG_LOG}});
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', false);

// Memory limits
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// Security keys - These should be unique for each site
define('AUTH_KEY',         '{{AUTH_KEY}}');
define('SECURE_AUTH_KEY',  '{{SECURE_AUTH_KEY}}');
define('LOGGED_IN_KEY',    '{{LOGGED_IN_KEY}}');
define('NONCE_KEY',        '{{NONCE_KEY}}');
define('AUTH_SALT',        '{{AUTH_SALT}}');
define('SECURE_AUTH_SALT', '{{SECURE_AUTH_SALT}}');
define('LOGGED_IN_SALT',   '{{LOGGED_IN_SALT}}');
define('NONCE_SALT',       '{{NONCE_SALT}}');

// Multisite configuration (disabled for individual sites)
define('WP_ALLOW_MULTISITE', false);

// Force SSL for admin
// {{FORCE_SSL_ADMIN}} will be replaced with true or false
define('FORCE_SSL_ADMIN', {{FORCE_SSL_ADMIN}});

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

// Cookie settings for multi-tenant with subdomains
define('COOKIE_DOMAIN', false);
define('COOKIEPATH', '/');
define('SITECOOKIEPATH', '/');
define('ADMIN_COOKIE_PATH', '/wp-admin');

// Custom user and usermeta tables (if needed)
// define('CUSTOM_USER_TABLE', $table_prefix . 'users');
// define('CUSTOM_USER_META_TABLE', $table_prefix . 'usermeta');

// Cron settings
define('DISABLE_WP_CRON', false);
define('ALTERNATE_WP_CRON', false);

// FTP settings (usually not needed)
define('FS_METHOD', 'direct');

// Site identifier for internal use
define('WP_SAAS_SITE', '{{SITE_SUBDOMAIN}}');

/** Absolute path to the WordPress directory. */
if (!defined('ABSPATH')) {
    define('ABSPATH', '{{WP_CORE_PATH}}');
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';