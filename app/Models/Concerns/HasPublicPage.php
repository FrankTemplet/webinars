<?php

namespace App\Models\Concerns;

/**
 * URLs de la página pública del registro. Hay dos:
 *
 *  - `previewUrl()` siempre apunta a esta misma aplicación (la ruta de
 *    fallback con el cliente en el path), así el iframe del panel queda en el
 *    mismo origen y funciona igual en local y en producción.
 *  - `liveUrl()` apunta al subdominio real del cliente, que es la dirección
 *    que se comparte. En local no hay subdominios, así que cae en el preview.
 */
trait HasPublicPage
{
    /**
     * Nombre de la ruta local (sin el sufijo `.local`) de la página pública.
     */
    abstract protected function publicRouteName(): string;

    /**
     * Segmento que lleva la URL pública en producción: /webinars/{slug}.
     */
    abstract protected function publicPathPrefix(): string;

    public function previewUrl(): ?string
    {
        $client = $this->client;

        if (! $client || ! $this->slug) {
            return null;
        }

        return route($this->publicRouteName().'.local', [
            'client' => $client->slug,
            'slug' => $this->slug,
        ]);
    }

    public function liveUrl(): ?string
    {
        $client = $this->client;
        $domain = config('webinars.client_domain');

        if (! $client || ! $this->slug) {
            return null;
        }

        if (! $domain || app()->environment('local', 'testing')) {
            return $this->previewUrl();
        }

        $prefix = trim($this->publicPathPrefix(), '/');
        $path = $prefix === '' ? $this->slug : $prefix.'/'.$this->slug;

        return sprintf(
            '%s://%s.%s/%s',
            config('webinars.client_scheme', 'https'),
            $client->slug,
            $domain,
            $path,
        );
    }
}
