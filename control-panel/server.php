<?php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$host = $_SERVER['HTTP_HOST'];

// Handle subdomains
if (preg_match('/^([a-zA-Z0-9-]+)\.(wptest\.local|wpsaas\.in)/', $host, $matches)) {
    $subdomain = $matches[1];
    
    if ($subdomain === 'control') {
        // Laravel control panel
        require_once __DIR__.'/public/index.php';
    } else {
        // WordPress sites
        $_SERVER['SCRIPT_NAME'] = '/wp.php';
        require_once __DIR__.'/public/wp.php';
    }
} else {
    // Default to control panel
    require_once __DIR__.'/public/index.php';
}