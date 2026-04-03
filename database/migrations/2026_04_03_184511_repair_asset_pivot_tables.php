<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Wir reparieren die asset_agent Tabelle
        Schema::table('asset_agent', function (Blueprint $table) {
            if (!Schema::hasColumn('asset_agent', 'asset_id')) {
                $table->foreignId('asset_id')->nullable()->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('asset_agent', 'agent_id')) {
                $table->foreignId('agent_id')->nullable()->constrained()->cascadeOnDelete();
            }
        });

        // Wir reparieren die asset_ingest_signal Tabelle
        Schema::table('asset_ingest_signal', function (Blueprint $table) {
            if (!Schema::hasColumn('asset_ingest_signal', 'asset_id')) {
                $table->foreignId('asset_id')->nullable()->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('asset_ingest_signal', 'ingest_signal_id')) {
                $table->foreignId('ingest_signal_id')->nullable()->constrained()->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        // Falls wir zurückmüssen (optional)
    }
};
