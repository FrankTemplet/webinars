<?php

namespace App\Models;

use App\Models\Concerns\HasPublicPage;
use App\Models\Concerns\HasTheme;
use App\Models\Scopes\ClientAccessScope;
use App\Support\InternalEmailDomains;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class Webinar extends Model
{
    use HasPublicPage;
    use HasTheme;
    use SoftDeletes;

    protected $fillable = [
        'client_id', 'title', 'show_title', 'subtitle', 'description', 'slug', 'event_date', 'zoom_webinar_id', 'multiple_sessions', 'sessions', 'sessions_label', 'attendees_synced_at', 'zoom_participants_count', 'hero_image', 'header_logo',
        'form_schema', 'chartable_fields', 'tracking_scripts', 'meta_title', 'meta_description', 'clay_webhook_url',
        'accent_color', 'accent_text_color', 'background_color',
        'button_radius', 'field_radius', 'custom_css',
        'meta_campaign_id', 'campaign', 'ad_spend', 'last_ad_spend_sync_at',
        'thank_you_enabled', 'thank_you_title', 'thank_you_message', 'thank_you_image',
        'thank_you_cta_text', 'thank_you_cta_url',
    ];

    protected $casts = [
        'show_title' => 'boolean',
        'thank_you_enabled' => 'boolean',
        'form_schema' => 'array',
        'chartable_fields' => 'array',
        'tracking_scripts' => 'array',
        'ad_spend' => 'float',
        'button_radius' => 'integer',
        'field_radius' => 'integer',
        'last_ad_spend_sync_at' => 'datetime',
        'event_date' => 'date',
        'multiple_sessions' => 'boolean',
        'sessions' => 'array',
        'attendees_synced_at' => 'datetime',
        'zoom_participants_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ClientAccessScope('client_id'));

        // Con sesiones, mandan las sesiones: el webinar es su última fecha y
        // el Zoom único deja de usarse.
        static::saving(function (Webinar $webinar) {
            if (! $webinar->multiple_sessions) {
                $webinar->sessions = null;

                return;
            }

            $webinar->sessions = collect($webinar->sessions)
                ->map(fn (array $session) => ['zoom_webinar_id' => (string) $session['zoom_webinar_id']] + $session)
                ->values()
                ->all();
            $webinar->zoom_webinar_id = null;
            $webinar->event_date = collect($webinar->sessions)->pluck('date')->filter()->max() ?? $webinar->event_date;
        });
    }

    public function hasSessions(): bool
    {
        return $this->multiple_sessions && ! empty($this->sessions);
    }

    /**
     * Webinars de Zoom en los que se registra y de los que se traen
     * asistentes: los de sus sesiones, o el único que tenga.
     *
     * @return array<int, string>
     */
    public function zoomWebinarIds(): array
    {
        if ($this->hasSessions()) {
            return collect($this->sessions)->pluck('zoom_webinar_id')->filter()->map(fn ($id) => (string) $id)->values()->all();
        }

        return filled($this->zoom_webinar_id) ? [(string) $this->zoom_webinar_id] : [];
    }

    public function sessionLabel(string $zoomWebinarId): string
    {
        return collect($this->sessions ?? [])->firstWhere('zoom_webinar_id', $zoomWebinarId)['label'] ?? $zoomWebinarId;
    }

    /**
     * Sesiones como las ve el formulario público: el label y el ID de Zoom
     * con el que vuelven en el envío.
     *
     * @return array<int, array{id: string, label: string}>
     */
    public function publicSessions(): array
    {
        return collect($this->sessions ?? [])
            ->map(fn (array $session) => ['id' => (string) $session['zoom_webinar_id'], 'label' => $session['label']])
            ->values()
            ->all();
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }

    public function surveys()
    {
        return $this->hasMany(Survey::class);
    }

    public function attendees()
    {
        return $this->hasMany(Attendee::class);
    }

    /**
     * Asistentes únicos externos, contados de la tabla attendees y no de Zoom:
     * Zoom deja de responder por los webinars viejos y esto debe seguir igual.
     */
    public function uniqueExternalAttendees(): int
    {
        $attendees = Attendee::withoutGlobalScopes()->where('webinar_id', $this->id);

        return InternalEmailDomains::scopeExternal($attendees, 'email', InternalEmailDomains::for($this->client))
            ->distinct()
            ->count(DB::raw('LOWER(email)'));
    }

    /**
     * Aviso de cuándo es o fue el webinar, para el panel y el dashboard.
     * Null mientras no tenga fecha.
     */
    public function eventDateSummary(): ?string
    {
        if (! $this->event_date) {
            return null;
        }

        $date = $this->event_date->format('d - m - Y');

        return match (true) {
            $this->event_date->isToday() => "Takes place today ({$date})",
            $this->event_date->isPast() => "Took place on {$date}",
            default => "Upcoming · {$date}",
        };
    }

    /**
     * Campos de la landing. Se enumeran para no publicar el webhook de Clay
     * ni el token de la API de conversiones de Meta, que viven en el modelo.
     */
    public function toPublicArray(bool $forEditor = false): array
    {
        return array_merge(
            $this->only([
                'slug', 'title', 'show_title', 'subtitle', 'description',
                'meta_title', 'meta_description', 'hero_image', 'header_logo',
                'form_schema',
            ]),
            [
                'tracking_scripts' => $this->publicTrackingScripts(),
                'sessions' => $this->publicSessions(),
                'sessions_label' => $this->sessions_label,
                'theme' => $this->theme($forEditor),
            ],
        );
    }

    /**
     * Los pixeles sí se renderizan en el navegador, pero el access token de
     * la Conversions API es de uso exclusivamente servidor.
     */
    public function publicTrackingScripts(): array
    {
        return collect($this->tracking_scripts ?? [])
            ->map(fn (array $script) => Arr::except($script, ['access_token']))
            ->values()
            ->all();
    }

    protected function themeParent(): ?Client
    {
        return $this->client;
    }

    protected function publicRouteName(): string
    {
        return 'webinar.show';
    }

    protected function previewRouteName(): string
    {
        return 'preview.webinar';
    }

    /**
     * Valores con los que nació la landing, para los clientes que no
     * configuraron su marca: cian, títulos azul oscuro, texto gris y los
     * radios que traía el formulario.
     */
    protected function themeDefaults(): array
    {
        return [
            '--font-family' => 'Roboto',
            '--accent' => '#00B0D3',
            '--accent-text' => '#FFFFFF',
            '--heading' => '#041B36',
            '--subtitle' => '#1A1A1A',
            '--text' => '#656668',
            '--bg' => '#FFFFFF',
            '--radius-button' => '0px',
            '--radius-field' => '5px',
        ];
    }
}
