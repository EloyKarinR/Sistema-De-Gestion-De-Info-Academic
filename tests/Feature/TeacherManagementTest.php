<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\SubjectAssignment;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class TeacherManagementTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_admin_puede_crear_un_docente_con_su_cuenta_de_acceso(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        Livewire::actingAs($admin)
            ->test('pages::teachers.index')
            ->set('firstName', 'Carlos')
            ->set('lastName', 'Mendoza')
            ->set('cedula', '9-999-9999')
            ->set('email', 'carlos.mendoza@siga.pa')
            ->set('password', 'password123')
            ->call('createTeacher')
            ->assertHasNoErrors();

        $user = User::where('email', 'carlos.mendoza@siga.pa')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('docente'));
        $this->assertNotNull($user->teacher);
        $this->assertSame('9-999-9999', $user->teacher->cedula);
        $this->assertTrue($user->belongsToTeam($team));
    }

    public function test_docente_no_puede_crear_otro_docente(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $docente = $this->makeStaffUser('docente', $team);

        Livewire::actingAs($docente)
            ->test('pages::teachers.index')
            ->set('firstName', 'X')
            ->set('lastName', 'Y')
            ->set('cedula', '1-111-1111')
            ->set('email', 'nope@siga.pa')
            ->set('password', 'password123')
            ->call('createTeacher')
            ->assertForbidden();
    }

    public function test_docente_solo_ve_y_guarda_notas_de_su_asignacion(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $matematica = $institution->subjects()->create(['name' => 'Matemática']);
        $espanol = $institution->subjects()->create(['name' => 'Español']);
        $grade->subjects()->attach([$matematica->id, $espanol->id]);

        $classroomA = $this->makeClassroom($grade, $year, 'A');
        $classroomB = $this->makeClassroom($grade, $year, 'B');

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroomA->id, 'academic_year_id' => $year->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);
        $period = $year->periods()->first();

        $docenteUser = $this->makeStaffUser('docente', $team);
        $teacher = Teacher::create(['user_id' => $docenteUser->id, 'cedula' => '8-000-0000', 'first_name' => 'Carlos', 'last_name' => 'Mendoza', 'shift' => 'matutino']);

        SubjectAssignment::create([
            'teacher_id' => $teacher->id, 'classroom_id' => $classroomA->id,
            'subject_id' => $matematica->id, 'academic_year_id' => $year->id,
        ]);

        // Puede guardar en su aula+materia asignada
        Livewire::actingAs($docenteUser)
            ->test('pages::scores.index')
            ->set('classroomId', (string) $classroomA->id)
            ->set('subjectId', (string) $matematica->id)
            ->set('periodId', (string) $period->id)
            ->set("scores.{$enrollment->id}", '4.5')
            ->call('saveScores')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('grade_scores', [
            'enrollment_id' => $enrollment->id, 'subject_id' => $matematica->id, 'score' => 4.5,
        ]);

        // No puede guardar Español (no asignado) en la misma aula
        Livewire::actingAs($docenteUser)
            ->test('pages::scores.index')
            ->set('classroomId', (string) $classroomA->id)
            ->set('subjectId', (string) $espanol->id)
            ->set('periodId', (string) $period->id)
            ->set("scores.{$enrollment->id}", '3.5')
            ->call('saveScores')
            ->assertForbidden();

        // No puede guardar Matemática en el aula B (no asignado ahí)
        Livewire::actingAs($docenteUser)
            ->test('pages::scores.index')
            ->set('classroomId', (string) $classroomB->id)
            ->set('subjectId', (string) $matematica->id)
            ->set('periodId', (string) $period->id)
            ->call('saveScores')
            ->assertForbidden();

        // El enlace "Ir a Notas" del Dashboard llega con aula+materia en la URL
        // y debe cargar la tabla de una vez, sin que el docente vuelva a elegir.
        Livewire::actingAs($docenteUser)
            ->withQueryParams(['aula' => (string) $classroomA->id, 'materia' => (string) $matematica->id])
            ->test('pages::scores.index')
            ->assertSet('classroomId', (string) $classroomA->id)
            ->assertSet('subjectId', (string) $matematica->id)
            ->assertSee('Ana Pérez')
            ->assertSee('Guardar notas');
    }

    public function test_docente_puede_tener_asignaciones_en_varias_aulas_y_niveles(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $year = $this->makeActiveYear($institution);

        $grade3 = $this->makeGrade($institution, '3°', 3);
        $grade7 = $this->makeGrade($institution, '7°', 7);
        $ingles = $institution->subjects()->create(['name' => 'Inglés', 'is_specialized' => true]);
        $grade3->subjects()->attach($ingles->id);
        $grade7->subjects()->attach($ingles->id);

        $classroom3A = $this->makeClassroom($grade3, $year, 'A');
        $classroom3B = $this->makeClassroom($grade3, $year, 'B');
        $classroom7A = $this->makeClassroom($grade7, $year, 'A');

        $docenteUser = $this->makeStaffUser('docente', $team);
        $teacher = Teacher::create(['user_id' => $docenteUser->id, 'cedula' => '8-111-1111', 'first_name' => 'Laura', 'last_name' => 'Green', 'shift' => 'matutino']);

        // Asigna las 3 aulas de un solo golpe (docente especialista itinerante, ej. Inglés)
        Livewire::actingAs($admin)
            ->test('pages::teachers.index')
            ->call('openAssignModal', $teacher->id)
            ->set('assignMode', 'specialist')
            ->set('assignClassroomIds', [(string) $classroom3A->id, (string) $classroom3B->id, (string) $classroom7A->id])
            ->set('assignSubjectIds', [(string) $ingles->id])
            ->call('addAssignment')
            ->assertHasNoErrors();

        $this->assertSame(3, SubjectAssignment::where('teacher_id', $teacher->id)->count());

        Livewire::actingAs($docenteUser)
            ->test('pages::scores.index')
            ->assertSee('3°-A')
            ->assertSee('3°-B')
            ->assertSee('7°-A');
    }

    public function test_asignacion_multiple_omite_aulas_que_ya_tienen_la_materia_con_otro_docente(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $ingles = $institution->subjects()->create(['name' => 'Inglés', 'is_specialized' => true]);
        $grade->subjects()->attach($ingles->id);

        $classroomA = $this->makeClassroom($grade, $year, 'A');
        $classroomB = $this->makeClassroom($grade, $year, 'B');

        $teacher1 = Teacher::create(['user_id' => $this->makeStaffUser('docente', $team)->id, 'cedula' => '8-111-1111', 'first_name' => 'Laura', 'last_name' => 'Green', 'shift' => 'matutino']);
        $teacher2 = Teacher::create(['user_id' => $this->makeStaffUser('docente', $team)->id, 'cedula' => '8-222-2222', 'first_name' => 'Pedro', 'last_name' => 'Ruiz', 'shift' => 'matutino']);

        // El aula A ya tiene Inglés asignado al docente 1
        SubjectAssignment::create([
            'teacher_id' => $teacher1->id, 'classroom_id' => $classroomA->id,
            'subject_id' => $ingles->id, 'academic_year_id' => $year->id,
        ]);

        // Al docente 2 se le intenta asignar Inglés en A (ya ocupada) y B (libre)
        Livewire::actingAs($admin)
            ->test('pages::teachers.index')
            ->call('openAssignModal', $teacher2->id)
            ->set('assignMode', 'specialist')
            ->set('assignClassroomIds', [(string) $classroomA->id, (string) $classroomB->id])
            ->set('assignSubjectIds', [(string) $ingles->id])
            ->call('addAssignment')
            ->assertHasNoErrors();

        // Aula A sigue siendo del docente 1, aula B ya es del docente 2
        $this->assertDatabaseHas('subject_assignments', ['classroom_id' => $classroomA->id, 'teacher_id' => $teacher1->id]);
        $this->assertDatabaseHas('subject_assignments', ['classroom_id' => $classroomB->id, 'teacher_id' => $teacher2->id]);
        $this->assertSame(1, SubjectAssignment::where('teacher_id', $teacher2->id)->count());
    }

    public function test_maestro_de_grado_recibe_automaticamente_todas_las_materias_generales_de_su_aula(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);

        $espanol = $institution->subjects()->create(['name' => 'Español', 'is_specialized' => false]);
        $matematica = $institution->subjects()->create(['name' => 'Matemática', 'is_specialized' => false]);
        $sociales = $institution->subjects()->create(['name' => 'Ciencias Sociales', 'is_specialized' => false]);
        $religion = $institution->subjects()->create(['name' => 'Religión', 'is_specialized' => false]);
        $naturales = $institution->subjects()->create(['name' => 'Ciencias Naturales', 'is_specialized' => false]);
        $ingles = $institution->subjects()->create(['name' => 'Inglés', 'is_specialized' => true]);
        $grade->subjects()->attach([$espanol->id, $matematica->id, $sociales->id, $religion->id, $naturales->id, $ingles->id]);

        $classroom = $this->makeClassroom($grade, $year, 'A');

        $docenteUser = $this->makeStaffUser('docente', $team);
        $teacher = Teacher::create(['user_id' => $docenteUser->id, 'cedula' => '8-333-3333', 'first_name' => 'Pedro', 'last_name' => 'Ruiz', 'shift' => 'matutino']);

        // Solo se le asigna el aula — el modo "Maestro de grado" (por defecto) le da
        // automáticamente todas las materias generales, sin elegirlas una por una.
        Livewire::actingAs($admin)
            ->test('pages::teachers.index')
            ->call('openAssignModal', $teacher->id)
            ->assertSet('assignMode', 'homeroom')
            ->set('assignClassroomIds', [(string) $classroom->id])
            ->call('addAssignment')
            ->assertHasNoErrors();

        // 5 materias generales, pero NO Inglés (esa es especializada, no se asigna sola)
        $this->assertSame(5, SubjectAssignment::where('teacher_id', $teacher->id)->count());
        $this->assertDatabaseMissing('subject_assignments', ['teacher_id' => $teacher->id, 'subject_id' => $ingles->id]);

        Livewire::actingAs($docenteUser)
            ->test('pages::scores.index')
            ->set('classroomId', (string) $classroom->id)
            ->assertSee('Español')
            ->assertSee('Matemática')
            ->assertSee('Ciencias Sociales')
            ->assertSee('Religión')
            ->assertSee('Ciencias Naturales')
            ->assertDontSee('Inglés');
    }

    public function test_no_se_puede_asignar_a_un_docente_un_aula_de_otro_turno(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $ingles = $institution->subjects()->create(['name' => 'Inglés', 'is_specialized' => true]);
        $grade->subjects()->attach($ingles->id);

        $classroomMatutino = $this->makeClassroom($grade, $year, 'A');
        $classroomVespertino = Classroom::create([
            'grade_id' => $grade->id, 'academic_year_id' => $year->id,
            'section' => 'B', 'shift' => 'vespertino', 'capacity' => 30,
        ]);

        $docenteUser = $this->makeStaffUser('docente', $team);
        $teacher = Teacher::create(['user_id' => $docenteUser->id, 'cedula' => '8-444-4444', 'first_name' => 'Sofía', 'last_name' => 'Bravo', 'shift' => 'matutino']);

        // El selector de aulas (calculado a partir del turno del docente) no debe ofrecer el aula vespertina.
        Livewire::actingAs($admin)
            ->test('pages::teachers.index')
            ->call('openAssignModal', $teacher->id)
            ->assertSee("{$grade->name}-A")
            ->assertDontSee("{$grade->name}-B");

        // Y si de todas formas se intenta forzar el aula equivocada, el backend la rechaza.
        Livewire::actingAs($admin)
            ->test('pages::teachers.index')
            ->call('openAssignModal', $teacher->id)
            ->set('assignMode', 'specialist')
            ->set('assignClassroomIds', [(string) $classroomVespertino->id])
            ->set('assignSubjectIds', [(string) $ingles->id])
            ->call('addAssignment')
            ->assertHasErrors('assignClassroomIds.0');

        $this->assertSame(0, SubjectAssignment::where('teacher_id', $teacher->id)->count());
    }
}
