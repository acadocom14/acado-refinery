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
        // Speichert die Anzahl gelesener Bücher pro Tag (z.B. {"wine": 10})
        $table->json('experience_stats')->nullable(); 
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            //
        });
    }
};
