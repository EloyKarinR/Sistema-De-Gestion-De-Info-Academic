<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\Student;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class StudentGuardianActionsTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_secretaria_puede_editar_y_dar_acceso_al_portal_desde_la_ficha_del_estudiante(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $secretaria = $this->makeStaffUser('secretaria', $team);

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $guardian = Guardian::create(['first_name' => 'Marta', 'last_name' => 'Pérez', 'relationship' => 'madre', 'primary_phone' => '6000-0000']);
        $student->guardians()->attach($guardian->id, ['is_primary' => true]);

        Livewire::actingAs($secretaria)
            ->test('pages::students.show', ['student' => $student])
            ->call('openEditModal', $guardian->id)
            ->set('editPrimaryPhone', '6000-9999')
            ->call('updateGuardian')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('guardians', ['id' => $guardian->id, 'primary_phone' => '6000-9999']);

        Livewire::actingAs($secretaria)
            ->test('pages::students.show', ['student' => $student])
            ->call('openPortalModal', $guardian->id)
            ->set('portalEmail', 'marta@correo.com')
            ->set('portalPassword', 'password123')
            ->call('grantPortalAccess')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'marta@correo.com']);
        $this->assertNotNull($guardian->fresh()->user_id);
    }

    public function test_docente_no_puede_editar_ni_dar_acceso_desde_la_ficha_del_estudiante(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $docente = $this->makeStaffUser('docente', $team);

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $guardian = Guardian::create(['first_name' => 'Marta', 'last_name' => 'Pérez', 'relationship' => 'madre', 'primary_phone' => '6000-0000']);
        $student->guardians()->attach($guardian->id, ['is_primary' => true]);

        Livewire::actingAs($docente)
            ->test('pages::students.show', ['student' => $student])
            ->call('openEditModal', $guardian->id)
            ->assertForbidden();
    }

    public function test_secretaria_puede_eliminar_un_estudiante_sin_matriculas_desde_su_ficha(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $secretaria = $this->makeStaffUser('secretaria', $team);

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);

        Livewire::actingAs($secretaria)
            ->test('pages::students.show', ['student' => $student])
            ->assertSee('Eliminar estudiante')
            ->call('deleteStudent')
            ->assertRedirect(route('students.index'));

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }
}
