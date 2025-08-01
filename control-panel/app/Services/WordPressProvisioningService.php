<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class WordPressProvisioningService
{
    private $wpCorePath;
    private $sitesPath;
    
    public function __construct()
    {
        // Use the paths from .env
        $this->wpCorePath = env('WP_CORE_PATH', '/Users/neerajbisht/Desktop/Diploy/GIT/wpsaas1/wordpress-core');
        $this->sitesPath = env('SITES_PATH', '/Users/neerajbisht/Desktop/Diploy/GIT/wpsaas1/sites');
        
        // Log the paths for debugging
        Log::info('WordPressProvisioningService initialized', [
            'wpCorePath' => $this->wpCorePath,
            'sitesPath' => $this->sitesPath
        ]);
    }
    
    /**
     * Create a new WordPress site
     */
    public function createSite($subdomain, $title, $adminUser, $adminEmail, $adminPassword)
    {
        try {
            // Set SQL mode to avoid datetime issues
            DB::statement("SET sql_mode = ''");
            
            // Validate subdomain
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $subdomain)) {
                throw new \Exception('Invalid subdomain format');
            }
            
            // Check if site already exists
            if (Site::where('subdomain', $subdomain)->exists()) {
                throw new \Exception('Site already exists');
            }
            
            // Generate database prefix
            $dbPrefix = 'wp_' . $subdomain . '_';
            
            // Create site directory
            $sitePath = $this->sitesPath . '/' . $subdomain;
            
            Log::info("Creating site: {$subdomain}", [
                'sitePath' => $sitePath,
                'dbPrefix' => $dbPrefix
            ]);
            
            // Create site directory structure
            $this->createSiteDirectories($sitePath);
            
            // Create wp-config.php
            $this->createWpConfig($sitePath, $subdomain, $dbPrefix);
            
            // Create WordPress database tables
            $this->createWordPressTables($dbPrefix);
            
            // Set up initial WordPress data
            $this->setupWordPressData($subdomain, $dbPrefix, $title, $adminUser, $adminEmail, $adminPassword);
            
            // Copy default theme
            $this->copyDefaultTheme($sitePath);
            
            // Create must-use plugin for admin fixes
            $this->createMustUsePlugin($sitePath);
            
            // Save site to Laravel database
            $site = Site::create([
                'user_id' => auth()->id() ?? 1,
                'subdomain' => $subdomain,
                'site_title' => $title,
                'admin_email' => $adminEmail,
                'admin_username' => $adminUser,
                'db_prefix' => $dbPrefix,
                'status' => 'active',
            ]);
            
            $siteUrl = $this->getSiteUrl($subdomain);
            
            Log::info("Site created successfully", [
                'subdomain' => $subdomain,
                'url' => $siteUrl
            ]);
            
            return [
                'success' => true,
                'site' => $site,
                'url' => $siteUrl,
                'admin_url' => $siteUrl . '/wp-admin',
                'credentials' => [
                    'username' => $adminUser,
                    'password' => $adminPassword
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error("Site creation failed", [
                'subdomain' => $subdomain,
                'error' => $e->getMessage()
            ]);
            
            // Clean up on failure
            if (isset($sitePath) && File::exists($sitePath)) {
                File::deleteDirectory($sitePath);
            }
            
            // Drop tables if created
            if (isset($dbPrefix)) {
                $this->dropTables($dbPrefix);
            }
            
            throw $e;
        }
    }
    
    /**
     * Create site directory structure
     */
    private function createSiteDirectories($sitePath)
    {
        $directories = [
            $sitePath,
            $sitePath . '/wp-content',
            $sitePath . '/wp-content/themes',
            $sitePath . '/wp-content/plugins',
            $sitePath . '/wp-content/uploads',
            $sitePath . '/wp-content/mu-plugins',
            $sitePath . '/wp-content/cache',
            $sitePath . '/wp-content/upgrade',
        ];
        
        foreach ($directories as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
                Log::info("Created directory: {$dir}");
            }
        }
        
        // Set proper permissions for uploads
        chmod($sitePath . '/wp-content/uploads', 0775);
    }
    
    /**
     * Create wp-config.php file
     */
    private function createWpConfig($sitePath, $subdomain, $dbPrefix)
{
    $siteUrl = $this->getSiteUrl($subdomain);
    
    $config = <<<PHP
<?php
/**
 * WordPress Configuration for {$subdomain}
 * Generated by WP SaaS Platform
 */

// Database settings
define('DB_NAME', '{$this->getDbName()}');
define('DB_USER', '{$this->getDbUser()}');
define('DB_PASSWORD', '{$this->getDbPassword()}');
define('DB_HOST', '{$this->getDbHost()}');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

// Table prefix is set by wp.php
if (!isset(\$table_prefix)) {
    \$table_prefix = '{$dbPrefix}';
}

// URLs
define('WP_HOME', '{$siteUrl}');
define('WP_SITEURL', '{$siteUrl}');

// Content directories - only define if not already defined
if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', '{$sitePath}/wp-content');
}
if (!defined('WP_CONTENT_URL')) {
    define('WP_CONTENT_URL', '{$siteUrl}/wp-content');
}

