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
        'perspectives',
        'is_active',
        'tags',
        'experience_stats', // <--- NEU
    ];

    protected $casts = [
        'perspectives' => 'array',
        'is_active' => 'boolean',
        'tags' => 'array',
        'experience_stats' => 'array', // <--- NEU: Macht es zu einem nutzbaren Array
    ];
}
