<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClayService
{
    /**
     * Send a lead to Clay for enrichment.
     *
     * Clay uses webhooks to receive data. You can configure a webhook URL
     * in Clay that triggers an enrichment workflow.
     *
     * @param string $webhookUrl The Clay webhook URL
     * @param array $leadData The lead data to enrich
     * @return bool Whether the lead was sent successfully
     */
    public function sendLead(string $webhookUrl, array $leadData): bool
    {
        if (empty($webhookUrl)) {
            Log::warning('Clay: Webhook URL not configured');
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->post($webhookUrl, $leadData);

            if ($response->successful()) {
                Log::info('Clay: Lead sent successfully', [
                    'webhook_url' => $webhookUrl,
                    'lead_email' => $leadData['email'] ?? 'N/A',
                    'status' => $response->status(),
                ]);
                return true;
            }

            Log::error('Clay: Failed to send lead', [
                'webhook_url' => $webhookUrl,
                'lead_email' => $leadData['email'] ?? 'N/A',
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('Clay: Exception while sending lead', [
                'webhook_url' => $webhookUrl,
                'lead_email' => $leadData['email'] ?? 'N/A',
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Prepare lead data for Clay enrichment.
     * Extracts common fields and formats them appropriately.
     *
     * @param array $submissionData Raw submission data
     * @param array $utmData UTM parameters
     * @param string|null $webinarTitle The webinar title
     * @param string|null $clientName The client name
     * @return array Formatted lead data
     */
    public function prepareLeadData(
        array $submissionData,
        array $utmData = [],
        ?string $webinarTitle = null,
        ?string $clientName = null
    ): array {
        $leadData = [
            'timestamp' => now()->toIso8601String(),
        ];

        // Extract common fields
        $fieldMapping = [
            'email' => ['email', 'correo', 'e-mail'],
            'first_name' => ['first_name', 'nombre', 'firstname', 'name'],
            'last_name' => ['last_name', 'apellido', 'lastname', 'surname'],
            'phone' => ['phone', 'telefono', 'tel', 'mobile', 'celular'],
            'company' => ['company', 'empresa', 'organization', 'organizacion'],
            'job_title' => ['job_title', 'cargo', 'position', 'puesto'],
            'country' => ['country', 'pais'],
            'state' => ['state', 'estado', 'region'],
            'city' => ['city', 'ciudad'],
        ];

        foreach ($fieldMapping as $standardKey => $possibleKeys) {
            foreach ($possibleKeys as $key) {
                if (isset($submissionData[$key]) && !empty($submissionData[$key])) {
                    $leadData[$standardKey] = $submissionData[$key];
                    break;
                }
            }
        }

        // Add all original submission data as metadata
        $leadData['submission_data'] = $submissionData;

        // Add UTM parameters
        if (!empty($utmData)) {
            $leadData['utm_source'] = $utmData['utm_source'] ?? null;
            $leadData['utm_medium'] = $utmData['utm_medium'] ?? null;
            $leadData['utm_campaign'] = $utmData['utm_campaign'] ?? null;
            $leadData['utm_term'] = $utmData['utm_term'] ?? null;
            $leadData['utm_content'] = $utmData['utm_content'] ?? null;
        }

        // Add webinar context
        if ($webinarTitle) {
            $leadData['webinar_title'] = $webinarTitle;
        }

        if ($clientName) {
            $leadData['client_name'] = $clientName;
        }

        return $leadData;
    }
}
