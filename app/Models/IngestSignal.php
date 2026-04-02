<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class IngestSignal extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'title',
        'file_path',
        'status',
        'author',            
        'original_filename', 
        'tags',              
        'raw_content',       // <--- WICHTIG: Für den extrahierten Text
        'processing_logs',   // <--- WICHTIG: Erlaubt das Speichern der Logs
        'master_blob_draft', // <--- WICHTIG: Erlaubt das Speichern des finalen Ergebnisses
        'category',
    ];

    protected $casts = [
        'tags' => 'array',   
        'processing_logs' => 'array', // <--- DER FIX: Macht den String zum Array!
    ];
}
