<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\HabitScore;
use App\Models\Student;
use Database\Seeders\HabitScoreBackfillSeeder;
use Database\Seeders\HabitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class HabitScoreBackfillSeederTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_rellena_un_habito_por_cada_trimestre_ya_iniciado(): void
    {
        $this->travelTo('2026-06-15');

        $this->seed(RoleSeeder::class);
        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $this->seed(HabitSeeder::class);
        $habitCount = $institution->habits()->count();

        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year);

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        $this->seed(HabitScoreBackfillSeeder::class);

        // A esta fecha solo el I Trimestre (2026-02-01 a 2026-05-01) ya terminó
        // y el II Trimestre (2026-05-02 a 2026-08-01) ya empezó — el III Trimestre
        // (2026-08-02 en adelante) todavía no, así que quedan 2 trimestres x N hábitos.
        $this->assertSame($habitCount * 2, HabitScore::where('enrollment_id', $enrollment->id)->count());
        $this->assertTrue(HabitScore::where('enrollment_id', $enrollment->id)->get()->every(
            fn ($score) => in_array($score->score, ['S', 'R', 'X'], true)
        ));

        // Correrlo dos veces no debe duplicar ni sobrescribir nada.
        $before = HabitScore::where('enrollment_id', $enrollment->id)->get()->keyBy(fn ($s) => "{$s->habit_id}-{$s->period_id}");
        $this->seed(HabitScoreBackfillSeeder::class);
        $after = HabitScore::where('enrollment_id', $enrollment->id)->get()->keyBy(fn ($s) => "{$s->habit_id}-{$s->period_id}");

        $this->assertSame($before->count(), $after->count());
        $this->assertTrue($before->keys()->diff($after->keys())->isEmpty());
    }

    public function test_no_genera_habitos_para_una_matricula_sin_trimestres_ya_iniciados(): void
    {
        $this->travelTo('2026-01-15');

        $this->seed(RoleSeeder::class);
        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $this->seed(HabitSeeder::class);

        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year);

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        $this->seed(HabitScoreBackfillSeeder::class);

        $this->assertSame(0, HabitScore::where('enrollment_id', $enrollment->id)->count());
    }
}
