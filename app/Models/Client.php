<?php

namespace App\Models;

use App\Models\Scopes\ClientAccessScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = [
        'name', 'slug', 'logo', 'footer_text', 'internal_email_domains',
        'meta_ad_account_id', 'meta_access_token',
        'font_family', 'accent_color', 'accent_text_color', 'heading_color',
        'subtitle_color', 'text_color', 'background_color',
        'button_radius', 'field_radius', 'custom_css',
    ];

    /**
     * `footer` se expone a las páginas públicas ya con los placeholders
     * resueltos. La API arma sus propios campos, así que no se filtra ahí.
     */
    protected $appends = ['footer'];

    protected $casts = [
        'internal_email_domains' => 'array',
        'button_radius' => 'integer',
        'field_radius' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ClientAccessScope('id'));
    }

    /**
     * Texto del pie de página con {year} y {client} sustituidos. Devuelve
     * null cuando el cliente no lo configuró, para que cada página use el
     * suyo por defecto.
     */
    protected function footer(): Attribute
    {
        return Attribute::get(function (): ?string {
            $text = trim((string) $this->footer_text);

            if ($text === '') {
                return null;
            }

            return strtr($text, [
                '{year}' => (string) now()->year,
                '{client}' => (string) $this->name,
            ]);
        });
    }

    /**
     * Solo lo que necesitan las páginas públicas. El modelo completo trae
     * credenciales de Meta, así que nunca se serializa tal cual.
     */
    public function toPublicArray(): array
    {
        if (! $this->relationLoaded('socialMedia')) {
            $this->load('socialMedia');
        }

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'logo' => $this->logo,
            'footer' => $this->footer,
            'social_media' => $this->socialMedia
                ->map(fn (SocialMediaLink $link) => [
                    'type' => $link->type,
                    'url' => $link->url,
                ])
                ->all(),
        ];
    }

    /**
     * El panel previsualiza la marca del cliente con una landing de ejemplo;
     * el cliente en sí no tiene página pública propia.
     */
    public function previewUrl(): ?string
    {
        return $this->exists ? route('preview.client', ['client' => $this->getKey()]) : null;
    }

    public function liveUrl(): ?string
    {
        return null;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function webinars(): HasMany
    {
        return $this->hasMany(Webinar::class);
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class);
    }

    public function socialMedia(): HasMany
    {
        return $this->hasMany(SocialMediaLink::class);
    }
}
