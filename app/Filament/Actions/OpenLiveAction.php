<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;

/**
 * Abre la página pública del registro en el dominio del cliente.
 */
class OpenLiveAction
{
    public static function make(string $name = 'openLive'): Action
    {
        return Action::make($name)
            ->label('Live')
            ->tooltip(fn ($record) => $record->liveUrl() ?: 'This record has no public URL yet')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->color('gray')
            ->url(fn ($record) => $record->liveUrl())
            ->openUrlInNewTab()
            ->visible(fn ($record) => filled($record->liveUrl()));
    }
}
