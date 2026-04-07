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
        Schema::table('assets', function (Blueprint $table) {
        $table->json('pipeline_outputs')->nullable(); // Speichert [stage_1 => '...', stage_2 => '...']
        $table->text('final_content')->nullable();    // Das fertige Produkt (Stage 4 + 5 Kombi)
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
