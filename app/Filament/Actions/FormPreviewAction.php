<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;

/**
 * Vista previa desde el formulario, incluidos los cambios sin guardar: el
 * modal manda el estado del formulario al iframe.
 *
 * Sirve igual en el alta, donde todavía no hay registro y la página se
 * arma sobre un ejemplo.
 */
class FormPreviewAction
{
    public static function forWebinar(): Action
    {
        return static::make(
            fn ($livewire): string => route('preview.webinar', array_filter([
                'webinar' => static::recordKey($livewire),
                'client' => data_get($livewire, 'data.client_id'),
            ])),
        );
    }

    public static function forSurvey(): Action
    {
        return static::make(
            fn ($livewire): string => route('preview.survey', array_filter([
                'survey' => static::recordKey($livewire),
                'client' => data_get($livewire, 'data.client_id'),
            ])),
        );
    }

    /**
     * En la ficha del cliente se previsualiza una landing de ejemplo: lo que
     * se edita es la marca, no una página concreta.
     */
    public static function forClient(): Action
    {
        return static::make(fn (): string => route('preview.webinar'));
    }

    /**
     * En el alta todavía no hay registro, así que la página se previsualiza
     * sobre un ejemplo.
     */
    private static function recordKey($livewire): int|string|null
    {
        return method_exists($livewire, 'getRecord')
            ? $livewire->getRecord()?->getKey()
            : null;
    }

    private static function make(\Closure $url): Action
    {
        return Action::make('preview')
            ->label('Preview')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->modalHeading('Preview')
            ->modalDescription('Includes the changes you have not saved yet.')
            ->modalWidth(Width::SixExtraLarge)
            ->modalContent(fn (Action $action) => view('filament.page-preview', [
                'url' => $action->evaluate($url),
                'live' => true,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }
}
