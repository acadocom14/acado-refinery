<?php

namespace App\Filament\Resources\IngestSignals\Pages; // EXACT PATH REQUIRED

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
            // --- 1. The Board Meeting Button ---
            Actions\Action::make('runBoardMeeting')
            ->label('Run Board Meeting')
            ->icon('heroicon-o-users')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Initiate Board Meeting')
            ->modalDescription('The experts will now analyze the document. You can track the progress in the live log.')
            ->action(function () {
                // THE FIX: Dispatch the job to the background queue
                ProcessBoardMeetingJob::dispatch($this->record);

                // Immediate feedback to the user
                Notification::make()
                    ->title('Meeting Started')
                    ->body('The experts are now convening. Check the live log!')
                    ->info()
                    ->send();
            
                // Set status to "processing" for immediate UI response
                $this->record->update(['status' => 'processing']);
            }),

            // --- 2. NEU: Der Wipe-Button für den Master Blob ---
            Actions\Action::make('clearBlob')
                ->label('Blob leeren')
                ->icon('heroicon-o-trash')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Master Blob wirklich leeren?')
                ->modalDescription('Achtung: Dies löscht die KI-Extraktion sofort und unwiderruflich aus der Datenbank.')
                ->action(function () {
                    // Hartes Update in der Datenbank
                    $this->record->update(['master_blob_draft' => null]);
                    
                    // Erfolgsmeldung
                    Notification::make()
                        ->title('Blob erfolgreich geleert!')
                        ->success()
                        ->send();
                        
                    // Seite hart neu laden, damit der Custom HTML-Placeholder aktualisiert wird
                    redirect(request()->header('Referer'));
                }),

            // --- 3. Standard Filament Delete Button ---
            Actions\DeleteAction::make(),
        ];
    }
}
