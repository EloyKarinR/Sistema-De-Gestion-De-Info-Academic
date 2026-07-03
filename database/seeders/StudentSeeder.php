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
        $registeredBy = User::role('secretaria')->first();

        $classroomsData = [
            [
                'grade_number' => 3, 'section' => 'A', 'shift' => 'matutino', 'capacity' => 28,
                'students' => [
                    ['cedula' => '1-810-1122', 'first_name' => 'Fernando', 'last_name' => 'Castillo Ríos', 'sex' => 'M', 'birth_date' => '2016-02-14', 'guardian' => ['first_name' => 'Roberto', 'last_name' => 'Castillo Solís', 'relationship' => 'padre', 'phone' => '6011-2233']],
                    ['cedula' => '1-811-2233', 'first_name' => 'Valentina', 'last_name' => 'Ortega Nieto', 'sex' => 'F', 'birth_date' => '2016-05-03', 'guardian' => ['first_name' => 'Yaritza', 'last_name' => 'Nieto Campos', 'relationship' => 'madre', 'phone' => '6022-3344']],
                    ['cedula' => '1-812-3344', 'first_name' => 'Diego', 'last_name' => 'Vargas Herrera', 'sex' => 'M', 'birth_date' => '2016-08-21', 'guardian' => ['first_name' => 'Elena', 'last_name' => 'Herrera Quintero', 'relationship' => 'madre', 'phone' => '6033-4455']],
                    ['cedula' => '1-813-4455', 'first_name' => 'Camila', 'last_name' => 'Rojas Delgado', 'sex' => 'F', 'birth_date' => '2016-11-09', 'guardian' => ['first_name' => 'Manuel', 'last_name' => 'Rojas Bermúdez', 'relationship' => 'padre', 'phone' => '6044-5566']],
                ],
            ],
            [
                'grade_number' => 4, 'section' => 'D', 'shift' => 'matutino', 'capacity' => 30,
                'students' => [
                    ['cedula' => '1-782-1109', 'first_name' => 'Osmar Jesse', 'last_name' => 'Bowie Miller', 'sex' => 'M', 'birth_date' => '2015-03-10', 'guardian' => ['first_name' => 'Rosa', 'last_name' => 'Miller Prescott', 'relationship' => 'madre', 'phone' => '6055-6677']],
                    ['cedula' => '1-800-2234', 'first_name' => 'María', 'last_name' => 'González Ruiz', 'sex' => 'F', 'birth_date' => '2015-06-22', 'guardian' => ['first_name' => 'Julio', 'last_name' => 'González Batista', 'relationship' => 'padre', 'phone' => '6066-7788']],
                    ['cedula' => '1-801-3345', 'first_name' => 'Carlos', 'last_name' => 'Pérez López', 'sex' => 'M', 'birth_date' => '2015-01-15', 'guardian' => ['first_name' => 'Marta', 'last_name' => 'López Sáez', 'relationship' => 'madre', 'phone' => '6077-8899']],
                    ['cedula' => '1-802-4456', 'first_name' => 'Sofía', 'last_name' => 'Rodríguez Ávila', 'sex' => 'F', 'birth_date' => '2015-09-08', 'guardian' => ['first_name' => 'Ricardo', 'last_name' => 'Rodríguez Núñez', 'relationship' => 'padre', 'phone' => '6088-9900']],
                    ['cedula' => '1-803-5567', 'first_name' => 'Luis', 'last_name' => 'Martínez Cruz', 'sex' => 'M', 'birth_date' => '2015-04-30', 'guardian' => ['first_name' => 'Ana', 'last_name' => 'Cruz Villareal', 'relationship' => 'madre', 'phone' => '6099-0011']],
                ],
            ],
            [
                'grade_number' => 6, 'section' => 'B', 'shift' => 'vespertino', 'capacity' => 25,
                'students' => [
                    ['cedula' => '1-820-6678', 'first_name' => 'Isabella', 'last_name' => 'Chen Aguilar', 'sex' => 'F', 'birth_date' => '2013-07-19', 'guardian' => ['first_name' => 'Wong', 'last_name' => 'Chen Fu', 'relationship' => 'padre', 'phone' => '6100-1122']],
                    ['cedula' => '1-821-7789', 'first_name' => 'Mateo', 'last_name' => 'Guerra Solano', 'sex' => 'M', 'birth_date' => '2013-12-02', 'guardian' => ['first_name' => 'Patricia', 'last_name' => 'Solano Vega', 'relationship' => 'madre', 'phone' => '6111-2233']],
                    ['cedula' => '1-822-8890', 'first_name' => 'Renata', 'last_name' => 'Jiménez Botello', 'sex' => 'F', 'birth_date' => '2013-03-27', 'guardian' => ['first_name' => 'Óscar', 'last_name' => 'Jiménez Ureña', 'relationship' => 'padre', 'phone' => '6122-3344']],
                ],
            ],
        ];

        foreach ($classroomsData as $classroomInfo) {
            $grade = Grade::where('number', $classroomInfo['grade_number'])->first();

            $classroom = Classroom::create([
                'grade_id' => $grade->id,
                'academic_year_id' => $academicYear->id,
                'section' => $classroomInfo['section'],
                'shift' => $classroomInfo['shift'],
                'capacity' => $classroomInfo['capacity'],
            ]);

            foreach ($classroomInfo['students'] as $data) {
                $guardianData = $data['guardian'];
                unset($data['guardian']);

                $student = Student::create(array_merge($data, [
                    'address' => 'Almirante, Bocas del Toro',
                ]));

                $guardian = Guardian::create([
                    'cedula' => '8-'.rand(100, 999).'-'.rand(1000, 9999),
                    'first_name' => $guardianData['first_name'],
                    'last_name' => $guardianData['last_name'],
                    'relationship' => $guardianData['relationship'],
                    'primary_phone' => $guardianData['phone'],
                ]);

                $student->guardians()->attach($guardian->id, ['is_primary' => true]);

                $student->enrollments()->create([
                    'classroom_id' => $classroom->id,
                    'academic_year_id' => $academicYear->id,
                    'registered_by' => $registeredBy->id,
                    'enrollment_date' => '2026-03-03',
                    'status' => 'activo',
                    'enrollment_type' => 'promovido',
                    'doc_cedula_student' => true,
                    'doc_cedula_guardian' => true,
                    'doc_boletin' => true,
                    'doc_foto' => true,
                    'doc_address' => true,
                ]);
            }
        }
    }
}
