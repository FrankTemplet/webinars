<?php

namespace App\Filament\Resources\Submissions\Actions;

use App\Models\Submission;
use App\Services\ClayService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class SendToClayBulkAction
{
    public static function make(): Action
    {
        return Action::make('sendToClay')
            ->label(function ($livewire) {
                // Obtener los filtros aplicados
                $filters = $livewire->tableFilters ?? [];
                $webinarId = $filters['submitted']['webinar_id'] ?? null;

                if (!$webinarId) {
                    return 'Enviar a Clay';
                }

                // Contar registros pendientes
                $pendingCount = Submission::query()
                    ->where('webinar_id', $webinarId)
                    ->whereNull('sent_to_clay_at')
                    ->count();

                return $pendingCount > 0
                    ? "Enviar ({$pendingCount}) a Clay"
                    : 'Enviar a Clay';
            })
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            ->visible(function ($livewire) {
                // Obtener los filtros aplicados
                $filters = $livewire->tableFilters ?? [];
                $webinarId = $filters['submitted']['webinar_id'] ?? null;
                $clientId = $filters['submitted']['client_id'] ?? null;

                // Solo mostrar si hay cliente y webinar seleccionados
                if (!$webinarId || !$clientId) {
                    return false;
                }

                // Verificar que el webinar tenga webhook configurado
                $webinar = \App\Models\Webinar::find($webinarId);
                if (!$webinar || empty($webinar->clay_webhook_url)) {
                    return false;
                }

                // Solo mostrar si hay registros pendientes
                $pendingCount = Submission::query()
                    ->where('webinar_id', $webinarId)
                    ->whereNull('sent_to_clay_at')
                    ->count();

                return $pendingCount > 0;
            })
            ->requiresConfirmation()
            ->modalHeading('Enviar registros a Clay')
            ->modalDescription(function ($livewire) {
                $filters = $livewire->tableFilters ?? [];
                $webinarId = $filters['submitted']['webinar_id'] ?? null;

                if ($webinarId) {
                    $pendingCount = Submission::query()
                        ->where('webinar_id', $webinarId)
                        ->whereNull('sent_to_clay_at')
                        ->count();

                    return "¿Estás seguro de que deseas enviar {$pendingCount} registro(s) no enviado(s) a Clay?";
                }

                return '¿Estás seguro de que deseas enviar todos los registros filtrados (no enviados) a Clay?';
            })
            ->modalSubmitActionLabel('Sí, enviar')
            ->action(function ($livewire) {
                $clayService = app(ClayService::class);

                // Obtener los filtros aplicados
                $filters = $livewire->tableFilters ?? [];
                $webinarId = $filters['submitted']['webinar_id'] ?? null;
                $clientId = $filters['submitted']['client_id'] ?? null;

                if (!$webinarId || !$clientId) {
                    Notification::make()
                        ->title('Error')
                        ->body('Debes seleccionar un cliente y un webinar para enviar registros a Clay.')
                        ->danger()
                        ->send();
                    return;
                }

                // Obtener el webinar y su configuración de Clay
                $webinar = \App\Models\Webinar::with('client')->find($webinarId);

                if (!$webinar) {
                    Notification::make()
                        ->title('Error')
                        ->body('Webinar no encontrado.')
                        ->danger()
                        ->send();
                    return;
                }

                $webhookUrl = $webinar->clay_webhook_url;

                if (empty($webhookUrl)) {
                    Notification::make()
                        ->title('Error')
                        ->body('El webinar no tiene configurada una URL de webhook de Clay.')
                        ->danger()
                        ->send();
                    return;
                }

                // Obtener todos los submissions no enviados del webinar
                $submissions = Submission::query()
                    ->where('webinar_id', $webinarId)
                    ->whereNull('sent_to_clay_at')
                    ->get();

                if ($submissions->isEmpty()) {
                    Notification::make()
                        ->title('Sin registros')
                        ->body('No hay registros pendientes de enviar a Clay.')
                        ->warning()
                        ->send();
                    return;
                }

                $successCount = 0;
                $errorCount = 0;

                foreach ($submissions as $submission) {
                    $leadData = $clayService->prepareLeadData(
                        $submission->data,
                        [
                            'utm_source' => $submission->utm_source,
                            'utm_medium' => $submission->utm_medium,
                            'utm_campaign' => $submission->utm_campaign,
                            'utm_term' => $submission->utm_term,
                            'utm_content' => $submission->utm_content,
                        ],
                        $webinar->title,
                        $webinar->client->name
                    );

                    $sent = $clayService->sendLead($webhookUrl, $leadData);

                    if ($sent) {
                        $submission->update(['sent_to_clay_at' => now()]);
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                }

                if ($errorCount === 0) {
                    Notification::make()
                        ->title('Éxito')
                        ->body("Se enviaron {$successCount} registros a Clay correctamente.")
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Proceso completado con errores')
                        ->body("Se enviaron {$successCount} registros correctamente. {$errorCount} registros fallaron.")
                        ->warning()
                        ->send();
                }
            });
    }
}
