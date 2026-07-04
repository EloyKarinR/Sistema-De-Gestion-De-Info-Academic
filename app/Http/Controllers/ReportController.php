<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\GradeScore;
use App\Models\Institution;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    public function boletin(Student $student): Response
    {
        $enrollment = $student->activeEnrollment()
            ->with(['classroom.grade.educationLevel', 'academicYear.periods'])
            ->first();

        $matrix = [];

        if ($enrollment) {
            foreach (GradeScore::where('enrollment_id', $enrollment->id)->with('subject')->get() as $score) {
                $matrix[$score->subject->name][$score->period_id] = $score->score;
            }
        }

        $institution = Institution::first();

        return Pdf::loadView('pdf.boletin', [
            'institution' => $institution,
            'logoPath' => $this->logoPath($institution),
            'student' => $student,
            'enrollment' => $enrollment,
            'matrix' => $matrix,
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
