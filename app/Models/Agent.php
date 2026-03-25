<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'role_code',
        'avatar_url',
        'acado_coins',
        'system_prompt',
        'bio',
        'soul',
        'soul_configuration', // <--- MUSS HIER REIN
        'perspectives',
        'is_active',
        'tags',
        'experience_stats',
    ];

    protected $casts = [
        'soul_configuration' => 'array', // <--- DAS IST DER ENTSCHEIDENDE FIX
        'perspectives' => 'array',
        'is_active' => 'boolean',
        'tags' => 'array',
        'experience_stats' => 'array',
    ];
}
