<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    /**
     * El Global Scope de BelongsToInstitution ya protege el route-model-binding
     * de {student} (un acceso cruzado da 404 en vez de encontrar el registro),
     * pero se agrega esta policy como defensa en profundidad y para que la
     * regla quede explícita en vez de ser un efecto secundario del scope.
     */
    public function view(User $user, Student $student): bool
    {
        return $user->currentInstitution()?->id === $student->institution_id;
    }
}
