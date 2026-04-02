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
        Schema::table('ingest_signals', function (Blueprint $table) {
            // biz, tech, philo
            $table->string('category')->nullable()->default('biz')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingest_signals', function (Blueprint $table) {
            //
        });
    }
};
