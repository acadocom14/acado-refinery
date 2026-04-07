<?php

namespace App\Filament\Resources\MasterBlobs\Pages;

use App\Filament\Resources\MasterBlobs\MasterBlobResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMasterBlob extends EditRecord
{
    protected static string $resource = MasterBlobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
