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
        // HIER KOMMT DEIN CODE REIN:
        Schema::table('pipeline_stages', function (Blueprint $table) {
            // Wir fügen die Spalte hinzu und verknüpfen sie mit der ai_models Tabelle
            $table->foreignId('ai_model_id')
                ->nullable()
                ->after('name') // Optional: Setzt die Spalte optisch hinter 'name'
                ->constrained('ai_models')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pipeline_stages', function (Blueprint $table) {
            // Falls wir die Migration rückgängig machen: Foreign Key und Spalte löschen
            $table->dropForeign(['ai_model_id']);
            $table->dropColumn('ai_model_id');
        });
    }
};
