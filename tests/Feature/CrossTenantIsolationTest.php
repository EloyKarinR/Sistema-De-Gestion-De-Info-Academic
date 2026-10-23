<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\Habit;
use App\Models\Institution;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Team;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
     * @return array{team: Team, admin: User, institution: Institution, year: AcademicYear, grade: Grade, student: Student, enrollment: Enrollment, classroom: Classroom, guardian: Guardian, teacher: Teacher}
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

        return compact('team', 'admin', 'institution', 'year', 'grade', 'student', 'enrollment', 'classroom', 'guardian', 'teacher');
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

    public function test_acceder_a_la_constancia_de_una_matricula_de_otra_institucion_da_404(): void
    {
        $this->seed(RoleSeeder::class);

        $schoolA = $this->makeFullSchool('A');
        $schoolB = $this->makeFullSchool('B');

        $this->actingAs($schoolA['admin'])
            ->get(route('reports.constancia', $schoolB['enrollment']))
            ->assertNotFound();
    }

    public function test_acceder_al_listado_de_un_aula_de_otra_institucion_da_404(): void
    {
        $this->seed(RoleSeeder::class);

        $schoolA = $this->makeFullSchool('A');
        $schoolB = $this->makeFullSchool('B');

        $this->actingAs($schoolA['admin'])
            ->get(route('reports.listado', $schoolB['classroom']))
            ->assertNotFound();
    }

    public function test_un_usuario_en_dos_equipos_ve_datos_distintos_al_cambiar_de_equipo(): void
    {
        $this->seed(RoleSeeder::class);

        $schoolA = $this->makeFullSchool('A');
        $schoolB = $this->makeFullSchool('B');

        $bothSchoolsUser = User::factory()->create();
        $bothSchoolsUser->assignRoleInTeam('admin', $schoolA['team']);
        $bothSchoolsUser->assignRoleInTeam('admin', $schoolB['team']);
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

    /*
     * Los siguientes tests simulan un cliente que manipula las propiedades
     * públicas de Livewire para apuntar a IDs de otra escuela. Classroom y
     * Enrollment no tienen institution_id propio — se aíslan a través de su
     * año escolar — así que estos IDs nunca pasan por el scope de Student.
     */

    public function test_no_se_puede_retirar_una_matricula_de_otra_institucion(): void
    {
        $this->seed(RoleSeeder::class);

        $schoolA = $this->makeFullSchool('A');
        $schoolB = $this->makeFullSchool('B');

        try {
            Livewire::actingAs($schoolA['admin'])
                ->test('pages::enrollments.index')
                ->set('statusEnrollmentId', $schoolB['enrollment']->id)
                ->set('newStatus', 'retirado')
                ->set('statusDate', '2026-03-01')
                ->call('updateStatus');
        } catch (ModelNotFoundException) {
            // Esperado: la matrícula de B no existe para A.
        }

        $this->assertDatabaseHas('enrollments', ['id' => $schoolB['enrollment']->id, 'status' => 'activo']);
    }

    public function test_no_se_pueden_guardar_notas_en_una_matricula_de_otra_institucion(): void
    {
        $this->seed(RoleSeeder::class);

        $schoolA = $this->makeFullSchool('A');
        $schoolB = $this->makeFullSchool('B');

        $subjectA = Subject::create(['institution_id' => $schoolA['institution']->id, 'name' => 'Matemática']);
        $schoolA['grade']->subjects()->attach($subjectA->id);
        $periodA = $schoolA['year']->periods()->first();

        Livewire::actingAs($schoolA['admin'])
            ->test('pages::scores.index')
            ->set('classroomId', (string) $schoolA['classroom']->id)
            ->set('subjectId', (string) $subjectA->id)
            ->set('periodId', (string) $periodA->id)
            ->set("scores.{$schoolB['enrollment']->id}", '1')
            ->call('saveScores');

        $this->assertDatabaseMissing('grade_scores', ['enrollment_id' => $schoolB['enrollment']->id]);
    }

    public function test_no_se_pueden_guardar_notas_apuntando_al_aula_de_otra_institucion(): void
    {
        $this->seed(RoleSeeder::class);

        $schoolA = $this->makeFullSchool('A');
        $schoolB = $this->makeFullSchool('B');

        $subjectB = Subject::create(['institution_id' => $schoolB['institution']->id, 'name' => 'Ciencias']);
        $schoolB['grade']->subjects()->attach($subjectB->id);
        $periodB = $schoolB['year']->periods()->first();

        Livewire::actingAs($schoolA['admin'])
            ->test('pages::scores.index')
            ->set('classroomId', (string) $schoolB['classroom']->id)
            ->set('subjectId', (string) $subjectB->id)
            ->set('periodId', (string) $periodB->id)
            ->set("scores.{$schoolB['enrollment']->id}", '1')
            ->call('saveScores');

        $this->assertDatabaseMissing('grade_scores', ['enrollment_id' => $schoolB['enrollment']->id]);
    }

    public function test_no_se_pueden_guardar_habitos_en_una_matricula_de_otra_institucion(): void
    {
        $this->seed(RoleSeeder::class);

        $schoolA = $this->makeFullSchool('A');
        $schoolB = $this->makeFullSchool('B');

        $habitB = Habit::create(['institution_id' => $schoolB['institution']->id, 'name' => 'Puntualidad', 'order' => 1]);
        $periodA = $schoolA['year']->periods()->first();

        Livewire::actingAs($schoolA['admin'])
            ->test('pages::habits.index')
            ->set('classroomId', (string) $schoolA['classroom']->id)
            ->set('periodId', (string) $periodA->id)
            ->set("scores.{$schoolB['enrollment']->id}.{$habitB->id}", 'X')
            ->call('saveScores');

        $this->assertDatabaseMissing('habit_scores', ['enrollment_id' => $schoolB['enrollment']->id]);
    }

    public function test_no_se_puede_registrar_asistencia_en_el_aula_de_otra_institucion(): void
    {
        $this->seed(RoleSeeder::class);

        $schoolA = $this->makeFullSchool('A');
        $schoolB = $this->makeFullSchool('B');

        Livewire::actingAs($schoolA['admin'])
            ->test('pages::attendance.index')
            ->set('classroomId', (string) $schoolB['classroom']->id)
            ->set('date', '2026-03-02')
            ->set("statuses.{$schoolB['enrollment']->id}", 'ausente')
            ->call('saveAttendance');

        $this->assertDatabaseMissing('attendance', ['enrollment_id' => $schoolB['enrollment']->id]);
    }

    public function test_no_se_puede_asignar_un_docente_al_aula_de_otra_institucion(): void
    {
        $this->seed(RoleSeeder::class);

        $schoolA = $this->makeFullSchool('A');
        $schoolB = $this->makeFullSchool('B');

        Livewire::actingAs($schoolA['admin'])
            ->test('pages::teachers.index')
            ->set('assignTeacherId', $schoolA['teacher']->id)
            ->set('assignMode', 'homeroom')
            ->set('assignClassroomIds', [(string) $schoolB['classroom']->id])
            ->call('addAssignment')
            ->assertHasErrors('assignClassroomIds.0');

        $this->assertDatabaseMissing('subject_assignments', ['classroom_id' => $schoolB['classroom']->id]);
    }

    public function test_no_se_puede_matricular_en_el_aula_de_otra_institucion(): void
    {
        $this->seed(RoleSeeder::class);

        $schoolA = $this->makeFullSchool('A');
        $schoolB = $this->makeFullSchool('B');

        app(TenantContext::class)->set($schoolA['institution']);
        $newStudent = Student::create([
            'first_name' => 'Nuevo', 'last_name' => 'Ríos', 'birth_date' => '2019-01-01', 'sex' => 'M', 'address' => 'Calle 3',
        ]);

        Livewire::actingAs($schoolA['admin'])
            ->test('pages::enrollments.create')
            ->set('studentId', $newStudent->id)
            ->set('step', 3)
            ->set('classroomId', (string) $schoolB['classroom']->id)
            ->set('enrollmentType', 'nuevo_ingreso')
            ->set('enrollmentDate', '2026-02-01')
            ->call('saveEnrollment')
            ->assertHasErrors('classroomId');

        $this->assertDatabaseMissing('enrollments', ['student_id' => $newStudent->id]);
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
