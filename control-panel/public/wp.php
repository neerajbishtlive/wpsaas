<?php

// Prevent loading Laravel's helpers to avoid conflicts
define('WORDPRESS_LOADING', true);

// Get subdomain from host
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!preg_match('/^([a-zA-Z0-9-]+)\.wptest\.local/', $host, $matches)) {
    die('Invalid domain');
}

$subdomain = $matches[1];

// Skip control subdomain
if ($subdomain === 'control') {
    die('Invalid request');
}

// Check if it's a wp-content request
if (strpos($_SERVER['REQUEST_URI'], '/wp-content/') === 0) {
    $sitePath = dirname(__DIR__) . '/sites/' . $subdomain;
    $file = $sitePath . $_SERVER['REQUEST_URI'];
    
    if (file_exists($file) && !is_dir($file)) {
        // Serve static file
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
        ];
        
        $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
        
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

// Load WordPress directly without Laravel
$sitePath = dirname(__DIR__) . '/sites/' . $subdomain;
$wpCorePath = dirname(__DIR__) . '/wordpress-core';

if (!file_exists($sitePath . '/wp-config.php')) {
    die('Site not found');
}

// Set table prefix
$table_prefix = 'wp_' . $subdomain . '_';

// Define ABSPATH
define('ABSPATH', $wpCorePath . '/');

// Change to core directory
chdir($wpCorePath);

// Load site config
require_once($sitePath . '/wp-config.php');