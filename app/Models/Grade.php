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

    /**
     * Grado inmediatamente siguiente en la secuencia completa del colegio
     * (Pre-Kinder → Kinder → 1° ... → 12°), cruzando niveles educativos.
     * "order" solo es único dentro de cada nivel, por eso se combina con el
     * order del nivel educativo. Null si este es el último grado (egresa).
     */
    public function next(): ?self
    {
        return static::query()
            ->join('education_levels', 'education_levels.id', '=', 'grades.education_level_id')
            ->where(function ($q) {
                $q->where('education_levels.order', '>', $this->educationLevel->order)
                    ->orWhere(function ($q) {
                        $q->where('education_levels.order', $this->educationLevel->order)
                            ->where('grades.order', '>', $this->order);
                    });
            })
            ->orderBy('education_levels.order')
            ->orderBy('grades.order')
            ->select('grades.*')
            ->first();
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'grade_subjects');
    }

    /**
     * Secundaria (Pre-Media/Media) exige nota mínima para pasar de año;
     * preescolar y primaria (Básica General) promueven sin importar la nota.
     */
    public function isSecondary(): bool
    {
        return in_array($this->educationLevel->name, ['Pre-Media', 'Media'], true);
    }
}
