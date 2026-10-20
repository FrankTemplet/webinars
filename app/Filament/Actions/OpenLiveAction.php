<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;

/**
 * Abre la página tal como está publicada.
 */
class OpenLiveAction
{
    public static function make(string $name = 'openLive'): Action
    {
        return Action::make($name)
            ->label('Live')
            ->tooltip(fn ($record) => $record->publicUrl() ?: 'This record has no public URL yet')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->color('gray')
            ->url(fn ($record) => $record->publicUrl())
            ->openUrlInNewTab()
            ->visible(fn ($record) => filled($record->publicUrl()));
    }
}
