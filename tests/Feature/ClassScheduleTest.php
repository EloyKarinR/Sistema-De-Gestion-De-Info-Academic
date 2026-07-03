<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\SubjectAssignment;
use App\Models\Teacher;
use Database\Seeders\ClassScheduleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class ClassScheduleTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_el_horario_generado_cubre_30_bloques_sin_amontonar_una_materia_en_un_dia(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);

        $matematica = $institution->subjects()->create(['name' => 'Matemática']);
        $espanol = $institution->subjects()->create(['name' => 'Español']);
        $ingles = $institution->subjects()->create(['name' => 'Inglés', 'is_specialized' => true]);
        $grade->subjects()->attach([$matematica->id, $espanol->id, $ingles->id]);

        $classroom = $this->makeClassroom($grade, $year);

        $teacher = Teacher::create(['user_id' => $this->makeStaffUser('docente', $team)->id, 'cedula' => '8-999-0000', 'first_name' => 'Carlos', 'last_name' => 'Mendoza']);
        SubjectAssignment::create(['teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'subject_id' => $matematica->id, 'academic_year_id' => $year->id]);
        SubjectAssignment::create(['teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'subject_id' => $espanol->id, 'academic_year_id' => $year->id]);
        SubjectAssignment::create(['teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'subject_id' => $ingles->id, 'academic_year_id' => $year->id]);

        (new ClassScheduleSeeder)->run();

        $schedules = $classroom->classSchedules()->with('subjectAssignment.subject')->get();

        $this->assertSame(30, $schedules->count());

        // Ninguna materia debería repetirse más de 3 veces en un mismo día
        // (con 3 materias disponibles y 6 bloques/día, lo esperable es 2 cada una).
        foreach ($schedules->groupBy('day_of_week') as $dayEntries) {
            $maxRepeats = $dayEntries->groupBy(fn ($s) => $s->subjectAssignment->subject->name)->map->count()->max();
            $this->assertLessThanOrEqual(3, $maxRepeats, 'Una materia se repitió demasiadas veces en un mismo día.');
        }
    }

    public function test_el_horario_se_ve_en_academico_y_en_el_portal(): void
    {
        $this->seed(RoleSeeder::class);

        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);
        $acudienteUser = $this->makeStaffUser('acudiente', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $matematica = $institution->subjects()->create(['name' => 'Matemática']);
        $grade->subjects()->attach($matematica->id);
        $classroom = $this->makeClassroom($grade, $year);

        $teacher = Teacher::create(['user_id' => $this->makeStaffUser('docente', $team)->id, 'cedula' => '8-999-0001', 'first_name' => 'Carlos', 'last_name' => 'Mendoza']);
        SubjectAssignment::create(['teacher_id' => $teacher->id, 'classroom_id' => $classroom->id, 'subject_id' => $matematica->id, 'academic_year_id' => $year->id]);

        (new ClassScheduleSeeder)->run();

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        Enrollment::create(['student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id, 'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso']);
        $guardian = Guardian::create(['first_name' => 'Marta', 'last_name' => 'Pérez', 'relationship' => 'madre', 'primary_phone' => '6000-0000', 'user_id' => $acudienteUser->id]);
        $student->guardians()->attach($guardian->id, ['is_primary' => true]);

        Livewire::actingAs($admin)
            ->test('pages::academic.index')
            ->call('viewSchedule', $classroom->id)
            ->assertSee('Matemática')
            ->assertSee('Carlos Mendoza');

        Livewire::actingAs($acudienteUser)
            ->test('pages::portal.index')
            ->assertSee('Horario semanal')
            ->assertSee('Matemática');
    }
}
