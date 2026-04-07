<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Die Spalte für das Live-Terminal
            if (!Schema::hasColumn('assets', 'processing_logs')) {
                $table->json('processing_logs')->nullable();
            }
            // Die Spalte für die Archivierung der einzelnen Stages
            if (!Schema::hasColumn('assets', 'pipeline_outputs')) {
                $table->json('pipeline_outputs')->nullable();
            }
            // Die Spalte für das fertige Produkt (Stage 4+5)
            if (!Schema::hasColumn('assets', 'final_content')) {
                $table->text('final_content')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['processing_logs', 'pipeline_outputs', 'final_content']);
        });
    }
};
