<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
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

    public function test_el_boton_de_copiar_aulas_solo_aparece_si_el_anio_activo_no_tiene_ninguna(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);

        Livewire::actingAs($admin)
            ->test('pages::academic.index')
            ->assertSee('Copiar aulas del año anterior');

        $this->makeClassroom($grade, $year, 'A');

        Livewire::actingAs($admin)
            ->test('pages::academic.index')
            ->assertDontSee('Copiar aulas del año anterior');
    }

    public function test_crear_un_anio_que_ya_existe_lo_reactiva_en_vez_de_duplicarlo(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);

        $yearOld = $this->makeActiveYear($institution, 2026);
        $classroom = $this->makeClassroom($grade, $yearOld, 'A');

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $yearOld->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        // Simula haber creado "2027" y ahora querer "volver" a 2026 usando el
        // mismo formulario de "Nuevo año" (el error real que motivó este fix).
        $yearOld->update(['is_active' => false]);
        $this->makeActiveYear($institution, 2027);

        Livewire::actingAs($admin)
            ->test('pages::academic.index')
            ->set('newYear', 2026)
            ->set('startDate', '2026-01-01')
            ->set('endDate', '2026-12-31')
            ->call('createYear')
            ->assertHasNoErrors();

        // Sigue existiendo un solo "2026", ahora activo, con su aula y su matrícula.
        $this->assertSame(1, AcademicYear::where('year', 2026)->count());
        $reactivated = AcademicYear::where('year', 2026)->first();
        $this->assertTrue($reactivated->is_active);
        $this->assertSame($yearOld->id, $reactivated->id);
        $this->assertSame(1, Classroom::where('academic_year_id', $reactivated->id)->count());
        $this->assertSame(1, Enrollment::where('academic_year_id', $reactivated->id)->where('status', 'activo')->count());

        // El 2027 quedó inactivo, no se tocó.
        $this->assertFalse(AcademicYear::where('year', 2027)->first()->is_active);
    }
}
