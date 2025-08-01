<?php
// fix-new-site-only.php - Fix only the new site's config
// Place in control-panel/public/

$site = $_GET['site'] ?? 'demo-site-LmczLn';

// Copy the working config from old site and adapt it
$workingConfig = <<<'CONFIG'
<?php
/**
 * WordPress Configuration File
 * Site: SITE_ID_PLACEHOLDER
 */

// Database configuration
define('DB_NAME', 'wp_saas_control');
define('DB_USER', 'root');
define('DB_PASSWORD', 'Bhunee@@1315');
define('DB_HOST', '127.0.0.1');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

// Table prefix
$table_prefix = 'TABLE_PREFIX_PLACEHOLDER';

// WordPress Memory
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// URLs
define('WP_HOME', 'http://localhost:8000/wp.php?site=SITE_ID_PLACEHOLDER');
define('WP_SITEURL', 'http://localhost:8000/wp.php?site=SITE_ID_PLACEHOLDER');

// Content Directory
define('WP_CONTENT_DIR', dirname(__FILE__) . '/wp-content');
define('WP_CONTENT_URL', 'http://localhost:8000/sites/SITE_ID_PLACEHOLDER/wp-content');

// Debug
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', true);

// Disable file editing
define('DISALLOW_FILE_EDIT', true);

// Authentication Keys and Salts
define('AUTH_KEY',         'put your unique phrase here');
define('SECURE_AUTH_KEY',  'put your unique phrase here');
define('LOGGED_IN_KEY',    'put your unique phrase here');
define('NONCE_KEY',        'put your unique phrase here');
define('AUTH_SALT',        'put your unique phrase here');
define('SECURE_AUTH_SALT', 'put your unique phrase here');
define('LOGGED_IN_SALT',   'put your unique phrase here');
define('NONCE_SALT',       'put your unique phrase here');

// Absolute path to the WordPress directory
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__, 3) . '/wordpress-core/');
}

// Sets up WordPress vars and included files
require_once ABSPATH . 'wp-settings.php';
CONFIG;

// Replace placeholders
$tablePrefix = 'wp_' . str_replace('-', '', $site) . '_';
$workingConfig = str_replace('SITE_ID_PLACEHOLDER', $site, $workingConfig);
$workingConfig = str_replace('TABLE_PREFIX_PLACEHOLDER', $tablePrefix, $workingConfig);

// Write the config
$sitePath = dirname(__DIR__, 2) . '/sites/' . $site;
$configPath = $sitePath . '/wp-config.php';

// Backup current
copy($configPath, $configPath . '.backup-' . time());

// Write new config
file_put_contents($configPath, $workingConfig);

echo "<h1>Fixed config for: $site</h1>";
echo "<p>✅ Config updated with working format</p>";
echo "<p><a href='/wp.php?site=$site'>Test Site</a></p>";