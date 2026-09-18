<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
  use RefreshDatabase;

  public function test_admin_can_create_a_user_with_any_supported_role(): void
  {
    $this->createRoles();
    $admin = $this->createUser('admin@example.com', 1);

    $response = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users', [
      'first_names' => 'Carlos',
      'last_names' => 'Perez',
      'email' => 'carlos.perez@test.com',
      'password' => 'password123',
      'password_confirmation' => 'password123',
      'role_id' => 2,
    ]);

    $response->assertCreated()->assertExactJson([
      'id' => 2,
      'first_names' => 'Carlos',
      'last_names' => 'Perez',
      'email' => 'carlos.perez@test.com',
      'role_id' => 2,
      'role' => [
        'id' => 2,
        'name' => 'tutor',
      ],
    ]);

    $this->assertDatabaseHas('users', [
      'email' => 'carlos.perez@test.com',
      'role_id' => 2,
    ]);
  }

  public function test_non_admin_cannot_create_users(): void
  {
    $this->createRoles();
    $student = $this->createUser('student@example.com', 3);

    $response = $this->actingAs($student, 'sanctum')->postJson('/api/admin/users', [
      'first_names' => 'Carlos',
      'last_names' => 'Perez',
      'email' => 'carlos.perez@test.com',
      'password' => 'password123',
      'password_confirmation' => 'password123',
      'role_id' => 2,
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('users', ['email' => 'carlos.perez@test.com']);
  }

  private function createRoles(): void
  {
    Role::query()->create(['id' => 1, 'name' => 'admin']);
    Role::query()->create(['id' => 2, 'name' => 'tutor']);
    Role::query()->create(['id' => 3, 'name' => 'student']);
  }

  private function createUser(string $email, int $roleId): User
  {
    return User::query()->create([
      'first_names' => 'Test',
      'last_names' => 'User',
      'email' => $email,
      'password' => Hash::make('password123'),
      'role_id' => $roleId,
    ]);
  }
}
