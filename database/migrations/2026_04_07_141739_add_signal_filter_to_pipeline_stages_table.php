<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Alles Alte abreißen (verhindert den SQLite-Bug)
        Schema::dropIfExists('pipeline_stages');

        // 2. Die Tabelle sauber, frisch und inkl. Dropdown-Schalter neu bauen
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->id();
            
            // Beziehung
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            
            // Felder
            $table->integer('stage_level');
            $table->string('name');
            $table->string('llm_model')->nullable();
            
            // Der Routing-Schalter
            $table->string('signal_filter')->default('previous_only');
            
            $table->text('fixed_prompt_template')->nullable();
            $table->text('custom_prompt_directive')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_stages');
    }
};
