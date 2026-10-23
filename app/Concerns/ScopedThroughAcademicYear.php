<?php

namespace App\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Aísla por institución a los modelos que no tienen institution_id propio
 * pero sí academic_year_id (Classroom, Enrollment): solo deja pasar filas
 * cuyo año escolar pertenezca a la institución actual. AcademicYear ya usa
 * BelongsToInstitution, así que el whereHas hereda su scope.
 *
 * Sin esto, Classroom::find($id) o Enrollment::findOrFail($id) con un ID
 * manipulado desde Livewire devuelve registros de otra escuela.
 */
trait ScopedThroughAcademicYear
{
    protected static function bootScopedThroughAcademicYear(): void
    {
        static::addGlobalScope('institution', function (Builder $builder) {
            if (app(TenantContext::class)->get()) {
                $builder->whereHas('academicYear');
            }
        });
    }
}
