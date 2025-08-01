<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CheckDatabaseStructure extends Command
{
    protected $signature = 'db:check-structure';
    protected $description = 'Check the current database structure for sites table';

    public function handle()
    {
        $this->info('Checking Sites Table Structure...');
        $this->info('=================================');
        
        // Check if sites table exists
        if (!Schema::hasTable('sites')) {
            $this->error('Sites table does not exist!');
            return;
        }
        
        // Get column information
        $columns = Schema::getColumnListing('sites');
        
        $this->info('Current columns in sites table:');
        $this->table(['Column Name'], array_map(fn($col) => [$col], $columns));
        
        // Check for required columns
        $requiredColumns = [
            'id',
            'subdomain',
            'user_id',
            'plan_id',
            'status',
            'expires_at',
            'suspended_at',
            'db_name',
            'db_user',
            'db_password',
            'db_prefix',
            'admin_email',
            'admin_username',
            'site_title',
            'settings',
            'usage_violations',
            'created_at',
            'updated_at'
        ];
        
        $missingColumns = array_diff($requiredColumns, $columns);
        
        if (!empty($missingColumns)) {
            $this->error("\nMissing columns:");
            $this->table(['Missing Column'], array_map(fn($col) => [$col], $missingColumns));
        } else {
            $this->info("\n✓ All required columns exist!");
        }
        
        // Show detailed column information
        $this->info("\nDetailed column information:");
        $columnDetails = DB::select("SHOW COLUMNS FROM sites");
        
        $tableData = [];
        foreach ($columnDetails as $column) {
            $tableData[] = [
                $column->Field,
                $column->Type,
                $column->Null,
                $column->Key,
                $column->Default,
                $column->Extra
            ];
        }
        
        $this->table(
            ['Field', 'Type', 'Null', 'Key', 'Default', 'Extra'],
            $tableData
        );
    }
}