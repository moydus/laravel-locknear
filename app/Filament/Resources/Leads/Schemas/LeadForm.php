<?php

namespace App\Filament\Resources\Leads\Schemas;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('customer_name')->required(),
                        TextInput::make('phone')->tel(),
                        TextInput::make('email')->email(),
                        Select::make('service_type')
                            ->options([
                                'lockout' => 'Lockout',
                                'rekey' => 'Rekey',
                                'lock_change' => 'Lock Change',
                                'key_replacement' => 'Key Replacement',
                                'other' => 'Other',
                            ]),
                    ]),
                    Textarea::make('description')->rows(2)->columnSpanFull(),
                ]),
                Section::make('Location')->schema([
                    Grid::make(3)->schema([
                        TextInput::make('city'),
                        TextInput::make('state')->maxLength(2),
                        TextInput::make('zip')->maxLength(10),
                    ]),
                ]),
                Section::make('Status')->schema([
                    Select::make('status')
                        ->options([
                            'new' => 'New',
                            'assigned' => 'Assigned',
                            'completed' => 'Completed',
                        ]),
                ]),
            ]);
    }
}
