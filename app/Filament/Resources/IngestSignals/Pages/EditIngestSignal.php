<?php

namespace App\Filament\Resources\IngestSignals\Pages; // EXAKT DIESER PFAD

use App\Filament\Resources\IngestSignals\IngestSignalResource;
use App\Services\IngestPipeline;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditIngestSignal extends EditRecord
{
    protected static string $resource = IngestSignalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Der Board-Meeting Button
            Actions\Action::make('runBoardMeeting')
                ->label('Run Board Meeting')
                ->icon('heroicon-o-users')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (IngestPipeline $pipeline) {
                    try {
                        $pipeline->processWithRouting($this->record);

                        Notification::make()
                            ->title('Board Meeting erfolgreich')
                            ->body('Die Agenten-Exzerpte wurden gespeichert.')
                            ->success()
                            ->send();

                        $this->refreshFormData(['master_blob_draft', 'status', 'tags']);
                        
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Fehler')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
