<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Student;
use App\Models\SubjectAssignment;
use App\Models\Teacher;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class StudentsIndexTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_estudiantes_se_ordenan_por_grado_por_defecto(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('secretaria', $team);

        $institution = $this->makeInstitution();
        $year = $this->makeActiveYear($institution);

        $grade6 = $this->makeGrade($institution, '6°', 6);
        $grade1 = $this->makeGrade($institution, '1°', 1);

        $classroom6 = $this->makeClassroom($grade6, $year, 'A');
        $classroom1 = $this->makeClassroom($grade1, $year, 'A');

        $studentIn6 = Student::create(['first_name' => 'Zoe', 'last_name' => 'Zambrano', 'birth_date' => '2014-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $studentIn1 = Student::create(['first_name' => 'Ana', 'last_name' => 'Aguilar', 'birth_date' => '2019-01-01', 'sex' => 'F', 'address' => 'Calle 2']);

        Enrollment::create(['student_id' => $studentIn6->id, 'classroom_id' => $classroom6->id, 'academic_year_id' => $year->id, 'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso']);
        Enrollment::create(['student_id' => $studentIn1->id, 'classroom_id' => $classroom1->id, 'academic_year_id' => $year->id, 'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso']);

        $html = Livewire::actingAs($admin)->test('pages::students.index')->html();

        // Aunque "Zambrano" iría antes que "Aguilar" alfabéticamente, el grado (1° antes que 6°) manda primero.
        $this->assertTrue(
            strpos($html, 'Ana Aguilar') < strpos($html, 'Zoe Zambrano'),
            'Se esperaba que el estudiante de 1° apareciera antes que el de 6°, aunque alfabéticamente sea al revés.'
        );
    }

    public function test_filtro_por_aula_solo_muestra_esos_estudiantes(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $secretaria = $this->makeStaffUser('secretaria', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);

        $classroomA = $this->makeClassroom($grade, $year, 'A');
        $classroomB = $this->makeClassroom($grade, $year, 'B');

        $studentA = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $studentB = Student::create(['first_name' => 'Beto', 'last_name' => 'Gómez', 'birth_date' => '2018-01-01', 'sex' => 'M', 'address' => 'Calle 2']);

        Enrollment::create(['student_id' => $studentA->id, 'classroom_id' => $classroomA->id, 'academic_year_id' => $year->id, 'registered_by' => $secretaria->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso']);
        Enrollment::create(['student_id' => $studentB->id, 'classroom_id' => $classroomB->id, 'academic_year_id' => $year->id, 'registered_by' => $secretaria->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso']);

        Livewire::actingAs($secretaria)
            ->test('pages::students.index')
            ->set('classroomId', (string) $classroomA->id)
            ->assertSee('Ana Pérez')
            ->assertDontSee('Beto Gómez');
    }

    public function test_un_docente_solo_ve_estudiantes_de_sus_aulas_asignadas(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $matematica = $institution->subjects()->create(['name' => 'Matemática']);

        $classroomA = $this->makeClassroom($grade, $year, 'A');
        $classroomB = $this->makeClassroom($grade, $year, 'B');

        $studentA = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $studentB = Student::create(['first_name' => 'Beto', 'last_name' => 'Gómez', 'birth_date' => '2018-01-01', 'sex' => 'M', 'address' => 'Calle 2']);

        Enrollment::create(['student_id' => $studentA->id, 'classroom_id' => $classroomA->id, 'academic_year_id' => $year->id, 'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso']);
        Enrollment::create(['student_id' => $studentB->id, 'classroom_id' => $classroomB->id, 'academic_year_id' => $year->id, 'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso']);

        $docenteUser = $this->makeStaffUser('docente', $team);
        $teacher = Teacher::create(['user_id' => $docenteUser->id, 'cedula' => '8-000-0000', 'first_name' => 'Karla', 'last_name' => 'Sánchez', 'shift' => 'matutino']);
        SubjectAssignment::create([
            'teacher_id' => $teacher->id, 'classroom_id' => $classroomA->id,
            'subject_id' => $matematica->id, 'academic_year_id' => $year->id,
        ]);

        Livewire::actingAs($docenteUser)
            ->test('pages::students.index')
            ->assertSee('Ana Pérez')
            ->assertDontSee('Beto Gómez');

        // El secretaria sí ve a ambos, sin restricción.
        $secretaria = $this->makeStaffUser('secretaria', $team);
        Livewire::actingAs($secretaria)
            ->test('pages::students.index')
            ->assertSee('Ana Pérez')
            ->assertSee('Beto Gómez');
    }
}
