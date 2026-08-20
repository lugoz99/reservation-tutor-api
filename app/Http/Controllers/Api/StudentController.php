<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Services\ReservationService;
use App\Services\StudentService;
use App\Http\Requests\UpdateStudentProfileRequest;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class StudentController extends Controller
{


    private const STUDENT_ROLE_ID = 3;
    public function __construct(
        private readonly ReservationService $reservationService,
        private readonly StudentService $studentService,
        private readonly PaymentService $paymentService

    ) {}

    #[OA\Get(
        path: '/api/my-profile',
        summary: 'Get my profile',
        description: 'Returns the profile information of the authenticated student.',
        tags: ['Students'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile retrieved successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated'
            ),
            new OA\Response(
                response: 403,
                description: 'Authenticated user is not a student'
            )
        ]
    )]
    public function myProfile(Request $request): JsonResponse
    {
        $student = $request->user();

        return response()->json([
            'message' => 'Profile retrieved successfully',
            'data' => $this->studentService->getMyProfile($student),
        ]);
    }


    #[OA\Put(
        path: '/api/my-profile',
        summary: 'Update my profile',
        description: 'Updates the profile information of the authenticated student.',
        tags: ['Students'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'first_names',
                        type: 'string',
                        example: 'John'
                    ),
                    new OA\Property(
                        property: 'last_names',
                        type: 'string',
                        example: 'Smith'
                    ),
                    new OA\Property(
                        property: 'email',
                        type: 'string',
                        format: 'email',
                        example: 'john.smith@example.com'
                    ),
                    new OA\Property(
                        property: 'phone',
                        type: 'string',
                        example: '3001234567'
                    ),
                    new OA\Property(
                        property: 'photo',
                        type: 'string',
                        nullable: true,
                        example: 'photos/students/john.jpg'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profile updated successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated'
            ),
            new OA\Response(
                response: 403,
                description: 'Authenticated user is not a student'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error'
            )
        ]
    )]
    public function updateMyProfile(
        UpdateStudentProfileRequest $request
    ): JsonResponse {
        $student = $request->user();

        $student = $this->studentService->updateMyProfile(
            $student,
            $request->validated()
        );

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $student,
        ]);
    }


    #[OA\Post(
        path: '/api/change-password',
        summary: 'Change password',
        description: 'Changes the password of the authenticated student.',
        tags: ['Students'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: [
                    'current_password',
                    'new_password',
                    'new_password_confirmation'
                ],
                properties: [
                    new OA\Property(
                        property: 'current_password',
                        type: 'string',
                        format: 'password',
                        example: 'OldPassword123'
                    ),
                    new OA\Property(
                        property: 'new_password',
                        type: 'string',
                        format: 'password',
                        example: 'NewPassword123'
                    ),
                    new OA\Property(
                        property: 'new_password_confirmation',
                        type: 'string',
                        format: 'password',
                        example: 'NewPassword123'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password changed successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error'
            )
        ]
    )]
    public function changePassword(
        ChangePasswordRequest $request
    ): JsonResponse {
        $student = $request->user();

        $this->studentService->changePassword(
            $student,
            $request->validated('new_password')
        );

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }



    #[OA\Get(
        path: '/api/my-reservations',
        summary: 'Get my reservations',
        description: 'Returns the reservations of the authenticated student.',
        tags: ['Students'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'date',
                description: 'Filter reservations by date',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    format: 'date'
                ),
                example: '2026-08-18'
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reservations retrieved successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated'
            ),
            new OA\Response(
                response: 403,
                description: 'Authenticated user is not a student'
            ),
            new OA\Response(
                response: 422,
                description: 'Invalid date'
            )
        ]
    )]
    public function myReservations(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $student = $request->user();

        $reservations = $this->reservationService->getMyReservations(
            $student,
            $request->query('date')
        );

        return response()->json([
            'message' => 'Reservations retrieved successfully',
            'data' => $reservations,
        ]);
    }


    #[OA\Get(
        path: '/api/my-payments',
        summary: 'Get my payments',
        description: 'Returns the payment history of the authenticated student.',
        tags: ['Students'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'date',
                description: 'Filter payments by reservation date',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'string',
                    format: 'date'
                ),
                example: '2026-08-18'
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Payments retrieved successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated'
            ),
            new OA\Response(
                response: 403,
                description: 'Authenticated user is not a student'
            ),
            new OA\Response(
                response: 422,
                description: 'Invalid date'
            )
        ]
    )]
    public function myPayments(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Only students can see their own payments
            if ($user->role_id != self::STUDENT_ROLE_ID) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not authorized. Only students can access this.',
                ], 403);
            }

            $payments = $this->paymentService->getMyPayments($user);

            return response()->json([
                'success' => true,
                'message' => 'Payments retrieved successfully',
                'data' => $payments,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payments',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
