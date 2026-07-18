<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Rellena notas para las matrículas que no tienen ninguna todavía — el caso
 * típico es el roster masivo de estudiantes (StudentRosterSeeder), que se
 * siembra después de GradeScoreSeeder y por eso se queda sin notas. Genera
 * una nota por cada materia asignada a su aula y cada trimestre del año
 * activo, con valores aleatorios pero realistas (escala 1.0-5.0).
 */
class GradeScoreBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        if (! $activeYear) {
            return;
        }

        $periods = $activeYear->periods()->pluck('id');

        if ($periods->isEmpty()) {
            return;
        }

        $enrollments = Enrollment::where('status', 'activo')
            ->where('academic_year_id', $activeYear->id)
            ->whereDoesntHave('gradeScores')
            ->with('classroom.subjectAssignments')
            ->get();

        DB::transaction(function () use ($enrollments, $periods) {
            $rows = [];
            $now = now();

            foreach ($enrollments as $enrollment) {
                foreach ($enrollment->classroom->subjectAssignments as $assignment) {
                    foreach ($periods as $periodId) {
                        $rows[] = [
                            'enrollment_id' => $enrollment->id,
                            'subject_id' => $assignment->subject_id,
                            'period_id' => $periodId,
                            'score' => $this->randomScore(),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                // Inserta en lotes para no acumular demasiado en memoria.
                if (count($rows) >= 1000) {
                    DB::table('grade_scores')->insert($rows);
                    $rows = [];
                }
            }

            if ($rows !== []) {
                DB::table('grade_scores')->insert($rows);
            }
        });
    }

    /**
     * Escala 1.0-5.0, con más peso hacia notas de aprobado (3.0+) como es
     * realista en un salón de clase típico.
     */
    private function randomScore(): float
    {
        $weighted = [
            1.5, 2.0, 2.3, 2.5,
            3.0, 3.0, 3.2, 3.5, 3.5, 3.7,
            4.0, 4.0, 4.2, 4.3, 4.5, 4.5, 4.7,
            5.0, 5.0,
        ];

        return $weighted[array_rand($weighted)];
    }
}
