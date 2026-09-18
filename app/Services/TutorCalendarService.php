<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\TutorCalendar;
use App\Models\TutorAvailability;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
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

    $calendar = TutorCalendar::query()
      ->where('tutor_id', $tutorId)
      ->whereDate('calendar_date', $date)
      ->first();

    $availabilityQuery = TutorAvailability::query()
      ->where('tutor_id', $tutorId)
      ->whereDate('availability_date', $date);

    if ($calendar) {
      $availabilityQuery->where('calendar_id', $calendar->id);
    }

    $configuredHours = $availabilityQuery
      ->pluck('start_time')
      ->map(static fn(string $time): string => substr($time, 0, 5))
      ->all();

    $hasConfiguredAvailability = count($configuredHours) > 0;

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
        'available' => !$isReserved
          && (!$hasConfiguredAvailability || in_array(
            substr($startTime, 0, 5),
            $configuredHours,
            true
          )),
        'hourly_rate' => $tutor->hourly_rate,
      ];
    }

    return [
      'tutor_id' => $tutor->id,
      'date' => $date,
      'calendar_id' => $calendar?->id,
      'calendar_name' => $calendar?->name,
      'hourly_rate' => $tutor->hourly_rate,
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

  /**
   * @param list<string> $hours
   */
  public function updateAvailability(
    User $tutor,
    string $date,
    string $name,
    array $hours
  ): array {
    if ($tutor->role_id !== self::TUTOR_ROLE_ID) {
      throw new RuntimeException('The authenticated user is not a tutor.');
    }

    DB::transaction(function () use ($tutor, $date, $name, $hours): void {
      $calendar = TutorCalendar::query()->updateOrCreate(
        [
          'tutor_id' => $tutor->id,
          'calendar_date' => $date,
        ],
        [
          'name' => $name,
        ]
      );

      TutorAvailability::query()
        ->where('tutor_id', $tutor->id)
        ->whereDate('availability_date', $date)
        ->delete();

      foreach ($hours as $hour) {
        TutorAvailability::query()->create([
          'tutor_id' => $tutor->id,
          'calendar_id' => $calendar->id,
          'availability_date' => $date,
          'start_time' => $hour,
          'end_time' => date('H:i', strtotime($hour) + 3600),
        ]);
      }
    });

    return $this->getCalendar($tutor->id, $date);
  }

  /**
   * @param list<string> $hours
   */
  public function supportsHours(int $tutorId, string $date, array $hours): bool
  {
    $calendar = TutorCalendar::query()
      ->where('tutor_id', $tutorId)
      ->whereDate('calendar_date', $date)
      ->first();

    $availabilityQuery = TutorAvailability::query()
      ->where('tutor_id', $tutorId)
      ->whereDate('availability_date', $date);

    if ($calendar) {
      $availabilityQuery->where('calendar_id', $calendar->id);
    }

    $configuredHours = $availabilityQuery->pluck('start_time')
      ->map(static fn(string $time): string => substr($time, 0, 5))
      ->all();

    if ($configuredHours === []) {
      return true;
    }

    return count(array_intersect($hours, $configuredHours)) === count($hours);
  }
}
