// database/migrations/xxxx_create_plans_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price', 8, 2);
            $table->integer('sites_limit');
            $table->bigInteger('storage_limit'); // in MB
            $table->bigInteger('bandwidth_limit'); // in MB
            $table->json('features');
            $table->boolean('is_active')->default(true);
            $table->integer('trial_days')->default(0);
            $table->string('stripe_price_id')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('plans');
    }
};