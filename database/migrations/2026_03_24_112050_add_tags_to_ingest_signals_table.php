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
        $table->json('tags')->nullable(); // Speichert die Themen des Buches
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
