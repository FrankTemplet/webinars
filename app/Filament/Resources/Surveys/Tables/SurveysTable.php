<?php

namespace App\Filament\Resources\Surveys\Tables;

use App\Filament\Actions\OpenLiveAction;
use App\Filament\Actions\PreviewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SurveysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('client'))
            ->columns([
                TextColumn::make('client.name')
                    ->label('Client')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('webinar.title')
                    ->label('Webinar')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                IconColumn::make('is_open')
                    ->label('Open')
                    ->boolean(),
                TextColumn::make('responses_count')
                    ->label('Responses')
                    ->counts('responses')
                    ->sortable(),
                TextColumn::make('responses_avg_experience_rating')
                    ->label('Satisfaction')
                    ->avg('responses', 'experience_rating')
                    ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 2).' / 5' : '—'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                PreviewAction::make(),
                OpenLiveAction::make(),
                EditAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
