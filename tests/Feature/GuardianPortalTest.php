<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\GradeScore;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class GuardianPortalTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_secretaria_puede_dar_acceso_al_portal_y_la_acudiente_ve_solo_a_su_hija(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);
        $secretaria = $this->makeStaffUser('secretaria', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $matematica = $institution->subjects()->create(['name' => 'Matemática']);
        $grade->subjects()->attach($matematica->id);
        $classroom = $this->makeClassroom($grade, $year);

        $studentA = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $studentB = Student::create(['first_name' => 'Beto', 'last_name' => 'Gómez', 'birth_date' => '2018-02-01', 'sex' => 'M', 'address' => 'Calle 3']);

        $enrollmentA = Enrollment::create(['student_id' => $studentA->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id, 'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso']);
        Enrollment::create(['student_id' => $studentB->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id, 'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso']);

        $period = $year->periods()->first();
        GradeScore::create(['enrollment_id' => $enrollmentA->id, 'subject_id' => $matematica->id, 'period_id' => $period->id, 'score' => 95.5]);

        $guardian = Guardian::create(['first_name' => 'Marta', 'last_name' => 'Pérez', 'relationship' => 'madre', 'primary_phone' => '6000-0000']);
        $studentA->guardians()->attach($guardian->id, ['is_primary' => true]);

        Livewire::actingAs($secretaria)
            ->test('pages::guardians.index')
            ->call('openPortalModal', $guardian->id)
            ->set('portalEmail', 'marta@correo.com')
            ->set('portalPassword', 'password123')
            ->call('grantPortalAccess')
            ->assertHasNoErrors();

        $guardianUser = User::where('email', 'marta@correo.com')->first();
        $this->assertNotNull($guardianUser);
        $this->assertTrue($guardianUser->hasRole('acudiente'));
        $this->assertTrue($guardianUser->belongsToTeam($team));

        Livewire::actingAs($guardianUser)
            ->test('pages::portal.index')
            ->assertSee($grade->name.'-'.$classroom->section)
            ->assertSee('Beto Gómez')
            ->assertSee('Matemática')
            ->assertSee('95.5')
            ->assertDontSee('Ana Pérez');
    }

    public function test_acudiente_sin_hijos_vinculados_no_ve_datos_de_otros(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $acudiente = $this->makeStaffUser('acudiente', $team);

        Livewire::actingAs($acudiente)
            ->test('pages::portal.index')
            ->assertSee('Sin estudiantes asociados');
    }
}
