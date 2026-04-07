<?php

namespace App\Filament\Resources\Assets;

use App\Models\Asset;
use Filament\Resources\Resource;
use Filament\Schemas\Schema; 
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Resources\Assets\Pages;
use App\Services\IngestPipeline;

// DIE TRENNUNG:
use Filament\Schemas\Components as Layout;
use Filament\Forms\Components as Field;

class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-briefcase';
    protected static string | \UnitEnum | null $navigationGroup = 'Business Strategy';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Sektion 1: Core
            Layout\Section::make('Asset Core')
                ->columnSpanFull()
                ->schema([
                    Field\TextInput::make('name')
                        ->label('Name des Assets')
                        ->required(),
                        
                    Field\Select::make('type')
                        ->options([
                            'portal' => 'Web-Portal',
                            'newsletter' => 'Newsletter',
                            'brand' => 'E-Commerce Brand',
                        ])->default('portal'),

                    Field\Select::make('status')
                        ->options([
                            'active' => 'Aktiv',
                            'planning' => 'In Planung',
                            'processing' => 'Veredelung läuft...',
                            'failed' => 'Fehlgeschlagen',
                        ])->default('active'),
                ])->columns(3),

            // Sektion 2: Pipeline Engine
            Layout\Section::make('Pipeline Engine')
                ->description('Konfiguriere hier das Prompt-Chaining.')
                ->columnSpanFull()
                ->schema([
                    Field\Repeater::make('pipelineStages')
                        ->relationship()
                        ->schema([
                            Layout\Grid::make(4)->schema([
                                Field\TextInput::make('stage_level')
                                    ->label('Level')
                                    ->numeric()
                                    ->required(),

                                Field\TextInput::make('name')
                                    ->label('Bezeichnung')
                                    ->required(),

                                Field\Select::make('llm_model')
                                    ->label('Engine (Live)')
                                    ->options(fn (IngestPipeline $pipeline) => $pipeline->getAvailableModels())
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                Field\Select::make('signal_filter')
                                    ->label('Input Routing')
                                    ->options([
                                        'fach' => 'Nur Fach-Signale injizieren',
                                        'business' => 'Nur Business-Signale injizieren',
                                        'poesie' => 'Nur Poesie/Philosophie-Signale injizieren',
                                        'all' => 'Alle Signale des Assets injizieren',
                                        'previous_only' => 'Keine Signale (Nur Output der Vorstufe)',
                                    ])
                                    ->required()
                                    ->default('previous_only'),
                            ]),

                            Field\Select::make('agents')
                                ->label('Zuständige Agents')
                                ->multiple()
                                ->relationship('agents', 'name')
                                ->preload(),

                            Layout\Grid::make(2)->schema([
                                Field\Textarea::make('fixed_prompt_template')
                                    ->label('System Prompt (Fixed)')
                                    ->rows(4),

                                Field\Textarea::make('custom_prompt_directive')
                                    ->label('Custom Directive (Feinschliff)')
                                    ->rows(4),
                            ]),
                        ])
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Neue Stage')
                        ->orderColumn('stage_level')
                        ->collapsible()
                        ->default([
                            [
                                'stage_level' => 1,
                                'name' => 'Stage 1: Domain-Extraktion (Facts)',
                                'llm_model' => 'gemini-2.5-flash',
                                'signal_filter' => 'fach',
                                'fixed_prompt_template' => "You are an elite data extraction unit. Extract strictly factual, domain-specific data from the provided Ingest Signal.\n\nFORMAT: Valid JSON payload only.",
                                'custom_prompt_directive' => "Isolate core technical specifications. Output only the clinical raw ingredients.",
                            ],
                            [
                                'stage_level' => 2,
                                'name' => 'Stage 2: Strategy & Value Proposition',
                                'llm_model' => 'gemini-2.5-flash',
                                'signal_filter' => 'business',
                                'fixed_prompt_template' => "You are the executive strategy layer. Analyze the JSON data and synthesize a market strategy.",
                                'custom_prompt_directive' => "Transform data into a sharp strategy. Identify the USP.",
                            ],
                            [
                                'stage_level' => 3,
                                'name' => 'Stage 3: Die Content-Fabrik',
                                'llm_model' => 'gemini-2.5-flash',
                                'signal_filter' => 'previous_only',
                                'fixed_prompt_template' => "You are the Chief Content Officer. Generate the final publishing formats.",
                                'custom_prompt_directive' => "Inject intense psychological hooks.",
                            ],
                            [
                                'stage_level' => 4,
                                'name' => 'Stage 4: Compliance & Risk Guard',
                                'llm_model' => 'gemini-2.5-flash',
                                'signal_filter' => 'business',
                                'fixed_prompt_template' => "You are the Chief Compliance and Risk Officer. Scrub for legal liability.",
                                'custom_prompt_directive' => "Make the copy bulletproof.",
                            ],
                            [
                                'stage_level' => 5,
                                'name' => 'Stage 5: Brand Soul & Philosophy',
                                'llm_model' => 'gemini-2.5-flash',
                                'signal_filter' => 'poesie',
                                'fixed_prompt_template' => "You are the Cultural Custodian and Brand Philosopher. Inject deeper emotional resonance.",
                                'custom_prompt_directive' => "Elevate the text to a lifestyle statement.",
                            ],
                        ]),
                ]),

            // SEKTION 3: DER LIVE-FEED (TELNET TERMINAL)
            Layout\Section::make('Refinery Live-Feed')
                ->description('Echtzeit-Überwachung der Agenten-Pipeline')
                ->columnSpanFull()
                ->schema([
                    Field\ViewField::make('processing_logs')
                        ->view('filament.components.refinery-terminal')
                        ->columnSpanFull()
                        ->label(false)
                        ->poll('2s'),
                ])
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('pipeline_stages_count')
                    ->label('Stages')
                    ->counts('pipelineStages')
                    ->badge(),
            ])
            ->recordUrl(fn ($record) => Pages\EditAsset::getUrl([$record->id]));
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'edit' => Pages\EditAsset::route('/{record}/edit'),
        ];
    }
}
