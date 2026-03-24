<?php

namespace App\Filament\Resources\Agents;

use App\Models\Agent;
use App\Models\IngestSignal; // WICHTIG: Für die XP-Radar Logik
use App\Filament\Resources\Agents\Pages;
use App\Services\IngestPipeline;
use Filament\Resources\Resource;

// 1. DAS NEUE SCHEMA-SYSTEM (Container)
use Filament\Schemas\Schema; 

// 2. LAYOUT-KOMPONENTEN (Struktur - Wohnen in Schemas)
use Filament\Schemas\Components\Section;

// 3. EINGABE-FELDER & REPEATER (Datenverarbeitung - Wohnen in Forms)
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\Repeater;

// 4. TABELLEN-IMPORTS
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\ToggleColumn;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;

    // PHP 8.4 kompatible Typisierung (Reihenfolge ist wichtig!)
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';
    protected static \UnitEnum|string|null $navigationGroup = 'Scout Portal';
    protected static ?string $navigationLabel = 'Agents';

    /**
     * Die form-Methode nutzt das neue Schema-Objekt (Behalte die Sektionen)
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // IDENTITY
                Section::make('Executive Identity')
                    ->columns(2)
                    ->schema([
                        FileUpload::make('avatar_url')
                            ->label('Portrait Icon')
                            ->image()
                            ->avatar()
                            ->disk('public')
                            ->directory('agents')
                            ->columnSpanFull(),

                        TextInput::make('name')
                            ->label('Full Name')
                            ->required(),

                        TextInput::make('role_code')
                            ->label('Role (Shorthand)')
                            ->required(),

                        TextInput::make('acado_coins')
                            ->label('Wallet (ACD)')
                            ->numeric()
                            ->default(0),

                        Toggle::make('is_active')
                            ->label('Im Dienst (Aktiv)')
                            ->default(true),
                    ]),

                // KI-GEHIRN (Mit dynamischem Modell-Select)
                Section::make('KI-Konfiguration')
                    ->description('Definition des LLM-Verhaltens')
                    ->columns(2)
                    ->schema([
                        Select::make('soul_configuration.model')
                            ->label('KI-Modell')
                            ->options(fn (IngestPipeline $pipeline) => $pipeline->getAvailableModels())
                            ->default('gemini-1.5-flash')
                            ->searchable(),

                        Slider::make('soul_configuration.temperature')
                            ->label('Kreativität')
                            ->minValue(0.1) // v4 Methodik
                            ->maxValue(1.0)
                            ->step(0.1)
                            ->default(0.2),

                        MarkdownEditor::make('soul')
                            ->label('Charakter-Definition (Soul)')
                            ->columnSpanFull(),

                        Textarea::make('system_prompt')
                            ->label('Extraktions-Anweisung')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                // EXPERTISE (Mit XP-Radar Logik)
                Section::make('Expertise & Stats')
                    ->schema([
                        TagsInput::make('tags')
                            ->label('Abonnierte Themen')
                            ->columnSpanFull(),

                        KeyValue::make('experience_stats')
                            ->label('XP-Radar')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(function ($state, $record) {
                                if (!$record || empty($record->tags)) {
                                    return ['Status' => 'Keine Expertise definiert'];
                                }
                                $stats = [];
                                $allBooks = IngestSignal::all();
                                foreach ($record->tags as $tag) {
                                    $match = $allBooks->filter(fn($b) => is_array($b->tags) && in_array($tag, $b->tags))->count();
                                    $read = ($state[$tag] ?? 0);
                                    $stats[$tag] = "{$match} in Lib | {$read} gelesen";
                                }
                                return $stats;
                            })
                            ->columnSpanFull(),
                    ]),

                // DIE 4 HOOKS (In einer eigenen Sektion für UI Stabilität)
                Section::make('Inhaltliche Perspektiven')
                    ->schema([
                        Repeater::make('perspectives')
                            ->label('Fokus-Themen') // Hauptlabel für die ganze Gruppe
                            ->schema([
                                TextInput::make('angle')
                            ->placeholder('z.B. CAC:LTV & Growth Engines')
                            ->hiddenLabel() // Entfernt das "Winkel" über jedem einzelnen Feld
                            ->required(),
                        ])
                            ->maxItems(4)
                            ->grid(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * HIER IST DIE VERBESSERTE TABELLEN-ANSICHT (Rückgerollt & Fixiert)
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 1. Avatar mit Graustufen-Logik
                ImageColumn::make('avatar_url')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->extraImgAttributes(fn ($record): array => [
                        'style' => $record->is_active 
                            ? 'filter: drop-shadow(0 0 5px rgba(34, 197, 94, 0.4));' 
                            : 'filter: grayscale(100%); opacity: 0.3;',
                    ]),

                // 2. Name als Link zur Bearbeitung
                TextColumn::make('name')
                    ->label('Executive Name')
                    ->weight('bold')
                    ->searchable()
                    ->url(fn (Agent $record): string => Pages\EditAgent::getUrl(['record' => $record])),

                // 3. Rolle als Badge
                TextColumn::make('role_code')
                    ->label('Role')
                    ->badge(),

                // 4. Tags als Expertise-Badges
                TextColumn::make('tags')
                    ->label('Expertise')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->wrap(),

                // 5. XP-Score Status-Badge (Read / Match / Total)
                TextColumn::make('xp_score')
                    ->label('XP (Read/Match/Total)')
                    ->getStateUsing(function (Agent $record) {
                        $totalBooks = IngestSignal::count();
                        $readBooks = is_array($record->experience_stats) ? array_sum($record->experience_stats) : 0;
                        $matchingBooks = 0;
                        if (!empty($record->tags)) {
                            $allBooks = IngestSignal::all();
                            $matchingBooks = $allBooks->filter(function($book) use ($record) {
                                if (!is_array($book->tags)) return false;
                                // Matcht exakten Tag oder Wildcards
                                return count(array_intersect($record->tags, $book->tags)) > 0 || in_array('*', $book->tags);
                            })->count();
                        }
                        return sprintf('%02d / %02d / %02d', $readBooks, $matchingBooks, $totalBooks);
                    })
                    ->badge()
                    ->color('success'),

                // 6. Urlaubsschalter (Status)
                ToggleColumn::make('is_active')
                    ->label('Im Dienst'),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgents::route('/'),
            'create' => Pages\CreateAgent::route('/create'),
            'edit' => Pages\EditAgent::route('/{record}/edit'),
        ];
    }
}
