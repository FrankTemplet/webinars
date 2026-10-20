<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;

/**
 * Muestra la página pública guardada dentro del panel, en un iframe a
 * escala. Se usa desde las listas, donde no hay formulario abierto.
 */
class PreviewAction
{
    public static function make(string $name = 'preview'): Action
    {
        return Action::make($name)
            ->label('Preview')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading(fn ($record) => 'Preview: '.($record->title ?? $record->name))
            ->modalDescription(fn ($record) => $record->publicUrl())
            ->modalWidth(Width::SixExtraLarge)
            ->modalContent(fn ($record) => view('filament.page-preview', [
                'url' => $record->previewUrl(),
                'liveUrl' => $record->publicUrl(),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->visible(fn ($record) => filled($record->previewUrl()));
    }
}
