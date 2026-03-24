<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Agent extends Model
{
    use HasFactory;

    /**
     * Die Attribute, die massenweise zugewiesen werden dürfen.
     * Hier fügen wir alles hinzu, was wir im Filament-Formular nutzen.
     */
    protected $fillable = [
        'name',
        'role_code',    // Wichtig für den Rollen-Shorthand
        'avatar_url',   // Der Übeltäter für deinen aktuellen Fehler
        'acado_coins',  // Damit das Wallet gespeichert werden kann
        'system_prompt', 
        'bio',
        'soul_path',
    ];
}
