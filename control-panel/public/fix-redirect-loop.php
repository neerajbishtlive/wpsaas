<?php
// fix-redirect-loop.php - Place in control-panel/public/

error_reporting(E_ALL);
ini_set('display_errors', 1);

$site = $_GET['site'] ?? 'test-5nCJo7';

echo "<h1>Fix WordPress Redirect Loop</h1>";
echo "<h2>Site: $site</h2>";
echo "<hr>";

// Database configuration
$dbHost = '127.0.0.1';
$dbName = 'wp_saas_control';
$dbUser = 'root';
$dbPass = 'Bhunee@@1315';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $tablePrefix = 'wp_' . str_replace('-', '', $site) . '_';
    
    echo "<h3>Current URL Settings:</h3>";
    
    // Get current URLs
    $stmt = $pdo->prepare("SELECT option_name, option_value FROM {$tablePrefix}options WHERE option_name IN ('siteurl', 'home')");
    $stmt->execute();
    $urls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Option</th><th>Current Value</th></tr>";
    foreach ($urls as $url) {
        echo "<tr><td>{$url['option_name']}</td><td>{$url['option_value']}</td></tr>";
    }
    echo "</table>";
    
    // Fix URLs to use absolute path
    $baseUrl = "http://localhost:8000";
    
    echo "<h3>Fixing URLs...</h3>";
    
    // Method 1: Set both URLs to the same value (no query string)
    $stmt = $pdo->prepare("UPDATE {$tablePrefix}options SET option_value = ? WHERE option_name = 'siteurl'");
    $stmt->execute([$baseUrl]);
    
    $stmt = $pdo->prepare("UPDATE {$tablePrefix}options SET option_value = ? WHERE option_name = 'home'");
    $stmt->execute([$baseUrl]);
    
    echo "<p>✅ Set both siteurl and home to: $baseUrl</p>";
    
    // Clear rewrite rules
    $stmt = $pdo->prepare("UPDATE {$tablePrefix}options SET option_value = '' WHERE option_name = 'rewrite_rules'");
    $stmt->execute();
    echo "<p>✅ Cleared rewrite rules</p>";
    
    // Disable SSL redirect
    $stmt = $pdo->prepare("DELETE FROM {$tablePrefix}options WHERE option_name = 'force_ssl_admin'");
    $stmt->execute();
    echo "<p>✅ Disabled SSL admin redirect</p>";
    
    // Clear any redirect-related options
    $stmt = $pdo->prepare("DELETE FROM {$tablePrefix}options WHERE option_name LIKE '%redirect%'");
    $stmt->execute();
    echo "<p>✅ Cleared redirect options</p>";
    
    echo "<hr>";
    echo "<h3>Updated Configuration:</h3>";
    
    // Show updated URLs
    $stmt = $pdo->prepare("SELECT option_name, option_value FROM {$tablePrefix}options WHERE option_name IN ('siteurl', 'home')");
    $stmt->execute();
    $urls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Option</th><th>New Value</th></tr>";
    foreach ($urls as $url) {
        echo "<tr><td>{$url['option_name']}</td><td>{$url['option_value']}</td></tr>";
    }
    echo "</table>";
    
    // Update wp-config.php to match
    $basePath = dirname(__DIR__, 2);
    $sitePath = $basePath . '/sites/' . $site;
    $wpConfigPath = $sitePath . '/wp-config.php';
    
    if (file_exists($wpConfigPath)) {
        $config = file_get_contents($wpConfigPath);
        
        // Update or add URL definitions
        $urlConfig = "\n// Force URLs to prevent redirects\n";
        $urlConfig .= "define('WP_HOME', '$baseUrl');\n";
        $urlConfig .= "define('WP_SITEURL', '$baseUrl');\n";
        $urlConfig .= "define('FORCE_SSL_ADMIN', false);\n";
        $urlConfig .= "define('CONCATENATE_SCRIPTS', false);\n\n";
        
        // Remove existing definitions
        $config = preg_replace("/define\s*\(\s*'WP_HOME'[^;]+;\s*/", "", $config);
        $config = preg_replace("/define\s*\(\s*'WP_SITEURL'[^;]+;\s*/", "", $config);
        
        // Add new definitions before table_prefix
        $config = str_replace('$table_prefix', $urlConfig . '$table_prefix', $config);
        
        file_put_contents($wpConfigPath, $config);
        echo "<p>✅ Updated wp-config.php</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>Clear your browser cookies for localhost</li>";
echo "<li>Use the simplified loader below</li>";
echo "</ol>";

// Create a simple loader link
$loaderUrl = "/wp-simple-loader.php?site=$site";
echo "<p><a href='$loaderUrl' style='padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px;'>Open with Simple Loader</a></p>";