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

        $sampleScores = [2.8, 3.0, 3.2, 3.5, 3.7, 3.9, 4.0, 4.2, 4.5, 4.7, 4.8, 5.0];
        $i = 0;

        foreach (Enrollment::with('classroom.grade.subjects')->get() as $enrollment) {
            // Inglés: todos los estudiantes cuyo grado la tenga en su plan de
            // estudio (no aplica a Preescolar).
            if ($enrollment->classroom->grade->subjects->contains('id', $ingles->id)) {
                GradeScore::create([
                    'enrollment_id' => $enrollment->id,
                    'subject_id' => $ingles->id,
                    'period_id' => $period->id,
                    'score' => $sampleScores[$i % count($sampleScores)],
                ]);
                $i++;
            }

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
