<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\Student;
use Database\Seeders\AttendanceBackfillSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SigaTestHelpers;
use Tests\TestCase;

class AttendanceBackfillSeederTest extends TestCase
{
    use RefreshDatabase, SigaTestHelpers;

    public function test_rellena_asistencia_solo_en_los_trimestres_ya_iniciados(): void
    {
        $this->travelTo('2026-06-15');

        $this->seed(RoleSeeder::class);
        $team = $this->makeTeam();
        $admin = $this->makeStaffUser('admin', $team);

        $institution = $this->makeInstitution();
        $grade = $this->makeGrade($institution);
        $year = $this->makeActiveYear($institution);
        $classroom = $this->makeClassroom($grade, $year);

        $student = Student::create(['first_name' => 'Ana', 'last_name' => 'Pérez', 'birth_date' => '2018-01-01', 'sex' => 'F', 'address' => 'Calle 2']);
        $enrollment = Enrollment::create([
            'student_id' => $student->id, 'classroom_id' => $classroom->id, 'academic_year_id' => $year->id,
            'registered_by' => $admin->id, 'enrollment_date' => '2026-02-01', 'status' => 'activo', 'enrollment_type' => 'nuevo_ingreso',
        ]);

        $this->seed(AttendanceBackfillSeeder::class);

        $records = Attendance::where('enrollment_id', $enrollment->id)->get();

        // El III Trimestre (2026-08-02 en adelante) todavía no empezó a esta fecha.
        $this->assertTrue($records->every(fn ($record) => $record->date->lessThan('2026-06-15')));

        // Correrlo dos veces no debe duplicar nada.
        $this->seed(AttendanceBackfillSeeder::class);
        $this->assertSame($records->count(), Attendance::where('enrollment_id', $enrollment->id)->count());
    }
}
