<?php
/**
 * WordPress Debug Script
 * Access: http://final.wptest.local:8000/wp-debug.php
 */

// Get subdomain
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!preg_match('/^([a-zA-Z0-9-]+)\.wptest\.local/', $host, $matches)) {
    die('Invalid domain');
}
$subdomain = $matches[1];

// Set paths
$projectRoot = dirname(__DIR__, 2);
$siteDir = $projectRoot . '/sites/' . $subdomain;
$wpCore = $projectRoot . '/wordpress-core';

// Load WordPress
chdir($wpCore);
$loader = '<?php require_once "' . $siteDir . '/wp-config.php"; ?>';
file_put_contents('wp-config.php', $loader);

// Load WordPress properly
require_once 'wp-load.php';

// After WordPress loads, check the user
echo "<h1>WordPress Debug Info for site: $subdomain</h1>";

// Check if WordPress loaded
echo "WordPress Version: " . (defined('ABSPATH') ? 'Loaded' : 'Not Loaded') . "<br>";
echo "ABSPATH: " . (defined('ABSPATH') ? ABSPATH : 'Not defined') . "<br><br>";

// Check current user
$user = wp_get_current_user();
echo "<h2>Current User:</h2>";
echo "ID: " . $user->ID . "<br>";
echo "Login: " . $user->user_login . "<br>";
echo "Email: " . $user->user_email . "<br>";

// Check capabilities
echo "<h2>User Capabilities:</h2>";
echo "Is Administrator: " . (current_user_can('administrator') ? 'YES' : 'NO') . "<br>";
echo "Can Manage Options: " . (current_user_can('manage_options') ? 'YES' : 'NO') . "<br>";
echo "Can Edit Posts: " . (current_user_can('edit_posts') ? 'YES' : 'NO') . "<br>";

// Check raw capabilities
echo "<h2>Raw Capabilities Array:</h2>";
echo "<pre>";
print_r($user->caps);
echo "</pre>";

// Check all capabilities
echo "<h2>All Capabilities:</h2>";
echo "<pre>";
print_r($user->allcaps);
echo "</pre>";

// Check table prefix
global $wpdb;
echo "<h2>Database Info:</h2>";
echo "Table Prefix: " . $wpdb->prefix . "<br>";
echo "Users Table: " . $wpdb->users . "<br>";
echo "Usermeta Table: " . $wpdb->usermeta . "<br>";

// Check what capability key WordPress is looking for
$cap_key = $wpdb->prefix . 'capabilities';
echo "Capability Key: " . $cap_key . "<br>";

// Check user meta directly
$user_caps = get_user_meta($user->ID, $cap_key, true);
echo "<h2>Capabilities from Database:</h2>";
echo "<pre>";
var_dump($user_caps);
echo "</pre>";

// Check if user_can works
echo "<h2>Direct Capability Checks:</h2>";
foreach(['administrator', 'manage_options', 'edit_dashboard', 'read'] as $cap) {
    echo "$cap: " . (user_can($user, $cap) ? 'YES' : 'NO') . "<br>";
}

// Clean up
@unlink('wp-config.php');