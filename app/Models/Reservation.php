<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'tutor_id',
        'reservation_date',
        'start_time',
        'end_time',
        'reservation_status',
        'total_amount',
        'payment_status',
        'cancellation_reason',
    ];

    protected $casts = [
        'reservation_date' => 'date',
        'total_amount' => 'decimal:2',
    ];


    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tutor_id');
    }

    public function reservationDetail(): HasOne
    {
        return $this->hasOne(ReservationDetail::class, 'reservation_id');
    }
}
