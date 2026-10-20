<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * URLs que el panel usa para esta página:
 *
 *  - `publicUrl()` es la dirección publicada, la que se comparte:
 *    /{cliente}/{slug} para los webinars y /{cliente}/surveys/{slug} para las
 *    encuestas.
 *  - `previewUrl()` es la vista previa del panel, que renderiza la misma
 *    página sin cargar pixeles ni aceptar envíos.
 */
trait HasPublicPage
{
    /**
     * Nombre de la ruta pública, sin el sufijo que llevan sus nombres.
     */
    abstract protected function publicRouteName(): string;

    /**
     * Nombre de la ruta de la vista previa del panel.
     */
    abstract protected function previewRouteName(): string;

    public function publicUrl(): ?string
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

    /**
     * Sin registro guardado no hay nada que previsualizar desde una lista;
     * el formulario usa su propia acción, que no necesita id.
     */
    public function previewUrl(): ?string
    {
        if (! $this->exists) {
            return null;
        }

        // Las rutas de preview nombran su parámetro como el modelo.
        $parameter = Str::lower(class_basename($this));

        return route($this->previewRouteName(), [$parameter => $this->getKey()]);
    }
}
