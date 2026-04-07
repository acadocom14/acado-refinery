<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PipelineStagesRelationManager extends RelationManager
{
    protected static string $relationship = 'pipelineStages';
    protected static ?string $title = 'Pipeline Übersicht';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('stage_level')->label('Lvl'),
                \Filament\Tables\Columns\TextColumn::make('name')->label('Stage'),
                \Filament\Tables\Columns\TextColumn::make('llm_model')->label('Engine'),
            ])
            // WIR LASSEN DIE ARRAYS ABSOLUT LEER
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
