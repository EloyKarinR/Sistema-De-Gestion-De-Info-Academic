<?php

namespace App\Actions\Students;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Borra un estudiante que nunca llegó a matricularse, junto con cualquier
 * acudiente que haya quedado sin ningún otro estudiante vinculado como
 * consecuencia (por ejemplo, si el proceso de matrícula se abandonó justo
 * después de registrar al acudiente). Un acudiente que todavía tiene otros
 * hijos vinculados nunca se toca.
 */
class DeleteOrphanedStudent
{
    public function handle(Student $student): void
    {
        DB::transaction(function () use ($student) {
            $guardians = $student->guardians;

            $student->delete();

            foreach ($guardians as $guardian) {
                if ($guardian->students()->count() === 0) {
                    $guardian->user?->delete();
                    $guardian->delete();
                }
            }
        });
    }
}
