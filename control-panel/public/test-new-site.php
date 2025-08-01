<?php
// test-new-site.php - Test the newly created site
// Place in control-panel/public/

error_reporting(E_ALL);
ini_set('display_errors', 1);

$site = $_GET['site'] ?? 'demo-site-LmczLn';

echo "<h1>Testing Site: $site</h1>";

// Check paths
$basePath = dirname(__DIR__, 2);
$sitePath = $basePath . '/sites/' . $site;
$wpConfigPath = $sitePath . '/wp-config.php';
$wpCorePath = $basePath . '/wordpress-core';

echo "<h2>1. Path Checks</h2>";
echo "<pre>";
echo "Site Path: $sitePath - " . (is_dir($sitePath) ? "✅ EXISTS" : "❌ MISSING") . "\n";
echo "Config: $wpConfigPath - " . (file_exists($wpConfigPath) ? "✅ EXISTS" : "❌ MISSING") . "\n";
echo "WP Core: $wpCorePath - " . (is_dir($wpCorePath) ? "✅ EXISTS" : "❌ MISSING") . "\n";
echo "</pre>";

// Check wp-content
echo "<h2>2. Content Directory</h2>";
$wpContent = $sitePath . '/wp-content';
echo "<pre>";
echo "wp-content: $wpContent - " . (is_dir($wpContent) ? "✅ EXISTS" : "❌ MISSING") . "\n";
if (is_dir($wpContent)) {
    echo "- themes: " . (is_dir($wpContent . '/themes') ? "✅" : "❌") . "\n";
    echo "- plugins: " . (is_dir($wpContent . '/plugins') ? "✅" : "❌") . "\n";
    echo "- uploads: " . (is_dir($wpContent . '/uploads') ? "✅" : "❌") . "\n";
}
echo "</pre>";

// Read wp-config content
if (file_exists($wpConfigPath)) {
    echo "<h2>3. Config File Check</h2>";
    $config = file_get_contents($wpConfigPath);
    
    // Check for important constants
    $checks = [
        'DB_NAME' => strpos($config, "define('DB_NAME'") !== false,
        'DB_USER' => strpos($config, "define('DB_USER'") !== false,
        'WP_HOME' => strpos($config, "define('WP_HOME'") !== false,
        'ABSPATH' => strpos($config, "define('ABSPATH'") !== false,
        'table_prefix' => strpos($config, '$table_prefix') !== false,
    ];
    
    echo "<pre>";
    foreach ($checks as $constant => $exists) {
        echo "$constant: " . ($exists ? "✅ DEFINED" : "❌ MISSING") . "\n";
    }
    echo "</pre>";
}

// Database check
echo "<h2>4. Database Check</h2>";
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=wp_saas_control', 'root', 'Bhunee@@1315');
    $tablePrefix = 'wp_' . str_replace('-', '', $site) . '_';
    
    $stmt = $pdo->query("SHOW TABLES LIKE '{$tablePrefix}%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<pre>";
    echo "Table Prefix: $tablePrefix\n";
    echo "Tables Found: " . count($tables) . "\n";
    if (count($tables) > 0) {
        echo "\nTables:\n";
        foreach ($tables as $table) {
            echo "- $table\n";
        }
    }
    echo "</pre>";
    
    // Check options
    if (in_array($tablePrefix . 'options', $tables)) {
        $stmt = $pdo->prepare("SELECT option_name, option_value FROM {$tablePrefix}options WHERE option_name IN ('siteurl', 'home', 'blogname')");
        $stmt->execute();
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Site Options:</h3>";
        echo "<pre>";
        foreach ($options as $option) {
            echo $option['option_name'] . ": " . $option['option_value'] . "\n";
        }
        echo "</pre>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
}

// Test loading WordPress
echo "<h2>5. WordPress Load Test</h2>";

if (file_exists($wpConfigPath)) {
    // Change to WP directory
    $originalDir = getcwd();
    chdir($wpCorePath);
    
    // Set minimal environment
    $_SERVER['HTTP_HOST'] = 'localhost:8000';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['PHP_SELF'] = '/index.php';
    
    // Try loading config
    ob_start();
    $error = null;
    
    try {
        if (!defined('ABSPATH')) {
            define('ABSPATH', $wpCorePath . '/');
        }
        
        // Suppress errors temporarily
        $old_error_reporting = error_reporting(E_ERROR | E_PARSE);
        
        require_once $wpConfigPath;
        
        error_reporting($old_error_reporting);
        
        echo "<p style='color: green;'>✅ Config loaded successfully</p>";
        
        // Check if WP is loaded
        if (function_exists('get_option')) {
            echo "<p style='color: green;'>✅ WordPress functions available</p>";
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
    
    $output = ob_get_clean();
    
    if ($error) {
        echo "<p style='color: red;'>❌ Error: $error</p>";
    }
    
    if ($output && trim($output)) {
        echo "<h3>Output during load:</h3>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    }
    
    chdir($originalDir);
}

echo "<hr>";
echo "<h2>Actions:</h2>";
echo "<p>";
echo "<a href='/wp-view.php?site=$site'>View Site Data</a> | ";
echo "<a href='/fix-wp-config.php?site=$site'>Fix Config</a> | ";
echo "<a href='/wp.php?site=$site&debug=1'>Try Loading with Debug</a>";
echo "</p>";