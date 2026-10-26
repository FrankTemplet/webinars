<?php

namespace App\Filament\Resources\Webinars\RelationManagers;

use App\Jobs\SyncAttendeesJob;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Asistentes guardados del webinar. Se leen de la base, no de Zoom, así que
 * siguen disponibles aunque Zoom ya haya expirado el webinar.
 */
class AttendeesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendees';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->defaultSort('join_time')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('join_time')
                    ->label('Joined')
                    ->dateTime('d - m - Y H:i')
                    ->sortable(),
                TextColumn::make('duration')
                    ->label('Minutes')
                    ->formatStateUsing(fn (int $state) => intdiv($state, 60))
                    ->sortable(),
                TextColumn::make('device')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('location')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('syncFromZoom')
                    ->label('Sync from Zoom')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn () => filled($this->getOwnerRecord()->zoom_webinar_id))
                    ->action(function () {
                        $webinar = $this->getOwnerRecord();
                        $before = $webinar->attendees()->count();

                        SyncAttendeesJob::dispatchSync($webinar->id);

                        $after = $webinar->attendees()->count();

                        Notification::make()
                            ->title("Synced from Zoom: {$after} attendees (".($after - $before).' new)')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
