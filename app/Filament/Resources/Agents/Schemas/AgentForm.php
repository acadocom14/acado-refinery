<?php

namespace App\Filament\Resources\Agents\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AgentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('role')
                    ->required(),
                TextInput::make('acado_coins')
                    ->required()
                    ->numeric()
                    ->default(1000),
                Textarea::make('system_prompt')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
