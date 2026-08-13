<?php

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegisterUserService
{
  /**
   * @param array<string, mixed> $data
   */
  public function execute(array $data): User
  {
    $roleId = $this->resolveRoleId();

    return DB::transaction(function () use ($data, $roleId): User {
      return User::create([
        'first_names' => $data['first_names'],
        'last_names' => $data['last_names'],
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
        'role_id' => $roleId,
      ]);
    });
  }

  private function resolveRoleId(): int
  {
    $studentRole = Role::query()
      ->where('name', 'student')
      ->orWhere('id', 3)
      ->first();

    if (!$studentRole) {
      throw ValidationException::withMessages([
        'role' => ['No existe un rol valido para registro. Crea el rol student o el id 3 antes de registrar usuarios.'],
      ]);
    }

    return (int) $studentRole->id;
  }
}
