<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    /**
     * Enrollment no tiene institution_id propio (llega vía academic_year_id,
     * obligatorio), así que se verifica a través de esa cadena. Se ignora el
     * scope de AcademicYear a propósito: si no, un año de OTRA institución
     * jamás se podría cargar para compararlo, y la policy siempre negaría.
     */
    public function view(User $user, Enrollment $enrollment): bool
    {
        $institution = $user->currentInstitution();

        if ($institution === null) {
            return false;
        }

        $academicYearInstitutionId = $enrollment->academicYear()
            ->withoutGlobalScopes()
            ->value('institution_id');

        return $academicYearInstitutionId === $institution->id;
    }
}
