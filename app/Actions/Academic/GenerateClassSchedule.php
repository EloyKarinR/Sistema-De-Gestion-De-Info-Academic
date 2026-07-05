<?php

namespace App\Actions\Academic;

use App\Models\Classroom;
use App\Models\ClassSchedule;

/**
 * Genera el horario semanal (lunes a viernes) de un aula a partir de las
 * materias ya asignadas (subject_assignments). Decisiones propias, no
 * verificadas contra un currículo oficial — ajustar si hace falta:
 *
 * - 6 períodos de 45 min por turno + 1 receso de 15 min.
 * - Cada materia aparece más o menos veces por semana según un peso relativo
 *   (Español/Matemática más veces que Religión, por ejemplo), repartido con
 *   el método de "mayor resto" para que la suma cierre exacta en 30
 *   bloques/semana por aula.
 * - Al armar cada día se evita repetir una materia si todavía queda otra
 *   disponible, para no dejar un día entero con solo 2-3 materias.
 */
class GenerateClassSchedule
{
    private const SLOTS_PER_WEEK = 30; // 6 períodos × 5 días

    /** @var array<int, array{0: string, 1: string}> */
    private array $matutinoPeriods = [
        1 => ['07:00', '07:45'],
        2 => ['07:45', '08:30'],
        3 => ['08:30', '09:15'],
        4 => ['09:30', '10:15'], // receso 09:15–09:30
        5 => ['10:15', '11:00'],
        6 => ['11:00', '11:45'],
    ];

    /** @var array<int, array{0: string, 1: string}> */
    private array $vespertinoPeriods = [
        1 => ['12:15', '13:00'],
        2 => ['13:00', '13:45'],
        3 => ['13:45', '14:30'],
        4 => ['14:45', '15:30'], // receso 14:30–14:45
        5 => ['15:30', '16:15'],
        6 => ['16:15', '17:00'],
    ];

    /** @var array<string, int> */
    private array $subjectWeights = [
        'Español' => 7,
        'Matemática' => 7,
        'Ciencias Naturales' => 4,
        'Ciencias Sociales' => 4,
        'Religión, Moral y Valores' => 3,
        'Inglés' => 5,
        'Salud y Educación Física' => 4,
        'Expresión Artística' => 3,
        'Tecnologías' => 2,
        'Desarrollo Personal y Social' => 6,
        'Comunicación y Lenguaje' => 8,
        'Pensamiento Lógico-Matemático' => 6,
        'Expresión Corporal y Psicomotricidad' => 5,
    ];

    /**
     * Genera el horario del aula. No hace nada si ya tiene uno, o si todavía
     * no tiene ninguna materia asignada.
     */
    public function handle(Classroom $classroom): void
    {
        if ($classroom->classSchedules()->exists()) {
            return;
        }

        $assignments = $classroom->subjectAssignments()->with('subject')->get();

        if ($assignments->isEmpty()) {
            return;
        }

        $remaining = $this->distributeWeeklyCounts($assignments);
        $periods = $classroom->shift === 'vespertino' ? $this->vespertinoPeriods : $this->matutinoPeriods;

        foreach (range(1, 5) as $day) {
            $usedToday = [];

            foreach (range(1, 6) as $period) {
                if (empty($remaining)) {
                    break;
                }

                // Prefiere una materia que no se haya dado ya ese mismo día;
                // solo repite si no queda ninguna otra materia disponible.
                $candidates = array_diff_key($remaining, $usedToday);
                $pool = $candidates ?: $remaining;

                arsort($pool);
                $assignmentId = array_key_first($pool);

                [$start, $end] = $periods[$period];

                ClassSchedule::create([
                    'classroom_id' => $classroom->id,
                    'subject_assignment_id' => $assignmentId,
                    'day_of_week' => $day,
                    'start_time' => $start,
                    'end_time' => $end,
                ]);

                $usedToday[$assignmentId] = true;
                $remaining[$assignmentId]--;
                if ($remaining[$assignmentId] <= 0) {
                    unset($remaining[$assignmentId]);
                }
            }
        }
    }

    /**
     * Reparte 30 bloques/semana entre las materias de la aula, proporcional
     * a su peso relativo, usando el método de mayor resto para que la suma
     * cierre exacta.
     *
     * @return array<int, int> subject_assignment_id => bloques/semana
     */
    private function distributeWeeklyCounts($assignments): array
    {
        $weights = $assignments->mapWithKeys(
            fn ($a) => [$a->id => $this->subjectWeights[$a->subject->name] ?? 4]
        );

        $weightSum = $weights->sum();

        $raw = $weights->map(fn ($w) => $w / $weightSum * self::SLOTS_PER_WEEK);
        $counts = $raw->map(fn ($c) => (int) floor($c))->all();

        $remainder = self::SLOTS_PER_WEEK - array_sum($counts);

        $fractions = $raw->map(fn ($c, $id) => $c - floor($c))->sortDesc();

        foreach ($fractions->keys()->take($remainder) as $id) {
            $counts[$id] = $counts[$id] + 1;
        }

        return $counts;
    }
}
