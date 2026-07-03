<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\SubjectAssignment;
use App\Models\Teacher;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class DashboardContentTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_admin_ve_estadisticas_generales(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year);

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        Livewire::actingAs($admin)
            ->test('pages::dashboard')
            ->assertSee('Estudiantes')
            ->assertSee('Matrículas activas')
            ->assertSee('Ana Pérez')
            ->assertSee($grade->name.'-'.$classroom->section);
    }

    public function test_docente_ve_sus_aulas_asignadas_y_no_las_estadisticas_generales(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);
        $docenteUser = $this->makeStaffUser('docente', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $matematica = $institution->subjects()->create(['name' => 'Matemática']);
        $grade->subjects()->attach($matematica->id);
        $classroom = $this->makeClassroom($grade, $year);

        $teacher = Teacher::create(['user_id' => $docenteUser->id, 'cedula' => '8-000-0000', 'first_name' => 'Carlos', 'last_name' => 'Mendoza']);
        SubjectAssignment::create([
            'teacher_id' => $teacher->id, 'classroom_id' => $classroom->id,
            'subject_id' => $matematica->id, 'academic_year_id' => $year->id,
        ]);

        Livewire::actingAs($docenteUser)
            ->test('pages::dashboard')
            ->assertSee('Tus aulas asignadas')
            ->assertSee('Matemática')
            ->assertDontSee('Matrículas activas');
    }

    public function test_acudiente_ve_a_su_hijo_y_no_las_estadisticas_generales(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);
        $acudienteUser = $this->makeStaffUser('acudiente', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year);

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        $guardian = Guardian::create(['first_name' => 'Marta', 'last_name' => 'Pérez', 'relationship' => 'madre', 'primary_phone' => '6000-0000', 'user_id' => $acudienteUser->id]);
        $student->guardians()->attach($guardian->id, ['is_primary' => true]);

        Livewire::actingAs($acudienteUser)
            ->test('pages::dashboard')
            ->assertSee('Ana Pérez')
            ->assertSee($grade->name.'-'.$classroom->section)
            ->assertDontSee('Matrículas activas');
    }
}
