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
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->integer('stage_level'); // 1, 2, 3, 4 (Reihenfolge)
            $table->string('name'); // z.B. "Stage 1: Fachteam Extraktion"
            $table->text('fixed_prompt_template')->nullable(); // Das harte System-Regelwerk
            $table->text('custom_prompt_directive')->nullable(); // Deine flexible Anweisung
            $table->string('llm_model')->default('gemini-1.5-flash'); // Flash oder Pro
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipeline_stages');
    }
};
