<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePaypalOrderRequest;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class PaypalController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {}

    #[OA\Post(
        path: '/api/paypal/create-order',
        summary: 'Create PayPal order',
        description: 'Validates the tutoring reservation data and creates a PayPal order without creating the reservation.',
        tags: ['PayPal'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tutor_id', 'date', 'hours'],
                properties: [
                    new OA\Property(
                        property: 'tutor_id',
                        type: 'integer',
                        example: 26
                    ),
                    new OA\Property(
                        property: 'date',
                        type: 'string',
                        format: 'date',
                        example: '2026-08-18'
                    ),
                    new OA\Property(
                        property: 'hours',
                        type: 'array',
                        items: new OA\Items(
                            type: 'string',
                            example: '10:00'
                        ),
                        example: [
                            '10:00',
                            '11:00',
                            '12:00'
                        ]
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'PayPal order created successfully'
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthenticated'
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error'
            ),
        ]
    )]
    public function createOrder(
        CreatePaypalOrderRequest $request
    ): JsonResponse {
        $student = $request->user();

        $order = $this->paymentService->createOrder(
            $student,
            $request->integer('tutor_id'),
            $request->string('date')->toString(),
            $request->input('hours')
        );

        return response()->json([
            'message' => 'PayPal order created successfully',
            'data' => $order,
        ]);
    }
}
