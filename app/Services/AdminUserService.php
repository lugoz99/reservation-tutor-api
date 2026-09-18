<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserService
{
  /**
   * @param array<string, mixed> $data
   */
  public function create(array $data): User
  {
    return DB::transaction(function () use ($data): User {
      return User::query()->create([
        'first_names' => $data['first_names'],
        'last_names' => $data['last_names'],
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
        'role_id' => $data['role_id'],
        'hourly_rate' => (int) $data['role_id'] === 2
          ? $data['hourly_rate']
          : null,
        'phone' => $data['phone'] ?? null,
        'photo' => $data['photo'] ?? null,
      ])->load('role');
    });
  }
}
