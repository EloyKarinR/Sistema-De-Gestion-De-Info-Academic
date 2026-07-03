<?php

namespace Tests\Feature;

use App\Models\Guardian;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class GuardianManagementTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_secretaria_puede_editar_los_datos_de_un_acudiente(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $secretaria = $this->makeStaffUser('secretaria', $team);

        $guardian = Guardian::create([
            'first_name' => 'Marta', 'last_name' => 'Pérez', 'relationship' => 'madre', 'primary_phone' => '6000-0000',
        ]);

        Livewire::actingAs($secretaria)
            ->test('pages::guardians.index')
            ->call('openEditModal', $guardian->id)
            ->set('editFirstName', 'Marta Elena')
            ->set('editPrimaryPhone', '6000-9999')
            ->set('editEmail', 'marta.perez@correo.com')
            ->call('updateGuardian')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('guardians', [
            'id' => $guardian->id,
            'first_name' => 'Marta Elena',
            'primary_phone' => '6000-9999',
            'email' => 'marta.perez@correo.com',
        ]);
    }

    public function test_docente_no_puede_editar_acudientes(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $docente = $this->makeStaffUser('docente', $team);

        $guardian = Guardian::create([
            'first_name' => 'Marta', 'last_name' => 'Pérez', 'relationship' => 'madre', 'primary_phone' => '6000-0000',
        ]);

        Livewire::actingAs($docente)
            ->test('pages::guardians.index')
            ->call('openEditModal', $guardian->id)
            ->assertForbidden();
    }
}
