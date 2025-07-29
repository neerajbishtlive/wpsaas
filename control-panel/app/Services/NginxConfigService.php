<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class NginxConfigService
{
    protected $nginxPath;
    protected $sitesAvailable;
    protected $sitesEnabled;
    
    public function __construct()
    {
        $this->nginxPath = '/usr/local/etc/nginx'; // Mac path
        $this->sitesAvailable = $this->nginxPath . '/sites-available';
        $this->sitesEnabled = $this->nginxPath . '/sites-enabled';
        
        // Create directories if they don't exist
        if (!File::exists($this->sitesAvailable)) {
            File::makeDirectory($this->sitesAvailable, 0755, true);
        }
        
        if (!File::exists($this->sitesEnabled)) {
            File::makeDirectory($this->sitesEnabled, 0755, true);
        }
    }
    
    public function createSiteConfig(Site $site)
    {
        $sitePath = config('wordpress.sites_path') . '/site_' . $site->id;
        $domain = $site->custom_domain ?? "{$site->subdomain}." . config('app.wildcard_domain');
        
        $config = $this->generateNginxConfig($site, $sitePath, $domain);
        
        // Write config file
        $configFile = $this->sitesAvailable . '/site_' . $site->id . '.conf';
        File::put($configFile, $config);
        
        // Create symlink to sites-enabled
        $enabledLink = $this->sitesEnabled . '/site_' . $site->id . '.conf';
        if (!File::exists($enabledLink)) {
            exec("ln -s " . escapeshellarg($configFile) . " " . escapeshellarg($enabledLink));
        }
        
        // Test nginx configuration
        exec('nginx -t 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            // Reload nginx
            exec('nginx -s reload');
            Log::info("Nginx configuration created for site {$site->subdomain}");
        } else {
            // Remove the bad config
            File::delete($configFile);
            if (File::exists($enabledLink)) {
                File::delete($enabledLink);
            }
            
            throw new \Exception('Nginx configuration test failed: ' . implode("\n", $output));
        }
    }
    
    public function removeSiteConfig(Site $site)
    {
        $configFile = $this->sitesAvailable . '/site_' . $site->id . '.conf';
        $enabledLink = $this->sitesEnabled . '/site_' . $site->id . '.conf';
        
        // Remove symlink first
        if (File::exists($enabledLink)) {
            File::delete($enabledLink);
        }
        
        // Remove config file
        if (File::exists($configFile)) {
            File::delete($configFile);
        }
        
        // Test and reload nginx
        exec('nginx -t 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            exec('nginx -s reload');
            Log::info("Nginx configuration removed for site {$site->subdomain}");
        } else {
            Log::error('Nginx configuration test failed after removing site config: ' . implode("\n", $output));
        }
    }
    
    protected function generateNginxConfig($site, $sitePath, $domain)
    {
        $phpSocket = '/usr/local/var/run/php-fpm.sock'; // Mac PHP-FPM socket
        
        $config = <<<EOD
server {
    listen 80;
    listen 443 ssl http2;
    server_name {$domain};
    
    root {$sitePath};
    index index.php index.html;
    
    # SSL Configuration (will be added by certbot)
    # ssl_certificate /etc/letsencrypt/live/{$domain}/fullchain.pem;
    # ssl_certificate_key /etc/letsencrypt/live/{$domain}/privkey.pem;
    
    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    
    # Resource limits based on site type
    client_max_body_size {$this->getUploadLimit($site->type)};
    
    # Logging
    access_log /usr/local/var/log/nginx/site_{$site->id}_access.log;
    error_log /usr/local/var/log/nginx/site_{$site->id}_error.log;
    
    # WordPress specific rules
    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }
    
    # PHP processing
    location ~ \.php$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:{$phpSocket};
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
        
        # PHP resource limits
        fastcgi_param PHP_VALUE "
            memory_limit = {$this->getMemoryLimit($site->type)}
            max_execution_time = {$this->getExecutionTime($site->type)}
            max_input_time = 60
            post_max_size = {$this->getUploadLimit($site->type)}
            upload_max_filesize = {$this->getUploadLimit($site->type)}
        ";
    }
    
    # Static file caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf|woff|woff2|ttf|svg)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
    
    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }
    
    location ~ ^/wp-content/uploads/.*\.php$ {
        deny all;
    }
    
    # Deny access to WordPress config and sensitive files
    location ~ ^/(wp-config\.php|readme\.html|license\.txt)$ {
        deny all;
    }
    
    # Block access to xmlrpc.php
    location = /xmlrpc.php {
        deny all;
    }
    
    # WordPress admin security
    location ~ ^/wp-admin/admin-ajax\.php$ {
        include fastcgi_params;
        fastcgi_pass unix:{$phpSocket};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }
    
    # Rate limiting for login attempts
    location = /wp-login.php {
        limit_req zone=login burst=2 nodelay;
        include fastcgi_params;
        fastcgi_pass unix:{$phpSocket};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/javascript
        application/xml+rss
        application/json;
}
EOD;
        
        return $config;
    }
    
    protected function getUploadLimit($type)
    {
        $limits = [
            'guest' => '2M',
            'registered' => '10M',
            'paid' => '50M'
        ];
        
        return $limits[$type] ?? $limits['guest'];
    }
    
    protected function getMemoryLimit($type)
    {
        $limits = [
            'guest' => '64M',
            'registered' => '128M',
            'paid' => '256M'
        ];
        
        return $limits[$type] ?? $limits['guest'];
    }
    
    protected function getExecutionTime($type)
    {
        $limits = [
            'guest' => 30,
            'registered' => 60,
            'paid' => 120
        ];
        
        return $limits[$type] ?? $limits['guest'];
    }
}