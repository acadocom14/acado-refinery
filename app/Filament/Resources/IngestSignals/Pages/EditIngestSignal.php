<?php

namespace App\Filament\Resources\IngestSignals\Pages; // EXAKT DIESER PFAD

use App\Filament\Resources\IngestSignals\IngestSignalResource;
use App\Services\IngestPipeline;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

use App\Jobs\ProcessBoardMeetingJob;


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
            ->modalHeading('Board Meeting einberufen')
            ->modalDescription('Die Experten werden das Dokument jetzt analysieren. Du kannst den Fortschritt im Live-Log verfolgen.')
            ->action(function () {
                // DER FIX: Wir starten den Job im Hintergrund
                ProcessBoardMeetingJob::dispatch($this->record);

                // Sofortige Rückmeldung an den User
                Notification::make()
                    ->title('Meeting gestartet')
                    ->body('Die Experten treten jetzt zusammen. Schau ins Live-Log!')
                    ->info()
                    ->send();
            
                // Status auf "processing" setzen, damit das UI sofort reagiert
                $this->record->update(['status' => 'processing']);
            }),

            Actions\DeleteAction::make(),
        ];
    }
}
