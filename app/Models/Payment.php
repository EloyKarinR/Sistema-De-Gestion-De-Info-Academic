<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'enrollment_id', 'received_by', 'receipt_number', 'amount',
        'concept', 'previous_balance', 'payment', 'balance', 'payment_date',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'previous_balance' => 'decimal:2',
        'payment'          => 'decimal:2',
        'balance'          => 'decimal:2',
        'payment_date'     => 'date',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
