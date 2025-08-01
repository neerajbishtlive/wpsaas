<?php
// fix-wp-config.php - Fixes the wp-config to prevent double loading
// Place in control-panel/public/

$site = $_GET['site'] ?? 'test-5nCJo7';

echo "<h1>Fix wp-config.php for: $site</h1>";

$basePath = dirname(__DIR__, 2);
$sitePath = $basePath . '/sites/' . $site;
$wpConfigPath = $sitePath . '/wp-config.php';

if (!file_exists($wpConfigPath)) {
    die("wp-config.php not found for site: $site");
}

// Generate a proper wp-config.php that prevents double loading
$newConfig = <<<'CONFIG'
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
$table_prefix = 'TABLE_PREFIX_PLACEHOLDER';

// WordPress Memory
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// URLs - Set to base URL to prevent redirects
define('WP_HOME', 'http://localhost:8000');
define('WP_SITEURL', 'http://localhost:8000');

// Content Directory
define('WP_CONTENT_DIR', dirname(__FILE__) . '/wp-content');
define('WP_CONTENT_URL', 'http://localhost:8000/sites/SITE_ID_PLACEHOLDER/wp-content');

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
CONFIG;

// Replace placeholders
$tablePrefix = 'wp_' . str_replace('-', '', $site) . '_';
$newConfig = str_replace('TABLE_PREFIX_PLACEHOLDER', $tablePrefix, $newConfig);
$newConfig = str_replace('SITE_ID_PLACEHOLDER', $site, $newConfig);

// Backup old config
$backupPath = $wpConfigPath . '.backup-' . date('YmdHis');
copy($wpConfigPath, $backupPath);

// Write new config
file_put_contents($wpConfigPath, $newConfig);

echo "<p style='color: green;'>✅ wp-config.php has been fixed</p>";
echo "<p>Backup saved as: " . basename($backupPath) . "</p>";

echo "<h2>Changes Made:</h2>";
echo "<ul>";
echo "<li>Added double-loading prevention</li>";
echo "<li>Set URLs to base domain (no query strings)</li>";
echo "<li>Added proper ABSPATH check</li>";
echo "<li>Added WPINC check before loading wp-settings.php</li>";
echo "<li>Disabled canonical redirects</li>";
echo "</ul>";

echo "<h2>Next Steps:</h2>";
echo "<p>";
echo "<a href='/wp-view.php?site=$site' style='margin-right: 10px;'>View Site Data</a>";
echo "<a href='/wp.php?site=$site' style='margin-right: 10px;'>Try Loading Site</a>";
echo "<a href='/diagnostic.php?site=$site'>Run Diagnostic</a>";
echo "</p>";

// Also update database URLs
echo "<h2>Updating Database URLs...</h2>";

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=wp_saas_control', 'root', 'Bhunee@@1315');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Update URLs in database
    $stmt = $pdo->prepare("UPDATE {$tablePrefix}options SET option_value = 'http://localhost:8000' WHERE option_name IN ('siteurl', 'home')");
    $stmt->execute();
    
    echo "<p style='color: green;'>✅ Database URLs updated</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database update failed: " . $e->getMessage() . "</p>";
}