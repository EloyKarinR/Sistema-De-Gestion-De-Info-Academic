<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Classroom extends Model
{
    protected $fillable = [
        'grade_id', 'academic_year_id', 'section', 'shift', 'capacity',
    ];

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function advisor(): HasOne
    {
        return $this->hasOne(ClassroomAdvisor::class);
    }

    public function subjectAssignments(): HasMany
    {
        return $this->hasMany(SubjectAssignment::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function classSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->grade->name.'-'.$this->section;
    }

    public function getAvailableSpotsAttribute(): int
    {
        return $this->capacity - $this->enrollments()->where('status', 'activo')->count();
    }
}
