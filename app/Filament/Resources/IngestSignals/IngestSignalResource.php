<?php

// HIER IST DER MAGISCHE FIX: Der exakte Ordnerpfad!
namespace App\Filament\Resources\IngestSignals;

// Der Pfad zu deinen Pages muss ebenfalls den Unterordner enthalten
use App\Filament\Resources\IngestSignals\IngestSignalResource\Pages;
use App\Models\IngestSignal;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class IngestSignalResource extends Resource
{
    protected static ?string $model = IngestSignal::class;

    // PHP 8.4 Strenge Typisierung für das Icon
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    // PHP 8.4 Strenge Typisierung für die Gruppe
    protected static string|\UnitEnum|null $navigationGroup = 'Scout Portal';

    protected static ?string $navigationLabel = 'Ingest Signals';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Keine Sections mehr - direkte Übergabe der Felder!
                // 1. Das Eingabefeld für den Titel
                \Filament\Forms\Components\TextInput::make('title')
                    ->label('Asset Name / Deal Title')
                    ->required()
                    ->columnSpanFull(), // <--- Hier fehlte vorhin das Komma!

                // 2. Das Eingabefeld für die Tags (Forms\Components, NICHT Tables\Columns!)
   
                \Filament\Forms\Components\TagsInput::make('tags')
                ->label('Themen-Tags (Routing)')
                ->helperText('Welche Themen behandelt dieses Buch? (z.B. Marketing, SaaS, Finance). Nur Agenten mit den passenden Tags werden dieses Buch analysieren.')
                ->columnSpanFull(),

                \Filament\Forms\Components\Select::make('status')
                    ->options([
                        'draft' => 'Draft (Unprocessed)',
                        'queued' => 'Queued for Batch Processing',
                        'routing' => 'Phase 1: Chapter Routing',
                        'extracting' => 'Phase 2: 4-Vector Extraction',
                        'trading_floor_ready' => 'Phase 3: Trading Floor Ready',
                    ])
                    ->default('draft')
                    ->required(),


                \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('documents')
                    ->collection('scouts')
                    ->label('M&A Documents (PDFs)')
                    ->multiple()
                    ->maxSize(102400) // 100MB in KB
                    ->acceptedFileTypes(['application/pdf'])
                    ->multiple()
                    ->reorderable()
                    ->disk('public')
                    ->visibility('public')
                    ->columnSpanFull(),

                \Filament\Forms\Components\Textarea::make('raw_content')
                    ->label('Master Blob Draft (JSON / Excerpts)')
                    ->rows(15)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('title')
                    ->label('Deal Title (Click to Edit)')
                    ->weight('bold')
                    ->searchable()
                    ->url(fn (IngestSignal $record): string => Pages\EditIngestSignal::getUrl(['record' => $record])),

                    // 2. Anzeige der Tags als blaue Badges
                \Filament\Tables\Columns\TextColumn::make('tags')
                    ->label('Routing-Tags')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->wrap() // Erlaubt Zeilenumbruch, damit die Spalte nicht zu breit wird
                    ->extraAttributes(['style' => 'max-width: 300px;']),

                \Filament\Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'danger' => 'draft',
                        'warning' => 'routing',
                        'primary' => 'extracting',
                        'success' => 'trading_floor_ready',
                    ]),

                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->label('Ingest Date')
                    ->dateTime('M d, Y')
                    ->sortable(),
            ])
            ->actions([])
            ->bulkActions([]);
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
