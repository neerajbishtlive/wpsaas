<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WordPressProvisioningService;

class CreateWordPressSite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wordpress:create-site 
                            {subdomain : The subdomain for the site} 
                            {--title=My WordPress Site : The site title}
                            {--admin=admin : The admin username}
                            {--email=admin@example.com : The admin email}
                            {--password=admin123 : The admin password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new WordPress site';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $subdomain = $this->argument('subdomain');
        $title = $this->option('title');
        $adminUser = $this->option('admin');
        $adminEmail = $this->option('email');
        $adminPassword = $this->option('password');
        
        $this->info('Creating WordPress site...');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Subdomain', $subdomain],
                ['Title', $title],
                ['Admin User', $adminUser],
                ['Admin Email', $adminEmail],
                ['Admin Password', str_repeat('*', strlen($adminPassword))],
            ]
        );
        
        try {
            $service = new WordPressProvisioningService();
            $result = $service->createSite($subdomain, $title, $adminUser, $adminEmail, $adminPassword);
            
            if ($result['success']) {
                $this->info('✅ Site created successfully!');
                $this->line('');
                $this->line('Site ID: ' . $result['site_id']);
                $this->line('Site URL: ' . $result['site_url']);
                $this->line('Admin URL: ' . $result['admin_url']);
                $this->line('');
                
                // Fix database URLs
                $tablePrefix = 'wp_' . str_replace('-', '', $result['site_id']) . '_';
                \DB::statement("UPDATE {$tablePrefix}options SET option_value = 'http://localhost:8000' WHERE option_name IN ('siteurl', 'home')");
                
                $this->info('Database URLs have been fixed to prevent redirect loops.');
                $this->line('');
                $this->line('You can now access your site at:');
                $this->line('Frontend: http://localhost:8000/wp.php?site=' . $result['site_id']);
                $this->line('Admin: http://localhost:8000/wp.php?site=' . $result['site_id'] . '&wp-admin');
                
                return Command::SUCCESS;
            } else {
                $this->error('Failed to create site: ' . $result['error']);
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Exception: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}