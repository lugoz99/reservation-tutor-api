<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaypalOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'user_id',
        'tutor_id',
        'reservation_date',
        'hours',
        'total_amount',
        'paypal_order_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'hours' => 'array',
            'total_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tutor_id');
    }
}
