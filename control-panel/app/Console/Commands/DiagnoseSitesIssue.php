<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Site;

class DiagnoseSitesIssue extends Command
{
    protected $signature = 'diagnose:sites';
    protected $description = 'Diagnose issues with sites table and model';

    public function handle()
    {
        $this->info('Diagnosing Sites Table Issues');
        $this->info('============================');
        
        // 1. Check table structure
        $this->info("\n1. Table Structure:");
        $columns = DB::select("SHOW COLUMNS FROM sites");
        
        $tableData = [];
        $columnNames = [];
        foreach ($columns as $column) {
            $tableData[] = [
                $column->Field,
                $column->Type,
                $column->Null,
                $column->Default ?? 'NULL',
                $column->Extra
            ];
            $columnNames[] = $column->Field;
        }
        
        $this->table(
            ['Field', 'Type', 'Null', 'Default', 'Extra'],
            $tableData
        );
        
        // 2. Check model fillable
        $this->info("\n2. Model Fillable Properties:");
        $site = new Site();
        $fillable = $site->getFillable();
        
        if (empty($fillable)) {
            $this->warn("No fillable properties defined!");
        } else {
            $this->line("Fillable: " . implode(', ', $fillable));
        }
        
        // 3. Check for mismatches
        $this->info("\n3. Checking for mismatches:");
        
        // Find fillable fields not in database
        $missingInDb = array_diff($fillable, $columnNames);
        if (!empty($missingInDb)) {
            $this->error("Fields in fillable but NOT in database: " . implode(', ', $missingInDb));
        }
        
        // Find required fields (NOT NULL without default)
        $this->info("\n4. Required fields (NOT NULL without default):");
        foreach ($columns as $column) {
            if ($column->Null === 'NO' && $column->Default === null && !in_array($column->Extra, ['auto_increment'])) {
                $inFillable = in_array($column->Field, $fillable) ? '✓' : '✗';
                $this->line("  - {$column->Field} (In fillable: {$inFillable})");
            }
        }
        
        // 5. Quick fix suggestion
        $this->info("\n5. Quick Fix:");
        $this->line("Run: php artisan fix:sites-table");
        $this->line("Or manually fix with:");
        $this->line("ALTER TABLE sites MODIFY admin_password VARCHAR(255) NULL;");
    }
}