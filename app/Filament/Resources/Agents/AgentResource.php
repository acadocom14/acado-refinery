<?php

namespace App\Filament\Resources\Agents;

use App\Filament\Resources\Agents\AgentResource\Pages;
use App\Models\Agent;
use Filament\Resources\Resource;
use Filament\Schemas\Schema; // v4/2026 Standard laut deinem Error-Log
use Filament\Tables\Table;
use BackedEnum;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Agents';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\FileUpload::make('avatar_url')
                    ->label('Portrait Icon')
                    ->image()
                    ->avatar()
                    ->disk('public')      // Muss auf 'public' stehen
                    ->visibility('public') // <--- Das hier sicherheitshalber hinzufügen
                    ->directory('agents')
                    ->columnSpanFull(),

                \Filament\Forms\Components\TextInput::make('name')
                    ->label('Executive Name')
                    ->required(),

                \Filament\Forms\Components\TextInput::make('role_code')
                    ->label('Role')
                    ->required(),

                \Filament\Forms\Components\TextInput::make('acado_coins')
                    ->label('Wallet (ACD)')
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name')
                    ->label('Executive Name (Click to Edit)')
                    ->weight('bold')
                    ->searchable()
                    // DAS IST DER RETTER: Wir nutzen die Spalten-URL statt einer Action-Klasse
                    ->url(fn (Agent $record): string => Pages\EditAgent::getUrl(['record' => $record])),

                \Filament\Tables\Columns\TextColumn::make('role_code')
                    ->label('Role'),

                \Filament\Tables\Columns\TextColumn::make('acado_coins')
                    ->label('Wallet')
                    ->money('USD')
                    ->sortable(),
            ])
            // Wir lassen diese Arrays leer, damit Filament nicht nach fehlenden Klassen sucht
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgents::route('/'),
            'create' => Pages\CreateAgent::route('/create'),
            'edit' => Pages\EditAgent::route('/{record}/edit'),
        ];
    }
}
