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
        Schema::create('asset_ingest_signal', function (Blueprint $table) {
        $table->id();
        $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
        $table->foreignId('ingest_signal_id')->constrained()->cascadeOnDelete();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_ingest_signal');
    }
};