// Security Keys
define('AUTH_KEY',         '{$this->generateKey()}');
define('SECURE_AUTH_KEY',  '{$this->generateKey()}');
define('LOGGED_IN_KEY',    '{$this->generateKey()}');
define('NONCE_KEY',        '{$this->generateKey()}');
define('AUTH_SALT',        '{$this->generateKey()}');
define('SECURE_AUTH_SALT', '{$this->generateKey()}');
define('LOGGED_IN_SALT',   '{$this->generateKey()}');
define('NONCE_SALT',       '{$this->generateKey()}');

// File permissions
define('FS_METHOD', 'direct');
define('FS_CHMOD_DIR', (0755 & ~ umask()));
define('FS_CHMOD_FILE', (0644 & ~ umask()));

// Disable file editing
define('DISALLOW_FILE_EDIT', false);
define('DISALLOW_FILE_MODS', false);

// Debug settings
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', false);

// Performance
define('WP_MEMORY_LIMIT', '256M');
define('WP_MAX_MEMORY_LIMIT', '512M');

// Disable automatic updates for this multi-tenant setup
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_AUTO_UPDATE_CORE', false);

// Absolute path to WordPress - only define if not already defined
if (!defined('ABSPATH')) {
    define('ABSPATH', '{$this->wpCorePath}/');
}

