<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\HabitScore;
use App\Models\Student;
use App\Models\SubjectAssignment;
use App\Models\Teacher;
use Database\Seeders\HabitSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class HabitsTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_docente_puede_registrar_habitos_de_su_aula(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $this->seed(HabitSeeder::class);
        $habit = $institution->habits()->orderBy('order')->first();

        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $matematica = $institution->subjects()->create(['name' => 'Matemática']);
        $classroom = $this->makeClassroom($grade, $year, 'A');
        $period = $year->periods->first();

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        $docenteUser = $this->makeStaffUser('docente', $team);
        $teacher = Teacher::create(['user_id' => $docenteUser->id, 'cedula' => '8-000-0000', 'first_name' => 'Carlos', 'last_name' => 'Mendoza', 'shift' => 'matutino']);
        SubjectAssignment::create([
            'teacher_id' => $teacher->id, 'classroom_id' => $classroom->id,
            'subject_id' => $matematica->id, 'academic_year_id' => $year->id,
        ]);

        Livewire::actingAs($docenteUser)
            ->test('pages::habits.index')
            ->set('classroomId', (string) $classroom->id)
            ->set('periodId', (string) $period->id)
            ->set("scores.{$enrollment->id}.{$habit->id}", 'S')
            ->call('saveScores')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('habit_scores', [
            'enrollment_id' => $enrollment->id, 'habit_id' => $habit->id, 'period_id' => $period->id, 'score' => 'S',
        ]);
    }

    public function test_guardar_de_nuevo_actualiza_el_habito_existente_sin_duplicar(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $this->seed(HabitSeeder::class);
        $habit = $institution->habits()->orderBy('order')->first();

        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $matematica = $institution->subjects()->create(['name' => 'Matemática']);
        $classroom = $this->makeClassroom($grade, $year, 'A');
        $period = $year->periods->first();

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        $docenteUser = $this->makeStaffUser('docente', $team);
        $teacher = Teacher::create(['user_id' => $docenteUser->id, 'cedula' => '8-000-0000', 'first_name' => 'Carlos', 'last_name' => 'Mendoza', 'shift' => 'matutino']);
        SubjectAssignment::create([
            'teacher_id' => $teacher->id, 'classroom_id' => $classroom->id,
            'subject_id' => $matematica->id, 'academic_year_id' => $year->id,
        ]);

        Livewire::actingAs($docenteUser)
            ->test('pages::habits.index')
            ->set('classroomId', (string) $classroom->id)
            ->set('periodId', (string) $period->id)
            ->set("scores.{$enrollment->id}.{$habit->id}", 'R')
            ->call('saveScores')
            ->assertHasNoErrors();

        Livewire::actingAs($docenteUser)
            ->test('pages::habits.index')
            ->set('classroomId', (string) $classroom->id)
            ->set('periodId', (string) $period->id)
            ->set("scores.{$enrollment->id}.{$habit->id}", 'X')
            ->call('saveScores')
            ->assertHasNoErrors();

        $this->assertSame(1, HabitScore::where('enrollment_id', $enrollment->id)->where('habit_id', $habit->id)->count());
        $this->assertDatabaseHas('habit_scores', [
            'enrollment_id' => $enrollment->id, 'habit_id' => $habit->id, 'period_id' => $period->id, 'score' => 'X',
        ]);
    }

    public function test_docente_no_puede_registrar_habitos_de_un_aula_que_no_tiene_asignada(): void
    {
        $this->seed(RoleSeeder::class);

        $institution = $this->makeInstitution();
        $this->seed(HabitSeeder::class);

        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year, 'A');
        $period = $year->periods->first();

        $team = $this->makeTeam();
        $docenteUser = $this->makeStaffUser('docente', $team);
        Teacher::create(['user_id' => $docenteUser->id, 'cedula' => '8-000-0000', 'first_name' => 'Carlos', 'last_name' => 'Mendoza', 'shift' => 'matutino']);

        Livewire::actingAs($docenteUser)
            ->test('pages::habits.index')
            ->set('classroomId', (string) $classroom->id)
            ->set('periodId', (string) $period->id)
            ->call('saveScores')
            ->assertForbidden();
    }

    public function test_secretaria_solo_puede_ver_habitos_no_registrarlos(): void
    {
        $this->seed(RoleSeeder::class);

        $institution = $this->makeInstitution();
        $this->seed(HabitSeeder::class);

        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year, 'A');
        $period = $year->periods->first();

        $team = $this->makeTeam();
        $secretaria = $this->makeStaffUser('secretaria', $team);

        Livewire::actingAs($secretaria)
            ->test('pages::habits.index')
            ->set('classroomId', (string) $classroom->id)
            ->set('periodId', (string) $period->id)
            ->call('saveScores')
            ->assertForbidden();
    }
}
