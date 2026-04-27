<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $academicYear = AcademicYear::where('is_active', true)->first();
        $grade4       = Grade::where('number', 4)->first();

        $classroom = Classroom::create([
            'grade_id'        => $grade4->id,
            'academic_year_id'=> $academicYear->id,
            'section'         => 'D',
            'shift'           => 'matutino',
            'capacity'        => 30,
        ]);

        $studentsData = [
            ['cedula' => '1-782-1109', 'first_name' => 'Osmar Jesse', 'last_name' => 'Bowie Miller',  'sex' => 'M', 'birth_date' => '2015-03-10'],
            ['cedula' => '1-800-2234', 'first_name' => 'María',       'last_name' => 'González Ruiz', 'sex' => 'F', 'birth_date' => '2015-06-22'],
            ['cedula' => '1-801-3345', 'first_name' => 'Carlos',      'last_name' => 'Pérez López',   'sex' => 'M', 'birth_date' => '2015-01-15'],
            ['cedula' => '1-802-4456', 'first_name' => 'Sofía',       'last_name' => 'Rodríguez Ávila','sex' => 'F', 'birth_date' => '2015-09-08'],
            ['cedula' => '1-803-5567', 'first_name' => 'Luis',        'last_name' => 'Martínez Cruz', 'sex' => 'M', 'birth_date' => '2015-04-30'],
        ];

        $registeredBy = User::role('secretaria')->first();

        foreach ($studentsData as $data) {
            $student = Student::create(array_merge($data, [
                'address' => 'Almirante, Bocas del Toro',
            ]));

            $guardian = Guardian::create([
                'cedula'        => '8-' . rand(100, 999) . '-' . rand(1000, 9999),
                'first_name'    => 'Acudiente de',
                'last_name'     => $data['last_name'],
                'relationship'  => 'padre',
                'primary_phone' => '6000-' . rand(1000, 9999),
            ]);

            $student->guardians()->attach($guardian->id, ['is_primary' => true]);

            $student->enrollments()->create([
                'classroom_id'        => $classroom->id,
                'academic_year_id'    => $academicYear->id,
                'registered_by'       => $registeredBy->id,
                'enrollment_date'     => '2026-03-03',
                'status'              => 'activo',
                'enrollment_type'     => 'promovido',
                'doc_cedula_student'  => true,
                'doc_cedula_guardian' => true,
                'doc_boletin'         => true,
                'doc_foto'            => true,
                'doc_address'         => true,
            ]);
        }
    }
}
