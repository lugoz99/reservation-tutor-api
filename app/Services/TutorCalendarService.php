<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

class TutorCalendarService
{
  private const TUTOR_ROLE_ID = 2;

  private const START_HOUR = 8;
  private const END_HOUR = 18;

  public function getCalendar(int $tutorId, ?string $date = null): array
  {
    $tutor = User::query()
      ->where('id', $tutorId)
      ->where('role_id', self::TUTOR_ROLE_ID)
      ->first();

    if (!$tutor) {
      throw new ModelNotFoundException(
        "Tutor with ID {$tutorId} was not found."
      );
    }

    $date ??= now()->toDateString();

    $reservations = Reservation::query()
      ->where('tutor_id', $tutorId)
      ->where('reservation_date', $date)
      ->whereIn('reservation_status', ['pending', 'confirmed'])
      ->orderBy('start_time')
      ->get();

    $slots = [];

    for ($hour = self::START_HOUR; $hour < self::END_HOUR; $hour++) {
      $startTime = sprintf('%02d:00:00', $hour);
      $endTime = sprintf('%02d:00:00', $hour + 1);

      $isReserved = $reservations->contains(function ($reservation) use (
        $startTime,
        $endTime
      ) {
        return $reservation->start_time < $endTime
          && $reservation->end_time > $startTime;
      });

      $slots[] = [
        'start_time' => substr($startTime, 0, 5),
        'end_time' => substr($endTime, 0, 5),
        'available' => !$isReserved,
      ];
    }

    return [
      'tutor_id' => $tutor->id,
      'date' => $date,
      'hourly_rate' => 35000,
      'slots' => $slots,
    ];
  }

  public function getAuthenticatedTutorCalendar(
    User $tutor,
    ?string $date = null
  ): array {
    if ($tutor->role_id !== self::TUTOR_ROLE_ID) {
      throw new RuntimeException('The authenticated user is not a tutor.');
    }

    return $this->getCalendar($tutor->id, $date);
  }
}
