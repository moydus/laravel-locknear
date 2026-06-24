<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CompanyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Info')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')->required()->maxLength(255),
                            TextInput::make('phone')->tel()->maxLength(20),
                            TextInput::make('email')->email()->maxLength(255),
                            TextInput::make('website')->url()->maxLength(255),
                        ]),
                        Textarea::make('description')->rows(3)->columnSpanFull(),
                    ]),

                Section::make('Location')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('city'),
                            TextInput::make('state')->maxLength(2),
                            TextInput::make('zip')->maxLength(10),
                        ]),
                        TextInput::make('address')->columnSpanFull(),
                    ]),

                Section::make('Status')
                    ->schema([
                        Grid::make(4)->schema([
                            Toggle::make('is_active')->label('Active'),
                            Toggle::make('is_verified')->label('Verified'),
                            Toggle::make('is_claimed')->label('Claimed'),
                            Toggle::make('is_online')->label('Online'),
                        ]),
                    ]),
            ]);
    }
}
