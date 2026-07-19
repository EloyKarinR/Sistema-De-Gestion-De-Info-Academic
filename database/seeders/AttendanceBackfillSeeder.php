<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Enrollment;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Genera ausencias y tardanzas variadas y realistas para cada matrícula
 * activa, solo dentro de los trimestres que ya comenzaron — nunca inventa
 * asistencia para un trimestre futuro. Es seguro correrlo varias veces:
 * nunca duplica un (matrícula, fecha, tipo) que ya exista.
 */
class AttendanceBackfillSeeder extends Seeder
{
    private const JUSTIFIED_ABSENCE_REASONS = [
        'Cita médica', 'Enfermedad', 'Viaje familiar', 'Trámite personal', 'Duelo familiar',
    ];

    private const JUSTIFIED_TARDINESS_REASONS = [
        'Problema de transporte', 'Tráfico', 'Cita médica en la mañana', 'Emergencia familiar',
    ];

    public function run(): void
    {
        $activeYear = AcademicYear::where('is_active', true)->first();

        if (! $activeYear) {
            return;
        }

        $today = now()->startOfDay();

        $periods = $activeYear->periods()->where('start_date', '<=', $today)->get();

        if ($periods->isEmpty()) {
            return;
        }

        $enrollments = Enrollment::where('status', 'activo')
            ->where('academic_year_id', $activeYear->id)
            ->with('attendance')
            ->get();

        DB::transaction(function () use ($enrollments, $periods, $today) {
            $rows = [];
            $now = now();

            foreach ($enrollments as $enrollment) {
                $existing = $enrollment->attendance
                    ->map(fn ($record) => $record->date->format('Y-m-d').'-'.$record->type)
                    ->flip();

                foreach ($periods as $period) {
                    $rangeEnd = $period->end_date->min($today);
                    $weekdays = $this->weekdaysBetween($period->start_date, $rangeEnd)->shuffle();

                    if ($weekdays->isEmpty()) {
                        continue;
                    }

                    $absenceCount = $this->randomAbsenceCount();
                    $tardyCount = $this->randomTardyCount();

                    foreach ($weekdays->take($absenceCount) as $date) {
                        $key = $date->format('Y-m-d').'-ausencia';

                        if (isset($existing[$key])) {
                            continue;
                        }

                        $justified = fake()->boolean(35);

                        $rows[] = [
                            'enrollment_id' => $enrollment->id,
                            'date' => $date->format('Y-m-d'),
                            'type' => 'ausencia',
                            'justified' => $justified,
                            'reason' => $justified ? fake()->randomElement(self::JUSTIFIED_ABSENCE_REASONS) : null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    foreach ($weekdays->slice($absenceCount, $tardyCount) as $date) {
                        $key = $date->format('Y-m-d').'-tardanza';

                        if (isset($existing[$key])) {
                            continue;
                        }

                        $justified = fake()->boolean(30);

                        $rows[] = [
                            'enrollment_id' => $enrollment->id,
                            'date' => $date->format('Y-m-d'),
                            'type' => 'tardanza',
                            'justified' => $justified,
                            'reason' => $justified ? fake()->randomElement(self::JUSTIFIED_TARDINESS_REASONS) : null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                // Inserta en lotes para no acumular demasiado en memoria.
                if (count($rows) >= 1000) {
                    DB::table('attendance')->insert($rows);
                    $rows = [];
                }
            }

            if ($rows !== []) {
                DB::table('attendance')->insert($rows);
            }
        });
    }

    /**
     * @return Collection<int, CarbonInterface>
     */
    private function weekdaysBetween($start, $end)
    {
        if ($start->greaterThan($end)) {
            return collect();
        }

        return collect(CarbonPeriod::create($start, $end))
            ->filter(fn ($date) => ! $date->isWeekend())
            ->values();
    }

    /**
     * La mayoría de los estudiantes faltan poco; unos pocos faltan más —
     * como en un salón real.
     */
    private function randomAbsenceCount(): int
    {
        $weighted = [0, 0, 0, 1, 1, 1, 2, 2, 3, 4, 5];

        return $weighted[array_rand($weighted)];
    }

    private function randomTardyCount(): int
    {
        $weighted = [0, 0, 1, 1, 1, 2, 2, 3, 4];

        return $weighted[array_rand($weighted)];
    }
}
