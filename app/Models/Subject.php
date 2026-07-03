<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    protected $fillable = [
        'institution_id', 'name', 'code', 'is_specialized',
    ];

    protected $casts = [
        'is_specialized' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function grades(): BelongsToMany
    {
        return $this->belongsToMany(Grade::class, 'grade_subjects');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SubjectAssignment::class);
    }

    public function gradeScores(): HasMany
    {
        return $this->hasMany(GradeScore::class);
    }
}
