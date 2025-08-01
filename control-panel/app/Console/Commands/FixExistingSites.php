<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Site;

class FixExistingSites extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sites:fix';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix existing sites with admin plugin';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to fix existing sites...');
        
        $sites = Site::all();
        
        if ($sites->isEmpty()) {
            $this->warn('No sites found in database.');
            return 0;
        }
        
        foreach ($sites as $site) {
            $this->info("Fixing site: {$site->subdomain}");
            
            $sitePath = base_path('../sites/' . $site->subdomain);
            
            // Check if site directory exists
            if (!file_exists($sitePath)) {
                $this->error("Site directory not found: {$sitePath}");
                continue;
            }
            
            $muPluginsPath = $sitePath . '/wp-content/mu-plugins';
            
            // Create mu-plugins directory
            if (!file_exists($muPluginsPath)) {
                mkdir($muPluginsPath, 0755, true);
                $this->info("Created mu-plugins directory for {$site->subdomain}");
            }
            
            // Copy admin fix plugin
            $sourceFile = resource_path('wordpress/saas-admin-fix.php');
            $destFile = $muPluginsPath . '/saas-admin-fix.php';
            
            if (!file_exists($sourceFile)) {
                $this->error("Source file not found: {$sourceFile}");
                $this->info("Creating saas-admin-fix.php in resources directory...");
                
                // Create the resources/wordpress directory if it doesn't exist
                $resourceDir = resource_path('wordpress');
                if (!file_exists($resourceDir)) {
                    mkdir($resourceDir, 0755, true);
                }
                
                // Create the plugin file
                file_put_contents($sourceFile, $this->getAdminFixPluginContent());
                $this->info("Created source plugin file");
            }
            
            if (file_exists($sourceFile)) {
                copy($sourceFile, $destFile);
                chmod($destFile, 0644);
                $this->info("✓ Added admin fix plugin to {$site->subdomain}");
            }
        }
        
        $this->info('All sites fixed!');
        return 0;
    }
    
    /**
     * Get the admin fix plugin content
     */
    private function getAdminFixPluginContent()
    {
        return <<<'PHP'
<?php
/**
 * Plugin Name: SaaS Admin Fix
 * Description: Fixes admin permissions for multi-tenant WordPress setup
 * Version: 1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Fix admin detection early
add_action('muplugins_loaded', function() {
    if (is_admin() || strpos($_SERVER['REQUEST_URI'], '/wp-admin') !== false) {
        if (!defined('WP_ADMIN')) {
            define('WP_ADMIN', true);
        }
    }
});

// Override file system method
add_filter('filesystem_method', function() {
    return 'direct';
});

// Fix capability checks
add_filter('user_has_cap', function($allcaps, $caps, $args, $user) {
    // Only apply to logged-in users
    if (!$user || !$user->ID) {
        return $allcaps;
    }
    
    // Check if user is administrator
    if (isset($allcaps['administrator']) && $allcaps['administrator']) {
        // Grant all requested capabilities
        foreach ($caps as $cap) {
            $allcaps[$cap] = true;
        }
    }
    
    return $allcaps;
}, 10, 4);

// Fix admin menu registration
add_action('admin_menu', function() {
    global $menu, $submenu;
    
    // Ensure admin user can see all menus
    if (current_user_can('administrator')) {
        // Fix capability requirements for core menus
        if (isset($menu)) {
            foreach ($menu as $key => $item) {
                if (isset($item[1])) {
                    $menu[$key][1] = 'read';
                }
            }
        }
    }
}, 999);

// Fix admin ajax
add_action('admin_init', function() {
    if (defined('DOING_AJAX') && DOING_AJAX) {
        // Allow all ajax actions for administrators
        if (current_user_can('administrator')) {
            add_filter('user_has_cap', function($caps) {
                return array_merge($caps, ['manage_options' => true]);
            }, 999);
        }
    }
});

// Fix file permissions checks
add_filter('file_mod_allowed', function($allowed, $context) {
    if (current_user_can('administrator')) {
        return true;
    }
    return $allowed;
}, 10, 2);

// Fix theme and plugin installation
add_filter('upgrader_pre_install', function($response, $hook_extra) {
    if (current_user_can('administrator')) {
        return true;
    }
    return $response;
}, 10, 2);

// Debug helper
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('admin_notices', function() {
        if (current_user_can('administrator')) {
            echo '<div class="notice notice-info"><p>SaaS Admin Fix is active. User ID: ' . get_current_user_id() . '</p></div>';
        }
    });
}
PHP;
    }
}