<?php

namespace App\Filament\Resources\Packages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('bold'),
                TextColumn::make('price_monthly')
                    ->money('USD')
                    ->sortable()
                    ->label('Monthly'),
                TextColumn::make('price_yearly')
                    ->money('USD')
                    ->sortable()
                    ->label('Yearly'),
                TextColumn::make('max_leads_per_month')
                    ->numeric()
                    ->label('Leads/mo'),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('sort_order')->numeric()->sortable()->label('Order'),
            ])
            ->defaultSort('sort_order')
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
