<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaConversionsService
{
    private const API_VERSION = 'v18.0';
    private const BASE_URL = 'https://graph.facebook.com';

    /**
     * Send a server-side event to Meta Conversions API.
     *
     * @param string $pixelId The Meta Pixel ID
     * @param string $accessToken The Conversions API access token
     * @param string $eventName The event name (e.g., 'CompleteRegistration')
     * @param string $eventId The unique event ID for deduplication
     * @param array $userData User data for matching (email, phone, etc.)
     * @param array $customData Additional event data
     * @param string|null $eventSourceUrl The URL where the event occurred
     * @return bool Whether the event was sent successfully
     */
    public function sendEvent(
        string $pixelId,
        string $accessToken,
        string $eventName,
        string $eventId,
        array $userData = [],
        array $customData = [],
        ?string $eventSourceUrl = null
    ): bool {
        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $pixelId . '/events';

        // Hash user data for privacy (Meta requires SHA256 hashing)
        $hashedUserData = $this->hashUserData($userData);

        $eventData = [
            'event_name' => $eventName,
            'event_time' => time(),
            'event_id' => $eventId,
            'action_source' => 'website',
            'user_data' => $hashedUserData,
        ];

        if ($eventSourceUrl) {
            $eventData['event_source_url'] = $eventSourceUrl;
        }

        if (!empty($customData)) {
            $eventData['custom_data'] = $customData;
        }

        $payload = [
            'data' => [json_encode([$eventData])],
            'access_token' => $accessToken,
        ];

        try {
            $response = Http::asForm()->post($url, $payload);

            if ($response->successful()) {
                $body = $response->json();
                Log::info('Meta CAPI: Event sent successfully', [
                    'pixel_id' => $pixelId,
                    'event_name' => $eventName,
                    'event_id' => $eventId,
                    'events_received' => $body['events_received'] ?? 0,
                ]);
                return true;
            }

            Log::error('Meta CAPI: Failed to send event', [
                'pixel_id' => $pixelId,
                'event_name' => $eventName,
                'event_id' => $eventId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('Meta CAPI: Exception while sending event', [
                'pixel_id' => $pixelId,
                'event_name' => $eventName,
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Hash user data according to Meta's requirements.
     * Meta requires lowercase, trimmed, SHA256 hashed values.
     *
     * @param array $userData Raw user data
     * @return array Hashed user data
     */
    private function hashUserData(array $userData): array
    {
        $hashed = [];

        // Email - must be lowercase and trimmed
        if (!empty($userData['email'])) {
            $hashed['em'] = hash('sha256', strtolower(trim($userData['email'])));
        }

        // Phone - remove non-numeric characters except +
        if (!empty($userData['phone'])) {
            $phone = preg_replace('/[^\d+]/', '', $userData['phone']);
            $hashed['ph'] = hash('sha256', $phone);
        }

        // First name - lowercase and trimmed
        if (!empty($userData['first_name'])) {
            $hashed['fn'] = hash('sha256', strtolower(trim($userData['first_name'])));
        }

        // Last name - lowercase and trimmed
        if (!empty($userData['last_name'])) {
            $hashed['ln'] = hash('sha256', strtolower(trim($userData['last_name'])));
        }

        // Client IP address (from request)
        if (!empty($userData['client_ip_address'])) {
            $hashed['client_ip_address'] = $userData['client_ip_address'];
        }

        // Client user agent (from request)
        if (!empty($userData['client_user_agent'])) {
            $hashed['client_user_agent'] = $userData['client_user_agent'];
        }

        // External ID (e.g., submission ID)
        if (!empty($userData['external_id'])) {
            $hashed['external_id'] = hash('sha256', (string) $userData['external_id']);
        }

        return $hashed;
    }

    /**
     * Send a CompleteRegistration event.
     *
     * @param string $pixelId
     * @param string $accessToken
     * @param string $eventId
     * @param array $submissionData Form submission data
     * @param string|null $sourceUrl
     * @param string|null $clientIp
     * @param string|null $userAgent
     * @return bool
     */
    public function sendCompleteRegistration(
        string $pixelId,
        string $accessToken,
        string $eventId,
        array $submissionData,
        ?string $sourceUrl = null,
        ?string $clientIp = null,
        ?string $userAgent = null
    ): bool {
        // Extract user data from submission
        $userData = $this->extractUserData($submissionData, $clientIp, $userAgent);

        return $this->sendEvent(
            $pixelId,
            $accessToken,
            'CompleteRegistration',
            $eventId,
            $userData,
            ['status' => 'registered'],
            $sourceUrl
        );
    }

    /**
     * Extract user data from form submission for Meta matching.
     *
     * @param array $submissionData
     * @param string|null $clientIp
     * @param string|null $userAgent
     * @return array
     */
    private function extractUserData(array $submissionData, ?string $clientIp = null, ?string $userAgent = null): array
    {
        $userData = [];

        // Common field name patterns for email
        foreach (['email', 'correo', 'e-mail', 'mail'] as $key) {
            if (!empty($submissionData[$key])) {
                $userData['email'] = $submissionData[$key];
                break;
            }
        }

        // Common field name patterns for phone
        foreach (['phone', 'telefono', 'tel', 'celular', 'mobile'] as $key) {
            if (!empty($submissionData[$key])) {
                $userData['phone'] = $submissionData[$key];
                break;
            }
        }

        // Common field name patterns for first name
        foreach (['first_name', 'nombre', 'name', 'gustavo'] as $key) {
            if (!empty($submissionData[$key])) {
                $userData['first_name'] = $submissionData[$key];
                break;
            }
        }

        // Common field name patterns for last name
        foreach (['last_name', 'apellido', 'surname', 'cuadrado'] as $key) {
            if (!empty($submissionData[$key])) {
                $userData['last_name'] = $submissionData[$key];
                break;
            }
        }

        if ($clientIp) {
            $userData['client_ip_address'] = $clientIp;
        }

        if ($userAgent) {
            $userData['client_user_agent'] = $userAgent;
        }

        return $userData;
    }
}
