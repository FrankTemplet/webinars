<?php

namespace App\Support;

/**
 * Tipografías que puede elegir un cliente para sus páginas públicas, con la
 * familia de fonts.bunny.net que las sirve.
 */
final class Fonts
{
    public const BASE_URL = 'https://fonts.bunny.net/css?family=';

    public const AVAILABLE = [
        'Roboto' => 'roboto:400,500,700',
        'Maven Pro' => 'maven-pro:400,500,600,700,800',
        'Inter' => 'inter:400,500,600,700',
        'Montserrat' => 'montserrat:400,500,600,700',
        'Poppins' => 'poppins:400,500,600,700',
        'Lato' => 'lato:400,700',
        'Open Sans' => 'open-sans:400,600,700',
        'Work Sans' => 'work-sans:400,500,600,700',
        'Nunito Sans' => 'nunito-sans:400,600,700',
        'Source Sans 3' => 'source-sans-3:400,600,700',
        'Space Grotesk' => 'space-grotesk:400,500,700',
        'DM Sans' => 'dm-sans:400,500,700',
    ];

    /**
     * Opciones para el select del panel.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(array_keys(self::AVAILABLE), array_keys(self::AVAILABLE));
    }

    public static function has(?string $font): bool
    {
        return $font !== null && isset(self::AVAILABLE[$font]);
    }

    public static function url(?string $font): ?string
    {
        return self::has($font) ? self::BASE_URL.self::AVAILABLE[$font] : null;
    }

    /**
     * Familia elegida más los respaldos del sistema.
     */
    public static function stack(?string $font): string
    {
        $font = self::has($font) ? $font : array_key_first(self::AVAILABLE);

        return "'{$font}', sans-serif";
    }
}
