<?php

namespace App\Filament\Pages;

use App\Settings\LlmSettings;
use App\Services\IngestPipeline;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;

class ManageLlmSettings extends SettingsPage
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cpu-chip';
    protected static \UnitEnum|string|null $navigationGroup = 'Administration';
    
    protected static string $settings = LlmSettings::class;
    
    protected static ?string $navigationLabel = 'KI-Einstellungen';
    protected static ?string $title = 'Globale LLM-Konfiguration';

    /**
     * FIX: Die Methode MUSS 'form' heißen, damit Filament sie erkennt.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Globale KI-Strategie')
                    ->description('Lege fest, wie das System standardmäßig entscheidet.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('use_intelligent_fallback')
                            ->label('Intelligente Rückfallregel')
                            ->helperText('Wählt automatisch Flash (Dev) oder Pro (Prod).')
                            ->reactive()
                            ->default(true),

                        Select::make('global_default_model')
                            ->label('Fixes Standard-Modell')
                            ->options(fn (IngestPipeline $p) => $p->getAvailableModels())
                            ->hidden(fn ($get) => $get('use_intelligent_fallback'))
                            ->searchable(),
                    ]),

                Section::make('Pipeline-Phasen')
                    ->description('Erzwinge spezielle Modelle für bestimmte Arbeitsschritte.')
                    ->columns(3)
                    ->schema([
                        Select::make('model_extraction')
                            ->label('1. Daten-Extraktion')
                            ->options(fn (IngestPipeline $p) => $p->getAvailableModels())
                            ->placeholder('Standard nutzen'),

                        Select::make('model_analysis')
                            ->label('2. Agenten-Analyse')
                            ->options(fn (IngestPipeline $p) => $p->getAvailableModels())
                            ->placeholder('Agenten-Wahl / Standard'),

                        Select::make('model_association')
                            ->label('3. Assoziation & Trigger')
                            ->options(fn (IngestPipeline $p) => $p->getAvailableModels())
                            ->placeholder('Standard nutzen'),
                    ]),
            ]);
    }
}
