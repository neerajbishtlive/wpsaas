<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>WordPress Handler Test</h1>";

// Test 1: Basic PHP
echo "<h2>Test 1: PHP Working</h2>";
echo "PHP Version: " . phpversion() . "<br>";

// Test 2: Extract subdomain
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$host_without_port = preg_replace('/:\d+$/', '', $host);
$subdomain = '';

if (preg_match('/^([a-zA-Z0-9-]+)\.wptest\.local$/', $host_without_port, $matches)) {
    $subdomain = $matches[1];
}

echo "<h2>Test 2: Subdomain Detection</h2>";
echo "Host: $host<br>";
echo "Subdomain: $subdomain<br>";

// Test 3: Path calculation
$basePath = dirname(__DIR__, 2);
$sitePath = $basePath . '/sites/' . $subdomain;
$wpConfigPath = $sitePath . '/wp-config.php';
$wpCorePath = $basePath . '/wordpress-core';

echo "<h2>Test 3: Paths</h2>";
echo "Base Path: $basePath<br>";
echo "Site Path: $sitePath<br>";
echo "WP Config: $wpConfigPath<br>";
echo "WP Core: $wpCorePath<br>";

// Test 4: File existence
echo "<h2>Test 4: File Checks</h2>";
echo "Site directory exists: " . (is_dir($sitePath) ? 'YES' : 'NO') . "<br>";
echo "wp-config.php exists: " . (file_exists($wpConfigPath) ? 'YES' : 'NO') . "<br>";
echo "WordPress core exists: " . (is_dir($wpCorePath) ? 'YES' : 'NO') . "<br>";

// Test 5: Database connection
echo "<h2>Test 5: Database</h2>";
try {
    require_once __DIR__ . '/../vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();
    
    $db_host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $db_name = $_ENV['DB_DATABASE'] ?? 'wp_saas_control';
    $db_user = $_ENV['DB_USERNAME'] ?? 'root';
    $db_pass = $_ENV['DB_PASSWORD'] ?? '';
    
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    echo "Database connection: SUCCESS<br>";
    
    $stmt = $pdo->prepare("SELECT * FROM sites WHERE subdomain = ?");
    $stmt->execute([$subdomain]);
    $site = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($site) {
        echo "Site found in database: YES<br>";
        echo "Site status: " . $site['status'] . "<br>";
    } else {
        echo "Site found in database: NO<br>";
    }
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
}

// Test 6: Try loading wp-config
echo "<h2>Test 6: WordPress Config</h2>";
if (file_exists($wpConfigPath)) {
    echo "Attempting to include wp-config.php...<br>";
    
    // Set up basic environment
    $_SERVER['HTTP_HOST'] = $host;
    define('WP_USE_THEMES', false);
    
    // Try to include it
    try {
        // Don't actually execute it yet, just check
        $config_content = file_get_contents($wpConfigPath);
        echo "Config file size: " . strlen($config_content) . " bytes<br>";
        echo "Config contains ABSPATH: " . (strpos($config_content, 'ABSPATH') !== false ? 'YES' : 'NO') . "<br>";
    } catch (Exception $e) {
        echo "Error reading config: " . $e->getMessage() . "<br>";
    }
}

echo "<h2>Test Complete</h2>";
echo "<p>If all tests pass, the issue is in WordPress loading. Check the wp-config.php file.</p>";