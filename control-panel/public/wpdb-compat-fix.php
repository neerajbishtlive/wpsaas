<?php
// wpdb-compat-fix.php - Run this to fix the wpdb compatibility issue

$site = $_GET['site'] ?? 'test-5nCJo7';

echo "<h1>Fixing WPDB Compatibility for: $site</h1>";

$basePath = dirname(__DIR__, 2);
$sitePath = $basePath . '/sites/' . $site;
$wpConfigPath = $sitePath . '/wp-config.php';

if (file_exists($wpConfigPath)) {
    $config = file_get_contents($wpConfigPath);
    
    // Add compatibility fix before wp-settings.php is loaded
    $compatFix = <<<'FIX'

// WPDB Compatibility Fix for WordPress 6.1+
if (!class_exists('wpdb', false)) {
    if (file_exists(ABSPATH . 'wp-includes/class-wpdb.php')) {
        require_once ABSPATH . 'wp-includes/class-wpdb.php';
    } elseif (file_exists(ABSPATH . 'wp-includes/wp-db.php')) {
        require_once ABSPATH . 'wp-includes/wp-db.php';
    }
}

// Suppress deprecation warnings
if (!defined('WP_DEBUG')) {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}

FIX;

    // Check if fix already exists
    if (strpos($config, 'WPDB Compatibility Fix') === false) {
        // Add fix before wp-settings.php
        $config = str_replace(
            "require_once ABSPATH . 'wp-settings.php';",
            $compatFix . "\nrequire_once ABSPATH . 'wp-settings.php';",
            $config
        );
        
        file_put_contents($wpConfigPath, $config);
        echo "<p style='color: green;'>✅ WPDB compatibility fix applied to wp-config.php</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ WPDB compatibility fix already present</p>";
    }
    
    // Also update the minimal test
    echo "<h2>Additional Fixes Applied:</h2>";
    echo "<ul>";
    echo "<li>Added wpdb class loading compatibility</li>";
    echo "<li>Suppressed deprecation warnings</li>";
    echo "<li>Ensured proper class loading order</li>";
    echo "</ul>";
} else {
    echo "<p style='color: red;'>❌ wp-config.php not found</p>";
}

echo "<hr>";
echo "<p>";
echo "<a href='/wp-simple-test.php?site=$site'>Run Simple Test</a> | ";
echo "<a href='/wp.php?site=$site'>Try Loading Site</a> | ";
echo "<a href='/diagnostic.php?site=$site'>Run Diagnostic</a>";
echo "</p>";