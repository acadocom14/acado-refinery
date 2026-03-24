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
        Schema::create('ingest_signals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            
            // ->nullable() erlaubt ein leeres Feld beim Start
            $table->text('raw_content')->nullable(); 
            
            $table->string('status')->default('draft'); // draft, bidding, compliance_check, live
            $table->json('master_blob')->nullable(); // Das finale KI-Ergebnis
            
            // Die zwei fehlenden Felder aus dem Pflichtenheft / Model
            $table->string('source_type')->nullable();
            $table->string('state')->nullable();
            
            $table->timestamps();
        }); 
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingest_signals');
    }
};
