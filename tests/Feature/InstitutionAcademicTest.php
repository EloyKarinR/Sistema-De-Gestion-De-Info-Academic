<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
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

    public function test_admin_puede_copiar_las_aulas_del_anio_anterior_sin_duplicar_las_existentes(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade3 = $this->makeGrade($institution, '3°', 3);
        $grade4 = $this->makeGrade($institution, '4°', 4);

        $yearOld = $this->makeActiveYear($institution, 2025);
        $yearNew = $this->makeActiveYear($institution, 2026);
        $yearOld->update(['is_active' => false]);

        $this->makeClassroom($grade3, $yearOld, 'A');
        $this->makeClassroom($grade4, $yearOld, 'B');
        // Esta ya existe en el año nuevo, no debería duplicarse.
        $this->makeClassroom($grade3, $yearNew, 'A');

        Livewire::actingAs($admin)
            ->test('pages::academic.index')
            ->call('copyClassroomsFromPreviousYear');

        $this->assertSame(2, Classroom::where('academic_year_id', $yearNew->id)->count());
        $this->assertDatabaseHas('classrooms', ['academic_year_id' => $yearNew->id, 'grade_id' => $grade4->id, 'section' => 'B']);
    }
}
