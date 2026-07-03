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

        $barbara = Teacher::where('last_name', 'Wilson')->first();

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

        // Docentes especialistas itinerantes: dan su materia en todas las aulas de
        // su turno cuyo grado la tenga en el plan de estudios (así Tecnologías, por
        // ejemplo, no se le asigna a preescolar aunque comparta turno).
        $this->assignItinerantSpecialist('Karla', 'Sánchez', 'Inglés', 'matutino', $year);
        $this->assignItinerantSpecialist('Diana', 'Fuentes', 'Inglés', 'vespertino', $year);
        $this->assignItinerantSpecialist('Rogelio', 'Batista', 'Salud y Educación Física', 'matutino', $year);
        $this->assignItinerantSpecialist('Yolanda', 'Prado', 'Salud y Educación Física', 'vespertino', $year);
        $this->assignItinerantSpecialist('Marisol', 'Chen', 'Expresión Artística', 'matutino', $year);
        $this->assignItinerantSpecialist('Andrés', 'Quirós', 'Expresión Artística', 'vespertino', $year);
        $this->assignItinerantSpecialist('Iván', 'Cedeño', 'Tecnologías', 'matutino', $year);
        $this->assignItinerantSpecialist('Lucía', 'Ábrego', 'Tecnologías', 'vespertino', $year);
    }

    private function assignItinerantSpecialist(string $teacherFirstName, string $teacherLastName, string $subjectName, string $shift, AcademicYear $year): void
    {
        $teacher = Teacher::where('first_name', $teacherFirstName)->where('last_name', $teacherLastName)->first();
        $subject = Subject::where('name', $subjectName)->first();

        $classrooms = Classroom::where('academic_year_id', $year->id)
            ->where('shift', $shift)
            ->whereHas('grade.subjects', fn ($q) => $q->where('subjects.id', $subject->id))
            ->get();

        foreach ($classrooms as $classroom) {
            SubjectAssignment::create([
                'teacher_id' => $teacher->id,
                'classroom_id' => $classroom->id,
                'subject_id' => $subject->id,
                'academic_year_id' => $year->id,
            ]);
        }
    }
}
