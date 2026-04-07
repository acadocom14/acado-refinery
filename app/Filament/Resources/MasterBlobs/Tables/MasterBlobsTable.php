<?php

namespace App\Filament\Resources\MasterBlobs\Tables;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class MasterBlobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('asset.name')
                    ->label('Asset Name')
                    ->searchable(),
                
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'warning',
                        'approved' => 'success',
                        'published' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('sqids_hash')
                    ->label('Hash')
                    ->placeholder('Click row to edit/approve'),
            ])
            // ANSTATT BUTTONS: Klick auf die Zeile führt direkt zum Edit-Formular
            ->recordUrl(
                fn ($record): string => \App\Filament\Resources\MasterBlobs\Pages\EditMasterBlob::getUrl([$record->id]),
            );
    }
}
