<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Classroom;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Rellena cada aula hasta ~70% de su capacidad con estudiantes ficticios
 * (más un acudiente cada uno), para que la demo no se vea con salones vacíos.
 * Nunca reduce lo que ya haya — solo agrega los cupos que falten.
 */
class StudentRosterSeeder extends Seeder
{
    /** @var array<int, string> */
    private array $maleFirstNames = [
        'Sebastián', 'Mateo', 'Diego', 'Emiliano', 'Adrián', 'Gabriel', 'Samuel', 'Iker', 'Bruno', 'Kevin',
        'Josué', 'Aarón', 'Emilio', 'Dylan', 'Ismael', 'Julián', 'Ángel', 'Yandel', 'Jesús', 'Anthony',
    ];

    /** @var array<int, string> */
    private array $femaleFirstNames = [
        'Valentina', 'Camila', 'Isabella', 'Mariana', 'Génesis', 'Ashley', 'Britany', 'Nicole', 'Emily', 'Paola',
        'Yaretzi', 'Ariana', 'Michelle', 'Kiara', 'Melany', 'Dulce', 'Fátima', 'Alondra', 'Naomi', 'Zoé',
    ];

    /** @var array<int, string> */
    private array $lastNames = [
        'Aguilar', 'Bernal', 'Cedeño', 'Domínguez', 'Espino', 'Franco', 'Guerrero', 'Him', 'Icaza', 'Justavino',
        'Kam', 'Lezcano', 'Miranda', 'Nieto', 'Ortiz', 'Pinto', 'Quintero', 'Reyes', 'Samaniego', 'Tejada',
        'Ureña', 'Vega', 'Wong', 'Ábrego', 'Barsallo', 'Chen', 'De León', 'Escobar', 'Fuentes', 'Grajales',
    ];

    private int $nameIndex = 0;

    private int $studentCedulaCounter = 900;

    private int $guardianCedulaCounter = 900;

    public function run(): void
    {
        $year = AcademicYear::where('is_active', true)->first();
        $registeredBy = User::role('secretaria')->first();

        DB::transaction(function () use ($year, $registeredBy) {
            foreach (Classroom::where('academic_year_id', $year->id)->with('grade')->get() as $classroom) {
                $currentCount = $classroom->enrollments()->where('status', 'activo')->count();
                $target = (int) floor($classroom->capacity * 0.7);
                $toCreate = max(0, $target - $currentCount);

                for ($i = 0; $i < $toCreate; $i++) {
                    $this->createEnrolledStudent($classroom, $year, $registeredBy);
                }
            }
        });
    }

    private function createEnrolledStudent(Classroom $classroom, AcademicYear $year, User $registeredBy): void
    {
        $isMale = $this->nameIndex % 2 === 0;
        $firstName = $isMale
            ? $this->maleFirstNames[$this->nameIndex % count($this->maleFirstNames)]
            : $this->femaleFirstNames[$this->nameIndex % count($this->femaleFirstNames)];
        $lastName = $this->lastNames[$this->nameIndex % count($this->lastNames)];
        $this->nameIndex++;

        $age = $this->approximateAge($classroom->grade->name, $classroom->grade->number);
        $birthYear = $year->year - $age;
        $birthDate = sprintf('%d-%02d-%02d', $birthYear, random_int(1, 12), random_int(1, 28));

        $this->studentCedulaCounter++;
        $studentCedula = '1-'.$this->studentCedulaCounter.'-'.str_pad((string) ($this->studentCedulaCounter * 7 % 9999), 4, '0', STR_PAD_LEFT);

        $student = Student::create([
            'cedula' => $studentCedula,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'birth_date' => $birthDate,
            'sex' => $isMale ? 'M' : 'F',
            'address' => 'Almirante, Bocas del Toro',
        ]);

        $guardianFirstName = $isMale
            ? $this->femaleFirstNames[($this->nameIndex + 3) % count($this->femaleFirstNames)]
            : $this->maleFirstNames[($this->nameIndex + 3) % count($this->maleFirstNames)];

        $this->guardianCedulaCounter++;
        $guardianCedula = '8-'.$this->guardianCedulaCounter.'-'.str_pad((string) ($this->guardianCedulaCounter * 11 % 9999), 4, '0', STR_PAD_LEFT);

        $guardian = Guardian::create([
            'cedula' => $guardianCedula,
            'first_name' => $guardianFirstName,
            'last_name' => $lastName,
            'relationship' => $isMale ? 'madre' : 'padre',
            'primary_phone' => '6'.random_int(100, 999).'-'.random_int(1000, 9999),
        ]);

        $student->guardians()->attach($guardian->id, ['is_primary' => true]);

        $student->enrollments()->create([
            'classroom_id' => $classroom->id,
            'academic_year_id' => $year->id,
            'registered_by' => $registeredBy->id,
            'enrollment_date' => $year->start_date,
            'status' => 'activo',
            'enrollment_type' => 'nuevo_ingreso',
            'doc_cedula_student' => true,
            'doc_cedula_guardian' => true,
            'doc_boletin' => false,
            'doc_foto' => true,
            'doc_address' => true,
        ]);
    }

    private function approximateAge(string $gradeName, ?int $number): int
    {
        return match (true) {
            $gradeName === 'Pre-Kinder' => 4,
            $gradeName === 'Kinder' => 5,
            $number !== null => 5 + $number,
            default => 6,
        };
    }
}
