<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    protected $fillable = [
        'education_level_id', 'name', 'number', 'min_age', 'max_age', 'order',
    ];

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    /**
     * Si el grado no tiene un rango de edad definido, se acepta cualquier edad.
     */
    public function acceptsAge(?int $age): bool
    {
        if ($this->min_age === null || $this->max_age === null || $age === null) {
            return true;
        }

        return $age >= $this->min_age && $age <= $this->max_age;
    }

    public function classrooms(): HasMany
    {
        return $this->hasMany(Classroom::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'grade_subjects');
    }
}
