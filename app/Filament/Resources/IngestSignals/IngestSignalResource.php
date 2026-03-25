<?php

namespace App\Filament\Resources\IngestSignals;

use App\Models\IngestSignal;
use Filament\Resources\Resource;
use Filament\Schemas\Schema; 
use Filament\Tables\Table;
use App\Filament\Resources\IngestSignals\Pages;

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
            
            \Filament\Forms\Components\Select::make('status')
                ->options([
                    'draft' => 'Entwurf',
                    'processing' => 'Analyse läuft',
                    'cancelled' => 'Abgebrochen',
                    'done' => 'Board Ready',
                ])->default('draft'),

            \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('documents')->collection('scouts')->multiple()->columnSpanFull(),

            // --- HIER IST DEIN TELNET FENSTER WIEDER ---
            \Filament\Schemas\Components\Tabs::make('Details')
                ->tabs([
                    \Filament\Schemas\Components\Tabs\Tab::make('Master Blob (Protokoll)')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->schema([
                            \Filament\Forms\Components\MarkdownEditor::make('master_blob_draft')
                                ->label('Zusammenfassung')
                                ->nullable() 
                                ->dehydrated(true) 
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
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    // Klickbarer Titel, um die kaputten EditActions zu umgehen
                    ->url(fn (IngestSignal $record): string => static::getUrl('edit', ['record' => $record]))
                    ->color('primary'),
                \Filament\Tables\Columns\TextColumn::make('status')->badge(),
                \Filament\Tables\Columns\TextColumn::make('created_at')->dateTime()->label('Datum'),
            ])
            ->actions([]); // Leer lassen, damit Filament hier nicht abstürzt
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
