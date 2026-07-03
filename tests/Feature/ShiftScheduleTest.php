<?php

namespace Tests\Feature;

use App\Enums\Shift;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class ShiftScheduleTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_el_enum_shift_devuelve_el_horario_correcto(): void
    {
        $this->assertSame('7:00 a.m. – 12:00 p.m.', Shift::Matutino->timeRange());
        $this->assertSame('12:15 p.m. – 5:00 p.m.', Shift::Vespertino->timeRange());
        $this->assertSame('Matutino (7:00 a.m. – 12:00 p.m.)', Shift::Matutino->labelWithTime());
        $this->assertCount(2, Shift::cases());
    }

    public function test_no_se_puede_crear_un_aula_con_turno_nocturno(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $this->makeActiveYear($institution);

        Livewire::actingAs($admin)
            ->test('pages::academic.index')
            ->set('gradeId', (string) $grade->id)
            ->set('section', 'A')
            ->set('shift', 'nocturno')
            ->set('capacity', 30)
            ->call('addClassroom')
            ->assertHasErrors('shift');
    }

    public function test_el_horario_se_muestra_en_la_ficha_del_estudiante_y_el_portal(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);
        $acudienteUser = $this->makeStaffUser('acudiente', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year, 'A');
        $classroom->update(['shift' => 'vespertino']);

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        $guardian = Guardian::create(['first_name' => 'Marta', 'last_name' => 'Pérez', 'relationship' => 'madre', 'primary_phone' => '6000-0000', 'user_id' => $acudienteUser->id]);
        $student->guardians()->attach($guardian->id, ['is_primary' => true]);

        Livewire::actingAs($admin)
            ->test('pages::students.show', ['student' => $student])
            ->assertSee('12:15 p.m. – 5:00 p.m.', false);

        Livewire::actingAs($acudienteUser)
            ->test('pages::portal.index')
            ->assertSee('12:15 p.m. – 5:00 p.m.', false);
    }
}
