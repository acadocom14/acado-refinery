<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use App\Services\IngestPipeline;
use App\Jobs\ProcessAssetPipelineJob; 

class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('run_pipeline')
                ->label('🚀 Pipeline starten (Queue)')
                ->color('success')
                ->icon('heroicon-o-rocket-launch')
                ->requiresConfirmation()
                ->modalHeading('Asset-Veredelung starten')
                ->modalDescription('Dies wird alle Agenten (inkl. Julian Valmont) wecken. Der Prozess läuft im Hintergrund ab.')
                ->modalSubmitActionLabel('Ja, Produktion starten')
                ->action(function () {
                    // 1. Lokales UI-Update: Status auf 'processing' und Logs resetten
                    // Das triggert das Terminal-Widget im Frontend sofort an.
                    $this->record->update([
                        'status' => 'processing',
                        'processing_logs' => [], 
                    ]);

                    // 2. Den Job in die Queue werfen
                    // Wir geben das frisch aktualisierte Record-Objekt mit.
                    ProcessAssetPipelineJob::dispatch($this->record);
                    
                    // 3. Bestätigung für den User
                    Notification::make()
                        ->title('Veredelung gestartet')
                        ->body('Trigger Source: Dashboard. Der Worker übernimmt jetzt.')
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
