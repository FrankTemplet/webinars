<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ZoomService
{
    protected string $accountId;
    protected string $clientId;
    protected string $clientSecret;
    protected string $baseUrl = 'https://api.zoom.us/v2';

    public function __construct()
    {
        $this->accountId = config('services.zoom.account_id');
        $this->clientId = config('services.zoom.client_id');
        $this->clientSecret = config('services.zoom.client_secret');
    }

    /**
     * Get Server-to-Server OAuth Access Token
     */
    protected function getAccessToken(): ?string
    {
        return Cache::remember('zoom_access_token', 3500, function () {
            $response = Http::asForm()
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->post("https://zoom.us/oauth/token", [
                    'grant_type' => 'account_credentials',
                    'account_id' => $this->accountId,
                ]);

            if ($response->failed()) {
                Log::error('Zoom OAuth Failed', [
                    'error' => $response->json(),
                    'status' => $response->status()
                ]);
                return null;
            }

            return $response->json()['access_token'];
        });
    }

    /**
     * Opciones para el select: los webinars próximos de la cuenta. Los que ya
     * pasaron se encuentran por ID con findWebinar().
     */
    public function listWebinars(): array
    {
        $options = [];
        foreach ($this->upcomingWebinars() as $webinar) {
            $options[$webinar['id']] = $this->formatLabel($webinar);
        }

        return $options;
    }

    /**
     * Un webinar puntual, aunque Zoom ya lo haya expirado. Null si no existe.
     */
    public function findWebinar(string $webinarId): ?array
    {
        $listed = collect($this->upcomingWebinars())->firstWhere('id', (int) $webinarId);
        if ($listed) return $listed;

        $token = $this->getAccessToken();
        if (!$token) return null;

        // Zoom responde 404 en /webinars cuando el webinar expiró; el reporte
        // sigue existiendo y es lo que usa la sincronización de asistentes.
        foreach (["webinars/{$webinarId}", "report/webinars/{$webinarId}"] as $path) {
            $response = Http::withToken($token)->get("{$this->baseUrl}/{$path}");

            if ($response->successful()) {
                return $response->json();
            }
        }

        Log::warning('Zoom Get Webinar Failed', ['webinar_id' => $webinarId, 'error' => $response->json()]);

        return null;
    }

    public function webinarLabel(string $webinarId): ?string
    {
        $webinar = $this->findWebinar($webinarId);

        return $webinar ? $this->formatLabel($webinar) : null;
    }

    /**
     * Día del webinar en su propia zona horaria, para que uno de la tarde no
     * se recorra al día siguiente en UTC.
     */
    public function startDate(array $webinar): Carbon
    {
        return Carbon::parse($webinar['start_time'])->setTimezone($webinar['timezone'] ?? config('app.timezone'));
    }

    protected function upcomingWebinars(): array
    {
        return once(function () {
            $token = $this->getAccessToken();
            if (!$token) return [];

            $response = Http::withToken($token)
                ->get("{$this->baseUrl}/users/me/webinars", [
                    'type' => 'upcoming',
                    'page_size' => 300,
                ]);

            if ($response->failed()) {
                Log::error('Zoom List Webinars Failed', $response->json());
                return [];
            }

            return $response->json()['webinars'] ?? [];
        });
    }

    protected function hasEnded(array $webinar): bool
    {
        $end = isset($webinar['end_time'])
            ? Carbon::parse($webinar['end_time'])
            : Carbon::parse($webinar['start_time'])->addMinutes($webinar['duration'] ?? 0);

        return $end->isPast();
    }

    protected function formatLabel(array $webinar): string
    {
        $label = trim($webinar['topic']) . " — {$this->startDate($webinar)->format('d - m - Y')}";

        return $this->hasEnded($webinar) ? "{$label} (Ended)" : $label;
    }

    /**
     * Register a person for a webinar
     */
    public function registerRegistrant(string $webinarId, array $userData): bool
    {
        $token = $this->getAccessToken();
        if (!$token) return false;

        // Map fields to Zoom requirements
        $payload = [
            'email' => $userData['email'] ?? $userData['correo'] ?? null,
            'first_name' => $userData['first_name'] ?? $userData['nombre'] ?? $userData['name'] ?? 'Registrant',
            'last_name' => $userData['last_name'] ?? $userData['apellido'] ?? '',
        ];

        if (!$payload['email']) {
            Log::warning('Zoom Registration Skipped: No email found in data', ['data' => $userData]);
            return false;
        }

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/webinars/{$webinarId}/registrants", $payload);

        if ($response->failed()) {
            Log::error('Zoom Registration Failed', [
                'webinar_id' => $webinarId,
                'payload' => $payload,
                'error' => $response->json()
            ]);
            return false;
        }

        Log::info('Zoom Registration Successful', [
            'webinar_id' => $webinarId,
            'email' => $payload['email']
        ]);

        return true;
    }

    /**
     * Registrantes de un webinar, con sus campos tal como los guarda Zoom.
     * Pagina hasta traerlos todos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listRegistrants(string $webinarId, string $status = 'approved'): array
    {
        $token = $this->getAccessToken();
        if (!$token) return [];

        $registrants = [];
        $pageToken = null;

        do {
            $response = Http::withToken($token)->get("{$this->baseUrl}/webinars/{$webinarId}/registrants", array_filter([
                'status' => $status,
                'page_size' => 300,
                'next_page_token' => $pageToken,
            ]));

            if ($response->failed()) {
                Log::error('Zoom listRegistrants failed', [
                    'webinar_id' => $webinarId,
                    'status' => $status,
                    'error' => $response->json(),
                ]);

                return $registrants;
            }

            $body = $response->json();
            $registrants = array_merge($registrants, $body['registrants'] ?? []);
            $pageToken = $body['next_page_token'] ?? null;
        } while (!empty($pageToken));

        return $registrants;
    }

    /**
     * Get webinar participants (attendance)
     */
    public function getWebinarParticipants(string $webinarId): int
    {
        $token = $this->getAccessToken();
        if (!$token) return 0;

        // Try to get past webinar participants report
        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/report/webinars/{$webinarId}/participants", [
                'page_size' => 300,
            ]);

        if ($response->failed()) {
            Log::warning('Zoom Get Participants Failed', [
                'webinar_id' => $webinarId,
                'error' => $response->json()
            ]);
            return 0;
        }

        $data = $response->json();
        return $data['total_records'] ?? 0;
    }

    /**
     * Get full webinar participants list (paginated through Zoom's report API).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getWebinarParticipantsDetailed(string $webinarId): array
    {
        $token = $this->getAccessToken();
        if (!$token) return [];

        $participants = [];
        $nextPageToken = null;

        do {
            $query = ['page_size' => 300];
            if ($nextPageToken) {
                $query['next_page_token'] = $nextPageToken;
            }

            $response = Http::withToken($token)
                ->get("{$this->baseUrl}/report/webinars/{$webinarId}/participants", $query);

            if ($response->failed()) {
                Log::warning('Zoom Get Participants Detailed Failed', [
                    'webinar_id' => $webinarId,
                    'error' => $response->json(),
                ]);
                break;
            }

            $data = $response->json();
            $participants = array_merge($participants, $data['participants'] ?? []);
            $nextPageToken = $data['next_page_token'] ?? null;
        } while (!empty($nextPageToken));

        return $participants;
    }
}