// Load WordPress settings only if not already loaded
if (!defined('WPINC')) {
    require_once(ABSPATH . 'wp-settings.php');
}
PHP;
    
    $configPath = $sitePath . '/wp-config.php';
    file_put_contents($configPath, $config);
    chmod($configPath, 0644);
    
    Log::info("Created wp-config.php at: {$configPath}");
}
    /**
     * Helper methods for database credentials
     */
    private function getDbName()
    {
        return env('DB_DATABASE', 'wp_saas_control');
    }
    
    private function getDbUser()
    {
        return env('DB_USERNAME', 'root');
    }
    
    private function getDbPassword()
    {
        return env('DB_PASSWORD', '');
    }
    
    private function getDbHost()
    {
        return env('DB_HOST', '127.0.0.1');
    }
    
    /**
     * Generate secure key
     */
    private function generateKey()
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_ []{}<>~`+=,.;:/?|';
        $key = '';
        for ($i = 0; $i < 64; $i++) {
            $key .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $key;
    }
    
    /**
     * Create WordPress database tables
     */
    private function createWordPressTables($prefix)
    {
        Log::info("Creating WordPress tables with prefix: {$prefix}");
        
        // Create all WordPress tables
        $this->createOptionsTable($prefix);
        $this->createUsersTable($prefix);
        $this->createUsermetaTable($prefix);
        $this->createPostsTable($prefix);
        $this->createPostmetaTable($prefix);
        $this->createTermsTable($prefix);
        $this->createTermTaxonomyTable($prefix);
        $this->createTermRelationshipsTable($prefix);
        $this->createTermmetaTable($prefix);
        $this->createCommentsTable($prefix);
        $this->createCommentmetaTable($prefix);
        $this->createLinksTable($prefix);
        
        Log::info("All WordPress tables created successfully");
    }
    
    // Table creation methods
    private function createOptionsTable($prefix)
    {
        DB::statement("CREATE TABLE IF NOT EXISTS `{$prefix}options` (
            `option_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `option_name` varchar(191) NOT NULL DEFAULT '',
            `option_value` longtext NOT NULL,
            `autoload` varchar(20) NOT NULL DEFAULT 'yes',
            PRIMARY KEY (`option_id`),
            UNIQUE KEY `option_name` (`option_name`),
            KEY `autoload` (`autoload`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci");
    }
    
    private function createUsersTable($prefix)
    {
        DB::statement("CREATE TABLE IF NOT EXISTS `{$prefix}users` (
            `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_login` varchar(60) NOT NULL DEFAULT '',
            `user_pass` varchar(255) NOT NULL DEFAULT '',
            `user_nicename` varchar(50) NOT NULL DEFAULT '',
            `user_email` varchar(100) NOT NULL DEFAULT '',
            `user_url` varchar(100) NOT NULL DEFAULT '',
            `user_registered` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `user_activation_key` varchar(255) NOT NULL DEFAULT '',
            `user_status` int(11) NOT NULL DEFAULT '0',
            `display_name` varchar(250) NOT NULL DEFAULT '',
            PRIMARY KEY (`ID`),
            KEY `user_login_key` (`user_login`),
            KEY `user_nicename` (`user_nicename`),
            KEY `user_email` (`user_email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci");
    }
    
    private function createUsermetaTable($prefix)
    {
        DB::statement("CREATE TABLE IF NOT EXISTS `{$prefix}usermeta` (
            `umeta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` bigint(20) unsigned NOT NULL DEFAULT '0',
            `meta_key` varchar(255) DEFAULT NULL,
            `meta_value` longtext,
            PRIMARY KEY (`umeta_id`),
            KEY `user_id` (`user_id`),
            KEY `meta_key` (`meta_key`(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci");
    }
    
    private function createPostsTable($prefix)
    {
        DB::statement("CREATE TABLE IF NOT EXISTS `{$prefix}posts` (
            `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `post_author` bigint(20) unsigned NOT NULL DEFAULT '0',
            `post_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `post_date_gmt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `post_content` longtext NOT NULL,
            `post_title` text NOT NULL,
            `post_excerpt` text NOT NULL,
            `post_status` varchar(20) NOT NULL DEFAULT 'publish',
            `comment_status` varchar(20) NOT NULL DEFAULT 'open',
            `ping_status` varchar(20) NOT NULL DEFAULT 'open',
            `post_password` varchar(255) NOT NULL DEFAULT '',
            `post_name` varchar(200) NOT NULL DEFAULT '',
            `to_ping` text NOT NULL,
            `pinged` text NOT NULL,
            `post_modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `post_modified_gmt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `post_content_filtered` longtext NOT NULL,
            `post_parent` bigint(20) unsigned NOT NULL DEFAULT '0',
            `guid` varchar(255) NOT NULL DEFAULT '',
            `menu_order` int(11) NOT NULL DEFAULT '0',
            `post_type` varchar(20) NOT NULL DEFAULT 'post',
            `post_mime_type` varchar(100) NOT NULL DEFAULT '',
            `comment_count` bigint(20) NOT NULL DEFAULT '0',
            PRIMARY KEY (`ID`),
            KEY `post_name` (`post_name`(191)),
            KEY `type_status_date` (`post_type`,`post_status`,`post_date`,`ID`),
            KEY `post_parent` (`post_parent`),
            KEY `post_author` (`post_author`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci");
    }
    
    private function createPostmetaTable($prefix)
    {
        DB::statement("CREATE TABLE IF NOT EXISTS `{$prefix}postmeta` (
            `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `post_id` bigint(20) unsigned NOT NULL DEFAULT '0',
            `meta_key` varchar(255) DEFAULT NULL,
            `meta_value` longtext,
            PRIMARY KEY (`meta_id`),
            KEY `post_id` (`post_id`),
            KEY `meta_key` (`meta_key`(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci");
    }
    
    private function createTermsTable($prefix)
    {
        DB::statement("CREATE TABLE IF NOT EXISTS `{$prefix}terms` (
            `term_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(200) NOT NULL DEFAULT '',
            `slug` varchar(200) NOT NULL DEFAULT '',
            `term_group` bigint(10) NOT NULL DEFAULT '0',
            PRIMARY KEY (`term_id`),
            KEY `slug` (`slug`(191)),
            KEY `name` (`name`(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci");
    }
    
    private function createTermTaxonomyTable($prefix)
    {
        DB::statement("CREATE TABLE IF NOT EXISTS `{$prefix}term_taxonomy` (
            `term_taxonomy_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `term_id` bigint(20) unsigned NOT NULL DEFAULT '0',
            `taxonomy` varchar(32) NOT NULL DEFAULT '',
            `description` longtext NOT NULL,
            `parent` bigint(20) unsigned NOT NULL DEFAULT '0',
            `count` bigint(20) NOT NULL DEFAULT '0',
            PRIMARY KEY (`term_taxonomy_id`),
            UNIQUE KEY `term_id_taxonomy` (`term_id`,`taxonomy`),
            KEY `taxonomy` (`taxonomy`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci");
    }
    
    private function createTermRelationshipsTable($prefix)
    {
        DB::statement("CREATE TABLE IF NOT EXISTS `{$prefix}term_relationships` (
            `object_id` bigint(20) unsigned NOT NULL DEFAULT '0',
            `term_taxonomy_id` bigint(20) unsigned NOT NULL DEFAULT '0',
            `term_order` int(11) NOT NULL DEFAULT '0',
            PRIMARY KEY (`object_id`,`term_taxonomy_id`),
            KEY `term_taxonomy_id` (`term_taxonomy_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci");
    }
    
    private function createTermmetaTable($prefix)
    {
        DB::statement("CREATE TABLE IF NOT EXISTS `{$prefix}termmeta` (
            `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `term_id` bigint(20) unsigned NOT NULL DEFAULT '0',
            `meta_key` varchar(255) DEFAULT NULL,
            `meta_value` longtext,
            PRIMARY KEY (`meta_id`),
            KEY `term_id` (`term_id`),
            KEY `meta_key` (`meta_key`(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci");
    }
    
    private function createCommentsTable($prefix)
    {
        DB::statement("CREATE TABLE IF NOT EXISTS `{$prefix}comments` (
            `comment_ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `comment_post_ID` bigint(20) unsigned NOT NULL DEFAULT '0',
            `comment_author` tinytext NOT NULL,
            `comment_author_email` varchar(100) NOT NULL DEFAULT '',
            `comment_author_url` varchar(200) NOT NULL DEFAULT '',
            `comment_author_IP` varchar(100) NOT NULL DEFAULT '',
            `comment_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `comment_date_gmt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `comment_content` text NOT NULL,
            `comment_karma` int(11) NOT NULL DEFAULT '0',
            `comment_approved` varchar(20) NOT NULL DEFAULT '1',
            `comment_agent` varchar(255) NOT NULL DEFAULT '',
            `comment_type` varchar(20) NOT NULL DEFAULT 'comment',
            `comment_parent` bigint(20) unsigned NOT NULL DEFAULT '0',
            `user_id` bigint(20) unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (`comment_ID`),
            KEY `comment_post_ID` (`comment_post_ID`),
            KEY `comment_approved_date_gmt` (`comment_approved`,`comment_date_gmt`),
            KEY `comment_date_gmt` (`comment_date_gmt`),
            KEY `comment_parent` (`comment_parent`),
            KEY `comment_author_email` (`comment_author_email`(10))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci");
    }
    
    private function createCommentmetaTable($prefix)
    {
        DB::statement("CREATE TABLE IF NOT EXISTS `{$prefix}commentmeta` (
            `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `comment_id` bigint(20) unsigned NOT NULL DEFAULT '0',
            `meta_key` varchar(255) DEFAULT NULL,
            `meta_value` longtext,
            PRIMARY KEY (`meta_id`),
            KEY `comment_id` (`comment_id`),
            KEY `meta_key` (`meta_key`(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci");
    }
    
    private function createLinksTable($prefix)
    {
        DB::statement("CREATE TABLE IF NOT EXISTS `{$prefix}links` (
            `link_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `link_url` varchar(255) NOT NULL DEFAULT '',
            `link_name` varchar(255) NOT NULL DEFAULT '',
            `link_image` varchar(255) NOT NULL DEFAULT '',
            `link_target` varchar(25) NOT NULL DEFAULT '',
            `link_description` varchar(255) NOT NULL DEFAULT '',
            `link_visible` varchar(20) NOT NULL DEFAULT 'Y',
            `link_owner` bigint(20) unsigned NOT NULL DEFAULT '1',
            `link_rating` int(11) NOT NULL DEFAULT '0',
            `link_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `link_rel` varchar(255) NOT NULL DEFAULT '',
            `link_notes` mediumtext NOT NULL,
            `link_rss` varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY (`link_id`),
            KEY `link_visible` (`link_visible`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci");
    }
    
    /**
     * Set up initial WordPress data
     */
    private function setupWordPressData($subdomain, $prefix, $title, $adminUser, $adminEmail, $adminPassword)
    {
        Log::info("Setting up WordPress data for {$subdomain}");
        
        $now = now()->format('Y-m-d H:i:s');
        $siteUrl = $this->getSiteUrl($subdomain);
        
        // Insert essential options
        $options = [
            ['option_name' => 'siteurl', 'option_value' => $siteUrl, 'autoload' => 'yes'],
            ['option_name' => 'home', 'option_value' => $siteUrl, 'autoload' => 'yes'],
            ['option_name' => 'blogname', 'option_value' => $title, 'autoload' => 'yes'],
            ['option_name' => 'blogdescription', 'option_value' => 'Just another WordPress site', 'autoload' => 'yes'],
            ['option_name' => 'users_can_register', 'option_value' => '0', 'autoload' => 'yes'],
            ['option_name' => 'admin_email', 'option_value' => $adminEmail, 'autoload' => 'yes'],
            ['option_name' => 'template', 'option_value' => 'twentytwentyfour', 'autoload' => 'yes'],
            ['option_name' => 'stylesheet', 'option_value' => 'twentytwentyfour', 'autoload' => 'yes'],
            ['option_name' => 'current_theme', 'option_value' => 'Twenty Twenty-Four', 'autoload' => 'yes'],
            ['option_name' => 'active_plugins', 'option_value' => serialize([]), 'autoload' => 'yes'],
            ['option_name' => 'permalink_structure', 'option_value' => '/%postname%/', 'autoload' => 'yes'],
            ['option_name' => 'rewrite_rules', 'option_value' => '', 'autoload' => 'yes'],
            ['option_name' => 'db_version', 'option_value' => '57155', 'autoload' => 'yes'],
            ['option_name' => 'initial_db_version', 'option_value' => '57155', 'autoload' => 'yes'],
            ['option_name' => 'fresh_site', 'option_value' => '1', 'autoload' => 'yes'],
            ['option_name' => 'WPLANG', 'option_value' => '', 'autoload' => 'yes'],
            ['option_name' => 'timezone_string', 'option_value' => 'UTC', 'autoload' => 'yes'],
            ['option_name' => 'date_format', 'option_value' => 'F j, Y', 'autoload' => 'yes'],
            ['option_name' => 'time_format', 'option_value' => 'g:i a', 'autoload' => 'yes'],
            ['option_name' => 'start_of_week', 'option_value' => '1', 'autoload' => 'yes'],
        ];
        
        foreach ($options as $option) {
            DB::table($prefix . 'options')->insertOrIgnore($option);
        }
        
        // Create admin user with MD5 password (WordPress will auto-upgrade on first login)
        $userId = DB::table($prefix . 'users')->insertGetId([
            'user_login' => $adminUser,
            'user_pass' => md5($adminPassword),
            'user_nicename' => Str::slug($adminUser),
            'user_email' => $adminEmail,
            'user_url' => '',
            'user_registered' => $now,
            'user_activation_key' => '',
            'user_status' => 0,
            'display_name' => $adminUser,
        ]);
        
        // Set user capabilities and meta
        $userMeta = [
            ['user_id' => $userId, 'meta_key' => $prefix . 'capabilities', 'meta_value' => serialize(['administrator' => true])],
            ['user_id' => $userId, 'meta_key' => $prefix . 'user_level', 'meta_value' => '10'],
            ['user_id' => $userId, 'meta_key' => 'nickname', 'meta_value' => $adminUser],
            ['user_id' => $userId, 'meta_key' => 'first_name', 'meta_value' => ''],
            ['user_id' => $userId, 'meta_key' => 'last_name', 'meta_value' => ''],
            ['user_id' => $userId, 'meta_key' => 'description', 'meta_value' => ''],
            ['user_id' => $userId, 'meta_key' => 'rich_editing', 'meta_value' => 'true'],
            ['user_id' => $userId, 'meta_key' => 'syntax_highlighting', 'meta_value' => 'true'],
            ['user_id' => $userId, 'meta_key' => 'comment_shortcuts', 'meta_value' => 'false'],
            ['user_id' => $userId, 'meta_key' => 'admin_color', 'meta_value' => 'fresh'],
            ['user_id' => $userId, 'meta_key' => 'use_ssl', 'meta_value' => '0'],
            ['user_id' => $userId, 'meta_key' => 'show_admin_bar_front', 'meta_value' => 'true'],
            ['user_id' => $userId, 'meta_key' => 'locale', 'meta_value' => ''],
        ];
        
        DB::table($prefix . 'usermeta')->insert($userMeta);
        
        // Create default category
        $termId = DB::table($prefix . 'terms')->insertGetId([
            'name' => 'Uncategorized',
            'slug' => 'uncategorized',
            'term_group' => 0,
        ]);
        
        $termTaxonomyId = DB::table($prefix . 'term_taxonomy')->insertGetId([
            'term_id' => $termId,
            'taxonomy' => 'category',
            'description' => '',
            'parent' => 0,
            'count' => 1,
        ]);
        
        // Update default category option
        DB::table($prefix . 'options')->updateOrInsert(
            ['option_name' => 'default_category'],
            ['option_value' => $termId, 'autoload' => 'yes']
        );
        
        // Create first post
        $postId = DB::table($prefix . 'posts')->insertGetId([
            'post_author' => $userId,
            'post_date' => $now,
            'post_date_gmt' => $now,
            'post_content' => 'Welcome to your new WordPress site powered by WP SaaS Platform!',
            'post_title' => 'Hello World!',
            'post_excerpt' => '',
            'post_status' => 'publish',
            'comment_status' => 'open',
            'ping_status' => 'open',
            'post_password' => '',
            'post_name' => 'hello-world',
            'to_ping' => '',
            'pinged' => '',
            'post_modified' => $now,
            'post_modified_gmt' => $now,
            'post_content_filtered' => '',
            'post_parent' => 0,
            'guid' => $siteUrl . '/?p=1',
            'menu_order' => 0,
            'post_type' => 'post',
            'post_mime_type' => '',
            'comment_count' => 0,
        ]);
        
        // Add post to category
        DB::table($prefix . 'term_relationships')->insert([
            'object_id' => $postId,
            'term_taxonomy_id' => $termTaxonomyId,
            'term_order' => 0,
        ]);
        
        Log::info("WordPress data setup completed for {$subdomain}");
    }
    
    /**
     * Copy default theme
     */
    private function copyDefaultTheme($sitePath)
    {
        $defaultTheme = $this->wpCorePath . '/wp-content/themes/twentytwentyfour';
        $targetTheme = $sitePath . '/wp-content/themes/twentytwentyfour';
        
        if (File::exists($defaultTheme)) {
            if (!File::exists($targetTheme)) {
                File::copyDirectory($defaultTheme, $targetTheme);
                Log::info("Copied default theme to {$targetTheme}");
            }
        } else {
            Log::warning("Default theme not found at {$defaultTheme}");
        }
    }
    
    /**
     * Create must-use plugin for admin fixes
     */
    private function createMustUsePlugin($sitePath)
    {
        $muPluginContent = <<<'PHP'
<?php
/**
 * Plugin Name: WP SaaS Admin Fix
 * Description: Fixes admin permissions and functionality for multi-tenant WordPress setup
 * Version: 1.0
 * Author: WP SaaS Platform
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Fix admin detection
add_action('muplugins_loaded', function() {
    if (is_admin() || strpos($_SERVER['REQUEST_URI'], '/wp-admin') !== false) {
        if (!defined('WP_ADMIN')) {
            define('WP_ADMIN', true);
        }
    }
});

// Override file system method to direct
add_filter('filesystem_method', function() {
    return 'direct';
});

// Fix capability checks for administrators
add_filter('user_has_cap', function($allcaps, $caps, $args, $user) {
    if (!$user || !isset($user->ID) || !$user->ID) {
        return $allcaps;
    }
    
    // If user is administrator, grant all requested capabilities
    if (isset($allcaps['administrator']) && $allcaps['administrator']) {
        foreach ($caps as $cap) {
            $allcaps[$cap] = true;
        }
    }
    
    return $allcaps;
}, 10, 4);

// Fix file modification permissions
add_filter('file_mod_allowed', function($allowed, $context) {
    if (current_user_can('administrator')) {
        return true;
    }
    return $allowed;
}, 10, 2);

// Fix admin menu permissions
add_action('admin_menu', function() {
    if (!current_user_can('administrator')) {
        return;
    }
    
    global $menu, $submenu;
    
    // Ensure all admin menus are accessible
    if (is_array($menu)) {
        foreach ($menu as $key => $item) {
            if (isset($item[1]) && $item[1] !== 'read') {
                $menu[$key][1] = 'read';
            }
        }
    }
}, 999);

// Fix theme and plugin installation
add_filter('upgrader_pre_install', function($response, $hook_extra) {
    if (current_user_can('administrator')) {
        return true;
    }
    return $response;
}, 10, 2);

// Ensure admin has all capabilities
add_filter('map_meta_cap', function($caps, $cap, $user_id, $args) {
    if ($user_id && user_can($user_id, 'administrator')) {
        return ['exist'];
    }
    return $caps;
}, 10, 4);

// Fix nonce verification for admin actions
add_filter('nonce_user_logged_out', function($uid, $action) {
    if (strpos($action, 'wp_rest') !== false) {
        return 0;
    }
    return $uid;
}, 10, 2);
PHP;
        
        $muPluginPath = $sitePath . '/wp-content/mu-plugins/saas-admin-fix.php';
        file_put_contents($muPluginPath, $muPluginContent);
        chmod($muPluginPath, 0644);
        
        Log::info("Created must-use plugin at {$muPluginPath}");
    }
    
    /**
     * Get site URL based on environment
     */
    private function getSiteUrl($subdomain)
    {
        if (app()->environment('local')) {
            $domain = env('APP_LOCAL_DOMAIN', 'wptest.local');
            return 'http://' . $subdomain . '.' . $domain . ':8000';
        }
        
        $domain = env('APP_DOMAIN', 'wpsaas.in');
        $protocol = env('APP_PROTOCOL', 'https');
        return $protocol . '://' . $subdomain . '.' . $domain;
    }
    
    /**
     * Drop tables on failure
     */
    private function dropTables($prefix)
    {
        $tables = [
            'options', 'users', 'usermeta', 'posts', 'postmeta', 'terms',
            'term_taxonomy', 'term_relationships', 'termmeta', 'comments',
            'commentmeta', 'links'
        ];
        
        foreach ($tables as $table) {
            try {
                DB::statement("DROP TABLE IF EXISTS `{$prefix}{$table}`");
            } catch (\Exception $e) {
                Log::warning("Failed to drop table {$prefix}{$table}: " . $e->getMessage());
            }
        }
        
        Log::info("Dropped all tables with prefix: {$prefix}");
    }
}