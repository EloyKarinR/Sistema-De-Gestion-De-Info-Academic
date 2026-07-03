<?php

namespace Database\Seeders;

use App\Models\Enrollment;
use App\Models\GradeScore;
use App\Models\Period;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class GradeScoreSeeder extends Seeder
{
    public function run(): void
    {
        $period = Period::where('number', 1)->first();
        $ingles = Subject::where('name', 'Inglés')->first();

        // Materias que da Barbara Wilson (maestra de grado) solo en 4°-D
        $maestraDeGrado = Subject::whereIn('name', [
            'Español', 'Matemática', 'Ciencias Sociales', 'Ciencias Naturales', 'Religión, Moral y Valores',
        ])->get();

        $sampleScores = [72.0, 78.5, 83.0, 85.5, 88.0, 90.5, 91.0, 93.5, 95.0, 96.5, 98.0, 100.0];
        $i = 0;

        foreach (Enrollment::with('classroom.grade')->get() as $enrollment) {
            // Inglés: todos los estudiantes (Karla Sánchez da clases en todas las aulas)
            GradeScore::create([
                'enrollment_id' => $enrollment->id,
                'subject_id' => $ingles->id,
                'period_id' => $period->id,
                'score' => $sampleScores[$i % count($sampleScores)],
            ]);
            $i++;

            // Las materias de Barbara Wilson: solo su aula (4°-D)
            if ($enrollment->classroom->grade->number === 4 && $enrollment->classroom->section === 'D') {
                foreach ($maestraDeGrado as $subject) {
                    GradeScore::create([
                        'enrollment_id' => $enrollment->id,
                        'subject_id' => $subject->id,
                        'period_id' => $period->id,
                        'score' => $sampleScores[$i % count($sampleScores)],
                    ]);
                    $i++;
                }
            }
        }
    }
}
