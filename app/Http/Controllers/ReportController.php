<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\GradeScore;
use App\Models\HabitScore;
use App\Models\Institution;
use App\Models\Student;
use App\Models\SubjectAssignment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    public function boletin(Student $student): Response
    {
        $enrollment = $student->activeEnrollment()
            ->with(['classroom.grade.educationLevel', 'academicYear.periods', 'attendance'])
            ->first();

        $matrix = [];

        if ($enrollment) {
            foreach (GradeScore::where('enrollment_id', $enrollment->id)->with('subject')->get() as $score) {
                $matrix[$score->subject->name][$score->period_id] = $score->score;
            }
        }

        $institution = Institution::first();

        $habits = $institution?->habits()->orderBy('order')->get() ?? collect();
        $habitMatrix = [];

        if ($enrollment) {
            foreach (HabitScore::where('enrollment_id', $enrollment->id)->get() as $score) {
                $habitMatrix[$score->habit_id][$score->period_id] = $score->score;
            }
        }

        return Pdf::loadView('pdf.boletin', [
            'institution' => $institution,
            'logoPath' => $this->logoPath($institution),
            'student' => $student,
            'enrollment' => $enrollment,
            'matrix' => $matrix,
            'homeroomTeacher' => $enrollment ? $this->homeroomTeacher($enrollment) : null,
            'attendance' => $enrollment ? $this->attendanceSummary($enrollment) : [],
            'habits' => $habits,
            'habitMatrix' => $habitMatrix,
        ])->stream("boletin-{$student->id}.pdf");
    }

    public function constancia(Enrollment $enrollment): Response
    {
        $enrollment->load([
            'student.guardians',
            'classroom.grade.educationLevel',
            'academicYear',
            'registeredBy',
        ]);

        $institution = Institution::first();

        return Pdf::loadView('pdf.constancia', [
            'institution' => $institution,
            'logoPath' => $this->logoPath($institution),
            'enrollment' => $enrollment,
        ])->stream("constancia-{$enrollment->id}.pdf");
    }

    public function listado(Classroom $classroom): Response
    {
        $classroom->load('grade.educationLevel');

        $enrollments = Enrollment::where('classroom_id', $classroom->id)
            ->where('status', 'activo')
            ->with('student')
            ->get()
            ->sortBy(fn ($e) => $e->student->last_name);

        $institution = Institution::first();

        return Pdf::loadView('pdf.listado', [
            'institution' => $institution,
            'logoPath' => $this->logoPath($institution),
            'classroom' => $classroom,
            'enrollments' => $enrollments,
        ])->stream("listado-{$classroom->id}.pdf");
    }

    /**
     * El maestro de grado es quien tiene asignada alguna materia general
     * (no especializada) en el aula — no hay una tabla dedicada de "asesor
     * de aula" en uso todavía, así que se deriva de las asignaciones.
     */
    private function homeroomTeacher(Enrollment $enrollment): ?string
    {
        $assignment = SubjectAssignment::where('classroom_id', $enrollment->classroom_id)
            ->where('academic_year_id', $enrollment->academic_year_id)
            ->whereHas('subject', fn ($q) => $q->where('is_specialized', false))
            ->with('teacher')
            ->first();

        return $assignment?->teacher?->full_name;
    }

    /**
     * Cuenta ausencias y tardanzas por trimestre a partir de las fechas de
     * asistencia registradas — la tabla attendance no guarda period_id.
     *
     * @return array<int, array{ausencias: int, tardanzas: int}>
     */
    private function attendanceSummary(Enrollment $enrollment): array
    {
        $records = $enrollment->attendance;

        $summary = [];

        foreach ($enrollment->academicYear->periods as $period) {
            $inPeriod = $records->filter(
                fn ($record) => $record->date->between($period->start_date, $period->end_date)
            );

            $summary[$period->id] = [
                'ausencias' => $inPeriod->where('type', 'ausencia')->count(),
                'tardanzas' => $inPeriod->where('type', 'tardanza')->count(),
            ];
        }

        return $summary;
    }

    /**
     * DomPDF needs a real filesystem path (not a storage URL) to embed images.
     */
    private function logoPath(?Institution $institution): ?string
    {
        if (! $institution?->logo || ! Storage::disk('public')->exists($institution->logo)) {
            return null;
        }

        return Storage::disk('public')->path($institution->logo);
    }
}
