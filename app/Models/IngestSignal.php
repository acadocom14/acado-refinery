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
        'author',            // Falls du das schon drin hast
        'original_filename', // Falls du das schon drin hast
        'tags',              // <--- NEU
    ];

    protected $casts = [
        'tags' => 'array',   // <--- NEU: Macht aus dem JSON eine nutzbare Liste
    ];
}
