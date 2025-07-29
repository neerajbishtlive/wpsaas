<?php
define('DB_NAME', 'wp_saas_control');
define('DB_USER', 'root');
define('DB_PASSWORD', 'Bhunee@@1315');
define('DB_HOST', '127.0.0.1');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

$table_prefix = 'wp_testelCWqR_';

define("WP_DEBUG", true);
define("WP_DEBUG", true);
define("WP_DEBUG", true);

// Security
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', true);
define('WP_AUTO_UPDATE_CORE', false);

// Performance
define('WP_CACHE', true);
define('WP_MEMORY_LIMIT', '128M');
define('WP_MAX_MEMORY_LIMIT', '256M');

// Multisite (if needed)
define('WP_ALLOW_MULTISITE', false);

// URLs
define('WP_HOME', 'http://localhost:8000');
define('WP_SITEURL', 'http://localhost:8000');

// Salts (generate unique ones)

define('AUTH_KEY', 'XVs5q93bi7QVWcoab2A8FIWowu8Q1eR3r1Sv12LPiMiJAWxNHWzrWLbPq5iU1fhF');
define('SECURE_AUTH_KEY', 'EUokkR5ObRmN67DfZoHEfSJNIoIRY13zCgVQzMao87FaTYRdFN3IGwRaSFwJaKbs');
define('LOGGED_IN_KEY', 'Dld6eueki4y22PrL1YDjSjbucpOsl6Uz4HSs8hucqrUcBDUn4LPHLD9fYG1Da3vX');
define('NONCE_KEY', '4ClgQpaBNSTvfeOPXoJUfVyvUJPplLLB1hg2kupvPJoTyOcQ4widfcV6O1YnCOq5');
define('AUTH_SALT', 'AbMLxjSJYiF8JDOlfMSMSDPDqb1pd43kT4SlqJ1q8RrzKSPQuJsOHakEZIMBWdah');
define('SECURE_AUTH_SALT', 'dntHW40JWZ0kVMIdMwWfFEFkrwa5L0nNu7mCaXr15RcLGEkGlMSM0dHOZDq6tFCy');
define('LOGGED_IN_SALT', 'iO4wmJfdGxYW9LYkKlD4596bwWjJc2oepXvSUyI4yprdYzsDiZgULX4kv5ykbfAp');
define('NONCE_SALT', 'pfMyNpqHRF7AO9YI5t1vMguBYo7MQXz0X5gmDKC1spJMwJXHacCSvU7reRWKHFhd');


// Resource limits for SaaS
define('WP_SAAS_SITE_ID', '10');

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

require_once(ABSPATH . 'wp-settings.php');