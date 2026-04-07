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
        Schema::create('agent_pipeline_stage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agents')->cascadeOnDelete(); // Angenommen deine Agenten-Tabelle heißt 'agents'
            $table->string('role')->default('support'); // 'lead' oder 'support' für das 70-20-10 Ledger
            $table->timestamps();
        
            // Ein Agent sollte pro Stage nur einmal zugewiesen werden
            $table->unique(['pipeline_stage_id', 'agent_id']);
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_pipeline_stage');
    }
};
