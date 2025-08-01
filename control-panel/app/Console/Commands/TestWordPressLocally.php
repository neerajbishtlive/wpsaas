<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestWordPressLocally extends Command
{
    protected $signature = 'test:wordpress {subdomain} {--port=8080}';
    protected $description = 'Test a WordPress site locally without nginx';

    public function handle()
    {
        $subdomain = $this->argument('subdomain');
        $port = $this->option('port');
        
        $sitePath = config('wordpress.sites_path') . '/' . $subdomain;
        
        if (!file_exists($sitePath)) {
            $this->error("Site directory not found: {$sitePath}");
            return;
        }
        
        $this->info("Starting WordPress site: {$subdomain}");
        $this->info("URL: http://localhost:{$port}");
        $this->info("Admin URL: http://localhost:{$port}/wp-admin");
        $this->info("Press Ctrl+C to stop the server");
        
        // Update wp-config.php temporarily for localhost
        $wpConfig = file_get_contents($sitePath . '/wp-config.php');
        $wpConfigBackup = $wpConfig;
        
        // Replace the URLs temporarily
        $wpConfig = preg_replace(
            "/define\(\s*'WP_HOME',\s*'[^']+'\s*\);/",
            "define('WP_HOME', 'http://localhost:{$port}');",
            $wpConfig
        );
        $wpConfig = preg_replace(
            "/define\(\s*'WP_SITEURL',\s*'[^']+'\s*\);/",
            "define('WP_SITEURL', 'http://localhost:{$port}');",
            $wpConfig
        );
        
        file_put_contents($sitePath . '/wp-config.php', $wpConfig);
        
        // Start the server
        $process = proc_open(
            "php -S localhost:{$port}",
            [
                0 => STDIN,
                1 => STDOUT,
                2 => STDERR,
            ],
            $pipes,
            $sitePath
        );
        
        if (is_resource($process)) {
            // Wait for the process
            proc_close($process);
        }
        
        // Restore original wp-config.php when done
        file_put_contents($sitePath . '/wp-config.php', $wpConfigBackup);
        $this->info("\nServer stopped. wp-config.php restored.");
    }
}