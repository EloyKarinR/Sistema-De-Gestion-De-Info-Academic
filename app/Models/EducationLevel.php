<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EducationLevel extends Model
{
    protected $fillable = [
        'institution_id', 'name', 'institution_type', 'grade_from', 'grade_to', 'order',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class)->orderBy('order');
    }
}
