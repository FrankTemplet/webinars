<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limita los registros a los clientes asignados al usuario autenticado.
 *
 * Solo aplica a usuarios con rol viewer que tengan al menos un cliente
 * asignado: un viewer sin clientes asignados sigue viendo todo.
 */
class ClientAccessScope implements Scope
{
    /**
     * @param  string  $column  Columna client_id del modelo, o null si hay que
     *                          llegar al cliente a través de una relación.
     */
    public function __construct(
        protected ?string $column = 'client_id',
        protected ?string $relation = null,
    ) {}

    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user || ! $user->isViewer()) {
            return;
        }

        $clientIds = $user->allowedClientIds();

        if (empty($clientIds)) {
            return;
        }

        if ($this->relation) {
            $builder->whereHas($this->relation, fn (Builder $q) => $q->whereIn($q->qualifyColumn('client_id'), $clientIds));

            return;
        }

        $builder->whereIn($model->qualifyColumn($this->column), $clientIds);
    }
}
