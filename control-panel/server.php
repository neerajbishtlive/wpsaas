<?php
/**
 * Custom development server for handling subdomains
 * Place this in control-panel/server.php
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// Get host
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$host_without_port = preg_replace('/:\d+$/', '', $host);

// Check if this is a subdomain request (not control panel)
if (preg_match('/^([a-zA-Z0-9-]+)\.wptest\.local$/', $host_without_port, $matches)) {
    $subdomain = $matches[1];
    
    // If it's the control subdomain, route to Laravel
    if ($subdomain === 'control') {
        // Check if requesting a static file
        if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
            return false;
        }
        require_once __DIR__.'/public/index.php';
        return;
    }
    
    // Otherwise, it's a WordPress site - route to wp.php
    $_SERVER['REQUEST_URI'] = '/wp.php' . ($_SERVER['REQUEST_URI'] ?? '/');
    require_once __DIR__.'/public/wp.php';
    return;
}

// Default behavior - check for static files
if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

// Otherwise, route to Laravel
require_once __DIR__.'/public/index.php';