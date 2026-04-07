<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
    Schema::table('assets', function (Blueprint $table) {
        if (!Schema::hasColumn('assets', 'pipeline_outputs')) {
            $table->json('pipeline_outputs')->nullable();
        }
        if (!Schema::hasColumn('assets', 'final_content')) {
            $table->text('final_content')->nullable();
        }
        if (!Schema::hasColumn('assets', 'processing_logs')) {
            $table->json('processing_logs')->nullable();
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            //
        });
    }
};
