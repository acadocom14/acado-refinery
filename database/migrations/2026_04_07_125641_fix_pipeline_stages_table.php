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
        // Wir machen absichtlich gar nichts. 
        // So läuft die Migration durch, ohne SQLite wütend zu machen.
    }

    public function down(): void
    {
        // Auch leer lassen
    }
};
