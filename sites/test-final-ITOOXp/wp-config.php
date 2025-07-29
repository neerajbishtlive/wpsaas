<?php
/**
 * WordPress Configuration for Site: test-final-ITOOXp
 * Generated: 2025-07-29 18:00:07
 */

// Prevent double loading
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
$table_prefix = 'wp_testfinalITOOXp_';

// URLs - Use base URL to prevent redirects
define('WP_HOME', 'http://localhost:8000');
define('WP_SITEURL', 'http://localhost:8000');

// Content directories
define('WP_CONTENT_DIR', __DIR__ . '/wp-content');
define('WP_CONTENT_URL', 'http://localhost:8000/sites/test-final-ITOOXp/wp-content');

// Disable redirects
define('REDIRECT_CANONICAL', false);
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);

// Debug settings
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', false);

// Memory limits
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// Security
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', false);
define('FORCE_SSL_ADMIN', false);

// Authentication Keys and Salts
define('AUTH_KEY',         'aSG5LheyKiXoIyofh2vIVNslFIKofXqGtrYd5BO4OIFRmtgcUaNTTiSUQeDVpYSY');
define('SECURE_AUTH_KEY',  'iZAvYbDJJfmZUYuVDgNZcrSB0vrTzIcIiHKb6LutbVO1Q3prMbTyfC14mjEhzgCG');
define('LOGGED_IN_KEY',    '0CCgjdHrRANlCtczL3Mi3iRgX6nDetDoIgzc0pvADcP481PEYsJLsbFzApBBMOaI');
define('NONCE_KEY',        'Yc2UTiy7WVeXZDh54PKgQAZnjpgTteEP6BFzxBfWhvpL1AeXytvxmKtaNKxu6XEf');
define('AUTH_SALT',        'kYzfELvxw44cTFEYxEBioVX92w93OlrGSfjbY5Ec9HODLnrpAZFp2vH5ZEoSMoMq');
define('SECURE_AUTH_SALT', 'mRV5hcXJQNVhCp4hI4KGsgJusc0yvG0yILvsh7qvzV7R3vPkpibQcxEZb5yXZaUA');
define('LOGGED_IN_SALT',   '5yTSpHJ5jlB2sLa3Rzwt1FbD5R7dvgOopuEXERguAqieRzTolbpwGxWNEMc8xYnY');
define('NONCE_SALT',       '45Sw75MdWGeBL7UkWEXQHCQGLQs8EqF7rYVDFsqTdZMfledJA4ANPq4Awefknbfi');

// Performance
define('CONCATENATE_SCRIPTS', false);
define('COMPRESS_CSS', true);
define('COMPRESS_SCRIPTS', true);
define('ENFORCE_GZIP', true);

// Misc settings
define('WP_POST_REVISIONS', 5);
define('EMPTY_TRASH_DAYS', 30);
define('AUTOSAVE_INTERVAL', 160);
define('WP_ALLOW_MULTISITE', false);

// Absolute path to the WordPress directory
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__, 3) . '/wordpress-core/');
}

// Sets up WordPress vars and included files
if (!defined('WPINC')) {
    require_once ABSPATH . 'wp-settings.php';
}