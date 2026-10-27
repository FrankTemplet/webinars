<?php

namespace App\Filament\Resources\Submissions\Actions;

use App\Models\Submission;
use App\Services\SubmissionZoomRegistrar;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class RegisterInZoomAction
{
    public static function make(): Action
    {
        return Action::make('registerInZoom')
            ->label(fn (Submission $record) => $record->pendingZoomWebinarIds() ? 'Registrar en Zoom' : 'Re-registrar en Zoom')
            ->icon('heroicon-o-video-camera')
            ->color('info')
            ->visible(fn (Submission $record) => $record->webinar && $record->zoomWebinarIds())
            ->requiresConfirmation()
            ->modalHeading('Registrar en Zoom')
            ->modalDescription(fn (Submission $record) => $record->pendingZoomWebinarIds()
                ? '¿Deseas registrar a esta persona en el webinar de Zoom?'
                : 'Este registro ya fue enviado a Zoom. ¿Deseas volver a enviarlo?')
            ->modalSubmitActionLabel('Sí, registrar')
            ->action(function (Submission $record) {
                // Con pendientes solo completa esas; sin pendientes reenvía todas.
                $registered = app(SubmissionZoomRegistrar::class)->register($record, again: ! $record->pendingZoomWebinarIds());

                if (!$registered) {
                    Notification::make()
                        ->title('Error')
                        ->body('No se pudo registrar en Zoom. Revisa los logs para más detalle.')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Éxito')
                    ->body('El registro fue enviado a Zoom correctamente.')
                    ->success()
                    ->send();
            });
    }
}
