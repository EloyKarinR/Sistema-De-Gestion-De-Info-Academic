<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\SubjectAssignment;
use App\Models\Teacher;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_docente_puede_registrar_ausencias_y_tardanzas_de_su_aula(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $matematica = $institution->subjects()->create(['name' => 'Matemática']);
        $classroom = $this->makeClassroom($grade, $year, 'A');

        $studentA = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $studentB = Student::create(['first_name' => 'Beto', 'last_name' => 'Gómez', 'birth_date' => '2018-01-01', 'sex' => 'M', 'address' => 'Calle 2']);

        $enrollmentA = Enrollment::create([
            'student_id' => $studentA->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);
        $enrollmentB = Enrollment::create([
            'student_id' => $studentB->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        $docenteUser = $this->makeStaffUser('docente', $team);
        $teacher = Teacher::create(['user_id' => $docenteUser->id, 'cedula' => '8-000-0000', 'first_name' => 'Carlos', 'last_name' => 'Mendoza', 'shift' => 'matutino']);
        SubjectAssignment::create([
            'teacher_id' => $teacher->id, 'classroom_id' => $classroom->id,
            'subject_id' => $matematica->id, 'academic_year_id' => $year->id,
        ]);

        Livewire::actingAs($docenteUser)
            ->test('pages::attendance.index')
            ->set('classroomId', (string) $classroom->id)
            ->set('date', '2026-03-10')
            ->set("statuses.{$enrollmentA->id}", 'ausencia')
            ->set("justified.{$enrollmentA->id}", true)
            ->set("reasons.{$enrollmentA->id}", 'Cita médica')
            ->call('saveAttendance')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('attendance', [
            'enrollment_id' => $enrollmentA->id, 'type' => 'ausencia',
            'justified' => true, 'reason' => 'Cita médica',
        ]);
        $this->assertTrue(Attendance::where('enrollment_id', $enrollmentA->id)->whereDate('date', '2026-03-10')->exists());

        // Beto quedó "presente" (por defecto) — no debe generar ninguna fila.
        $this->assertDatabaseMissing('attendance', ['enrollment_id' => $enrollmentB->id]);
    }

    public function test_cambiar_de_ausencia_a_tardanza_no_deja_un_registro_duplicado(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $matematica = $institution->subjects()->create(['name' => 'Matemática']);
        $classroom = $this->makeClassroom($grade, $year, 'A');

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        $docenteUser = $this->makeStaffUser('docente', $team);
        $teacher = Teacher::create(['user_id' => $docenteUser->id, 'cedula' => '8-000-0000', 'first_name' => 'Carlos', 'last_name' => 'Mendoza', 'shift' => 'matutino']);
        SubjectAssignment::create([
            'teacher_id' => $teacher->id, 'classroom_id' => $classroom->id,
            'subject_id' => $matematica->id, 'academic_year_id' => $year->id,
        ]);

        Livewire::actingAs($docenteUser)
            ->test('pages::attendance.index')
            ->set('classroomId', (string) $classroom->id)
            ->set('date', '2026-03-10')
            ->set("statuses.{$enrollment->id}", 'ausencia')
            ->call('saveAttendance')
            ->assertHasNoErrors();

        $this->assertSame(1, Attendance::where('enrollment_id', $enrollment->id)->whereDate('date', '2026-03-10')->count());

        Livewire::actingAs($docenteUser)
            ->test('pages::attendance.index')
            ->set('classroomId', (string) $classroom->id)
            ->set('date', '2026-03-10')
            ->set("statuses.{$enrollment->id}", 'tardanza')
            ->call('saveAttendance')
            ->assertHasNoErrors();

        $this->assertSame(1, Attendance::where('enrollment_id', $enrollment->id)->whereDate('date', '2026-03-10')->count());
        $this->assertDatabaseHas('attendance', ['enrollment_id' => $enrollment->id, 'type' => 'tardanza']);
    }

    public function test_docente_no_puede_registrar_asistencia_de_un_aula_que_no_tiene_asignada(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year, 'A');

        $docenteUser = $this->makeStaffUser('docente', $team);
        Teacher::create(['user_id' => $docenteUser->id, 'cedula' => '8-000-0000', 'first_name' => 'Carlos', 'last_name' => 'Mendoza', 'shift' => 'matutino']);

        Livewire::actingAs($docenteUser)
            ->test('pages::attendance.index')
            ->set('classroomId', (string) $classroom->id)
            ->set('date', '2026-03-10')
            ->call('saveAttendance')
            ->assertForbidden();
    }

    public function test_secretaria_solo_puede_ver_asistencia_no_registrarla(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $secretaria = $this->makeStaffUser('secretaria', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year, 'A');

        Livewire::actingAs($secretaria)
            ->test('pages::attendance.index')
            ->set('classroomId', (string) $classroom->id)
            ->set('date', '2026-03-10')
            ->call('saveAttendance')
            ->assertForbidden();
    }
}
