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

        $subjects = [
            'Español', 'Matemática', 'Ciencias Naturales', 'Ciencias Sociales',
            'Inglés', 'Religión, Moral y Valores', 'Expresión Artística',
            'Salud y Educación Física', 'Tecnologías',
        ];

        foreach ($subjects as $name) {
            $institution->subjects()->create(['name' => $name]);
        }

        // Asignar todas las materias a los grados de Básica General (1°-6°)
        $basicaGrades = Grade::whereHas('educationLevel', fn ($q) => $q->where('name', 'Básica General'))->get();
        $allSubjects  = $institution->subjects;

        foreach ($basicaGrades as $grade) {
            $grade->subjects()->sync($allSubjects->pluck('id'));
        }
    }
}
