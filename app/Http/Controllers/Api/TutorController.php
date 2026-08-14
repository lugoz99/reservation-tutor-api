<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TutorCalendarService;
use App\Services\TutorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class TutorController extends Controller
{
    public function __construct(
        private readonly TutorService $tutorService,
        private readonly TutorCalendarService $tutorCalendarService
    ) {}

    #[OA\Get(
        path: '/api/tutors',
        summary: 'Get tutors',
        tags: ['Tutors'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tutors retrieved successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated'
            )
        ]
    )]
    public function index(): JsonResponse
    {
        return response()->json([
            'message' => 'Tutors retrieved successfully',
            'data' => $this->tutorService->getAll(),
        ]);
    }

    #[OA\Get(
        path: '/api/tutors/{id}',
        summary: 'Get tutor by ID',
        description: 'Returns the tutor information and reservation statistics.',
        tags: ['Tutors'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Tutor ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 26
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tutor retrieved successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated'
            ),
            new OA\Response(
                response: 404,
                description: 'Tutor not found'
            )
        ]
    )]
    public function show(int $id): JsonResponse
    {
        return response()->json([
            'message' => 'Tutor retrieved successfully',
            'data' => $this->tutorService->getById($id),
        ]);
    }

    #[OA\Get(
        path: '/api/tutors/{id}/calendar',
        summary: 'Get tutor calendar availability',
        tags: ['Tutors'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Tutor ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 26
            ),
            new OA\Parameter(
                name: 'date',
                description: 'Date to check availability',
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
                description: 'Tutor calendar retrieved successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated'
            ),
            new OA\Response(
                response: 404,
                description: 'Tutor not found'
            ),
            new OA\Response(
                response: 422,
                description: 'Invalid date'
            )
        ]
    )]
    public function calendar(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return response()->json([
            'message' => 'Tutor calendar retrieved successfully',
            'data' => $this->tutorCalendarService->getCalendar(
                $id,
                $request->query('date')
            ),
        ]);
    }

    #[OA\Get(
        path: '/api/tutors/reservations',
        summary: 'Get authenticated tutor reservations',
        description: 'Returns all reservations belonging to the authenticated tutor, including the student who made each reservation.',
        tags: ['Tutors'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tutor reservations retrieved successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated'
            ),
            new OA\Response(
                response: 403,
                description: 'Authenticated user is not a tutor'
            )
        ]
    )]
    public function reservations(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $tutor = $request->user();

        return response()->json([
            'message' => 'Tutor reservations retrieved successfully',
            'data' => $this->tutorService->getAuthenticatedTutorReservations(
                $tutor,
                $request->query('date')
            ),
        ]);
    }

    #[OA\Get(
        path: '/api/tutors/calendar',
        summary: 'Get authenticated tutor calendar',
        description: 'Returns the availability calendar of the authenticated tutor.',
        tags: ['Tutors'],
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'date',
                description: 'Date to check availability',
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
                description: 'Tutor calendar retrieved successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated'
            ),
            new OA\Response(
                response: 403,
                description: 'Authenticated user is not a tutor'
            ),
            new OA\Response(
                response: 422,
                description: 'Invalid date'
            )
        ]
    )]
    public function myCalendar(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $tutor = $request->user();

        return response()->json([
            'message' => 'Tutor calendar retrieved successfully',
            'data' => $this->tutorCalendarService->getAuthenticatedTutorCalendar(
                $tutor,
                $request->query('date')
            ),
        ]);
    }
}
