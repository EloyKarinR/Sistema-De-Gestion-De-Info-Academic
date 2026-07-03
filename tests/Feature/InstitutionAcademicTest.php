<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class InstitutionAcademicTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_admin_puede_editar_la_institucion_pero_secretaria_solo_puede_ver(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);
        $secretaria = $this->makeStaffUser('secretaria', $team);

        Livewire::actingAs($admin)
            ->test('pages::institution.edit')
            ->set('name', 'Escuela Bilingüe Berta A. López')
            ->set('type', 'escuela')
            ->set('address', 'Una Milla, Almirante')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('institutions', ['name' => 'Escuela Bilingüe Berta A. López']);

        Livewire::actingAs($secretaria)
            ->test('pages::institution.edit')
            ->set('name', 'Otro nombre')
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseMissing('institutions', ['name' => 'Otro nombre']);
    }

    public function test_solo_admin_puede_crear_anio_escolar_y_aulas_secretaria_solo_ve(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);
        $secretaria = $this->makeStaffUser('secretaria', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);

        Livewire::actingAs($admin)
            ->test('pages::academic.index')
            ->set('newYear', 2026)
            ->set('startDate', '2026-02-01')
            ->set('endDate', '2026-11-30')
            ->call('createYear')
            ->assertHasNoErrors();

        $year = AcademicYear::where('is_active', true)->first();
        $this->assertNotNull($year);
        $this->assertCount(3, $year->periods);

        Livewire::actingAs($admin)
            ->test('pages::academic.index')
            ->set('gradeId', (string) $grade->id)
            ->set('section', 'A')
            ->set('shift', 'matutino')
            ->set('capacity', 30)
            ->call('addClassroom')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('classrooms', ['grade_id' => $grade->id, 'section' => 'A']);

        // Secretaria no puede crear otro año escolar
        Livewire::actingAs($secretaria)
            ->test('pages::academic.index')
            ->set('newYear', 2027)
            ->set('startDate', '2027-02-01')
            ->set('endDate', '2027-11-30')
            ->call('createYear')
            ->assertForbidden();

        $this->assertDatabaseMissing('academic_years', ['year' => 2027]);
    }
}
