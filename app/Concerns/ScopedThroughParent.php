<?php

namespace App\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aísla por institución a los modelos que no tienen institution_id propio
 * pero sí cuelgan de un padre que lo tiene (Classroom y Enrollment → año
 * escolar, Grade → nivel educativo): solo deja pasar filas cuyo padre
 * pertenezca a la institución actual. El padre usa BelongsToInstitution,
 * así que el whereHas hereda su scope.
 *
 * Sin esto, Classroom::find($id) o Enrollment::findOrFail($id) con un ID
 * manipulado desde Livewire devuelve registros de otra escuela.
 *
 * El modelo debe declarar: protected static string $tenantParent = 'relación';
 */
trait ScopedThroughParent
{
    protected static function bootScopedThroughParent(): void
    {
        static::addGlobalScope('institution', function (Builder $builder) {
            if (app(TenantContext::class)->get()) {
                $builder->whereHas(static::$tenantParent);
            }
        });
    }
}
