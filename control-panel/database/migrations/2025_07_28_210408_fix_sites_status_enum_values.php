<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add missing status values to the enum
        DB::statement("ALTER TABLE sites MODIFY status ENUM('pending', 'provisioning', 'active', 'suspended', 'failed', 'deleted') DEFAULT 'provisioning'");
    }

    public function down(): void
    {
        // Revert to original enum
        DB::statement("ALTER TABLE sites MODIFY status ENUM('provisioning', 'active', 'suspended', 'deleted') DEFAULT 'provisioning'");
    }
};