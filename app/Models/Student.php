<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    protected $fillable = [
        'cedula', 'first_name', 'last_name', 'birth_date', 'sex',
        'birth_place', 'blood_type', 'medical_conditions', 'previous_school',
        'photo', 'address',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function primaryGuardian()
    {
        return $this->guardians()->wherePivot('is_primary', true)->first();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activeEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)->where('status', 'activo')->latestOfMany();
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
