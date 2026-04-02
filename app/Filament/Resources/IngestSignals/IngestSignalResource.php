<?php

namespace App\Filament\Resources\IngestSignals;

use App\Models\IngestSignal;
use Filament\Resources\Resource;
use Filament\Schemas\Schema; 
use Filament\Tables\Table;
use App\Filament\Resources\IngestSignals\Pages;

use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

use Filament\Notifications\Notification;

class IngestSignalResource extends Resource
{
    protected static ?string $model = IngestSignal::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-magnifying-glass';
    protected static string | \UnitEnum | null $navigationGroup = 'Scout Portal';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Forms\Components\TextInput::make('title')->label('Deal / Asset')->required()->columnSpanFull(),
            \Filament\Forms\Components\TagsInput::make('tags')->label('Themen-Tags')->columnSpanFull(),
            
            // Kategorie-Auswahl im Formular hinzugefügt
            \Filament\Forms\Components\Select::make('category')
                ->label('Inhalts-Typ')
                ->options([
                    'biz' => '💼 Business / Strategy',
                    'tech' => '🧪 Fachwissen / Specialist',
                    'philo' => '✨ Philo / Poesy',
                ])
                ->default('biz')
                ->required(),

            \Filament\Forms\Components\Select::make('status')
                ->label('Status')
                ->options([
                    'draft' => 'Entwurf',
                    'processing' => 'Analyse läuft',
                    'cancelled' => 'Abgebrochen',
                    'done' => 'Board Ready',
                ])
                ->default('draft')
                ->required(),

            \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('documents')->collection('scouts')->multiple()->columnSpanFull(),

            \Filament\Schemas\Components\Tabs::make('Details')
                ->tabs([
                    \Filament\Schemas\Components\Tabs\Tab::make('Master Blob (Protokoll)')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->schema([
                            Placeholder::make('master_blob_draft')
                                ->label('Master Blob (Live-Ansicht)')
                                ->content(fn ($record) => new HtmlString('
                                    <div class="flex justify-end mb-2">
                                        <button type="button" 
                                            onclick="navigator.clipboard.writeText(document.getElementById(\'blob-content\').innerText); alert(\'Blob kopiert!\');" 
                                            class="px-3 py-1 text-xs font-medium text-white bg-gray-700 rounded-lg hover:bg-gray-600 transition">
                                            📋 Kopieren
                                        </button>
                                    </div>
                                    <div id="blob-content" style="max-height: 600px; overflow-y: auto; white-space: pre-wrap; font-family: monospace; font-size: 12px; background: #111827; color: #e5e7eb; padding: 1rem; border-radius: 0.5rem;">' 
                                    . e($record?->master_blob_draft ?? 'Wird nach dem Ingest durch die Agenten generiert...') 
                                    . '</div>
                                '))
                                ->columnSpanFull(),
                        ]),

                    \Filament\Schemas\Components\Tabs\Tab::make('Live Log (Telnet)')
                        ->icon('heroicon-o-command-line')
                        ->schema([
                            \Filament\Forms\Components\Placeholder::make('processing_logs')
                                ->label('Ereignis-Stream')
                                ->content(fn ($record) => $record 
                                    ? view('filament.components.telnet-log', ['record' => $record]) 
                                    : 'Standby...'
                                ),
                        ]),
                ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('title')
                    ->label('Asset / Deal')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->url(fn (IngestSignal $record): string => static::getUrl('edit', ['record' => $record]))
                    ->color('primary'),

                // NEU: Kategorie-Spalte mit Badges und Farben
                \Filament\Tables\Columns\TextColumn::make('category')
                    ->label('Typ')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'biz' => 'emerald',   // Grün für Business
                        'tech' => 'info',      // Blau für Fachwissen
                        'philo' => 'indigo',   // Violett für Philo
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'biz' => '💼 Business',
                        'tech' => '🧪 Specialist',
                        'philo' => '✨ Philo/Poesy',
                        default => $state,
                    }),

                // Status-Spalte: Sortierbar gemacht und bunte Badges hinzugefügt
                \Filament\Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'processing' => 'warning', // Gelb/Orange
                        'done' => 'success',    // Grün
                        'cancelled' => 'danger',   // Rot
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => ucfirst($state)),

                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->label('Datum')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([]); 
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIngestSignals::route('/'),
            'create' => Pages\CreateIngestSignal::route('/create'),
            'edit' => Pages\EditIngestSignal::route('/{record}/edit'),
        ];
    }
}
