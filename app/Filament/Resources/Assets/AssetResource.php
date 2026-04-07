<?php

namespace App\Filament\Resources\Assets;

use App\Models\Asset;
use Filament\Resources\Resource;
use Filament\Schemas\Schema; 
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Resources\Assets\Pages;
use App\Services\IngestPipeline;
use Illuminate\Support\Str;

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
            // Sektion 1: Core & Ingest Connection
            Layout\Section::make('Asset Core')
                ->description('Stammdaten und Wissens-Zuteilung')
                ->columnSpanFull()
                ->schema([
                    Layout\Grid::make(3)->schema([
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
                            ])->default('active')
                            ->native(false),
                    ]),

                    // NEU: Die Verbindung zu deinen Fachbüchern
                    Field\Select::make('ingestSignals')
                        ->label('Zugehörige Ingest-Signale (Wissen)')
                        ->relationship('ingestSignals', 'title') 
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->hint('Wähle hier deine Wein-Fachbücher (Typ: fach) aus.')
                        ->columnSpanFull(),

                    // NEU: Der Trigger-Eingang (Stage 0)
                    Field\Textarea::make('trigger_content')
                        ->label('🚀 Trigger Content (Der Funke)')
                        ->placeholder('Hier landet der X-Post vom Webhook oder der Sandbox...')
                        ->rows(5)
                        ->columnSpanFull()
                        ->helperText('Dieser Text wird in Stage 1 gegen die oben gewählten Signale geprüft.')
                        ->extraAttributes(['class' => 'font-mono text-sm bg-gray-50']),
                ]),

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
                                        'tech' => 'Nur Fach-Signale injizieren (Tech)',
                                        'biz' => 'Nur Business-Signale injizieren (Biz)',
                                        'philo' => 'Nur Poesie/Philosophie-Signale injizieren (Philo)',
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
                        // ----------------------------------------------------
                        // NEU: DIE ANTI-CHATTER PROMPT DEFAULTS
                        // ----------------------------------------------------
                        ->default([
                            [
                                'stage_level' => 1,
                                'name' => 'Stage 1: Domain-Extraktion (Facts)',
                                'llm_model' => 'gemini-2.5-flash',
                                'signal_filter' => 'tech',
                                'fixed_prompt_template' => "You are an elite data extraction unit. Extract strictly factual, domain-specific data from the provided Subject.\n\nCRITICAL SYSTEM RULE: Output ONLY a valid JSON array or object. Do NOT wrap the JSON in markdown code blocks (like ```json). Do NOT add any introductory or concluding text. Silence is mandatory.",
                                'custom_prompt_directive' => "Isolate core technical specifications and validate against the Reference Knowledge. Output clinical raw data only.",
                            ],
                            [
                                'stage_level' => 2,
                                'name' => 'Stage 2: Strategy & Value Proposition',
                                'llm_model' => 'gemini-2.5-flash',
                                'signal_filter' => 'biz',
                                'fixed_prompt_template' => "You are the executive strategy layer. Analyze the provided data and synthesize a high-yield market strategy.\n\nCRITICAL SYSTEM RULE: Output ONLY the final strategic text. Do NOT introduce yourself or your role. Do NOT explain your reasoning. Start immediately with the brief.",
                                'custom_prompt_directive' => "Transform data into a sharp strategy. Identify the USP.",
                            ],
                            [
                                'stage_level' => 3,
                                'name' => 'Stage 3: Die Content-Fabrik',
                                'llm_model' => 'gemini-2.5-flash',
                                'signal_filter' => 'previous_only',
                                'fixed_prompt_template' => "You are the Chief Content Officer. Generate the final publishing formats based strictly on the provided strategy.\n\nCRITICAL SYSTEM RULE: Output ONLY the requested copy. Absolutely NO meta-commentary, NO greetings, and NO explanations of your creative process.",
                                'custom_prompt_directive' => "Inject intense psychological hooks. Format clearly.",
                            ],
                            [
                                'stage_level' => 4,
                                'name' => 'Stage 4: Compliance & Risk Guard',
                                'llm_model' => 'gemini-2.5-flash',
                                'signal_filter' => 'biz',
                                'fixed_prompt_template' => "You are the Chief Compliance and Risk Officer. Scrub the provided text for legal liability and rewrite it to be safe but impactful.\n\nCRITICAL SYSTEM RULE: Output ONLY the revised text. Do NOT explain what you changed or why. Do NOT add disclaimers about being an AI.",
                                'custom_prompt_directive' => "Make the copy bulletproof but preserve the elite, arrogant tone.",
                            ],
                            [
                                'stage_level' => 5,
                                'name' => 'Stage 5: Brand Soul & Philosophy',
                                'llm_model' => 'gemini-2.5-flash',
                                'signal_filter' => 'philo',
                                'fixed_prompt_template' => "You are the Cultural Custodian and Brand Philosopher. Inject deeper emotional resonance into the text.\n\nCRITICAL SYSTEM RULE: Output ONLY the final masterpiece. No introductions, no process explanations. Start directly with the poetic prose.",
                                'custom_prompt_directive' => "Elevate the text to a lifestyle statement.",
                            ],
                        ]),
                ]),

            // SEKTION 3: DER LIVE-FEED (TERMINAL)
            Layout\Section::make('Refinery Live-Feed')
                ->description('Echtzeit-Überwachung der Agenten-Pipeline')
                ->columnSpanFull()
                ->schema([
                    Field\ViewField::make('processing_logs')
                        ->view('filament.components.refinery-terminal')
                        ->columnSpanFull()
                        ->label(false)
                        ->poll('2s')
                        ->afterStateHydrated(fn ($component, $record) => $component->state($record->processing_logs)),
                ])
                ->collapsible(),

            // SEKTION 4: PRODUCTION RESULTS 
            Layout\Section::make('Production Results')
                ->description('Das fertige Veredelungs-Manifest')
                ->columnSpanFull()
                ->schema([
                    Field\MarkdownEditor::make('final_content')
                        ->label('🏆 Finales Manifest (Julian Valmont & Dexter Edition)')
                        ->helperText('Die Symbiose aus Compliance und Philosophie.')
                        ->columnSpanFull()
                        ->disabled()
                        ->dehydrated(false),

                    Layout\Tabs::make('Stage Archive') 
                        ->tabs([
                            Layout\Tabs\Tab::make('Stage 1')
                                ->schema([
                                    Field\Textarea::make('pipeline_outputs.stage_1.content')
                                        ->label('Raw JSON Facts')
                                        ->rows(10)
                                        ->disabled()
                                ]),
                            Layout\Tabs\Tab::make('Stage 2')
                                ->schema([
                                    Field\MarkdownEditor::make('pipeline_outputs.stage_2.content')
                                        ->label('Strategy Brief')
                                        ->disabled()
                                ]),
                            Layout\Tabs\Tab::make('Stage 3')
                                ->schema([
                                    Field\MarkdownEditor::make('pipeline_outputs.stage_3.content')
                                        ->label('B2C Formats')
                                        ->disabled()
                                ]),
                            Layout\Tabs\Tab::make('Stage 4')
                                ->schema([
                                    Field\MarkdownEditor::make('pipeline_outputs.stage_4.content')
                                        ->label('Compliance Check')
                                        ->disabled()
                                ]),
                            Layout\Tabs\Tab::make('Stage 5')
                                ->schema([
                                    Field\MarkdownEditor::make('pipeline_outputs.stage_5.content')
                                        ->label('Cultural Soul')
                                        ->disabled()
                                ]),
                        ])
                        ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'processing' => 'warning',
                        'active' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
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
