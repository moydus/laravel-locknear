<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('email')->email()->required(),
                        Select::make('role')
                            ->options([
                                'business' => 'Business',
                                'customer' => 'Customer',
                            ])
                            ->required(),
                        TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->label('New Password'),
                    ]),
                ]),
            ]);
    }
}
