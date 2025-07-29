<?php
// Save as wp-direct.php in control-panel/public/
// This bypasses complex routing and loads WordPress directly

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration
$site = $_GET['site'] ?? 'test-5nCJo7';
$projectRoot = '/Users/neerajbisht/Desktop/Diploy/GIT/wp-saas-platform';

// Define all paths
define('SITE_DIR', $projectRoot . '/sites/' . $site);
define('WP_CORE_DIR', $projectRoot . '/wordpress-core');

// Check if site exists
if (!is_dir(SITE_DIR)) {
    die('Site directory not found: ' . SITE_DIR);
}

// Set up WordPress environment
define('WP_USE_THEMES', true);
define('WP_CONTENT_DIR', SITE_DIR . '/wp-content');
define('WP_CONTENT_URL', 'http://localhost:8000/sites/' . $site . '/wp-content');

// CRITICAL: Set these before wp-config loads
define('WP_HOME', 'http://localhost:8000/wp-direct.php?site=' . $site);
define('WP_SITEURL', 'http://localhost:8000/wp-direct.php?site=' . $site);

// Set server variables
$_SERVER['HTTP_HOST'] = 'localhost:8000';
$_SERVER['PHP_SELF'] = '/wp-direct.php';
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['SCRIPT_NAME'] = '/wp-direct.php';

// Load wp-config from site directory
chdir(SITE_DIR);
if (file_exists('wp-config.php')) {
    require_once 'wp-config.php';
} else {
    die('wp-config.php not found in: ' . SITE_DIR);
}

// Switch to WordPress core and load it
chdir(WP_CORE_DIR);

// Route based on request
$path = $_GET['path'] ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

if (strpos($path, 'wp-admin') !== false) {
    // Admin request
    define('WP_ADMIN', true);
    require 'wp-admin/admin.php';
} elseif (strpos($path, 'wp-login.php') !== false) {
    // Login page
    require 'wp-login.php';
} else {
    // Frontend
    require 'index.php';
}