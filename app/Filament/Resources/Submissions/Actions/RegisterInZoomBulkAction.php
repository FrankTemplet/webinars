<?php

namespace App\Filament\Resources\Submissions\Actions;

use App\Models\Submission;
use App\Models\Webinar;
use App\Services\ZoomService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class RegisterInZoomBulkAction
{
    public static function make(): Action
    {
        return Action::make('registerInZoom')
            ->label(function ($livewire) {
                $pendingCount = static::pendingCount($livewire);

                return $pendingCount > 0
                    ? "Registrar ({$pendingCount}) en Zoom"
                    : 'Registrar en Zoom';
            })
            ->icon('heroicon-o-video-camera')
            ->color('info')
            ->visible(function ($livewire) {
                $webinar = static::filteredWebinar($livewire);

                // Solo si hay webinar filtrado, con Zoom configurado y pendientes
                if (!$webinar || empty($webinar->zoom_webinar_id)) {
                    return false;
                }

                return static::pendingCount($livewire) > 0;
            })
            ->requiresConfirmation()
            ->modalHeading('Registrar registros en Zoom')
            ->modalDescription(function ($livewire) {
                $pendingCount = static::pendingCount($livewire);

                return "¿Estás seguro de que deseas registrar {$pendingCount} registro(s) pendiente(s) en Zoom?";
            })
            ->modalSubmitActionLabel('Sí, registrar')
            ->action(function ($livewire) {
                $webinar = static::filteredWebinar($livewire);

                if (!$webinar || empty($webinar->zoom_webinar_id)) {
                    Notification::make()
                        ->title('Error')
                        ->body('Debes seleccionar un cliente y un webinar con Zoom configurado.')
                        ->danger()
                        ->send();

                    return;
                }

                $submissions = Submission::query()
                    ->where('webinar_id', $webinar->id)
                    ->whereNull('registered_in_zoom_at')
                    ->get();

                if ($submissions->isEmpty()) {
                    Notification::make()
                        ->title('Sin registros')
                        ->body('No hay registros pendientes de registrar en Zoom.')
                        ->warning()
                        ->send();

                    return;
                }

                $zoomService = app(ZoomService::class);
                $successCount = 0;
                $errorCount = 0;

                foreach ($submissions as $submission) {
                    $registered = $zoomService->registerRegistrant(
                        $webinar->zoom_webinar_id,
                        $submission->data ?? []
                    );

                    if ($registered) {
                        $submission->update(['registered_in_zoom_at' => now()]);
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                }

                if ($errorCount === 0) {
                    Notification::make()
                        ->title('Éxito')
                        ->body("Se registraron {$successCount} registros en Zoom correctamente.")
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Proceso completado con errores')
                        ->body("Se registraron {$successCount} registros correctamente. {$errorCount} registros fallaron.")
                        ->warning()
                        ->send();
                }
            });
    }

    /**
     * Webinar actualmente filtrado en la tabla, si hay cliente y webinar seleccionados.
     */
    protected static function filteredWebinar($livewire): ?Webinar
    {
        $filters = $livewire->tableFilters ?? [];
        $webinarId = $filters['submitted']['webinar_id'] ?? null;
        $clientId = $filters['submitted']['client_id'] ?? null;

        if (!$webinarId || !$clientId) {
            return null;
        }

        return Webinar::find($webinarId);
    }

    protected static function pendingCount($livewire): int
    {
        $webinar = static::filteredWebinar($livewire);

        if (!$webinar) {
            return 0;
        }

        return Submission::query()
            ->where('webinar_id', $webinar->id)
            ->whereNull('registered_in_zoom_at')
            ->count();
    }
}
