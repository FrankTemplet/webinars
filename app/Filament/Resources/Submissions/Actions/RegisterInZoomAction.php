<?php

namespace App\Filament\Resources\Submissions\Actions;

use App\Models\Submission;
use App\Services\ZoomService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class RegisterInZoomAction
{
    public static function make(): Action
    {
        return Action::make('registerInZoom')
            ->label(fn (Submission $record) => $record->registered_in_zoom_at ? 'Re-registrar en Zoom' : 'Registrar en Zoom')
            ->icon('heroicon-o-video-camera')
            ->color('info')
            ->visible(fn (Submission $record) => !empty($record->webinar?->zoom_webinar_id))
            ->requiresConfirmation()
            ->modalHeading('Registrar en Zoom')
            ->modalDescription(fn (Submission $record) => $record->registered_in_zoom_at
                ? 'Este registro ya fue enviado a Zoom. ¿Deseas volver a enviarlo?'
                : '¿Deseas registrar a esta persona en el webinar de Zoom?')
            ->modalSubmitActionLabel('Sí, registrar')
            ->action(function (Submission $record) {
                $registered = app(ZoomService::class)->registerRegistrant(
                    $record->webinar->zoom_webinar_id,
                    $record->data ?? []
                );

                if (!$registered) {
                    Notification::make()
                        ->title('Error')
                        ->body('No se pudo registrar en Zoom. Revisa los logs para más detalle.')
                        ->danger()
                        ->send();

                    return;
                }

                $record->update(['registered_in_zoom_at' => now()]);

                Notification::make()
                    ->title('Éxito')
                    ->body('El registro fue enviado a Zoom correctamente.')
                    ->success()
                    ->send();
            });
    }
}
