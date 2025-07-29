<?php
/**
 * WordPress Multi-tenant Handler with Subdomain Support
 * This version supports both subdomain and query parameter access
 */

// Load Laravel to get configuration
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

// Get domain configuration
$saas_domain = config('saas.current_domain');
if (is_callable($saas_domain)) {
    $saas_domain = $saas_domain();
}

// Extract subdomain from host or query parameter
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$subdomain = '';

// Remove port from host if present
$host_without_port = preg_replace('/:\d+$/', '', $host);

// Try to extract subdomain from host
if (preg_match('/^([a-zA-Z0-9-]+)\.' . preg_quote($saas_domain, '/') . '$/i', $host_without_port, $matches)) {
    $subdomain = $matches[1];
} else {
    // Fallback to query parameter for backward compatibility
    $subdomain = $_GET['site'] ?? null;
}

if (!$subdomain || !preg_match('/^[a-zA-Z0-9-]+$/', $subdomain)) {
    die('Site not found. Please access via subdomain (e.g., http://yoursite.' . $saas_domain . ')');
}

// Validate site exists in database
use App\Models\Site;
$site = Site::where('subdomain', $subdomain)->first();

if (!$site) {
    header('HTTP/1.1 404 Not Found');
    die('Site not found: ' . htmlspecialchars($subdomain));
}

if ($site->status !== 'active') {
    header('HTTP/1.1 503 Service Unavailable');
    die('This site is currently inactive.');
}

// Define paths
$basePath = dirname(__DIR__);
$sitePath = $basePath . '/sites/' . $subdomain;
$wpConfigPath = $sitePath . '/wp-config.php';
$wpCorePath = $basePath . '/wordpress-core';

// Validate paths
if (!file_exists($wpConfigPath)) {
    header('HTTP/1.1 500 Internal Server Error');
    die('Site configuration not found. Please contact support.');
}

// Parse request
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

// Handle static assets
if (preg_match('/\.(css|js|jpg|jpeg|png|gif|svg|woff|woff2|ttf|eot|ico|webp)$/i', $requestUri)) {
    $filePath = null;
    
    if (strpos($requestUri, 'wp-content') !== false && preg_match('/wp-content\/(.+)/', $requestUri, $matches)) {
        $filePath = $sitePath . '/wp-content/' . $matches[1];
    } elseif (strpos($requestUri, 'wp-includes') !== false && preg_match('/wp-includes\/(.+)/', $requestUri, $matches)) {
        $filePath = $wpCorePath . '/wp-includes/' . $matches[1];
    } elseif (strpos($requestUri, 'wp-admin') !== false && preg_match('/wp-admin\/(.+)/', $requestUri, $matches)) {
        $filePath = $wpCorePath . '/wp-admin/' . $matches[1];
    }
    
    if ($filePath) {
        $filePath = strtok($filePath, '?');
        if (file_exists($filePath) && is_file($filePath)) {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $mimeTypes = [
                'css' => 'text/css',
                'js' => 'application/javascript',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                'ico' => 'image/x-icon',
                'woff' => 'font/woff',
                'woff2' => 'font/woff2',
                'ttf' => 'font/ttf',
                'eot' => 'application/vnd.ms-fontobject',
                'webp' => 'image/webp',
            ];
            
            header('Content-Type: ' . ($mimeTypes[$ext] ?? 'application/octet-stream'));
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: public, max-age=31536000');
            readfile($filePath);
            exit;
        }
    }
}

// Set up environment for WordPress
$_SERVER['HTTP_HOST'] = $host;
$_SERVER['SERVER_NAME'] = $host_without_port;

// Determine protocol
if (app()->environment('local')) {
    $_SERVER['HTTPS'] = 'off';
} else {
    $_SERVER['HTTPS'] = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'on' : 'off';
}

// Set the site context
$_SERVER['WP_SAAS_SITE'] = $subdomain;
define('WP_SAAS_SITE_PATH', $sitePath);

// Check if admin request
$isAdmin = isset($_GET['wp-admin']) || strpos($requestUri, 'wp-admin') !== false;

if ($isAdmin) {
    $_SERVER['REQUEST_URI'] = '/wp-admin/';
    $_SERVER['PHP_SELF'] = '/wp-admin/index.php';
    define('WP_ADMIN', true);
} else {
    // Clean up the request URI (remove site parameter if present)
    $_SERVER['REQUEST_URI'] = preg_replace('/[?&]site=[^&]*/', '', $requestUri);
    if ($_SERVER['REQUEST_URI'] === '') {
        $_SERVER['REQUEST_URI'] = '/';
    }
    $_SERVER['PHP_SELF'] = '/index.php';
}

// Change directory to WordPress core
chdir($wpCorePath);

// Load WordPress configuration
require_once $wpConfigPath;

// WordPress is already loaded by wp-settings.php in the config
// For frontend requests, we need to load the theme
if (!$isAdmin) {
    if (!defined('WP_USE_THEMES')) {
        define('WP_USE_THEMES', true);
    }
    
    // Load WordPress template
    if (file_exists(ABSPATH . 'wp-includes/template-loader.php')) {
        require_once ABSPATH . 'wp-includes/template-loader.php';
    }
}