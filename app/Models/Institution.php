<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    protected $fillable = [
        'name', 'type', 'ruc', 'address', 'phone', 'email', 'director_name', 'logo',
    ];

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function educationLevels(): HasMany
    {
        return $this->hasMany(EducationLevel::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function habits(): HasMany
    {
        return $this->hasMany(Habit::class);
    }

    public function activeAcademicYear()
    {
        return $this->academicYears()->where('is_active', true)->first();
    }
}
