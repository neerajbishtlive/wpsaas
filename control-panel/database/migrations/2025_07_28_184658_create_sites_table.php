// database/migrations/xxxx_create_sites_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->nullable()->constrained();
            $table->string('subdomain')->unique();
            $table->string('custom_domain')->nullable();
            $table->string('db_name');
            $table->string('db_user');
            $table->string('db_password');
            $table->string('db_prefix');
            $table->enum('status', ['provisioning', 'active', 'suspended', 'deleted'])->default('provisioning');
            $table->enum('type', ['guest', 'registered', 'paid'])->default('guest');
            $table->bigInteger('storage_used')->default(0);
            $table->bigInteger('bandwidth_used')->default(0);
            $table->string('admin_email');
            $table->string('admin_password');
            $table->json('settings')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'expires_at']);
            $table->index('type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sites');
    }
};