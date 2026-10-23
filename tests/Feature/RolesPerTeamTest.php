<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

/**
 * Los roles de la escuela (admin, secretaria, docente, acudiente) son por
 * equipo: ser admin en una escuela no da ningún permiso en otra.
 */
class RolesPerTeamTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_ser_admin_en_una_escuela_no_da_permisos_de_admin_en_otra(): void
    {
        $this->seed(RoleSeeder::class);

        $teamA = $this->makeTeam('Escuela A');
        $teamB = $this->makeTeam('Escuela B');
        $this->makeInstitution($teamA, 'Escuela A');
        $this->makeInstitution($teamB, 'Escuela B');

        $user = $this->makeStaffUser('admin', $teamA);
        $teamB->members()->attach($user->id, ['role' => TeamRole::Member->value]);
        $user->assignRoleInTeam('docente', $teamB);

        $this->actingAs($user);
        $this->assertTrue($user->can('institution.edit'));

        $user->switchTeam($teamB);
        $this->actingAs($user = $user->fresh());

        $this->assertFalse($user->can('institution.edit'));
        $this->assertFalse($user->can('enrollment.create'));
        $this->assertTrue($user->can('scores.enter'));
    }

    public function test_un_miembro_sin_rol_en_la_escuela_no_tiene_ningun_permiso_ahi(): void
    {
        $this->seed(RoleSeeder::class);

        $teamA = $this->makeTeam('Escuela A');
        $teamB = $this->makeTeam('Escuela B');
        $this->makeInstitution($teamA, 'Escuela A');
        $this->makeInstitution($teamB, 'Escuela B');

        // Como si hubiera aceptado una invitación a B: miembro, pero sin rol.
        $user = $this->makeStaffUser('secretaria', $teamA);
        $teamB->members()->attach($user->id, ['role' => TeamRole::Member->value]);
        $user->switchTeam($teamB);

        $this->actingAs($user->fresh())
            ->get(route('students.index'))
            ->assertForbidden();
    }

    public function test_crear_un_docente_le_da_el_rol_solo_en_la_escuela_actual(): void
    {
        $this->seed(RoleSeeder::class);

        $teamA = $this->makeTeam('Escuela A');
        $teamB = $this->makeTeam('Escuela B');
        $this->makeInstitution($teamB, 'Escuela B');
        $this->makeInstitution($teamA, 'Escuela A');
        $admin = $this->makeStaffUser('admin', $teamA);

        Livewire::actingAs($admin)
            ->test('pages::teachers.index')
            ->set('firstName', 'Carlos')
            ->set('lastName', 'Mendoza')
            ->set('cedula', '8-111-2222')
            ->set('email', 'carlos@escuela-a.pa')
            ->set('password', 'password123')
            ->set('shift', 'matutino')
            ->call('createTeacher')
            ->assertHasNoErrors();

        $teacherUser = User::where('email', 'carlos@escuela-a.pa')->firstOrFail();

        $this->assertDatabaseHas('model_has_roles', ['model_id' => $teacherUser->id, 'team_id' => $teamA->id]);
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $teacherUser->id, 'team_id' => $teamB->id]);
    }
}
