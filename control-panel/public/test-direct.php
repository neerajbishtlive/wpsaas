<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$site = 'demo-site-LmczLn';
$wpConfig = dirname(__DIR__, 2) . '/sites/' . $site . '/wp-config.php';
$wpCore = dirname(__DIR__, 2) . '/wordpress-core';

$_SERVER['HTTP_HOST'] = 'localhost:8000';
$_SERVER['REQUEST_URI'] = '/';

chdir($wpCore);
require $wpConfig;

echo "WordPress loaded. Site: " . get_option('blogname');
