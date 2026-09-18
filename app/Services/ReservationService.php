<?php

namespace App\Services;

use App\Exceptions\ReservationCancellationException;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use RuntimeException;

class ReservationService
{
  private const STUDENT_ROLE_ID = 3;

  public function __construct(
    private readonly NotificationService $notificationService
  ) {}

  public function getMyReservations(
    User $student,
    ?string $date = null
  ): LengthAwarePaginator {
    if ((int) $student->role_id !== self::STUDENT_ROLE_ID) {
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

  public function cancelReservation(
    User $student,
    int $reservationId,
    string $cancellationReason
  ): Reservation {
    if ($student->role_id !== self::STUDENT_ROLE_ID) {
      throw new ReservationCancellationException(
        'Only students can cancel reservations.',
        403
      );
    }

    $reservation = DB::transaction(function () use (
      $student,
      $reservationId,
      $cancellationReason
    ): Reservation {
      $reservation = Reservation::query()
        ->whereKey($reservationId)
        ->lockForUpdate()
        ->first();

      if ($reservation === null) {
        throw new ReservationCancellationException(
          'Reservation not found.',
          404
        );
      }

      if ((int) $reservation->user_id !== (int) $student->id) {
        throw new ReservationCancellationException(
          'You cannot cancel this reservation.',
          403
        );
      }

      if ($reservation->reservation_status !== 'confirmed') {
        throw new ReservationCancellationException(
          'Only confirmed reservations can be cancelled.',
          400
        );
      }

      $reservationDateTime = Carbon::parse(
        $reservation->reservation_date->toDateString()
          . ' '
          . $reservation->start_time
      );

      if ($reservationDateTime->isPast()) {
        throw new ReservationCancellationException(
          'You cannot cancel a past reservation.',
          400
        );
      }

      if (now()->diffInHours($reservationDateTime, false) < 24) {
        throw new ReservationCancellationException(
          'Reservations must be cancelled at least 24 hours in advance.',
          400
        );
      }

      $reservation->update([
        'reservation_status' => 'cancelled',
        'cancellation_reason' => $cancellationReason,
      ]);

      return $reservation->fresh(['student', 'tutor']);
    });

    $this->notificationService->sendCancellationEmail($reservation);
    $this->notificationService->sendCancellationWhatsApp($reservation);

    return $reservation;
  }
}
