<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Student;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_flujo_completo_de_matricula_con_estudiante_y_acudiente_nuevos(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $secretaria = $this->makeStaffUser('secretaria', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year);

        Livewire::actingAs($secretaria)
            ->test('pages::enrollments.create')
            // Paso 1: estudiante nuevo
            ->set('cedulaSearch', '8-999-1234')
            ->call('searchStudent')
            ->assertSet('studentMode', 'create')
            ->set('firstName', 'Ana')
            ->set('lastName', 'Pérez')
            ->set('birthDate', '2018-01-01')
            ->set('sex', 'F')
            ->set('address', 'Calle 2')
            ->call('createStudent')
            ->assertSet('step', 2)
            // Paso 2: acudiente nuevo
            ->set('guardianFirstName', 'Marta')
            ->set('guardianLastName', 'Pérez')
            ->set('relationship', 'madre')
            ->set('primaryPhone', '6000-0000')
            ->call('createGuardian')
            ->assertSet('step', 3)
            // Paso 3: matrícula
            ->set('classroomId', (string) $classroom->id)
            ->set('enrollmentType', 'nuevo_ingreso')
            ->call('saveEnrollment')
            ->assertSet('step', 4)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('students', ['cedula' => '8-999-1234', 'first_name' => 'Ana']);
        $this->assertDatabaseHas('guardians', ['first_name' => 'Marta', 'last_name' => 'Pérez']);

        $enrollment = Enrollment::first();
        $this->assertSame('activo', $enrollment->status);
        $this->assertSame($classroom->id, $enrollment->classroom_id);
        $this->assertNotNull($enrollment->receipt_number);

        // El estudiante quedó vinculado a la acudiente como principal
        $this->assertTrue(
            $enrollment->student->guardians()->wherePivot('is_primary', true)->exists()
        );
    }

    public function test_no_permite_matricular_dos_veces_al_mismo_estudiante_en_el_mismo_anio(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $secretaria = $this->makeStaffUser('secretaria', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year);

        $student = Student::create([
            'cedula' => '8-999-1234', 'first_name' => 'Ana', 'last_name' => 'Pérez',
            'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2',
        ]);

        Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $secretaria->id, 'enrollment_date' => '2026-02-01',
            'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        Livewire::actingAs($secretaria)
            ->test('pages::enrollments.create')
            ->set('cedulaSearch', '8-999-1234')
            ->call('searchStudent')
            ->assertHasErrors('cedulaSearch');

        $this->assertSame(1, Enrollment::where('student_id', $student->id)->count());
    }

    public function test_no_permite_matricular_a_un_estudiante_en_un_aula_fuera_de_su_rango_de_edad(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $secretaria = $this->makeStaffUser('secretaria', $team);

        $institution = $this->makeInstitution();
        $year = $this->makeActiveYear($institution);

        $grade = $this->makeGrade($institution, 'Pre-Kinder', 0);
        $grade->update(['min_age' => 4, 'max_age' => 5]);
        $classroom = $this->makeClassroom($grade, $year);

        // Estudiante de 10 años, muy grande para Pre-Kinder (4-5 años).
        $student = Student::create([
            'cedula' => '8-999-1234', 'first_name' => 'Ana', 'last_name' => 'Pérez',
            'birth_date' => now()->subYears(10)->format('Y-m-d'), 'sex' => 'F', 'address' => 'Calle 2',
        ]);

        Livewire::actingAs($secretaria)
            ->test('pages::enrollments.create')
            ->set('studentId', $student->id)
            ->set('studentMode', 'found')
            ->call('confirmFoundStudent')
            ->set('guardianFirstName', 'Marta')
            ->set('guardianLastName', 'Pérez')
            ->set('relationship', 'madre')
            ->set('primaryPhone', '6000-0000')
            ->call('createGuardian')
            ->assertSet('step', 3)
            // El aula de Pre-Kinder aparece marcada como que requiere otra edad.
            ->assertSee('requiere 4-5 años')
            // Y si de todas formas se fuerza la selección, el backend la rechaza.
            ->set('classroomId', (string) $classroom->id)
            ->call('saveEnrollment')
            ->assertHasErrors('classroomId');

        $this->assertSame(0, Enrollment::where('student_id', $student->id)->count());
    }
}
