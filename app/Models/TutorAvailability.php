<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorAvailability extends Model
{
  protected $fillable = [
    'tutor_id',
    'calendar_id',
    'availability_date',
    'start_time',
    'end_time',
  ];

  protected $casts = [
    'availability_date' => 'date',
  ];

  public function tutor(): BelongsTo
  {
    return $this->belongsTo(User::class, 'tutor_id');
  }

  public function calendar(): BelongsTo
  {
    return $this->belongsTo(TutorCalendar::class, 'calendar_id');
  }
}
