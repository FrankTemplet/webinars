<?php

namespace App\Support;

use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resuelve qué correos son "internos" (nuestra gente y la del cliente) para
 * poder reportar audiencia externa sin mezclar organizadores y ponentes.
 *
 * Vive en un solo lugar a propósito: el dashboard tenía la lista escrita a mano
 * con 'liberynet' mal escrito y llevaba meses sin excluir nada.
 */
class InternalEmailDomains
{
    /**
     * Dominios internos aplicables: los nuestros más los que declare el cliente.
     *
     * @return array<int, string>
     */
    public static function for(?Client $client = null): array
    {
        $ours = (array) config('webinars.internal_domains', []);
        $theirs = (array) ($client?->internal_email_domains ?? []);

        $all = array_map(
            fn ($domain) => strtolower(trim((string) $domain)),
            array_merge($ours, $theirs)
        );

        return array_values(array_unique(array_filter($all)));
    }

    /**
     * Limita la consulta a correos externos.
     *
     * @param  string  $emailExpr  Expresión SQL con el correo (columna o JSON).
     * @param  array<int, string>  $domains
     */
    public static function scopeExternal(Builder $query, string $emailExpr, array $domains): Builder
    {
        foreach ($domains as $domain) {
            $query->whereRaw("LOWER({$emailExpr}) NOT LIKE ?", ['%@%'.$domain.'%']);
        }

        return $query;
    }

    /**
     * Limita la consulta a correos internos.
     *
     * @param  string  $emailExpr  Expresión SQL con el correo (columna o JSON).
     * @param  array<int, string>  $domains
     */
    public static function scopeInternal(Builder $query, string $emailExpr, array $domains): Builder
    {
        if (empty($domains)) {
            // Sin dominios configurados nada es interno.
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $q) use ($emailExpr, $domains) {
            foreach ($domains as $domain) {
                $q->orWhereRaw("LOWER({$emailExpr}) LIKE ?", ['%@%'.$domain.'%']);
            }
        });
    }

    /**
     * @param  array<int, string>  $domains
     */
    public static function isInternal(?string $email, array $domains): bool
    {
        if (! $email || ! str_contains($email, '@')) {
            return false;
        }

        $afterAt = strtolower(substr($email, strpos($email, '@') + 1));

        foreach ($domains as $domain) {
            if (str_contains($afterAt, $domain)) {
                return true;
            }
        }

        return false;
    }
}
