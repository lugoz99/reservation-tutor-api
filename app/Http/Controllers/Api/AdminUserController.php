<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminCreateUserRequest;
use App\Services\AdminUserService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class AdminUserController extends Controller
{
  public function __construct(
    private readonly AdminUserService $adminUserService
  ) {}

  #[OA\Post(
    path: '/api/admin/users',
    summary: 'Create a user',
    description: 'Creates an admin, tutor, or student. Only users with role_id 1 can use this endpoint.',
    tags: ['Admin'],
    security: [['sanctum' => []]],
    requestBody: new OA\RequestBody(
      required: true,
      content: new OA\JsonContent(
        required: [
          'first_names',
          'last_names',
          'email',
          'password',
          'password_confirmation',
          'role_id',
        ],
        properties: [
          new OA\Property(property: 'first_names', type: 'string', example: 'Carlos'),
          new OA\Property(property: 'last_names', type: 'string', example: 'Perez'),
          new OA\Property(property: 'email', type: 'string', format: 'email', example: 'carlos.perez@test.com'),
          new OA\Property(property: 'password', type: 'string', format: 'password', example: 'Admin12345'),
          new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'Admin12345'),
          new OA\Property(property: 'role_id', type: 'integer', enum: [1, 2, 3], example: 2),
          new OA\Property(property: 'hourly_rate', type: 'integer', nullable: true, minimum: 30000, maximum: 100000, example: 40000),
          new OA\Property(property: 'phone', type: 'string', nullable: true, example: '3001234567'),
          new OA\Property(property: 'photo', type: 'string', nullable: true, example: 'photos/users/carlos.jpg'),
        ]
      )
    ),
    responses: [
      new OA\Response(
        response: 201,
        description: 'User created successfully'
      ),
      new OA\Response(
        response: 401,
        description: 'Unauthenticated'
      ),
      new OA\Response(
        response: 403,
        description: 'Authenticated user is not an admin'
      ),
      new OA\Response(
        response: 422,
        description: 'Validation error'
      ),
    ]
  )]
  public function store(AdminCreateUserRequest $request): JsonResponse
  {
    $user = $this->adminUserService->create($request->validated());

    return response()->json([
      'id' => $user->id,
      'first_names' => $user->first_names,
      'last_names' => $user->last_names,
      'email' => $user->email,
      'role_id' => $user->role_id,
      'hourly_rate' => $user->hourly_rate,
      'role' => [
        'id' => $user->role->id,
        'name' => $user->role->name,
      ],
    ], 201);
  }
}
