<?php

namespace App\Filament\Resources\MasterBlobs;

use App\Filament\Resources\MasterBlobs\Pages\CreateMasterBlob;
use App\Filament\Resources\MasterBlobs\Pages\EditMasterBlob;
use App\Filament\Resources\MasterBlobs\Pages\ListMasterBlobs;
use App\Filament\Resources\MasterBlobs\Schemas\MasterBlobForm;
use App\Filament\Resources\MasterBlobs\Tables\MasterBlobsTable;
use App\Models\MasterBlob;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MasterBlobResource extends Resource
{
    protected static ?string $model = MasterBlob::class;
    protected static string | \UnitEnum | null $navigationGroup = 'Pipeline & Quality';
    protected static ?string $navigationLabel = 'Approval Inbox';

    public static function form(Schema $schema): Schema
    {
        return MasterBlobForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MasterBlobsTable::configure($table);
        // KEINE ->actions() hier!
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMasterBlobs::route('/'),
            'create' => CreateMasterBlob::route('/create'),
            'edit' => EditMasterBlob::route('/{record}/edit'),
        ];
    }
}
