<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Site;
use App\Models\Plan;
use App\Models\User;
use App\Services\WordPressProvisioningService;
use Illuminate\Support\Str;

class TestSiteCreation extends Command
{
    protected $signature = 'test:site-creation';
    protected $description = 'Test WordPress site creation with direct database installation';

    public function handle()
    {
        $this->info('Testing WordPress Site Creation with Direct DB Installation');
        $this->info('=========================================================');
        
        // Test configuration
        $subdomain = 'test-' . Str::random(6);
        $siteTitle = 'Test WordPress Site';
        $adminUsername = 'testadmin';
        $adminEmail = 'test@example.com';
        $adminPassword = 'TestPass123!';
        
        // Display test configuration
        $this->line('Test Configuration:');
        $this->table(
            ['Parameter', 'Value'],
            [
                ['subdomain', $subdomain],
                ['site_title', $siteTitle],
                ['admin_username', $adminUsername],
                ['admin_email', $adminEmail],
                ['site_type', 'free'],
                ['db_name', config('database.connections.mysql.database')],
                ['db_user', config('database.connections.mysql.username')],
                ['db_host', config('database.connections.mysql.host')],
            ]
        );
        
        try {
            // Step 1: Create site record
            $this->info("\n1. Creating site record in database...");
            
            // Get or create test user
            $user = User::first();
            if (!$user) {
                $user = User::create([
                    'name' => 'Test User',
                    'email' => $adminEmail,
                    'password' => bcrypt($adminPassword),
                ]);
            }
            
            // Get free plan
            $plan = Plan::where('slug', 'free')->first();
            if (!$plan) {
                $plan = Plan::create([
                    'name' => 'Free',
                    'slug' => 'free',
                    'price' => 0,
                    'features' => [
                        'sites' => 1,
                        'storage' => 100, // MB
                        'bandwidth' => 1000, // MB
                        'ssl' => true,
                        'backups' => false,
                        'staging' => false,
                    ],
                    'limits' => [
                        'max_storage_mb' => 100,
                        'max_bandwidth_mb' => 1000,
                        'max_database_mb' => 50,
                    ],
                ]);
            }
            
            // Create site with all required fields
            $site = Site::create([
                'subdomain' => $subdomain,
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'provisioning',
                'expires_at' => now()->addDays(7),
                'db_name' => config('database.connections.mysql.database'),
                'db_user' => config('database.connections.mysql.username'),
                'db_password' => config('database.connections.mysql.password', ''),
                'db_prefix' => 'wp_' . str_replace('-', '', $subdomain) . '_',
                'settings' => [],
                // Add the missing fields
                'admin_email' => $adminEmail,
                'admin_username' => $adminUsername,
                'site_title' => $siteTitle,
            ]);
            
            $this->info("✓ Site record created with ID: {$site->id}");
            
            // Step 2: Provision WordPress
            $this->info("\n2. Provisioning WordPress installation...");
            
            $provisioningService = app(WordPressProvisioningService::class);
            
            $installData = [
                'subdomain' => $subdomain,
                'site_title' => $siteTitle,
                'admin_username' => $adminUsername,
                'admin_email' => $adminEmail,
                'admin_password' => $adminPassword,
                'site_id' => $site->id,
            ];
            
            $result = $provisioningService->createSite($installData);
            
            if ($result['success']) {
                $this->info('✓ WordPress installed successfully!');
                
                // Update site status
                $site->update(['status' => 'active']);
                
                // Display success information
                $this->newLine();
                $this->info('🎉 Site Creation Successful!');
                $this->table(
                    ['Property', 'Value'],
                    [
                        ['Site URL', "http://{$subdomain}.localhost"],
                        ['Admin URL', "http://{$subdomain}.localhost/wp-admin"],
                        ['Username', $adminUsername],
                        ['Email', $adminEmail],
                        ['Database', $site->db_name],
                        ['Table Prefix', $site->db_prefix],
                        ['Site Path', $result['site_path'] ?? 'N/A'],
                    ]
                );
                
                // Verify installation
                $this->info("\n3. Verifying installation...");
                
                // Check if wp-config.php exists
                $wpConfigPath = $result['site_path'] . '/wp-config.php';
                if (file_exists($wpConfigPath)) {
                    $this->info('✓ wp-config.php exists');
                } else {
                    $this->error('✗ wp-config.php not found');
                }
                
                // Check database tables
                $tables = \DB::select("SHOW TABLES LIKE '{$site->db_prefix}%'");
                $tableCount = count($tables);
                $this->info("✓ Found {$tableCount} WordPress tables");
                
                if ($tableCount > 0) {
                    // Check admin user
                    $adminUser = \DB::table($site->db_prefix . 'users')
                        ->where('user_login', $adminUsername)
                        ->first();
                    
                    if ($adminUser) {
                        $this->info('✓ Admin user created successfully');
                    } else {
                        $this->error('✗ Admin user not found');
                    }
                }
                
            } else {
                $this->error('✗ WordPress installation suspended: ' . ($result['error'] ?? 'Unknown error'));
                $site->update(['status' => 'suspended']);
            }
            
        } catch (\Exception $e) {
            $this->error('Installation suspended: ' . $e->getMessage());
            $this->error('Stack trace:');
            $this->line($e->getTraceAsString());
            
            // Clean up on failure
            if (isset($site)) {
                $site->update(['status' => 'suspended']);
            }
        }
    }
}