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

        // Áreas propias de Preescolar (Pre-Kinder y Kinder) — no son las mismas
        // que las de Básica General, son áreas de desarrollo, no asignaturas.
        $preescolarSubjects = [
            'Desarrollo Personal y Social' => false,
            'Comunicación y Lenguaje' => false,
            'Pensamiento Lógico-Matemático' => false,
            'Exploración del Entorno' => false,
        ];

        foreach ([...$subjects, ...$preescolarSubjects] as $name => $isSpecialized) {
            $institution->subjects()->create(['name' => $name, 'is_specialized' => $isSpecialized]);
        }

        $allSubjects = $institution->subjects;

        // Básica General (1°-6°): las 9 materias académicas.
        $basicaGrades = Grade::whereHas('educationLevel', fn ($q) => $q->where('name', 'Básica General'))->get();
        $basicaSubjectIds = $allSubjects->whereIn('name', array_keys($subjects))->pluck('id');

        foreach ($basicaGrades as $grade) {
            $grade->subjects()->sync($basicaSubjectIds);
        }

        // Preescolar (Pre-Kinder, Kinder): sus propias áreas + las especializadas
        // que también aplican a esta edad (Inglés, Ed. Física, Expresión Artística).
        // Tecnologías queda fuera — no aplica a esta edad.
        $preescolarGrades = Grade::whereHas('educationLevel', fn ($q) => $q->where('name', 'Pre-Escolar'))->get();
        $preescolarSubjectIds = $allSubjects
            ->whereIn('name', [...array_keys($preescolarSubjects), 'Inglés', 'Salud y Educación Física', 'Expresión Artística'])
            ->pluck('id');

        foreach ($preescolarGrades as $grade) {
            $grade->subjects()->sync($preescolarSubjectIds);
        }
    }
}
