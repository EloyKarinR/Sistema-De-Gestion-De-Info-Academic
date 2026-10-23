<?php

namespace Tests\Feature;

use App\Actions\Institutions\ProvisionInstitutionCatalog;
use App\Models\EducationLevel;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Habit;
use App\Models\HabitScore;
use App\Models\Institution;
use App\Models\Student;
use App\Models\Subject;
use App\Support\TenantContext;
use Database\Seeders\HabitScoreBackfillSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class ProvisionInstitutionCatalogTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    private function provisionedSchool(string $name): Institution
    {
        $institution = $this->makeInstitution($this->makeTeam($name), $name);
        app(ProvisionInstitutionCatalog::class)->handle($institution);

        return $institution;
    }

    /**
     * Grado por nombre dentro de UNA institución, sin depender del contexto.
     */
    private function gradeOf(Institution $institution, string $name): Grade
    {
        return Grade::withoutGlobalScopes()
            ->whereIn('education_level_id', EducationLevel::withoutGlobalScopes()->where('institution_id', $institution->id)->select('id'))
            ->where('name', $name)
            ->firstOrFail();
    }

    public function test_preescolar_no_recibe_ingles_pero_basica_general_si(): void
    {
        $institution = $this->provisionedSchool('Escuela A');

        $preKinder = $this->gradeOf($institution, 'Pre-Kinder');
        $kinder = $this->gradeOf($institution, 'Kinder');
        $primero = $this->gradeOf($institution, '1°');

        $this->assertFalse($preKinder->subjects->contains('name', 'Inglés'));
        $this->assertFalse($kinder->subjects->contains('name', 'Inglés'));
        $this->assertTrue($primero->subjects->contains('name', 'Inglés'));

        // Preescolar sí conserva Expresión Artística.
        $this->assertTrue($preKinder->subjects->contains('name', 'Expresión Artística'));
    }

    public function test_correrlo_dos_veces_no_duplica_nada(): void
    {
        $institution = $this->provisionedSchool('Escuela A');

        app(ProvisionInstitutionCatalog::class)->handle($institution);

        $this->assertSame(4, EducationLevel::withoutGlobalScopes()->where('institution_id', $institution->id)->count());
        $this->assertSame(1, Grade::withoutGlobalScopes()->where('name', '1°')->count());
        $this->assertSame(13, Subject::withoutGlobalScopes()->where('institution_id', $institution->id)->count());
        $this->assertSame(7, Habit::withoutGlobalScopes()->where('institution_id', $institution->id)->count());
    }

    public function test_cada_escuela_recibe_su_propio_catalogo_aunque_el_contexto_apunte_a_otra(): void
    {
        $schoolA = $this->provisionedSchool('Escuela A');

        // El contexto sigue en A mientras se crea B (como un admin que registra
        // una escuela nueva): el catálogo de B no debe salir vacío ni mezclado.
        $schoolB = new Institution(['name' => 'Escuela B', 'type' => 'escuela', 'address' => 'Calle 9']);
        $schoolB->team_id = $this->makeTeam('Escuela B')->id;
        $schoolB->save();
        app(TenantContext::class)->set($schoolA);

        app(ProvisionInstitutionCatalog::class)->handle($schoolB);

        foreach ([$schoolA, $schoolB] as $school) {
            $this->assertSame(4, EducationLevel::withoutGlobalScopes()->where('institution_id', $school->id)->count());
            $this->assertSame(7, Habit::withoutGlobalScopes()->where('institution_id', $school->id)->count());
        }

        // Las materias de los grados de B son las de B, no las de A.
        $subjectIdsOfB = Subject::withoutGlobalScopes()->where('institution_id', $schoolB->id)->pluck('id');
        $this->assertTrue($this->gradeOf($schoolB, '1°')->subjects()->withoutGlobalScopes()->get()->pluck('id')->diff($subjectIdsOfB)->isEmpty());
    }

    public function test_el_grado_siguiente_nunca_salta_a_otra_escuela(): void
    {
        $schoolA = $this->provisionedSchool('Escuela A');
        $schoolB = $this->provisionedSchool('Escuela B');

        app(TenantContext::class)->forget();

        $this->assertSame(
            $this->gradeOf($schoolB, '7°')->id,
            $this->gradeOf($schoolB, '6°')->next()->id
        );
        $this->assertSame(
            $this->gradeOf($schoolA, '7°')->id,
            $this->gradeOf($schoolA, '6°')->next()->id
        );
    }

    public function test_registrar_la_institucion_desde_la_app_crea_su_catalogo(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam('Escuela Nueva');
        $admin = $this->makeStaffUser('admin', $team);

        Livewire::actingAs($admin)
            ->test('pages::institution.edit')
            ->set('name', 'Escuela Nueva')
            ->set('type', 'escuela')
            ->set('address', 'Calle 5')
            ->call('save')
            ->assertHasNoErrors();

        $institution = $team->fresh()->institution;

        $this->assertSame(4, $institution->educationLevels()->withoutGlobalScopes()->count());
        $this->assertSame(7, $institution->habits()->withoutGlobalScopes()->count());
        $this->assertTrue($this->gradeOf($institution, '1°')->subjects->contains('name', 'Matemática'));
    }

    public function test_el_backfill_de_habitos_sin_contexto_rellena_todas_las_escuelas(): void
    {
        $this->travelTo('2026-06-15');
        $this->seed(RoleSeeder::class);

        $enrollments = [];

        foreach (['A', 'B'] as $label) {
            $team = $this->makeTeam("Escuela {$label}");
            $admin = $this->makeStaffUser('admin', $team);
            $institution = $this->makeInstitution($team, "Escuela {$label}");
            app(ProvisionInstitutionCatalog::class)->habits($institution);

            $year = $this->makeActiveYear($institution);
            $classroom = $this->makeClassroom($this->makeGrade($institution), $year);
            $student = Student::create(['first_name' => "Ana{$label}", 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);

            $enrollments[$label] = Enrollment::create([
                'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
                'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
            ]);
        }

        // Como se corre en producción: sin usuario y sin contexto fijado.
        app(TenantContext::class)->forget();
        $this->seed(HabitScoreBackfillSeeder::class);

        foreach ($enrollments as $enrollment) {
            $this->assertGreaterThan(0, HabitScore::where('enrollment_id', $enrollment->id)->count());
        }
    }
}
