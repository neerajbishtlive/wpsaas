<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class WordPressProvisioningService
{
    protected $wpCorePath;
    protected $sitesPath;
    protected $dbConnection;

    public function __construct()
    {
        $this->wpCorePath = base_path('../wordpress-core');
        $this->sitesPath = base_path('../sites');
        $this->dbConnection = config('database.default');
    }

    /**
     * Create a new WordPress site
     */
    public function createSite($subdomain, $siteTitle, $adminUser, $adminEmail, $adminPassword)
    {
        try {
            // Generate unique identifiers
            $siteId = $this->generateSiteId($subdomain);
            $tablePrefix = 'wp_' . str_replace('-', '', $siteId) . '_';
            
            // Create site directory structure
            $siteDir = $this->createSiteDirectory($siteId);
            
            // Generate wp-config.php
            $this->generateWpConfig($siteId, $siteDir, $tablePrefix);
            
            // Create WordPress database tables
            $this->createWordPressTables($tablePrefix);
            
            // Setup WordPress site
            $this->setupWordPressSite($tablePrefix, $siteTitle, $adminUser, $adminEmail, $adminPassword, $siteId);
            
            // Create wp-content structure
            $this->createWpContentStructure($siteDir);
            
            // Set proper permissions
            $this->setPermissions($siteDir);
            
            return [
                'success' => true,
                'site_id' => $siteId,
                'site_url' => $this->getSiteUrl($siteId),
                'admin_url' => $this->getAdminUrl($siteId),
                'message' => 'WordPress site created successfully'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Generate unique site ID
     */
    protected function generateSiteId($subdomain)
    {
        return $subdomain . '-' . Str::random(6);
    }

    /**
     * Create site directory structure
     */
    protected function createSiteDirectory($siteId)
    {
        $siteDir = $this->sitesPath . '/' . $siteId;
        
        if (!File::exists($siteDir)) {
            File::makeDirectory($siteDir, 0755, true);
        }
        
        return $siteDir;
    }

    /**
     * Generate wp-config.php from template
     */
    protected function generateWpConfig($siteId, $siteDir, $tablePrefix)
    {
        // Generate security keys
        $keys = $this->generateSecurityKeys();
        
        // Create the config content directly
        $config = '<?php
/**
 * WordPress Configuration for Site: ' . $siteId . '
 * Generated: ' . date('Y-m-d H:i:s') . '
 */

// Prevent double loading
if (defined(\'WP_CONFIG_LOADED\')) {
    return;
}
define(\'WP_CONFIG_LOADED\', true);

// Database configuration
define(\'DB_NAME\', \'' . env('DB_DATABASE') . '\');
define(\'DB_USER\', \'' . env('DB_USERNAME') . '\');
define(\'DB_PASSWORD\', \'' . env('DB_PASSWORD') . '\');
define(\'DB_HOST\', \'' . env('DB_HOST', '127.0.0.1') . '\');
define(\'DB_CHARSET\', \'utf8mb4\');
define(\'DB_COLLATE\', \'\');

// Table prefix for this site
$table_prefix = \'' . $tablePrefix . '\';

// URLs - Use base URL to prevent redirects
define(\'WP_HOME\', \'http://localhost:8000\');
define(\'WP_SITEURL\', \'http://localhost:8000\');

// Content directories
define(\'WP_CONTENT_DIR\', __DIR__ . \'/wp-content\');
define(\'WP_CONTENT_URL\', \'http://localhost:8000/sites/' . $siteId . '/wp-content\');

// Disable redirects
define(\'REDIRECT_CANONICAL\', false);
define(\'WP_DISABLE_FATAL_ERROR_HANDLER\', true);

// Debug settings
define(\'WP_DEBUG\', false);
define(\'WP_DEBUG_LOG\', true);
define(\'WP_DEBUG_DISPLAY\', false);
define(\'SCRIPT_DEBUG\', false);

// Memory limits
define(\'WP_MEMORY_LIMIT\', \'256M\');
define(\'WP_MAX_MEMORY_LIMIT\', \'512M\');

// Security
define(\'DISALLOW_FILE_EDIT\', true);
define(\'DISALLOW_FILE_MODS\', false);
define(\'FORCE_SSL_ADMIN\', false);

// Authentication Keys and Salts
define(\'AUTH_KEY\',         \'' . $keys['AUTH_KEY'] . '\');
define(\'SECURE_AUTH_KEY\',  \'' . $keys['SECURE_AUTH_KEY'] . '\');
define(\'LOGGED_IN_KEY\',    \'' . $keys['LOGGED_IN_KEY'] . '\');
define(\'NONCE_KEY\',        \'' . $keys['NONCE_KEY'] . '\');
define(\'AUTH_SALT\',        \'' . $keys['AUTH_SALT'] . '\');
define(\'SECURE_AUTH_SALT\', \'' . $keys['SECURE_AUTH_SALT'] . '\');
define(\'LOGGED_IN_SALT\',   \'' . $keys['LOGGED_IN_SALT'] . '\');
define(\'NONCE_SALT\',       \'' . $keys['NONCE_SALT'] . '\');

// Performance
define(\'CONCATENATE_SCRIPTS\', false);
define(\'COMPRESS_CSS\', true);
define(\'COMPRESS_SCRIPTS\', true);
define(\'ENFORCE_GZIP\', true);

// Misc settings
define(\'WP_POST_REVISIONS\', 5);
define(\'EMPTY_TRASH_DAYS\', 30);
define(\'AUTOSAVE_INTERVAL\', 160);
define(\'WP_ALLOW_MULTISITE\', false);

// Absolute path to the WordPress directory
if (!defined(\'ABSPATH\')) {
    define(\'ABSPATH\', dirname(__FILE__, 3) . \'/wordpress-core/\');
}

// Sets up WordPress vars and included files
if (!defined(\'WPINC\')) {
    require_once ABSPATH . \'wp-settings.php\';
}';
        
        // Write config file
        File::put($siteDir . '/wp-config.php', $config);
    }

    /**
     * Generate security keys
     */
    protected function generateSecurityKeys()
    {
        $keys = [
            'AUTH_KEY',
            'SECURE_AUTH_KEY',
            'LOGGED_IN_KEY',
            'NONCE_KEY',
            'AUTH_SALT',
            'SECURE_AUTH_SALT',
            'LOGGED_IN_SALT',
            'NONCE_SALT'
        ];
        
        $generated = [];
        
        foreach ($keys as $key) {
            $generated[$key] = Str::random(64);
        }
        
        return $generated;
    }

    /**
     * Create WordPress database tables
     */
    protected function createWordPressTables($tablePrefix)
    {
        $tables = [
            'users' => "CREATE TABLE IF NOT EXISTS `{$tablePrefix}users` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            'usermeta' => "CREATE TABLE IF NOT EXISTS `{$tablePrefix}usermeta` (
                `umeta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `user_id` bigint(20) unsigned NOT NULL DEFAULT '0',
                `meta_key` varchar(255) DEFAULT NULL,
                `meta_value` longtext,
                PRIMARY KEY (`umeta_id`),
                KEY `user_id` (`user_id`),
                KEY `meta_key` (`meta_key`(191))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            'posts' => "CREATE TABLE IF NOT EXISTS `{$tablePrefix}posts` (
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
                `post_modified` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `post_modified_gmt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            'postmeta' => "CREATE TABLE IF NOT EXISTS `{$tablePrefix}postmeta` (
                `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `post_id` bigint(20) unsigned NOT NULL DEFAULT '0',
                `meta_key` varchar(255) DEFAULT NULL,
                `meta_value` longtext,
                PRIMARY KEY (`meta_id`),
                KEY `post_id` (`post_id`),
                KEY `meta_key` (`meta_key`(191))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            'terms' => "CREATE TABLE IF NOT EXISTS `{$tablePrefix}terms` (
                `term_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(200) NOT NULL DEFAULT '',
                `slug` varchar(200) NOT NULL DEFAULT '',
                `term_group` bigint(10) NOT NULL DEFAULT '0',
                PRIMARY KEY (`term_id`),
                KEY `slug` (`slug`(191)),
                KEY `name` (`name`(191))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            'term_taxonomy' => "CREATE TABLE IF NOT EXISTS `{$tablePrefix}term_taxonomy` (
                `term_taxonomy_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `term_id` bigint(20) unsigned NOT NULL DEFAULT '0',
                `taxonomy` varchar(32) NOT NULL DEFAULT '',
                `description` longtext NOT NULL,
                `parent` bigint(20) unsigned NOT NULL DEFAULT '0',
                `count` bigint(20) NOT NULL DEFAULT '0',
                PRIMARY KEY (`term_taxonomy_id`),
                UNIQUE KEY `term_id_taxonomy` (`term_id`,`taxonomy`),
                KEY `taxonomy` (`taxonomy`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            'term_relationships' => "CREATE TABLE IF NOT EXISTS `{$tablePrefix}term_relationships` (
                `object_id` bigint(20) unsigned NOT NULL DEFAULT '0',
                `term_taxonomy_id` bigint(20) unsigned NOT NULL DEFAULT '0',
                `term_order` int(11) NOT NULL DEFAULT '0',
                PRIMARY KEY (`object_id`,`term_taxonomy_id`),
                KEY `term_taxonomy_id` (`term_taxonomy_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            'termmeta' => "CREATE TABLE IF NOT EXISTS `{$tablePrefix}termmeta` (
                `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `term_id` bigint(20) unsigned NOT NULL DEFAULT '0',
                `meta_key` varchar(255) DEFAULT NULL,
                `meta_value` longtext,
                PRIMARY KEY (`meta_id`),
                KEY `term_id` (`term_id`),
                KEY `meta_key` (`meta_key`(191))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            'comments' => "CREATE TABLE IF NOT EXISTS `{$tablePrefix}comments` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            'commentmeta' => "CREATE TABLE IF NOT EXISTS `{$tablePrefix}commentmeta` (
                `meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `comment_id` bigint(20) unsigned NOT NULL DEFAULT '0',
                `meta_key` varchar(255) DEFAULT NULL,
                `meta_value` longtext,
                PRIMARY KEY (`meta_id`),
                KEY `comment_id` (`comment_id`),
                KEY `meta_key` (`meta_key`(191))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            'options' => "CREATE TABLE IF NOT EXISTS `{$tablePrefix}options` (
                `option_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `option_name` varchar(191) NOT NULL DEFAULT '',
                `option_value` longtext NOT NULL,
                `autoload` varchar(20) NOT NULL DEFAULT 'yes',
                PRIMARY KEY (`option_id`),
                UNIQUE KEY `option_name` (`option_name`),
                KEY `autoload` (`autoload`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            
            'links' => "CREATE TABLE IF NOT EXISTS `{$tablePrefix}links` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];

        foreach ($tables as $table => $sql) {
            DB::statement($sql);
        }
    }

    /**
     * Setup WordPress site with initial data
     */
    protected function setupWordPressSite($tablePrefix, $siteTitle, $adminUser, $adminEmail, $adminPassword, $siteId)
    {
        $siteUrl = $this->getSiteUrl($siteId);
        $now = now()->format('Y-m-d H:i:s');
        
        // Insert admin user
        $userId = DB::table($tablePrefix . 'users')->insertGetId([
            'user_login' => $adminUser,
            'user_pass' => $this->hashPassword($adminPassword),
            'user_nicename' => $adminUser,
            'user_email' => $adminEmail,
            'user_registered' => $now,
            'user_status' => 0,
            'display_name' => $adminUser
        ]);
        
        // Set user capabilities
        DB::table($tablePrefix . 'usermeta')->insert([
            [
                'user_id' => $userId,
                'meta_key' => $tablePrefix . 'capabilities',
                'meta_value' => serialize(['administrator' => true])
            ],
            [
                'user_id' => $userId,
                'meta_key' => $tablePrefix . 'user_level',
                'meta_value' => '10'
            ],
            [
                'user_id' => $userId,
                'meta_key' => 'nickname',
                'meta_value' => $adminUser
            ],
            [
                'user_id' => $userId,
                'meta_key' => 'first_name',
                'meta_value' => ''
            ],
            [
                'user_id' => $userId,
                'meta_key' => 'last_name',
                'meta_value' => ''
            ],
            [
                'user_id' => $userId,
                'meta_key' => 'description',
                'meta_value' => ''
            ],
            [
                'user_id' => $userId,
                'meta_key' => 'rich_editing',
                'meta_value' => 'true'
            ],
            [
                'user_id' => $userId,
                'meta_key' => 'syntax_highlighting',
                'meta_value' => 'true'
            ],
            [
                'user_id' => $userId,
                'meta_key' => 'comment_shortcuts',
                'meta_value' => 'false'
            ],
            [
                'user_id' => $userId,
                'meta_key' => 'admin_color',
                'meta_value' => 'fresh'
            ],
            [
                'user_id' => $userId,
                'meta_key' => 'use_ssl',
                'meta_value' => '0'
            ],
            [
                'user_id' => $userId,
                'meta_key' => 'show_admin_bar_front',
                'meta_value' => 'true'
            ],
            [
                'user_id' => $userId,
                'meta_key' => 'locale',
                'meta_value' => ''
            ]
        ]);
        
        // Insert basic options
        $options = [
            'siteurl' => $siteUrl,
            'home' => $siteUrl,
            'blogname' => $siteTitle,
            'blogdescription' => 'Just another WordPress site',
            'users_can_register' => '0',
            'admin_email' => $adminEmail,
            'start_of_week' => '1',
            'use_balanceTags' => '0',
            'use_smilies' => '1',
            'require_name_email' => '1',
            'comments_notify' => '1',
            'posts_per_rss' => '10',
            'rss_use_excerpt' => '0',
            'mailserver_url' => 'mail.example.com',
            'mailserver_login' => 'login@example.com',
            'mailserver_pass' => 'password',
            'mailserver_port' => '110',
            'default_category' => '1',
            'default_comment_status' => 'open',
            'default_ping_status' => 'open',
            'default_pingback_flag' => '1',
            'posts_per_page' => '10',
            'date_format' => 'F j, Y',
            'time_format' => 'g:i a',
            'links_updated_date_format' => 'F j, Y g:i a',
            'comment_moderation' => '0',
            'moderation_notify' => '1',
            'permalink_structure' => '/%postname%/',
            'rewrite_rules' => '',
            'hack_file' => '0',
            'blog_charset' => 'UTF-8',
            'moderation_keys' => '',
            'active_plugins' => serialize([]),
            'category_base' => '',
            'ping_sites' => 'http://rpc.pingomatic.com/',
            'comment_max_links' => '2',
            'gmt_offset' => '0',
            'default_email_category' => '1',
            'recently_edited' => '',
            'template' => 'twentytwentyfour',
            'stylesheet' => 'twentytwentyfour',
            'comment_registration' => '0',
            'html_type' => 'text/html',
            'use_trackback' => '0',
            'default_role' => 'subscriber',
            'db_version' => '57155',
            'uploads_use_yearmonth_folders' => '1',
            'upload_path' => '',
            'blog_public' => '1',
            'default_link_category' => '2',
            'show_on_front' => 'posts',
            'tag_base' => '',
            'show_avatars' => '1',
            'avatar_rating' => 'G',
            'upload_url_path' => '',
            'thumbnail_size_w' => '150',
            'thumbnail_size_h' => '150',
            'thumbnail_crop' => '1',
            'medium_size_w' => '300',
            'medium_size_h' => '300',
            'avatar_default' => 'mystery',
            'large_size_w' => '1024',
            'large_size_h' => '1024',
            'image_default_link_type' => 'none',
            'image_default_size' => '',
            'image_default_align' => '',
            'close_comments_for_old_posts' => '0',
            'close_comments_days_old' => '14',
            'thread_comments' => '1',
            'thread_comments_depth' => '5',
            'page_comments' => '0',
            'comments_per_page' => '50',
            'default_comments_page' => 'newest',
            'comment_order' => 'asc',
            'sticky_posts' => serialize([]),
            'widget_categories' => serialize([]),
            'widget_text' => serialize([]),
            'widget_rss' => serialize([]),
            'uninstall_plugins' => serialize([]),
            'timezone_string' => '',
            'page_for_posts' => '0',
            'page_on_front' => '0',
            'default_post_format' => '0',
            'link_manager_enabled' => '0',
            'finished_splitting_shared_terms' => '1',
            'site_icon' => '0',
            'medium_large_size_w' => '768',
            'medium_large_size_h' => '0',
            'wp_page_for_privacy_policy' => '0',
            'show_comments_cookies_opt_in' => '1',
            'admin_email_lifespan' => '1735689600',
            'disallowed_keys' => '',
            'comment_previously_approved' => '1',
            'auto_plugin_theme_update_emails' => serialize([]),
            'auto_update_core_dev' => 'enabled',
            'auto_update_core_minor' => 'enabled',
            'auto_update_core_major' => 'enabled',
            'wp_force_deactivated_plugins' => serialize([]),
            'wp_user_roles' => serialize($this->getDefaultRoles($tablePrefix)),
            'fresh_site' => '1'
        ];
        
        foreach ($options as $name => $value) {
            DB::table($tablePrefix . 'options')->insert([
                'option_name' => $name,
                'option_value' => $value,
                'autoload' => 'yes'
            ]);
        }
        
        // Create default category
        DB::table($tablePrefix . 'terms')->insert([
            'term_id' => 1,
            'name' => 'Uncategorized',
            'slug' => 'uncategorized',
            'term_group' => 0
        ]);
        
        DB::table($tablePrefix . 'term_taxonomy')->insert([
            'term_taxonomy_id' => 1,
            'term_id' => 1,
            'taxonomy' => 'category',
            'description' => '',
            'parent' => 0,
            'count' => 0
        ]);
        
        // Create sample post
        $postId = DB::table($tablePrefix . 'posts')->insertGetId([
            'post_author' => $userId,
            'post_date' => $now,
            'post_date_gmt' => $now,
            'post_content' => 'Welcome to WordPress. This is your first post. Edit or delete it, then start writing!',
            'post_title' => 'Hello world!',
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
            'comment_count' => 1
        ]);
        
        // Create sample page
        DB::table($tablePrefix . 'posts')->insert([
            'post_author' => $userId,
            'post_date' => $now,
            'post_date_gmt' => $now,
            'post_content' => 'This is an example page. It\'s different from a blog post because it will stay in one place and will show up in your site navigation (in most themes). Most people start with an About page that introduces them to potential site visitors.',
            'post_title' => 'Sample Page',
            'post_excerpt' => '',
            'post_status' => 'publish',
            'comment_status' => 'closed',
            'ping_status' => 'open',
            'post_password' => '',
            'post_name' => 'sample-page',
            'to_ping' => '',
            'pinged' => '',
            'post_modified' => $now,
            'post_modified_gmt' => $now,
            'post_content_filtered' => '',
            'post_parent' => 0,
            'guid' => $siteUrl . '/?page_id=2',
            'menu_order' => 0,
            'post_type' => 'page',
            'post_mime_type' => '',
            'comment_count' => 0
        ]);
        
        // Create sample comment
        DB::table($tablePrefix . 'comments')->insert([
            'comment_post_ID' => $postId,
            'comment_author' => 'A WordPress Commenter',
            'comment_author_email' => 'wapuu@wordpress.example',
            'comment_author_url' => 'https://wordpress.org/',
            'comment_author_IP' => '',
            'comment_date' => $now,
            'comment_date_gmt' => $now,
            'comment_content' => 'Hi, this is a comment.\nTo get started with moderating, editing, and deleting comments, please visit the Comments screen in the dashboard.\nCommenter avatars come from <a href="https://gravatar.com">Gravatar</a>.',
            'comment_karma' => 0,
            'comment_approved' => '1',
            'comment_agent' => '',
            'comment_type' => 'comment',
            'comment_parent' => 0,
            'user_id' => 0
        ]);
    }

    /**
     * Create wp-content directory structure
     */
    protected function createWpContentStructure($siteDir)
    {
        $dirs = [
            $siteDir . '/wp-content',
            $siteDir . '/wp-content/themes',
            $siteDir . '/wp-content/plugins',
            $siteDir . '/wp-content/uploads',
            $siteDir . '/wp-content/upgrade',
            $siteDir . '/wp-content/languages',
            $siteDir . '/wp-content/languages/plugins',
            $siteDir . '/wp-content/languages/themes',
        ];
        
        foreach ($dirs as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
        }
        
        // Create index.php in wp-content
        $indexContent = "<?php\n// Silence is golden.";
        File::put($siteDir . '/wp-content/index.php', $indexContent);
        File::put($siteDir . '/wp-content/plugins/index.php', $indexContent);
        File::put($siteDir . '/wp-content/themes/index.php', $indexContent);
        
        // CRITICAL: Copy default theme from WordPress core
        $defaultTheme = 'twentytwentyfour';
        $sourceTheme = $this->wpCorePath . '/wp-content/themes/' . $defaultTheme;
        $destTheme = $siteDir . '/wp-content/themes/' . $defaultTheme;
        
        if (File::exists($sourceTheme) && !File::exists($destTheme)) {
            File::copyDirectory($sourceTheme, $destTheme);
        }
        
        // Also copy twentytwentythree as backup
        $backupTheme = 'twentytwentythree';
        $sourceBackup = $this->wpCorePath . '/wp-content/themes/' . $backupTheme;
        $destBackup = $siteDir . '/wp-content/themes/' . $backupTheme;
        
        if (File::exists($sourceBackup) && !File::exists($destBackup)) {
            File::copyDirectory($sourceBackup, $destBackup);
        }
    }

    /**
     * Set proper permissions
     */
    protected function setPermissions($siteDir)
    {
        // Set directory permissions
        File::chmod($siteDir, 0755);
        File::chmod($siteDir . '/wp-content', 0755);
        File::chmod($siteDir . '/wp-content/uploads', 0775);
        
        // Set file permissions
        if (File::exists($siteDir . '/wp-config.php')) {
            File::chmod($siteDir . '/wp-config.php', 0644);
        }
    }

    /**
     * Hash password WordPress style
     */
    protected function hashPassword($password)
    {
        // WordPress uses phpass library, but for simplicity we'll use bcrypt
        // In production, you should use WordPress's wp_hash_password function
        return password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * Get default WordPress roles
     */
    protected function getDefaultRoles($tablePrefix)
    {
        return [
            'administrator' => [
                'name' => 'Administrator',
                'capabilities' => [
                    'switch_themes' => true,
                    'edit_themes' => true,
                    'activate_plugins' => true,
                    'edit_plugins' => true,
                    'edit_users' => true,
                    'edit_files' => true,
                    'manage_options' => true,
                    'moderate_comments' => true,
                    'manage_categories' => true,
                    'manage_links' => true,
                    'upload_files' => true,
                    'import' => true,
                    'unfiltered_html' => true,
                    'edit_posts' => true,
                    'edit_others_posts' => true,
                    'edit_published_posts' => true,
                    'publish_posts' => true,
                    'edit_pages' => true,
                    'read' => true,
                    'level_10' => true,
                    'level_9' => true,
                    'level_8' => true,
                    'level_7' => true,
                    'level_6' => true,
                    'level_5' => true,
                    'level_4' => true,
                    'level_3' => true,
                    'level_2' => true,
                    'level_1' => true,
                    'level_0' => true,
                    'edit_others_pages' => true,
                    'edit_published_pages' => true,
                    'publish_pages' => true,
                    'delete_pages' => true,
                    'delete_others_pages' => true,
                    'delete_published_pages' => true,
                    'delete_posts' => true,
                    'delete_others_posts' => true,
                    'delete_published_posts' => true,
                    'delete_private_posts' => true,
                    'edit_private_posts' => true,
                    'read_private_posts' => true,
                    'delete_private_pages' => true,
                    'edit_private_pages' => true,
                    'read_private_pages' => true,
                    'delete_users' => true,
                    'create_users' => true,
                    'unfiltered_upload' => true,
                    'edit_dashboard' => true,
                    'update_plugins' => true,
                    'delete_plugins' => true,
                    'install_plugins' => true,
                    'update_themes' => true,
                    'install_themes' => true,
                    'update_core' => true,
                    'list_users' => true,
                    'remove_users' => true,
                    'promote_users' => true,
                    'edit_theme_options' => true,
                    'delete_themes' => true,
                    'export' => true
                ]
            ],
            'editor' => [
                'name' => 'Editor',
                'capabilities' => [
                    'moderate_comments' => true,
                    'manage_categories' => true,
                    'manage_links' => true,
                    'upload_files' => true,
                    'unfiltered_html' => true,
                    'edit_posts' => true,
                    'edit_others_posts' => true,
                    'edit_published_posts' => true,
                    'publish_posts' => true,
                    'edit_pages' => true,
                    'read' => true,
                    'level_7' => true,
                    'level_6' => true,
                    'level_5' => true,
                    'level_4' => true,
                    'level_3' => true,
                    'level_2' => true,
                    'level_1' => true,
                    'level_0' => true,
                    'edit_others_pages' => true,
                    'edit_published_pages' => true,
                    'publish_pages' => true,
                    'delete_pages' => true,
                    'delete_others_pages' => true,
                    'delete_published_pages' => true,
                    'delete_posts' => true,
                    'delete_others_posts' => true,
                    'delete_published_posts' => true,
                    'delete_private_posts' => true,
                    'edit_private_posts' => true,
                    'read_private_posts' => true,
                    'delete_private_pages' => true,
                    'edit_private_pages' => true,
                    'read_private_pages' => true
                ]
            ],
            'author' => [
                'name' => 'Author',
                'capabilities' => [
                    'upload_files' => true,
                    'edit_posts' => true,
                    'edit_published_posts' => true,
                    'publish_posts' => true,
                    'read' => true,
                    'level_2' => true,
                    'level_1' => true,
                    'level_0' => true,
                    'delete_posts' => true,
                    'delete_published_posts' => true
                ]
            ],
            'contributor' => [
                'name' => 'Contributor',
                'capabilities' => [
                    'edit_posts' => true,
                    'read' => true,
                    'level_1' => true,
                    'level_0' => true,
                    'delete_posts' => true
                ]
            ],
            'subscriber' => [
                'name' => 'Subscriber',
                'capabilities' => [
                    'read' => true,
                    'level_0' => true
                ]
            ]
        ];
    }

    /**
     * Get site URL
     */
    protected function getSiteUrl($siteId)
    {
        return url("/wp.php?site={$siteId}");
    }

    /**
     * Get admin URL
     */
    protected function getAdminUrl($siteId)
    {
        return url("/wp.php?site={$siteId}&wp-admin");
    }

    /**
     * Delete a WordPress site
     */
    public function deleteSite($siteId)
    {
        try {
            $tablePrefix = 'wp_' . str_replace('-', '', $siteId) . '_';
            
            // Get all tables with this prefix
            $tables = DB::select("SHOW TABLES LIKE '{$tablePrefix}%'");
            
            // Drop all tables
            foreach ($tables as $table) {
                $tableName = array_values((array)$table)[0];
                DB::statement("DROP TABLE IF EXISTS `{$tableName}`");
            }
            
            // Delete site directory
            $siteDir = $this->sitesPath . '/' . $siteId;
            if (File::exists($siteDir)) {
                File::deleteDirectory($siteDir);
            }
            
            return [
                'success' => true,
                'message' => 'Site deleted successfully'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}