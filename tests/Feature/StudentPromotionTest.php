<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Student;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class StudentPromotionTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_admin_puede_promover_estudiantes_al_siguiente_grado(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade3 = $this->makeGrade($institution, '3°', 3);
        $grade4 = $this->makeGrade($institution, '4°', 4);

        $yearOld = $this->makeActiveYear($institution, 2025);
        $yearNew = $this->makeActiveYear($institution, 2026);

        $classroomOld = $this->makeClassroom($grade3, $yearOld, 'A');
        $classroomNew = $this->makeClassroom($grade4, $yearNew, 'A');

        $student = Student::create([
            'first_name' => 'Ana', 'last_name' => 'Pérez',
            'birth_date' => now()->subYears(9)->format('Y-m-d'), 'sex' => 'F', 'address' => 'Calle 2',
        ]);
        Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroomOld->id, 'academic_year_id' => $yearOld->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2025-02-01',
            'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        Livewire::actingAs($admin)
            ->test('pages::academic.promote')
            ->set('sourceYearId', $yearOld->id)
            ->set('targetYearId', $yearNew->id)
            ->assertSee('Ana Pérez')
            ->call('confirmPromotion')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'academic_year_id' => $yearNew->id,
            'classroom_id' => $classroomNew->id,
            'status' => 'activo',
            'enrollment_type' => 'promovido',
        ]);

        // La matrícula del año anterior no se toca.
        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'academic_year_id' => $yearOld->id,
            'status' => 'activo',
        ]);
    }

    public function test_repetir_la_promocion_no_duplica_matriculas(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade3 = $this->makeGrade($institution, '3°', 3);
        $grade4 = $this->makeGrade($institution, '4°', 4);

        $yearOld = $this->makeActiveYear($institution, 2025);
        $yearNew = $this->makeActiveYear($institution, 2026);

        $classroomOld = $this->makeClassroom($grade3, $yearOld, 'A');
        $this->makeClassroom($grade4, $yearNew, 'A');

        $student = Student::create([
            'first_name' => 'Ana', 'last_name' => 'Pérez',
            'birth_date' => now()->subYears(9)->format('Y-m-d'), 'sex' => 'F', 'address' => 'Calle 2',
        ]);
        Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroomOld->id, 'academic_year_id' => $yearOld->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2025-02-01',
            'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        $component = Livewire::actingAs($admin)
            ->test('pages::academic.promote')
            ->set('sourceYearId', $yearOld->id)
            ->set('targetYearId', $yearNew->id)
            ->call('confirmPromotion');

        // Segunda vez: ya no debería aparecer como "lista para promover".
        $component->assertSee('Ya promovidos');

        $this->assertSame(
            1,
            Enrollment::where('student_id', $student->id)->where('academic_year_id', $yearNew->id)->count()
        );
    }

    public function test_estudiante_del_ultimo_grado_egresa_en_vez_de_promoverse(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade12 = $this->makeGrade($institution, '12°', 12);

        $yearOld = $this->makeActiveYear($institution, 2025);
        $yearNew = $this->makeActiveYear($institution, 2026);

        $classroomOld = $this->makeClassroom($grade12, $yearOld, 'A');

        $student = Student::create([
            'first_name' => 'Carlos', 'last_name' => 'Ruiz',
            'birth_date' => now()->subYears(17)->format('Y-m-d'), 'sex' => 'M', 'address' => 'Calle 3',
        ]);
        Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroomOld->id, 'academic_year_id' => $yearOld->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2025-02-01',
            'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        Livewire::actingAs($admin)
            ->test('pages::academic.promote')
            ->set('sourceYearId', $yearOld->id)
            ->set('targetYearId', $yearNew->id)
            ->assertSee('Egresan (último grado)')
            ->assertSee('Carlos Ruiz');

        $this->assertSame(0, Enrollment::where('academic_year_id', $yearNew->id)->count());
    }

    public function test_sin_aula_destino_queda_para_atencion_manual(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade3 = $this->makeGrade($institution, '3°', 3);
        $this->makeGrade($institution, '4°', 4); // Existe el grado, pero no se crea aula en el año nuevo.

        $yearOld = $this->makeActiveYear($institution, 2025);
        $yearNew = $this->makeActiveYear($institution, 2026);

        $classroomOld = $this->makeClassroom($grade3, $yearOld, 'A');

        $student = Student::create([
            'first_name' => 'Ana', 'last_name' => 'Pérez',
            'birth_date' => now()->subYears(9)->format('Y-m-d'), 'sex' => 'F', 'address' => 'Calle 2',
        ]);
        Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroomOld->id, 'academic_year_id' => $yearOld->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2025-02-01',
            'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        Livewire::actingAs($admin)
            ->test('pages::academic.promote')
            ->set('sourceYearId', $yearOld->id)
            ->set('targetYearId', $yearNew->id)
            ->assertSee('Requieren atención manual')
            ->assertSee('No existe aula de 4°');
    }

    public function test_secretaria_no_puede_acceder_a_la_promocion(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $secretaria = $this->makeStaffUser('secretaria', $team);

        $response = $this->actingAs($secretaria)->get(route('academic.promote'));

        $response->assertForbidden();
    }
}
