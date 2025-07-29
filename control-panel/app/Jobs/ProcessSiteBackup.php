<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\StorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Exception;

class ProcessSiteBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes
    public $tries = 2;

    protected $site;
    protected $backupType;
    protected $storageService;

    /**
     * Create a new job instance.
     */
    public function __construct(Site $site, string $backupType = 'full')
    {
        $this->site = $site;
        $this->backupType = $backupType; // 'full', 'files', 'database'
        $this->storageService = app(StorageService::class);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Starting backup for site: {$this->site->subdomain}", [
            'site_id' => $this->site->id,
            'backup_type' => $this->backupType
        ]);

        try {
            // Check if site is eligible for backups
            if (!$this->isBackupEligible()) {
                Log::info("Site {$this->site->subdomain} is not eligible for backups");
                return;
            }

            // Create backup directory
            $backupDir = $this->createBackupDirectory();
            
            // Perform backup based on type
            $backupFiles = [];
            
            switch ($this->backupType) {
                case 'full':
                    $backupFiles = array_merge(
                        $this->backupDatabase($backupDir),
                        $this->backupFiles($backupDir)
                    );
                    break;
                case 'database':
                    $backupFiles = $this->backupDatabase($backupDir);
                    break;
                case 'files':
                    $backupFiles = $this->backupFiles($backupDir);
                    break;
                default:
                    throw new Exception("Invalid backup type: {$this->backupType}");
            }

            // Create compressed backup archive
            $archivePath = $this->createBackupArchive($backupDir, $backupFiles);

            // Store backup metadata
            $backupInfo = $this->storeBackupMetadata($archivePath);

            // Cleanup old backups
            $this->cleanupOldBackups();

            // Upload to remote storage if configured
            if ($this->shouldUploadToRemote()) {
                $this->uploadToRemoteStorage($archivePath, $backupInfo);
            }

            Log::info("Backup completed successfully for site: {$this->site->subdomain}", [
                'backup_file' => basename($archivePath),
                'backup_size_mb' => round(filesize($archivePath) / 1024 / 1024, 2)
            ]);

            // Cleanup temporary files
            $this->cleanupTemporaryFiles($backupDir);

        } catch (Exception $e) {
            Log::error("Backup failed for site {$this->site->subdomain}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if site is eligible for backups
     */
    private function isBackupEligible(): bool
    {
        // Only paid plans get backups
        if (!$this->site->plan || !$this->site->plan->has_backups) {
            return false;
        }

        // Site must be active
        if ($this->site->status !== 'active') {
            return false;
        }

        // Check backup frequency limits
        $lastBackup = $this->getLastBackupTime();
        $backupFrequencyHours = $this->site->plan->backup_frequency_hours ?? 24;
        
        if ($lastBackup && $lastBackup->addHours($backupFrequencyHours)->isFuture()) {
            Log::info("Backup skipped - frequency limit not reached for site: {$this->site->subdomain}");
            return false;
        }

        return true;
    }

    /**
     * Create backup directory
     */
    private function createBackupDirectory(): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupDir = storage_path("sites/site_{$this->site->id}/backups/{$timestamp}_{$this->backupType}");
        
        if (!File::makeDirectory($backupDir, 0755, true)) {
            throw new Exception("Failed to create backup directory: {$backupDir}");
        }

        return $backupDir;
    }

    /**
     * Backup database
     */
    private function backupDatabase(string $backupDir): array
    {
        if ($this->backupType === 'files') {
            return [];
        }

        Log::info("Creating database backup for site: {$this->site->subdomain}");

        $dbConfig = $this->site->getDatabaseConfig();
        $backupFile = $backupDir . '/database.sql';
        
        // Build mysqldump command
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s',
            escapeshellarg($dbConfig['host']),
            escapeshellarg($dbConfig['username']),
            escapeshellarg($dbConfig['password']),
            escapeshellarg($dbConfig['database']),
            escapeshellarg($backupFile)
        );

        // Execute backup command
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception("Database backup failed: " . implode("\n", $output));
        }

        if (!file_exists($backupFile) || filesize($backupFile) === 0) {
            throw new Exception("Database backup file is empty or not created");
        }

        Log::info("Database backup created", [
            'file' => basename($backupFile),
            'size_mb' => round(filesize($backupFile) / 1024 / 1024, 2)
        ]);

        return [$backupFile];
    }

    /**
     * Backup files
     */
    private function backupFiles(string $backupDir): array
    {
        if ($this->backupType === 'database') {
            return [];
        }

        Log::info("Creating files backup for site: {$this->site->subdomain}");

        $siteDir = base_path("../sites/site_{$this->site->id}");
        $backupFiles = [];

        if (!is_dir($siteDir)) {
            Log::warning("Site directory not found: {$siteDir}");
            return [];
        }

        // Backup WordPress files (excluding cache and logs)
        $excludePatterns = [
            '*/cache/*',
            '*/logs/*',
            '*/tmp/*',
            '*/.DS_Store',
            '*/Thumbs.db'
        ];

        $filesBackupDir = $backupDir . '/files';
        File::makeDirectory($filesBackupDir, 0755, true);

        // Copy WordPress core files and content
        $this->copyDirectory($siteDir, $filesBackupDir, $excludePatterns);

        // Create manifest file
        $manifestFile = $backupDir . '/files_manifest.json';
        $manifest = $this->createFilesManifest($filesBackupDir);
        File::put($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT));

        $backupFiles[] = $filesBackupDir;
        $backupFiles[] = $manifestFile;

        Log::info("Files backup created", [
            'total_files' => $manifest['file_count'],
            'total_size_mb' => round($manifest['total_size'] / 1024 / 1024, 2)
        ]);

        return $backupFiles;
    }

    /**
     * Create backup archive
     */
    private function createBackupArchive(string $backupDir, array $backupFiles): string
    {
        if (empty($backupFiles)) {
            throw new Exception("No files to archive");
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $archiveName = "backup_{$this->site->subdomain}_{$timestamp}_{$this->backupType}.tar.gz";
        $archivePath = dirname($backupDir) . '/' . $archiveName;

        // Create tar.gz archive
        $command = sprintf(
            'cd %s && tar -czf %s .',
            escapeshellarg($backupDir),
            escapeshellarg($archivePath)
        );

        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception("Archive creation failed: " . implode("\n", $output));
        }

        if (!file_exists($archivePath)) {
            throw new Exception("Archive file not created");
        }

        return $archivePath;
    }

    /**
     * Store backup metadata
     */
    private function storeBackupMetadata(string $archivePath): array
    {
        $backupInfo = [
            'site_id' => $this->site->id,
            'backup_type' => $this->backupType,
            'filename' => basename($archivePath),
            'file_path' => $archivePath,
            'file_size' => filesize($archivePath),
            'created_at' => now()->toISOString(),
            'expires_at' => now()->addDays($this->getBackupRetentionDays())->toISOString()
        ];

        // Store in database or cache
        $backupsFile = storage_path("sites/site_{$this->site->id}/backups/backups.json");
        $backups = [];

        if (file_exists($backupsFile)) {
            $backups = json_decode(file_get_contents($backupsFile), true) ?: [];
        }

        $backups[] = $backupInfo;
        File::put($backupsFile, json_encode($backups, JSON_PRETTY_PRINT));

        return $backupInfo;
    }

    /**
     * Cleanup old backups
     */
    private function cleanupOldBackups(): void
    {
        $retentionDays = $this->getBackupRetentionDays();
        $backupsDir = storage_path("sites/site_{$this->site->id}/backups");
        $cutoffDate = now()->subDays($retentionDays);

        if (!is_dir($backupsDir)) {
            return;
        }

        $deletedCount = 0;
        $files = File::files($backupsDir);

        foreach ($files as $file) {
            $filePath = $file->getPathname();
            
            // Skip metadata files
            if (pathinfo($filePath, PATHINFO_EXTENSION) === 'json') {
                continue;
            }

            $fileTime = Carbon::createFromTimestamp(File::lastModified($filePath));
            
            if ($fileTime->isBefore($cutoffDate)) {
                File::delete($filePath);
                $deletedCount++;
                Log::info("Deleted old backup: " . basename($filePath));
            }
        }

        if ($deletedCount > 0) {
            Log::info("Cleaned up {$deletedCount} old backup files for site: {$this->site->subdomain}");
        }
    }

    /**
     * Check if should upload to remote storage
     */
    private function shouldUploadToRemote(): bool
    {
        return config('backup.upload_to_remote', false) && 
               $this->storageService->getStorageDriver() !== 'local';
    }

    /**
     * Upload backup to remote storage
     */
    private function uploadToRemoteStorage(string $archivePath, array $backupInfo): void
    {
        try {
            $remotePath = "backups/site_{$this->site->id}/" . basename($archivePath);
            
            Storage::put($remotePath, File::get($archivePath));
            
            Log::info("Backup uploaded to remote storage", [
                'local_path' => $archivePath,
                'remote_path' => $remotePath
            ]);

            // Update backup info with remote path
            $backupInfo['remote_path'] = $remotePath;
            $backupInfo['uploaded_to_remote'] = true;

        } catch (Exception $e) {
            Log::warning("Failed to upload backup to remote storage: " . $e->getMessage());
        }
    }

    /**
     * Copy directory with exclusions
     */
    private function copyDirectory(string $source, string $destination, array $excludePatterns = []): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = $iterator->getSubPathName();
            
            // Check if file should be excluded
            if ($this->shouldExcludeFile($relativePath, $excludePatterns)) {
                continue;
            }

            $destPath = $destination . '/' . $relativePath;

            if ($item->isDir()) {
                File::makeDirectory($destPath, 0755, true);
            } else {
                File::copy($item->getPathname(), $destPath);
            }
        }
    }

    /**
     * Check if file should be excluded
     */
    private function shouldExcludeFile(string $relativePath, array $excludePatterns): bool
    {
        foreach ($excludePatterns as $pattern) {
            if (fnmatch($pattern, $relativePath)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Create files manifest
     */
    private function createFilesManifest(string $directory): array
    {
        $fileCount = 0;
        $totalSize = 0;
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $fileCount++;
                $size = $file->getSize();
                $totalSize += $size;
                
                $files[] = [
                    'path' => $iterator->getSubPathName(),
                    'size' => $size,
                    'modified' => date('Y-m-d H:i:s', $file->getMTime())
                ];
            }
        }

        return [
            'created_at' => now()->toISOString(),
            'file_count' => $fileCount,
            'total_size' => $totalSize,
            'files' => $files
        ];
    }

    /**
     * Get last backup time
     */
    private function getLastBackupTime(): ?Carbon
    {
        $backupsFile = storage_path("sites/site_{$this->site->id}/backups/backups.json");
        
        if (!file_exists($backupsFile)) {
            return null;
        }

        $backups = json_decode(file_get_contents($backupsFile), true);
        
        if (empty($backups)) {
            return null;
        }

        $lastBackup = collect($backups)
            ->where('backup_type', $this->backupType)
            ->sortByDesc('created_at')
            ->first();

        return $lastBackup ? Carbon::parse($lastBackup['created_at']) : null;
    }

    /**
     * Get backup retention days
     */
    private function getBackupRetentionDays(): int
    {
        return $this->site->plan->backup_retention_days ?? config('backup.retention_days', 30);
    }

    /**
     * Cleanup temporary files
     */
    private function cleanupTemporaryFiles(string $backupDir): void
    {
        try {
            if (is_dir($backupDir)) {
                File::deleteDirectory($backupDir);
                Log::info("Cleaned up temporary backup directory: {$backupDir}");
            }
        } catch (Exception $e) {
            Log::warning("Failed to cleanup temporary backup directory: " . $e->getMessage());
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error("Backup job failed for site {$this->site->subdomain}", [
            'site_id' => $this->site->id,
            'backup_type' => $this->backupType,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // TODO: Send notification to user about backup failure
        // TODO: Send alert to administrators
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): Carbon
    {
        return now()->addHours(2);
    }
}