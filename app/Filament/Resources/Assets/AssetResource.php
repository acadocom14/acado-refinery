<?php

namespace App\Filament\Resources\Assets;

use App\Models\Asset;
use Filament\Resources\Resource;
use Filament\Schemas\Schema; 
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Resources\Assets\Pages;

// DIE ENTSCHEIDENDE TRENNUNG:
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
            // Layout kommt aus Schemas
            Layout\Section::make('Asset Core')
                ->schema([
                    // Felder kommen aus Forms
                    Field\TextInput::make('name')
                        ->label('Name des Assets (z.B. GourmetsOfWine)')
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
                        ])->default('active'),

                    Field\Textarea::make('description')
                        ->label('Beschreibung')
                        ->columnSpanFull(),
                ])->columns(2),

            Layout\Section::make('Task Force & Bibliothek')
                ->schema([
                    Field\Select::make('agents')
                        ->multiple()
                        ->relationship('agents', 'name')
                        ->preload(),

                    Field\Select::make('ingestSignals')
                        ->multiple()
                        ->relationship('ingestSignals', 'title')
                        ->preload(),
                ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'edit' => Pages\EditAsset::route('/{record}/edit'),
        ];
    }
}
