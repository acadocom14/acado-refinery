<?php

namespace App\Filament\Resources\Agents\Pages; // Muss EXAKT so heißen!

use App\Filament\Resources\Agents\AgentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAgent extends CreateRecord
{
    protected static string $resource = AgentResource::class;
}
