<?php

namespace App\Filament\Resources\IngestSignals\IngestSignalResource\Pages;

use App\Filament\Resources\IngestSignals\IngestSignalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIngestSignal extends EditRecord
{
    protected static string $resource = IngestSignalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
