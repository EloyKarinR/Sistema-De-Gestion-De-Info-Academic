<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\GradeScore;
use App\Models\Institution;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

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

        return Pdf::loadView('pdf.boletin', [
            'institution' => Institution::first(),
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

        return Pdf::loadView('pdf.constancia', [
            'institution' => Institution::first(),
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

        return Pdf::loadView('pdf.listado', [
            'institution' => Institution::first(),
            'classroom' => $classroom,
            'enrollments' => $enrollments,
        ])->stream("listado-{$classroom->id}.pdf");
    }
}
