<?php

namespace App\Filament\Resources\IngestSignals\Pages; // EXAKT DIESER PFAD

use App\Filament\Resources\IngestSignals\IngestSignalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIngestSignals extends ListRecords
{
    protected static string $resource = IngestSignalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
