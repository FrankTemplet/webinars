<?php

namespace App\Services;

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
     * List all webinars for the account
     */
    public function listWebinars(): array
    {
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

        $webinars = $response->json()['webinars'] ?? [];

        $options = [];
        foreach ($webinars as $webinar) {
            $options[$webinar['id']] = $webinar['topic'] . " ({$webinar['start_time']})";
        }

        return $options;
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
}
