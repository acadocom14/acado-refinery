<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Wir prüfen jede Spalte einzeln, um Fehler zu vermeiden
            if (!Schema::hasColumn('assets', 'type')) {
                $table->string('type')->default('portal')->after('name');
            }
            if (!Schema::hasColumn('assets', 'status')) {
                $table->string('status')->default('active')->after('type');
            }
            if (!Schema::hasColumn('assets', 'description')) {
                $table->text('description')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['type', 'status', 'description']);
        });
    }
};
