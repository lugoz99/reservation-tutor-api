<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PaypalOrder;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    /**
     * @OA\Post(
     *     path="/paypal/webhook",
     *     operationId="paypalWebhook",
     *     summary="Receive PayPal webhook events",
     *     description="This endpoint is called by PayPal. It listens for two events: CHECKOUT.ORDER.APPROVED (captures the payment) and PAYMENT.CAPTURE.COMPLETED (creates the reservation).",
     *     tags={"Payments"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Raw event data sent by PayPal",
     *         @OA\JsonContent(
     *             required={"event_type","resource"},
     *             @OA\Property(property="event_type", type="string", example="PAYMENT.CAPTURE.COMPLETED"),
     *             @OA\Property(
     *                 property="resource",
     *                 type="object",
     *                 @OA\Property(property="id", type="string", example="8XY123456789"),
     *                 @OA\Property(property="custom_id", type="string", example="b3f1c2a0-1234-4a2b-9f0e-abcdef123456"),
     *                 @OA\Property(property="status", type="string", example="COMPLETED")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event was processed correctly",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment confirmed and reservation created")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="The event data is missing or not valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid data")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Something went wrong on our side while processing the event",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error in webhook")
     *         )
     *     )
     * )
     */
    public function webhook(Request $request)
    {
        try {
            Log::info('================ PAYPAL WEBHOOK START =================');

            $payload = $request->all();
            Log::info('Payload:', $payload);

            $eventType = $payload['event_type'] ?? null;
            Log::info('Event Type:', ['event_type' => $eventType]);

            // If the order was approved, we capture the payment now
            if ($eventType === 'CHECKOUT.ORDER.APPROVED') {
                $orderId = $payload['resource']['id'] ?? null;

                if (!$orderId) {
                    Log::error('Order ID not found');
                    return response()->json([
                        'success' => false,
                        'message' => 'Order ID not found',
                    ], 400);
                }

                try {
                    $captureResponse = $this->paymentService->captureOrder($orderId);
                    Log::info('Capture response:', $captureResponse);
                } catch (\Throwable $e) {
                    Log::error('Error capturing the order', ['error' => $e->getMessage()]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Error capturing the order',
                    ], 500);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Order captured successfully',
                ], 200);
            }

            // If the payment capture is done, we create the reservation
            if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
                $reference = $payload['resource']['custom_id'] ?? null;
                $status = $payload['resource']['status'] ?? null;

                if (!$reference || $status !== 'COMPLETED') {
                    Log::error('Invalid data in CAPTURE COMPLETED event');
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid data',
                    ], 400);
                }

                $paypalOrder = PaypalOrder::where('reference', $reference)->first();

                if (!$paypalOrder) {
                    Log::warning('Order not found');
                    return response()->json([
                        'success' => false,
                        'message' => 'Order not found',
                    ], 400);
                }

                try {
                    // handlePaymentCompleted returns true if it created a new
                    // reservation, and false if this order was already done
                    // before (this can happen if PayPal sends the same
                    // webhook two times).
                    $created = $this->paymentService->handlePaymentCompleted($payload);
                } catch (\Throwable $e) {
                    Log::error('Error processing the completed payment', [
                        'error' => $e->getMessage(),
                        'line' => $e->getLine(),
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Error processing the payment',
                    ], 500);
                }

                if (!$created) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Order was already processed',
                    ], 200);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment confirmed and reservation created',
                ], 200);
            }

            Log::info('This event is not important, we ignore it.');

            return response()->json([
                'success' => true,
                'message' => 'Event ignored',
            ], 200);
        } catch (\Throwable $e) {
            Log::error('ERROR IN WEBHOOK', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error in webhook',
            ], 500);
        }
    }
}
