<?php

namespace App\Models\Concerns;

use App\Models\Client;
use App\Support\Fonts;

/**
 * Tokens de estilo de las páginas públicas, resueltos en cascada:
 *
 *   valor de la página  →  valor del cliente  →  valor de la plantilla
 *
 * El cliente concentra la marca (tipografía, colores de texto, CSS libre) y
 * cada página sobreescribe solo lo que la distingue. Un campo en blanco
 * siempre hereda, nunca pinta vacío.
 */
trait HasTheme
{
    /**
     * Valores de la plantilla. Cada modelo declara los suyos porque la
     * landing y la encuesta no parten del mismo diseño.
     *
     * @return array<string, string>
     */
    abstract protected function themeDefaults(): array;

    /**
     * Cliente del que hereda el estilo.
     */
    abstract protected function themeParent(): ?Client;

    /**
     * Token CSS => columna que lo guarda. El modelo solo resuelve los tokens
     * que su plantilla declara, así que la lista puede ser común.
     *
     * @return array<string, string>
     */
    protected function themeColorColumns(): array
    {
        return [
            '--accent' => 'accent_color',
            '--accent-text' => 'accent_text_color',
            '--heading' => 'heading_color',
            '--subtitle' => 'subtitle_color',
            '--text' => 'text_color',
            '--bg' => 'background_color',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function themeSizeColumns(): array
    {
        return [
            '--radius-button' => 'button_radius',
            '--radius-field' => 'field_radius',
        ];
    }

    /**
     * Estructura que consumen las páginas: variables CSS ya resueltas, hoja
     * de la tipografía y el CSS libre del cliente seguido del de la página.
     *
     * Con `$forEditor` incluye además lo que necesita el preview en vivo del
     * panel para recalcular la cascada sin volver al servidor.
     */
    public function theme(bool $forEditor = false): array
    {
        $theme = [
            'vars' => $this->resolveThemeVars(includeOverrides: true),
            'font_url' => Fonts::url($this->resolvedFont()),
            'custom_css' => $this->resolvedCustomCss(),
        ];

        if (! $forEditor) {
            return $theme;
        }

        // El editor manda los campos crudos de la página; para resolverlos
        // necesita la base heredada y el catálogo de tipografías.
        return $theme + [
            'editor' => [
                'inherited' => $this->resolveThemeVars(includeOverrides: false),
                'inherited_font' => $this->themeParent()?->font_family
                    ?: ($this->themeDefaults()['--font-family'] ?? null),
                'inherited_css' => static::sanitizeCss($this->themeParent()?->custom_css),
                'fonts' => Fonts::AVAILABLE,
                'font_base_url' => Fonts::BASE_URL,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function resolveThemeVars(bool $includeOverrides): array
    {
        $defaults = $this->themeDefaults();
        $client = $this->themeParent();
        $vars = [];

        foreach ($this->themeColorColumns() as $token => $column) {
            if (! array_key_exists($token, $defaults)) {
                continue;
            }

            $vars[$token] = static::firstFilledString(
                $includeOverrides ? $this->{$column} : null,
                $client?->{$column},
                $defaults[$token],
            );
        }

        foreach ($this->themeSizeColumns() as $token => $column) {
            if (! array_key_exists($token, $defaults)) {
                continue;
            }

            $value = $includeOverrides ? $this->{$column} : null;
            $value ??= $client?->{$column};

            $vars[$token] = $value === null ? $defaults[$token] : ((int) $value).'px';
        }

        $font = $includeOverrides
            ? $this->resolvedFont()
            : ($client?->font_family ?: ($defaults['--font-family'] ?? null));

        $vars['--font'] = Fonts::stack($font);

        return $vars;
    }

    /**
     * Tipografía efectiva. Las páginas no la eligen: es del cliente.
     */
    public function resolvedFont(): ?string
    {
        $font = $this->font_family ?: $this->themeParent()?->font_family;

        return Fonts::has($font)
            ? $font
            : ($this->themeDefaults()['--font-family'] ?? null);
    }

    /**
     * CSS libre del cliente primero, para que el de la página pueda
     * corregirlo sin pelear por especificidad.
     */
    public function resolvedCustomCss(): ?string
    {
        $blocks = array_filter([
            static::sanitizeCss($this->themeParent()?->custom_css),
            static::sanitizeCss($this->custom_css),
        ]);

        return empty($blocks) ? null : implode("\n\n", $blocks);
    }

    /**
     * El CSS libre va crudo al <style> del layout, así que se le quita
     * cualquier intento de cerrar la etiqueta.
     */
    public static function sanitizeCss(?string $css): ?string
    {
        $css = trim((string) $css);

        if ($css === '') {
            return null;
        }

        return preg_replace('#</\s*style#i', '', $css);
    }

    private static function firstFilledString(?string ...$values): string
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return trim($value);
            }
        }

        return '';
    }
}
