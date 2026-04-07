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
    Schema::create('ai_models', function (Blueprint $table) {
        $table->id();
        $table->string('name'); // Anzeige-Name (z.B. Gemini 1.5 Pro)
        $table->string('api_model_id'); // Technischer Name (z.B. gemini-1.5-pro)
        $table->string('provider')->default('google'); // google, openai, etc.
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
