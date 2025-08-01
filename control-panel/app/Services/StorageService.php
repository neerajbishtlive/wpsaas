<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class StorageService
{
    /**
     * Get storage path for sites
     */
    public function getSitesPath(): string
    {
        return config('wordpress.sites_path', base_path('../sites'));
    }

    /**
     * Get storage path for a specific site
     */
    public function getSitePath(string $subdomain): string
    {
        return $this->getSitesPath() . '/' . $subdomain;
    }

    /**
     * Check if site directory exists
     */
    public function siteExists(string $subdomain): bool
    {
        return File::exists($this->getSitePath($subdomain));
    }

    /**
     * Get site storage usage
     */
    public function getSiteStorageUsage(string $subdomain): int
    {
        $path = $this->getSitePath($subdomain);
        
        if (!File::exists($path)) {
            return 0;
        }

        return $this->getDirectorySize($path);
    }

    /**
     * Get directory size recursively
     */
    private function getDirectorySize(string $dir): int
    {
        $size = 0;
        
        if (!is_dir($dir)) {
            return $size;
        }
        
        foreach (glob(rtrim($dir, '/').'/*', GLOB_NOSORT) as $path) {
            $size += is_file($path) ? filesize($path) : $this->getDirectorySize($path);
        }
        
        return $size;
    }

    /**
     * Format bytes to human readable
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Clean up site storage
     */
    public function cleanupSite(string $subdomain): bool
    {
        $path = $this->getSitePath($subdomain);
        
        if (File::exists($path)) {
            return File::deleteDirectory($path);
        }
        
        return true;
    }

    /**
     * Create site directory structure
     */
    public function createSiteStructure(string $subdomain): bool
    {
        $sitePath = $this->getSitePath($subdomain);
        
        $directories = [
            $sitePath,
            $sitePath . '/wp-content',
            $sitePath . '/wp-content/themes',
            $sitePath . '/wp-content/plugins',
            $sitePath . '/wp-content/uploads',
        ];
        
        foreach ($directories as $dir) {
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
        }
        
        return true;
    }
}