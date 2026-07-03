<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Grade;
use App\Models\Institution;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\Teacher;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SchoolStructureSeeder extends Seeder
{
    /** @var array<int, string> */
    private array $firstNames = [
        'María', 'Ana', 'Rosa', 'Carmen', 'Yariela', 'Itzel', 'Digna', 'Nereida', 'Lourdes', 'Yaritza',
        'Carlos', 'Luis', 'José', 'Ricardo', 'Manuel', 'Ariel', 'Alexis', 'Rodrigo', 'Fernando', 'Eduardo',
    ];

    /** @var array<int, string> */
    private array $lastNames = [
        'González', 'Rodríguez', 'Pérez', 'Sánchez', 'Ramírez', 'Torres', 'Flores', 'Rivera', 'Gómez', 'Díaz',
        'Vásquez', 'Castillo', 'Jiménez', 'Morales', 'Ortega', 'Delgado', 'Guerra', 'Solís', 'Herrera', 'Núñez',
    ];

    private int $nameIndex = 0;

    private int $cedulaCounter = 300;

    public function run(): void
    {
        $institution = Institution::first();
        $year = AcademicYear::where('is_active', true)->first();
        $team = Team::first();

        // Preescolar: 2 aulas matutino + 2 vespertino por grado (Pre-Kinder y Kinder).
        foreach (['Pre-Kinder', 'Kinder'] as $gradeName) {
            $grade = Grade::where('name', $gradeName)->first();

            if (! $grade) {
                continue;
            }

            $this->fillGrade($grade, $year, $team, targetMatutino: 2, targetVespertino: 2, capacity: 15, sections: ['A', 'B', 'C', 'D']);
        }

        // Básica General (1°-6°): 5 secciones por grado, apuntando a 3 matutino + 2 vespertino.
        foreach (range(1, 6) as $number) {
            $grade = Grade::where('number', $number)->first();

            if (! $grade) {
                continue;
            }

            $this->fillGrade($grade, $year, $team, targetMatutino: 3, targetVespertino: 2, capacity: 30, sections: ['A', 'B', 'C', 'D', 'E']);
        }

        // Karla Sánchez (Inglés) da clases en TODAS las aulas del año, incluidas las nuevas.
        $ingles = Subject::where('name', 'Inglés')->first();
        $karla = Teacher::where('last_name', 'Sánchez')->where('first_name', 'Karla')->first();

        if ($ingles && $karla) {
            foreach (Classroom::where('academic_year_id', $year->id)->get() as $classroom) {
                SubjectAssignment::firstOrCreate([
                    'teacher_id' => $karla->id,
                    'classroom_id' => $classroom->id,
                    'subject_id' => $ingles->id,
                    'academic_year_id' => $year->id,
                ]);
            }
        }
    }

    /**
     * Ensures a grade has classrooms reaching the target shift split, creating
     * only what's missing (existing classrooms — with real students already
     * enrolled — are left untouched) and assigning a maestra de grado to any
     * new classroom that doesn't have one yet.
     */
    private function fillGrade(Grade $grade, AcademicYear $year, Team $team, int $targetMatutino, int $targetVespertino, int $capacity, array $sections): void
    {
        $existing = Classroom::where('grade_id', $grade->id)->where('academic_year_id', $year->id)->get();
        $usedSections = $existing->pluck('section')->all();

        $matutinoCount = $existing->where('shift', 'matutino')->count();
        $vespertinoCount = $existing->where('shift', 'vespertino')->count();

        $toAdd = [];
        foreach ($sections as $section) {
            if (in_array($section, $usedSections, true)) {
                continue;
            }

            if ($matutinoCount < $targetMatutino) {
                $toAdd[] = ['section' => $section, 'shift' => 'matutino'];
                $matutinoCount++;
            } elseif ($vespertinoCount < $targetVespertino) {
                $toAdd[] = ['section' => $section, 'shift' => 'vespertino'];
                $vespertinoCount++;
            }
        }

        foreach ($toAdd as $data) {
            $classroom = Classroom::create([
                'grade_id' => $grade->id,
                'academic_year_id' => $year->id,
                'section' => $data['section'],
                'shift' => $data['shift'],
                'capacity' => $capacity,
            ]);

            $this->assignHomeroomTeacher($classroom, $grade, $year, $team);
        }

        // Aulas que ya existían pero todavía no tienen maestra de grado asignada.
        foreach ($existing as $classroom) {
            $hasHomeroomTeacher = SubjectAssignment::where('classroom_id', $classroom->id)
                ->where('academic_year_id', $year->id)
                ->whereHas('subject', fn ($q) => $q->where('is_specialized', false))
                ->exists();

            if (! $hasHomeroomTeacher) {
                $this->assignHomeroomTeacher($classroom, $grade, $year, $team);
            }
        }
    }

    private function assignHomeroomTeacher(Classroom $classroom, Grade $grade, AcademicYear $year, Team $team): void
    {
        $firstName = $this->firstNames[$this->nameIndex % count($this->firstNames)];
        $lastName = $this->lastNames[$this->nameIndex % count($this->lastNames)];
        $this->nameIndex++;

        $slug = Str::slug("{$firstName} {$lastName}", '.');
        $email = "{$slug}@siga.pa";

        // Evita colisión de correo si el mismo nombre ya salió antes.
        $suffix = 1;
        while (User::where('email', $email)->exists()) {
            $email = "{$slug}{$suffix}@siga.pa";
            $suffix++;
        }

        $this->cedulaCounter++;
        $cedula = '8-'.$this->cedulaCounter.'-'.str_pad((string) ($this->cedulaCounter * 3 % 9999), 4, '0', STR_PAD_LEFT);

        $user = User::create([
            'name' => "{$firstName} {$lastName}",
            'email' => $email,
            'password' => Hash::make('password'),
            'current_team_id' => $team->id,
        ]);
        $user->assignRole('docente');
        $team->members()->attach($user->id, ['role' => TeamRole::Member->value]);

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'cedula' => $cedula,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'specialization' => 'Maestra de grado',
        ]);

        $generalSubjectIds = $grade->subjects()->where('is_specialized', false)->pluck('subjects.id');

        foreach ($generalSubjectIds as $subjectId) {
            SubjectAssignment::create([
                'teacher_id' => $teacher->id,
                'classroom_id' => $classroom->id,
                'subject_id' => $subjectId,
                'academic_year_id' => $year->id,
            ]);
        }
    }
}
