<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class CrossTenantIsolationTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    /**
     * Arma una institución completa (equipo, admin, año activo, aula,
     * estudiante matriculado, acudiente y docente) para poder comparar dos
     * escuelas independientes en un mismo test.
     *
     * @return array{team: Team, admin: User, student: Student, enrollment: Enrollment, classroom: Classroom, guardian: Guardian, teacher: Teacher}
     */
    private function makeFullSchool(string $label): array
    {
        $team = $this->makeTeam("Escuela {$label}");
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution($team, "Escuela {$label}");
        $grade = $this->makeGrade($institution, "Grado {$label}", 1);
        $year = $this->makeActiveYear($institution, 2026);
        $classroom = $this->makeClassroom($grade, $year, $label);

        $student = Student::create([
            'first_name' => "Estudiante{$label}", 'last_name' => 'Pérez',
            'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2',
        ]);

        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        $guardian = Guardian::create([
            'first_name' => "Acudiente{$label}", 'last_name' => 'Pérez', 'relationship' => 'madre', 'primary_phone' => '6000-0000',
        ]);
        $student->guardians()->attach($guardian->id, ['is_primary' => true]);

        $docenteUser = $this->makeStaffUser('docente', $team);
        $teacher = Teacher::create([
            'user_id' => $docenteUser->id, 'cedula' => "9-{$label}-0000",
            'first_name' => "Docente{$label}", 'last_name' => 'Gómez', 'shift' => 'matutino',
        ]);

        return compact('team', 'admin', 'student', 'enrollment', 'classroom', 'guardian', 'teacher');
    }

    public function test_el_listado_de_estudiantes_no_muestra_estudiantes_de_otra_institucion(): void
    {
        $this->seed(RoleSeeder::class);

        $schoolA = $this->makeFullSchool('A');
        $schoolB = $this->makeFullSchool('B');

        Livewire::actingAs($schoolA['admin'])
            ->test('pages::students.index')
            ->assertSee('EstudianteA')
            ->assertDontSee('EstudianteB');
    }

    public function test_acceder_al_boletin_de_un_estudiante_de_otra_institucion_da_404(): void
    {
        $this->seed(RoleSeeder::class);

        $schoolA = $this->makeFullSchool('A');
        $schoolB = $this->makeFullSchool('B');

        $this->actingAs($schoolA['admin'])
            ->get(route('reports.boletin', $schoolB['student']))
            ->assertNotFound();
    }

    public function test_acceder_a_la_constancia_de_una_matricula_de_otra_institucion_da_403(): void
    {
        $this->seed(RoleSeeder::class);

        $schoolA = $this->makeFullSchool('A');
        $schoolB = $this->makeFullSchool('B');

        $this->actingAs($schoolA['admin'])
            ->get(route('reports.constancia', $schoolB['enrollment']))
            ->assertForbidden();
    }

    public function test_acceder_al_listado_de_un_aula_de_otra_institucion_da_403(): void
    {
        $this->seed(RoleSeeder::class);

        $schoolA = $this->makeFullSchool('A');
        $schoolB = $this->makeFullSchool('B');

        $this->actingAs($schoolA['admin'])
            ->get(route('reports.listado', $schoolB['classroom']))
            ->assertForbidden();
    }

    public function test_un_usuario_en_dos_equipos_ve_datos_distintos_al_cambiar_de_equipo(): void
    {
        $this->seed(RoleSeeder::class);

        $schoolA = $this->makeFullSchool('A');
        $schoolB = $this->makeFullSchool('B');

        $bothSchoolsUser = User::factory()->create();
        $bothSchoolsUser->assignRole('admin');
        $schoolA['team']->members()->attach($bothSchoolsUser->id, ['role' => TeamRole::Member->value]);
        $schoolB['team']->members()->attach($bothSchoolsUser->id, ['role' => TeamRole::Member->value]);

        $bothSchoolsUser->update(['current_team_id' => $schoolA['team']->id]);
        $bothSchoolsUser = $bothSchoolsUser->fresh();

        Livewire::actingAs($bothSchoolsUser)
            ->test('pages::students.index')
            ->assertSee('EstudianteA')
            ->assertDontSee('EstudianteB');

        $bothSchoolsUser->switchTeam($schoolB['team']);
        $bothSchoolsUser = $bothSchoolsUser->fresh();

        Livewire::actingAs($bothSchoolsUser)
            ->test('pages::students.index')
            ->assertSee('EstudianteB')
            ->assertDontSee('EstudianteA');
    }

    public function test_crear_un_estudiante_sin_ningun_contexto_de_institucion_falla(): void
    {
        $this->expectException(QueryException::class);

        Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
    }

    public function test_un_usuario_con_equipo_personal_no_tiene_institucion_actual(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->currentInstitution());
    }
}
