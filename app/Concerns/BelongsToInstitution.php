<?php

namespace App\Concerns;

use App\Models\Institution;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aísla un modelo por institución: filtra automáticamente cualquier consulta
 * por la institución actual (vía TenantContext) y le pone institution_id a
 * cualquier registro nuevo que no lo traiga ya.
 *
 * Si TenantContext no tiene ninguna institución fijada (seeders, consola,
 * comandos sin contexto), el scope simplemente no filtra nada — ver el
 * comentario de TenantContext::forget() sobre por qué eso es un riesgo a
 * tener en cuenta en trabajo futuro en cola/consola.
 */
trait BelongsToInstitution
{
    protected static function bootBelongsToInstitution(): void
    {
        static::addGlobalScope('institution', function (Builder $builder) {
            $institution = app(TenantContext::class)->get();

            if ($institution) {
                $builder->where($builder->getModel()->getTable().'.institution_id', $institution->id);
            }
        });

        static::creating(function ($model) {
            if (empty($model->institution_id)) {
                $model->institution_id = app(TenantContext::class)->get()?->id;
            }
        });
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }
}
