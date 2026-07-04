<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Student;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class EnrollmentStatusTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_secretaria_puede_retirar_a_un_estudiante(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $secretaria = $this->makeStaffUser('secretaria', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year);

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $secretaria->id, 'enrollment_date' => '2026-02-01',
            'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        Livewire::actingAs($secretaria)
            ->test('pages::enrollments.index')
            ->call('openStatusModal', $enrollment->id, 'retirado')
            ->set('statusDate', '2026-05-15')
            ->set('statusReason', 'La familia se muda de ciudad')
            ->call('updateStatus')
            ->assertHasNoErrors();

        $enrollment->refresh();
        $this->assertSame('retirado', $enrollment->status);
        $this->assertSame('2026-05-15', $enrollment->status_date->format('Y-m-d'));
        $this->assertSame('La familia se muda de ciudad', $enrollment->status_reason);
    }

    public function test_un_estudiante_retirado_puede_ser_rehabilitado_el_mismo_anio(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $secretaria = $this->makeStaffUser('secretaria', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroomA = $this->makeClassroom($grade, $year, 'A');
        $classroomB = $this->makeClassroom($grade, $year, 'B');

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroomA->id, 'academic_year_id' => $year->id,
            'registered_by' => $secretaria->id, 'enrollment_date' => '2026-02-01',
            'status' => 'retirado', 'status_date' => '2026-03-01', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        // Como ya no tiene matrícula ACTIVA este año, la búsqueda debe dejarlo rehabilitar.
        Livewire::actingAs($secretaria)
            ->test('pages::enrollments.create')
            ->set('cedulaSearch', $student->cedula ?? '')
            ->set('studentId', $student->id)
            ->set('studentMode', 'found')
            ->call('confirmFoundStudent')
            ->set('guardianFirstName', 'Marta')
            ->set('guardianLastName', 'Pérez')
            ->set('relationship', 'madre')
            ->set('primaryPhone', '6000-0000')
            ->call('createGuardian')
            ->set('classroomId', (string) $classroomB->id)
            ->set('enrollmentType', 'rehabilitacion')
            ->call('saveEnrollment')
            ->assertHasNoErrors()
            ->assertSet('step', 4);

        $this->assertSame(2, Enrollment::where('student_id', $student->id)->count());
        $this->assertSame(1, Enrollment::where('student_id', $student->id)->where('status', 'activo')->count());
    }

    public function test_no_se_puede_cambiar_el_estado_de_una_matricula_que_no_esta_activa(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $secretaria = $this->makeStaffUser('secretaria', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year);

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $secretaria->id, 'enrollment_date' => '2026-02-01',
            'status' => 'retirado', 'status_date' => '2026-03-01', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        Livewire::actingAs($secretaria)
            ->test('pages::enrollments.index')
            ->call('openStatusModal', $enrollment->id, 'trasladado')
            ->set('statusDate', '2026-05-15')
            ->call('updateStatus')
            ->assertForbidden();
    }

    public function test_docente_no_puede_cambiar_el_estado_de_una_matricula(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $docente = $this->makeStaffUser('docente', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year);

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $docente->id, 'enrollment_date' => '2026-02-01',
            'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        Livewire::actingAs($docente)
            ->test('pages::enrollments.index')
            ->call('openStatusModal', $enrollment->id, 'retirado')
            ->assertForbidden();
    }
}
