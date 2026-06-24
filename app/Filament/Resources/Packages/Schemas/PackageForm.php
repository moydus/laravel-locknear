<?php

namespace App\Filament\Resources\Packages\Schemas;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Package Details')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')->required()->maxLength(255),
                        TextInput::make('slug')->required()->maxLength(255),
                    ]),
                    Textarea::make('description')->rows(2)->columnSpanFull(),
                ]),
                Section::make('Pricing')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('price_monthly')
                            ->numeric()->prefix('$')->required(),
                        TextInput::make('price_yearly')
                            ->numeric()->prefix('$'),
                        TextInput::make('stripe_price_id_monthly')
                            ->label('Stripe Monthly Price ID'),
                        TextInput::make('stripe_price_id_yearly')
                            ->label('Stripe Yearly Price ID'),
                    ]),
                ]),
                Section::make('Limits')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('max_leads_per_month')
                            ->numeric()->label('Max Leads / Month'),
                        TextInput::make('sort_order')
                            ->numeric()->default(0),
                    ]),
                    Toggle::make('is_active')->label('Active')->default(true),
                ]),
            ]);
    }
}
