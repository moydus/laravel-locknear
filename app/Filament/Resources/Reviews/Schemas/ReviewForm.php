<?php

namespace App\Filament\Resources\Reviews\Schemas;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ReviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Review')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('reviewer_name')->required(),
                        TextInput::make('reviewer_email')->email(),
                        Select::make('rating')
                            ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'])
                            ->required(),
                    ]),
                    Textarea::make('body')->rows(3)->columnSpanFull(),
                ]),
                Section::make('Moderation')->schema([
                    Grid::make(2)->schema([
                        Toggle::make('is_verified')->label('Verified'),
                        Toggle::make('is_published')->label('Published'),
                    ]),
                ]),
            ]);
    }
}
