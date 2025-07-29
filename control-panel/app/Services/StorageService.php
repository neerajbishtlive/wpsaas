<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Exception;

class StorageService
{
    private $config;
    
    public function __construct()
    {
        $this->config = config('storage');
    }

    /**
     * Get storage driver based on admin configuration
     */
    public function getStorageDriver(): string
    {
        return config('filesystems.default', 'local');
    }

    /**
     * Create storage directory structure for a new site
     */
    public function createSiteStorage(Site $site): bool
    {
        try {
            $siteDir = $this->getSiteStoragePath($site);
            
            // Create local directories
            $directories = [
                $siteDir,
                $siteDir . '/uploads',
                $siteDir . '/backups',
                $siteDir . '/logs',
                $siteDir . '/cache'
            ];

            foreach ($directories as $dir) {
                if (!File::exists($dir)) {
                    File::makeDirectory($dir, 0755, true);
                }
            }

            // Set proper permissions
            $this->setStoragePermissions($siteDir);

            // Create .htaccess for security
            $this->createSecurityFiles($siteDir);

            Log::info("Storage created for site: {$site->subdomain}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to create storage for site {$site->subdomain}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete storage for a site
     */
    public function deleteSiteStorage(Site $site): bool
    {
        try {
            $siteDir = $this->getSiteStoragePath($site);
            
            if (File::exists($siteDir)) {
                File::deleteDirectory($siteDir);
            }

            // Also delete from remote storage if configured
            if ($this->getStorageDriver() !== 'local') {
                $this->deleteRemoteStorage($site);
            }

            Log::info("Storage deleted for site: {$site->subdomain}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to delete storage for site {$site->subdomain}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get site storage path
     */
    public function getSiteStoragePath(Site $site): string
    {
        $basePath = config('app.storage_path', storage_path('sites'));
        return $basePath . "/site_{$site->id}";
    }

    /**
     * Get site uploads URL
     */
    public function getSiteUploadsUrl(Site $site): string
    {
        if ($this->getStorageDriver() === 'local') {
            return config('app.url') . "/storage/sites/site_{$site->id}/uploads";
        }
        
        // For remote storage (S3, DO Spaces, etc.)
        return Storage::url("sites/site_{$site->id}/uploads");
    }

    /**
     * Calculate storage usage for a site
     */
    public function calculateSiteStorageUsage(Site $site): array
    {
        $siteDir = $this->getSiteStoragePath($site);
        $usage = [
            'total_bytes' => 0,
            'uploads_bytes' => 0,
            'backups_bytes' => 0,
            'logs_bytes' => 0,
            'cache_bytes' => 0,
            'total_mb' => 0,
            'uploads_mb' => 0,
            'backups_mb' => 0
        ];

        if (!File::exists($siteDir)) {
            return $usage;
        }

        try {
            // Calculate each directory size
            $directories = [
                'uploads' => $siteDir . '/uploads',
                'backups' => $siteDir . '/backups',
                'logs' => $siteDir . '/logs',
                'cache' => $siteDir . '/cache'
            ];

            foreach ($directories as $type => $dir) {
                if (File::exists($dir)) {
                    $bytes = $this->getDirectorySize($dir);
                    $usage[$type . '_bytes'] = $bytes;
                    $usage[$type . '_mb'] = round($bytes / 1024 / 1024, 2);
                    $usage['total_bytes'] += $bytes;
                }
            }

            $usage['total_mb'] = round($usage['total_bytes'] / 1024 / 1024, 2);

        } catch (Exception $e) {
            Log::error("Failed to calculate storage usage for site {$site->subdomain}: " . $e->getMessage());
        }

        return $usage;
    }

    /**
     * Check if site is within storage limits
     */
    public function checkStorageLimit(Site $site): array
    {
        $usage = $this->calculateSiteStorageUsage($site);
        $plan = $site->plan;
        
        $limit_mb = $plan ? $plan->storage_limit_mb : 100; // Default 100MB for guest
        $used_mb = $usage['total_mb'];
        $percentage = $limit_mb > 0 ? round(($used_mb / $limit_mb) * 100, 2) : 0;
        
        return [
            'used_mb' => $used_mb,
            'limit_mb' => $limit_mb,
            'percentage' => $percentage,
            'over_limit' => $used_mb > $limit_mb,
            'warning' => $percentage > 80, // Warning at 80%
            'remaining_mb' => max(0, $limit_mb - $used_mb)
        ];
    }

    /**
     * Sync files to remote storage
     */
    public function syncToRemoteStorage(Site $site): bool
    {
        if ($this->getStorageDriver() === 'local') {
            return true; // No sync needed for local storage
        }

        try {
            $siteDir = $this->getSiteStoragePath($site);
            $remotePrefix = "sites/site_{$site->id}";

            // Sync uploads directory
            $uploadsDir = $siteDir . '/uploads';
            if (File::exists($uploadsDir)) {
                $this->syncDirectoryToRemote($uploadsDir, $remotePrefix . '/uploads');
            }

            // Sync backups (if enabled for remote storage)
            if (config('backup.store_remote', false)) {
                $backupsDir = $siteDir . '/backups';
                if (File::exists($backupsDir)) {
                    $this->syncDirectoryToRemote($backupsDir, $remotePrefix . '/backups');
                }
            }

            Log::info("Remote storage sync completed for site: {$site->subdomain}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to sync remote storage for site {$site->subdomain}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up cache and temporary files
     */
    public function cleanupSiteCache(Site $site): bool
    {
        try {
            $cacheDir = $this->getSiteStoragePath($site) . '/cache';
            
            if (File::exists($cacheDir)) {
                // Delete files older than 24 hours
                $files = File::allFiles($cacheDir);
                $cutoff = time() - (24 * 60 * 60);
                
                foreach ($files as $file) {
                    if (File::lastModified($file) < $cutoff) {
                        File::delete($file);
                    }
                }
            }

            return true;

        } catch (Exception $e) {
            Log::error("Failed to cleanup cache for site {$site->subdomain}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Private helper methods
     */
    private function setStoragePermissions(string $path): void
    {
        // Set proper permissions for security
        chmod($path, 0755);
        chmod($path . '/uploads', 0755);
        chmod($path . '/backups', 0700); // More restrictive for backups
        chmod($path . '/logs', 0750);
        chmod($path . '/cache', 0755);
    }

    private function createSecurityFiles(string $siteDir): void
    {
        // Create .htaccess files for security
        $htaccessContent = [
            'uploads' => "# Security rules for uploads\nOptions -Indexes\n<Files ~ \"\.php$\">\nOrder allow,deny\nDeny from all\n</Files>",
            'backups' => "# Deny all access to backups\nOrder allow,deny\nDeny from all",
            'logs' => "# Deny all access to logs\nOrder allow,deny\nDeny from all",
            'cache' => "# Deny all access to cache\nOrder allow,deny\nDeny from all"
        ];

        foreach ($htaccessContent as $dir => $content) {
            $htaccessPath = $siteDir . '/' . $dir . '/.htaccess';
            File::put($htaccessPath, $content);
        }

        // Create index.php files to prevent directory browsing
        $indexContent = "<?php\n// Silence is golden.";
        foreach (['uploads', 'backups', 'logs', 'cache'] as $dir) {
            $indexPath = $siteDir . '/' . $dir . '/index.php';
            File::put($indexPath, $indexContent);
        }
    }

    private function getDirectorySize(string $directory): int
    {
        $size = 0;
        if (File::exists($directory)) {
            $files = File::allFiles($directory);
            foreach ($files as $file) {
                $size += File::size($file);
            }
        }
        return $size;
    }

    private function deleteRemoteStorage(Site $site): void
    {
        $remotePrefix = "sites/site_{$site->id}";
        Storage::deleteDirectory($remotePrefix);
    }

    private function syncDirectoryToRemote(string $localDir, string $remotePrefix): void
    {
        $files = File::allFiles($localDir);
        
        foreach ($files as $file) {
            $relativePath = str_replace($localDir . '/', '', $file->getPathname());
            $remotePath = $remotePrefix . '/' . $relativePath;
            
            Storage::put($remotePath, File::get($file->getPathname()));
        }
    }
}