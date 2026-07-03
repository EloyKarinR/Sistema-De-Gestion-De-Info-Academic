<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

class SubjectAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $year = AcademicYear::where('is_active', true)->first();
        $ingles = Subject::where('name', 'Inglés')->first();

        $barbara = Teacher::where('last_name', 'Wilson')->first();
        $karla = Teacher::where('last_name', 'Sánchez')->first();

        $classroom4D = Classroom::where('section', 'D')
            ->whereHas('grade', fn ($q) => $q->where('number', 4))
            ->first();

        // Barbara Wilson: maestra de grado — da casi todas las materias, pero solo en su aula (4°-D)
        $maestraDeGrado = Subject::whereIn('name', [
            'Español', 'Matemática', 'Ciencias Sociales', 'Ciencias Naturales', 'Religión, Moral y Valores',
        ])->get();

        foreach ($maestraDeGrado as $subject) {
            SubjectAssignment::create([
                'teacher_id' => $barbara->id,
                'classroom_id' => $classroom4D->id,
                'subject_id' => $subject->id,
                'academic_year_id' => $year->id,
            ]);
        }

        // Karla Sánchez: Inglés en todas las aulas del año (docente itinerante)
        foreach (Classroom::where('academic_year_id', $year->id)->get() as $classroom) {
            SubjectAssignment::create([
                'teacher_id' => $karla->id,
                'classroom_id' => $classroom->id,
                'subject_id' => $ingles->id,
                'academic_year_id' => $year->id,
            ]);
        }
    }
}
