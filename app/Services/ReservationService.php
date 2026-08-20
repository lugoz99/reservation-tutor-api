<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

class ReservationService
{
  private const STUDENT_ROLE_ID = 3;

  public function getMyReservations(
    User $student,
    ?string $date = null
  ): LengthAwarePaginator {
    if ($student->role_id !== self::STUDENT_ROLE_ID) {
      throw new RuntimeException(
        'The authenticated user is not a student.'
      );
    }

    $query = Reservation::query()
      ->where('user_id', $student->id)
      ->with([
        'tutor:id,first_names,last_names,email,phone,photo',
      ])
      ->orderByDesc('reservation_date')
      ->orderByDesc('start_time');

    if ($date !== null) {
      $query->whereDate('reservation_date', $date);
    }

    return $query->paginate(10);
  }
}
