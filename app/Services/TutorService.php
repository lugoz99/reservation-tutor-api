<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class TutorService
{
  private const TUTOR_ROLE_ID = 2;

  public function getAll(): LengthAwarePaginator
  {
    return User::query()
      ->where('role_id', self::TUTOR_ROLE_ID)
      ->select([
        'id',
        'first_names',
        'last_names',
        'email',
        'phone',
        'photo',
        'role_id',
        'created_at',
      ])
      ->orderBy('id')
      ->paginate(10);
  }

  public function getById(int $id): array
  {
    $tutor = User::query()
      ->where('id', $id)
      ->where('role_id', self::TUTOR_ROLE_ID)
      ->first();

    if (!$tutor) {
      throw new ModelNotFoundException(
        "Tutor with ID {$id} was not found."
      );
    }

    $statistics = Reservation::query()
      ->where('tutor_id', $tutor->id)
      ->selectRaw('COUNT(*) as total_consultations')
      ->selectRaw('COALESCE(SUM(total_amount), 0) as total_revenue')
      ->first();

    return [
      'id' => $tutor->id,
      'first_names' => $tutor->first_names,
      'last_names' => $tutor->last_names,
      'email' => $tutor->email,
      'phone' => $tutor->phone,
      'photo' => $tutor->photo,
      'role_id' => $tutor->role_id,
      'created_at' => $tutor->created_at,
      'updated_at' => $tutor->updated_at,

      'statistics' => [
        'total_consultations' => (int) $statistics->total_consultations,
        'total_revenue' => (float) $statistics->total_revenue,
      ],
    ];
  }

  public function create(array $data): User
  {
    return User::create([
      'first_names' => $data['first_names'],
      'last_names' => $data['last_names'],
      'email' => $data['email'],
      'phone' => $data['phone'] ?? null,
      'photo' => $data['photo'] ?? null,
      'password' => Hash::make($data['password']),
      'role_id' => self::TUTOR_ROLE_ID,
    ]);
  }

  public function update(User $tutor, array $data): User
  {
    if ($tutor->role_id !== self::TUTOR_ROLE_ID) {
      throw new RuntimeException('The specified user is not a tutor.');
    }

    $tutor->update([
      'first_names' => $data['first_names'] ?? $tutor->first_names,
      'last_names' => $data['last_names'] ?? $tutor->last_names,
      'email' => $data['email'] ?? $tutor->email,
      'phone' => $data['phone'] ?? $tutor->phone,
      'photo' => $data['photo'] ?? $tutor->photo,
    ]);

    return $tutor->refresh();
  }

  public function delete(User $tutor): void
  {
    if ($tutor->role_id !== self::TUTOR_ROLE_ID) {
      throw new RuntimeException('The specified user is not a tutor.');
    }

    $tutor->delete();
  }



  public function getAuthenticatedTutorReservations(
    User $tutor,
    ?string $date = null
  ): LengthAwarePaginator {
    if ($tutor->role_id !== self::TUTOR_ROLE_ID) {
      throw new RuntimeException('The authenticated user is not a tutor.');
    }

    $query = Reservation::query()
      ->where('tutor_id', $tutor->id)
      ->with([
        'student:id,first_names,last_names,email,phone,photo',
      ])
      ->orderByDesc('reservation_date')
      ->orderByDesc('start_time');

    if ($date !== null) {
      $query->whereDate('reservation_date', $date);
    }

    return $query->paginate(10);
  }


  
}
