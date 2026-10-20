<?php

namespace App\Filament\Resources\Webinars\Tables;

use App\Filament\Actions\OpenLiveAction;
use App\Filament\Actions\PreviewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WebinarsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // `client` se precarga porque las acciones de preview y live
            // arman la URL pública con el slug del cliente.
            ->modifyQueryUsing(fn ($query) => $query->with('client'))
            ->columns([
                TextColumn::make('client.name')
                    ->label('Client')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('subtitle')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('meta_campaign_id')
                    ->label('Meta Campaign')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ad_spend')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(),
                ImageColumn::make('hero_image'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                PreviewAction::make(),
                OpenLiveAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
