<?php
// Direct admin access for testing
$subdomain = $_GET['site'] ?? 'final';

$projectRoot = dirname(__DIR__, 2);
$siteDir = $projectRoot . '/sites/' . $subdomain;
$wpCore = $projectRoot . '/wordpress-core';

// Create temp config
$config_content = '<?php require_once "' . $siteDir . '/wp-config.php"; ?>';
file_put_contents($wpCore . '/wp-config.php', $config_content);

// Change to WordPress directory
chdir($wpCore);

// Set this to avoid issues
$_SERVER['REQUEST_URI'] = '/wp-admin/';
$_SERVER['PHP_SELF'] = '/wp-admin/index.php';
define('WP_ADMIN', true);

// Load WordPress
require_once 'wp-admin/admin.php';

// Clean up
@unlink($wpCore . '/wp-config.php');