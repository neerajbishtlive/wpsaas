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
$table_prefix = 'wp_mytestsiterGaKPx_';

// URL Configuration - CRITICAL for multi-tenant
define('WP_HOME', 'http://localhost:8000/wp.php?site=my-test-site-rGaKPx');
define('WP_SITEURL', 'http://localhost:8000/wp.php?site=my-test-site-rGaKPx');

// Content directories
define('WP_CONTENT_DIR', __DIR__ . '/wp-content');
define('WP_CONTENT_URL', 'http://localhost:8000/wp.php?site=my-test-site-rGaKPx/sites/my-test-site-rGaKPx/wp-content');

// Plugin directory (optional custom path)
define('WP_PLUGIN_DIR', __DIR__ . '/wp-content/plugins');
define('WP_PLUGIN_URL', 'http://localhost:8000/wp.php?site=my-test-site-rGaKPx/sites/my-test-site-rGaKPx/wp-content/plugins');

// Uploads directory
define('UPLOADS', 'sites/my-test-site-rGaKPx/wp-content/uploads');

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
define('AUTH_KEY',         '5K8e0SrLYoDRUpmagZBPLBopZ3EBDpt3oYjf5rHPIoENDktsQfkEvlEkOCBNMJT0');
define('SECURE_AUTH_KEY',  '2vKK9khSn1o1O3cRnqcj0ExVXVaHj5KJVxgad0mnxP6nws7aZtJjYv1tJiYDXvZO');
define('LOGGED_IN_KEY',    '99zFg317upL7tIYExZT66PAGgsZVMqHAV3K0T4ChT6QjAllMq4MNTey0o5h9SeFa');
define('NONCE_KEY',        'CwPG6hPitYJCIDKVDZsHGAcVkmiYYjLGMGCTfXmbECnAy4zVZWI8wm4wx4CCGmno');
define('AUTH_SALT',        'wDb40VH51V1jFzkojsj4QUcr5H1sNRFTJ3cOaKAyxYFBtXaM9hxsqqBGhUkHHqo3');
define('SECURE_AUTH_SALT', 'YPKA71O6iBFhI7AI9pMKGQi4BB4eHE1qpfnGEJe25gbQATpAAheRgeOazHd0Kgid');
define('LOGGED_IN_SALT',   'pZWwDQQZLz2ylZCcRKmnGIzawlSjYiikXgqPTnfpTwoz5HMPLt8Cq4hYDDEpHaKi');
define('NONCE_SALT',       'z4QmHt7WQGO4LE5UucUglTpQdyepCgJh5cMBbrjsv3ymEVVJ8nPyo482XKM3P7Hg');

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