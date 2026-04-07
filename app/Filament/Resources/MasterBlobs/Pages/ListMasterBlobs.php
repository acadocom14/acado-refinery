<?php

namespace App\Filament\Resources\MasterBlobs\Pages;

use App\Filament\Resources\MasterBlobs\MasterBlobResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMasterBlobs extends ListRecords
{
    protected static string $resource = MasterBlobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
