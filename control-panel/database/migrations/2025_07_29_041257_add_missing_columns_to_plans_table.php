<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('plans', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('limits');
            }
            
            if (!Schema::hasColumn('plans', 'is_public')) {
                $table->boolean('is_public')->default(true)->after('is_active');
            }
            
            // Add any other missing columns that might be needed
            if (!Schema::hasColumn('plans', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            
            if (!Schema::hasColumn('plans', 'stripe_price_id')) {
                $table->string('stripe_price_id')->nullable()->after('price');
            }
            
            if (!Schema::hasColumn('plans', 'trial_days')) {
                $table->integer('trial_days')->default(0)->after('stripe_price_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'is_public', 'description', 'stripe_price_id', 'trial_days']);
        });
    }
};