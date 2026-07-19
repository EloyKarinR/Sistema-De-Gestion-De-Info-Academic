<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Habit;
use App\Models\Institution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Completa los hábitos y actitudes que le falten a cualquier matrícula
 * activa, solo para los trimestres que ya comenzaron — nunca inventa
 * hábitos de un trimestre futuro. Es seguro correrlo varias veces: nunca
 * toca un (matrícula, hábito, trimestre) que ya tenga calificación.
 */
class HabitScoreBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        if (! $activeYear) {
            return;
        }

        $today = now()->startOfDay();
        $periods = $activeYear->periods()->where('start_date', '<=', $today)->pluck('id');

        if ($periods->isEmpty()) {
            return;
        }

        $institution = Institution::first();
        $habits = $institution ? Habit::where('institution_id', $institution->id)->pluck('id') : collect();

        if ($habits->isEmpty()) {
            return;
        }

        $enrollments = Enrollment::where('status', 'activo')
            ->where('academic_year_id', $activeYear->id)
            ->with('habitScores')
            ->get();

        DB::transaction(function () use ($enrollments, $periods, $habits) {
            $rows = [];
            $now = now();

            foreach ($enrollments as $enrollment) {
                $existing = $enrollment->habitScores
                    ->map(fn ($score) => $score->habit_id.'-'.$score->period_id)
                    ->flip();

                foreach ($habits as $habitId) {
                    foreach ($periods as $periodId) {
                        $key = $habitId.'-'.$periodId;

                        if (isset($existing[$key])) {
                            continue;
                        }

                        $rows[] = [
                            'enrollment_id' => $enrollment->id,
                            'habit_id' => $habitId,
                            'period_id' => $periodId,
                            'score' => $this->randomScore(),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                // Inserta en lotes para no acumular demasiado en memoria.
                if (count($rows) >= 1000) {
                    DB::table('habit_scores')->insert($rows);
                    $rows = [];
                }
            }

            if ($rows !== []) {
                DB::table('habit_scores')->insert($rows);
            }
        });
    }

    /**
     * La mayoría de los estudiantes se comportan bien (S); un "R" ocasional
     * es normal; "X" es raro — como en un salón real.
     */
    private function randomScore(): string
    {
        $weighted = ['S', 'S', 'S', 'S', 'S', 'S', 'S', 'R', 'R', 'X'];

        return $weighted[array_rand($weighted)];
    }
}
