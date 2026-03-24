<?php

namespace App\Filament\Resources\Agents;

use App\Filament\Resources\Agents\AgentResource\Pages;
use App\Models\Agent;
use Filament\Resources\Resource;
use Filament\Schemas\Schema; // v4/2026 Standard laut deinem Error-Log
use Filament\Tables\Table;
use BackedEnum;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Agents';

public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
{
    return $schema
        ->components([
            // Wir lassen alle "Section"-Klassen weg und packen die Felder direkt rein.
            // Filament ordnet diese automatisch sauber untereinander an.

            \Filament\Forms\Components\FileUpload::make('avatar_url')
                ->label('Portrait Icon')
                ->image()
                ->avatar()
                ->disk('public')
                ->visibility('public')
                ->directory('agents')
                ->columnSpanFull(),

            \Filament\Forms\Components\TextInput::make('name')
                ->label('Executive Name')
                ->required(),

            \Filament\Forms\Components\TextInput::make('role_code')
                ->label('Role (Shorthand)')
                ->required(),

            \Filament\Forms\Components\TextInput::make('acado_coins')
                ->label('Wallet (ACD)')
                ->numeric()
                ->default(0),

            \Filament\Forms\Components\Toggle::make('is_active')
                ->label('Im Dienst (Aktiv)')
                ->helperText('Wenn deaktiviert, wird dieser Agent in der Redaktion übersprungen.')
                ->default(false)
                ->columnSpanFull(),

            // Wir nutzen das garantierte Standard-Feld für Tags
            \Filament\Forms\Components\TagsInput::make('tags')
                ->label('Abonnierte Themen (Tags)')
                ->helperText('Bei welchen Buch-Themen soll dieser Agent aufwachen? (z.B. Marketing, SaaS, Finance)')
                ->columnSpanFull(),

\Filament\Forms\Components\KeyValue::make('experience_stats')
                ->label('Level-System (XP-Radar)')
                ->keyLabel('Fachgebiet (Tag)')
                ->valueLabel('Bücher (Potenzial vs. XP)')
                ->disabled() 
                ->dehydrated(false) 
                ->formatStateUsing(function ($state, $record) {
                    // Wenn es ein neuer Agent ohne Tags ist
                    if (!$record || empty($record->tags)) {
                        return ['Keine Tags abonniert' => '0 in Bibliothek'];
                    }

                    $stats = [];
                    // $state sind die WIRKLICH gelesenen Bücher (aus der Datenbank)
                    $readStats = is_array($state) ? $state : [];

                    // Alle Bücher laden (für kleine bis mittlere Datenbanken absolut okay)
                    $allBooks = \App\Models\IngestSignal::all();

                    foreach ($record->tags as $tag) {
                        // Zähle, wie viele Bücher diesen spezifischen Tag (oder "all" / "*") haben
                        $matchingBooks = $allBooks->filter(function($book) use ($tag) {
                            if (!is_array($book->tags)) return false;
                            // Matcht exakten Tag oder Wildcards
                            return in_array($tag, $book->tags) || in_array('*', $book->tags) || in_array('all', $book->tags);
                        })->count();

                        $readCount = $readStats[$tag] ?? 0;
                        
                        // Das Format für die Anzeige: "3 in Bibliothek | 0 gelesen"
                        $stats[$tag] = "{$matchingBooks} in Bibliothek | {$readCount} gelesen";
                    }

                    return $stats;
                })
                ->columnSpanFull(),

            \Filament\Forms\Components\MarkdownEditor::make('soul')
                ->label('Charakter-Definition (soul.md)')
                ->placeholder('Du bist Jackson, ein analytischer ROI-Optimierer...')
                ->columnSpanFull(),

            \Filament\Forms\Components\Textarea::make('system_prompt')
                ->label('Extraktions-Prompt')
                ->placeholder('Worauf soll der Agent beim Lesen besonders achten?')
                ->rows(4)
                ->columnSpanFull(),

            \Filament\Forms\Components\Repeater::make('perspectives')
                ->label('Inhaltliche Perspektiven (Die 4 Hooks)')
                ->schema([
                    \Filament\Forms\Components\TextInput::make('angle')
                        ->label('Analyse-Winkel')
                        ->placeholder('z.B. ROI-Potenzial'),
                ])
                ->maxItems(4)
                ->grid(2)
                ->columnSpanFull(),
        ]);
}
public static function table(\Filament\Tables\Table $table): \Filament\Tables\Table
{
    return $table
        ->columns([
            
            // 3. Urlaubsschalter
            \Filament\Tables\Columns\ToggleColumn::make('is_active')
                ->label('Im Dienst'),

            // 1. Avatar mit Graustufen-Logik
            \Filament\Tables\Columns\ImageColumn::make('avatar_url')
                ->label('')
                ->disk('public')
                ->circular()
                ->extraImgAttributes(fn ($record): array => [
                    'style' => $record->is_active 
                        ? 'filter: drop-shadow(0 0 5px rgba(34, 197, 94, 0.4));' 
                        : 'filter: grayscale(100%); opacity: 0.3;',
                ]),

            // 2. Name als Link zur Bearbeitung (Dein bewährter Retter)
            \Filament\Tables\Columns\TextColumn::make('name')
                ->label('Executive Name')
                ->weight('bold')
                ->searchable()
                ->url(fn (\App\Models\Agent $record): string => Pages\EditAgent::getUrl(['record' => $record])),

            \Filament\Tables\Columns\TextColumn::make('role_code')
                ->label('Role')
                ->wrap(),


            // --- NEU: 1. Die Tags als schicke Badges ---
        \Filament\Tables\Columns\TextColumn::make('tags')
                ->label('Expertise')
                ->badge()
                ->color('info')
                ->searchable()
                ->wrap() // <--- NEU: Erlaubt Zeilenumbruch für die Badges
                ->extraAttributes(['style' => 'max-width: 300px;']), // Begrenzt die maximale Breite

            // --- NEU: 2. Der XP-Score ---
            \Filament\Tables\Columns\TextColumn::make('xp_score')
                ->label('XP (Read / Match / Total)')
                ->getStateUsing(function (\App\Models\Agent $record) {
                    // 1. Gesamtanzahl aller Bücher im System
                    $totalBooks = \App\Models\IngestSignal::count();
                    
                    // 2. Gelesene Bücher (Summe aus dem experience_stats Array)
                    $readBooks = is_array($record->experience_stats) ? array_sum($record->experience_stats) : 0;
                    
                    // 3. Passende Bücher in der Bibliothek
                    $matchingBooks = 0;
                    if (!empty($record->tags)) {
                        $allBooks = \App\Models\IngestSignal::all();
                        $matchingBooks = $allBooks->filter(function($book) use ($record) {
                            if (!is_array($book->tags)) return false;
                            foreach ($record->tags as $tag) {
                                if (in_array($tag, $book->tags) || in_array('*', $book->tags) || in_array('all', $book->tags)) {
                                    return true;
                                }
                            }
                            return false;
                        })->count();
                    }
                    
                    // Formatiert die Zahlen immer zweistellig: "05 / 12 / 45"
                    return sprintf('%02d / %02d / %02d', $readBooks, $matchingBooks, $totalBooks);
                })
                ->badge()
                ->color('success'),

            \Filament\Tables\Columns\ToggleColumn::make('is_active')
                ->label('Im Dienst'),

        ])
        // Wir lassen die Actions erst mal leer, um den Class-Error zu vermeiden
        // Du kannst Agenten ja über den Klick auf den Namen bearbeiten!
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
