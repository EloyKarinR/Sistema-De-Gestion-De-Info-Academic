<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rehabilitation extends Model
{
    protected $fillable = [
        'enrollment_id', 'subject_id', 'trimester', 'score', 'status',
    ];

    protected $casts = [
        'score' => 'decimal:1',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
