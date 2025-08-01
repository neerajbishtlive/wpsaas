// database/migrations/xxxx_create_site_statistics_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('site_statistics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->integer('visits')->default(0);
            $table->bigInteger('bandwidth')->default(0);
            $table->bigInteger('storage')->default(0);
            $table->timestamps();
            
            $table->unique(['site_id', 'date']);
            $table->index('date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('site_statistics');
    }
};