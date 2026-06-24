<?php

namespace App\Filament\Resources\Companies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('city')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('state')
                    ->sortable(),
                TextColumn::make('phone')
                    ->searchable(),
                IconColumn::make('is_verified')
                    ->boolean()
                    ->label('Verified'),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                IconColumn::make('is_online')
                    ->boolean()
                    ->label('Online'),
                IconColumn::make('is_claimed')
                    ->boolean()
                    ->label('Claimed'),
                TextColumn::make('rating')
                    ->numeric(2)
                    ->sortable(),
                TextColumn::make('review_count')
                    ->numeric()
                    ->sortable()
                    ->label('Reviews'),
                TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make(),
                TernaryFilter::make('is_verified')->label('Verified'),
                TernaryFilter::make('is_active')->label('Active'),
                TernaryFilter::make('is_claimed')->label('Claimed'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
