<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\Institution;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::first();

        // Las materias generales las da automáticamente el maestro de grado al
        // asignarle su aula. Las especializadas requieren un docente específico
        // por materia (pueden dar clases en varias aulas y niveles).
        $subjects = [
            'Español' => false,
            'Matemática' => false,
            'Ciencias Naturales' => false,
            'Ciencias Sociales' => false,
            'Religión, Moral y Valores' => false,
            'Inglés' => true,
            'Salud y Educación Física' => true,
            'Expresión Artística' => true,
            'Tecnologías' => true,
        ];

        foreach ($subjects as $name => $isSpecialized) {
            $institution->subjects()->create(['name' => $name, 'is_specialized' => $isSpecialized]);
        }

        // Asignar todas las materias a los grados de Básica General (1°-6°)
        $basicaGrades = Grade::whereHas('educationLevel', fn ($q) => $q->where('name', 'Básica General'))->get();
        $allSubjects = $institution->subjects;

        foreach ($basicaGrades as $grade) {
            $grade->subjects()->sync($allSubjects->pluck('id'));
        }
    }
}
