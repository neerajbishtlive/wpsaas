<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            // Add missing fields if they don't exist
            if (!Schema::hasColumn('sites', 'admin_email')) {
                $table->string('admin_email')->after('db_prefix');
            }
            if (!Schema::hasColumn('sites', 'admin_username')) {
                $table->string('admin_username')->after('admin_email');
            }
            if (!Schema::hasColumn('sites', 'site_title')) {
                $table->string('site_title')->after('admin_username');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['admin_email', 'admin_username', 'site_title']);
        });
    }
};