<?php

namespace App\Services;

class WordPressBootstrap
{
    private $subdomain;
    private $sitePath;
    private $corePath;
    
    public function __construct($subdomain)
    {
        $this->subdomain = $subdomain;
        $this->sitePath = base_path('../sites/' . $subdomain);
        $this->corePath = base_path('../wordpress-core');
    }
    
    public function boot()
    {
        // Check if site exists
        if (!file_exists($this->sitePath . '/wp-config.php')) {
            die('Site not found');
        }
        
        // Prevent function conflicts between Laravel and WordPress
        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }
        
        // Set up WordPress environment
        $this->setupEnvironment();
        
        // Load WordPress
        $this->loadWordPress();
    }
    
    private function setupEnvironment()
    {
        // Set server variables WordPress expects
        $_SERVER['DOCUMENT_ROOT'] = $this->corePath;
        
        // Fix REQUEST_URI if needed
        if (!isset($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = substr($_SERVER['PHP_SELF'], 1);
            if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '') {
                $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
            }
        }
        
        // Set WordPress environment
        if (strpos($_SERVER['REQUEST_URI'], '/wp-admin') !== false) {
            $_SERVER['PHP_SELF'] = '/wp-admin/index.php';
        }
    }
    
    private function loadWordPress()
    {
        // Override table prefix
        $GLOBALS['table_prefix'] = 'wp_' . $this->subdomain . '_';
        
        // Define ABSPATH before loading wp-config
        if (!defined('ABSPATH')) {
            define('ABSPATH', $this->corePath . '/');
        }
        
        // Change to WordPress core directory
        $originalDir = getcwd();
        chdir($this->corePath);
        
        // Clear any Laravel output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Start fresh output buffer for WordPress
        ob_start();
        
        // Temporarily suppress errors during WordPress load
        $errorLevel = error_reporting();
        error_reporting(E_ERROR | E_PARSE);
        
        // Load site's wp-config.php (but skip wp-settings.php)
        $configContent = file_get_contents($this->sitePath . '/wp-config.php');
        $configContent = str_replace("require_once(ABSPATH . 'wp-settings.php');", '', $configContent);
        eval('?>' . $configContent);
        
        // Now load WordPress settings
        require_once(ABSPATH . 'wp-settings.php');
        
        // Restore error reporting
        error_reporting($errorLevel);
        
        // Restore directory
        chdir($originalDir);
    }
}