<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TutorCalendar extends Model
{
  protected $fillable = [
    'tutor_id',
    'calendar_date',
    'name',
  ];

  protected $casts = [
    'calendar_date' => 'date',
  ];

  public function tutor(): BelongsTo
  {
    return $this->belongsTo(User::class, 'tutor_id');
  }

  public function availabilities(): HasMany
  {
    return $this->hasMany(TutorAvailability::class, 'calendar_id');
  }
}
