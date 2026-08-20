<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;
use Illuminate\Support\Facades\Hash;

class StudentService
{
  private const STUDENT_ROLE_ID = 3;

  public function getMyProfile(User $student): User
  {
    if ($student->role_id !== self::STUDENT_ROLE_ID) {
      throw new RuntimeException(
        'The authenticated user is not a student.'
      );
    }

    return $student;
  }

  public function updateMyProfile(
    User $student,
    array $data
  ): User {
    if ($student->role_id !== self::STUDENT_ROLE_ID) {
      throw new RuntimeException(
        'The authenticated user is not a student.'
      );
    }

    $student->update($data);

    return $student->fresh();
  }

  public function changePassword(
    User $student,
    string $newPassword
  ): void {
    if ($student->role_id !== self::STUDENT_ROLE_ID) {
      throw new RuntimeException(
        'The authenticated user is not a student.'
      );
    }

    $student->update([
      'password' => Hash::make($newPassword),
    ]);
  }
}
