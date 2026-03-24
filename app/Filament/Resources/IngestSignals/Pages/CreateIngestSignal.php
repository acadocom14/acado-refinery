<?php
namespace App\Filament\Resources\IngestSignals\Pages; // EXAKT DIESER PFAD

use App\Filament\Resources\IngestSignals\IngestSignalResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateIngestSignal extends CreateRecord
{
    protected static string $resource = IngestSignalResource::class;
}
