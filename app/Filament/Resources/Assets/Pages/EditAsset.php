<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use Filament\Actions;
use Filament\Forms\Components\Textarea;
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
            // --- DIE SANDBOX (TEST-LABOR) ---
            Actions\Action::make('sandbox_trigger')
                ->label('🧪 Refinery Sandbox')
                ->color('info')
                ->icon('heroicon-o-beaker')
                ->modalHeading('Simulation: Externer Signal-Eingang')
                ->modalDescription('Kopiere hier den Inhalt rein, den du testen möchtest (z.B. einen X-Post). Dieser Text wird als Stage 0 gesetzt und gegen deine Fachbücher geprüft.')
                ->modalSubmitActionLabel('Simulation starten')
                ->form([
                    Textarea::make('simulated_content')
                        ->label('Simulierter Trigger-Content')
                        ->placeholder('Beispiel: Die Weinlese 2024 im Bordeaux wird durch Frost verzögert...')
                        ->rows(8)
                        ->required(),
                ])
                ->action(function (array $data) {
                    // 1. Trigger Content im Asset speichern und Status setzen
                    $this->record->update([
                    'trigger_content' => $data['simulated_content'] ?? $this->record->trigger_content,
                    'status' => 'processing',
                    'processing_logs' => [], 
                    ]);

                    // NEU: Das Formular im Browser zwingen, die frischen (leeren) Daten zu laden
                    $this->fillForm();

                    // 2. Die Engine starten
                    ProcessAssetPipelineJob::dispatch($this->record);
                    
                    Notification::make()
                        ->title('Sandbox-Lauf gestartet')
                        ->body('Der simulierte Trigger wird nun veredelt.')
                        ->info()
                        ->send();
                }),

            // --- DER STANDART-TRIGGER (RE-RUN) ---
            Actions\Action::make('run_pipeline')
                ->label('🚀 Pipeline starten')
                ->color('success')
                ->icon('heroicon-o-rocket-launch')
                ->requiresConfirmation()
                ->modalHeading('Asset-Veredelung starten')
                ->modalDescription('Dies nutzt den aktuell im Asset gespeicherten Trigger-Content.')
                ->action(function () {
                    $this->record->update([
                        'trigger_content' => $data['simulated_content'] ?? $this->record->trigger_content,
                        'status' => 'processing',
                        'processing_logs' => [], 
                    ]);

                    // NEU: Das Formular im Browser zwingen, die frischen (leeren) Daten zu laden
                    $this->fillForm();

                    ProcessAssetPipelineJob::dispatch($this->record);
                    
                    Notification::make()
                        ->title('Veredelung gestartet')
                        ->body('Die Refinery verarbeitet den bestehenden Content.')
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
