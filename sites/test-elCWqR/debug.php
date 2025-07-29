<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Information</h1>";
echo "<p>Current working directory: " . getcwd() . "</p>";
echo "<p>PHP version: " . phpversion() . "</p>";

try {
    echo "<h2>Database Test</h2>";
    $connection = new PDO('mysql:host=127.0.0.1;dbname=wp_saas_control', 'root', 'Bhunee@@1315');
    echo "<p style='color: green;'>✅ Database connection successful!</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
}

try {
    echo "<h2>WordPress Config Test</h2>";
    require_once('./wp-config.php');
    echo "<p style='color: green;'>✅ wp-config.php loaded!</p>";
    echo "<p>DB_HOST: " . DB_HOST . "</p>";
    echo "<p>DB_NAME: " . DB_NAME . "</p>";
    echo "<p>DB_USER: " . DB_USER . "</p>";
    echo "<p>Table Prefix: " . $table_prefix . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Config error: " . $e->getMessage() . "</p>";
}

try {
    echo "<h2>WordPress Bootstrap Test</h2>";
    define('WP_USE_THEMES', false);
    require_once('./wp-load.php');
    echo "<p style='color: green;'>✅ WordPress loaded successfully!</p>";
    echo "<p>Site URL: " . get_option('siteurl') . "</p>";
    echo "<p>Home URL: " . get_option('home') . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ WordPress error: " . $e->getMessage() . "</p>";
}
?>
EOF