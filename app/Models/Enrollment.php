<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    /**
     * Nota mínima para pasar de año en secundaria (escala 1.0-5.0). En
     * primaria/preescolar la promoción es automática sin importar la nota.
     */
    public const MINIMUM_PASSING_AVERAGE = 3.0;

    protected $fillable = [
        'student_id', 'classroom_id', 'academic_year_id', 'registered_by',
        'enrollment_date', 'status', 'status_date', 'status_reason', 'enrollment_type', 'receipt_number',
        'doc_cedula_student', 'doc_cedula_guardian', 'doc_boletin', 'doc_foto', 'doc_address',
        'notes',
    ];

    protected $casts = [
        'enrollment_date' => 'date',
        'status_date' => 'date',
        'doc_cedula_student' => 'boolean',
        'doc_cedula_guardian' => 'boolean',
        'doc_boletin' => 'boolean',
        'doc_foto' => 'boolean',
        'doc_address' => 'boolean',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function gradeScores(): HasMany
    {
        return $this->hasMany(GradeScore::class);
    }

    /**
     * Promedio general del año (todas las materias, los 3 trimestres),
     * escala 1.0-5.0. Null si todavía no tiene ninguna nota registrada.
     */
    public function finalAverage(): ?float
    {
        $average = $this->gradeScores()->avg('score');

        return $average !== null ? round((float) $average, 1) : null;
    }

    public function habitScores(): HasMany
    {
        return $this->hasMany(HabitScore::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function rehabilitations(): HasMany
    {
        return $this->hasMany(Rehabilitation::class);
    }
}
