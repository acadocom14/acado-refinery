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
    Schema::table('agents', function (Blueprint $table) {
        $table->text('soul')->nullable();         // Die soul.md Charakterbeschreibung
        $table->json('perspectives')->nullable(); // Die 4 Analyse-Winkel
        $table->boolean('is_active')->default(true); // Der Urlaubs-Schalter
    });

    Schema::table('ingest_signals', function (Blueprint $table) {
        $table->string('author')->nullable();
        $table->string('original_filename')->nullable();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
