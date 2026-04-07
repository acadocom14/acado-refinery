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
        Schema::create('master_blobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            
            // Metadaten & Inhalt
            $table->string('trigger_source')->nullable(); // Woher kam die Idee? (z.B. Tweet-URL, RSS-Titel)
            $table->longText('generated_content'); // Der Text, den die Agenten-Pipeline generiert hat
            
            // Status & Freigabe
            $table->string('status')->default('draft'); // 'draft', 'approved', 'rejected', 'published'
            $table->string('sqids_hash')->nullable()->unique(); // Der M&A Krypto-Beweis
            $table->timestamp('approved_at')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_blobs');
    }
};
