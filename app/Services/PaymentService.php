<?php

namespace App\Services;

use App\Models\PaypalOrder;
use App\Models\Reservation;
use App\Models\ReservationDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PaypalServerSdkLib\Authentication\ClientCredentialsAuthCredentialsBuilder;
use PaypalServerSdkLib\Environment;
use PaypalServerSdkLib\PaypalServerSdkClient;
use PaypalServerSdkLib\PaypalServerSdkClientBuilder;
use RuntimeException;

class PaymentService
{
  private PaypalServerSdkClient $client;

  private const TUTOR_ROLE_ID = 3;

  public function __construct()
  {
    $this->client = PaypalServerSdkClientBuilder::init()
      ->clientCredentialsAuthCredentials(
        ClientCredentialsAuthCredentialsBuilder::init(
          config('paypal.client_id'),
          config('paypal.client_secret')
        )
      )
      ->environment(
        config('paypal.environment') === 'production'
          ? Environment::PRODUCTION
          : Environment::SANDBOX
      )
      ->build();
  }

  public function createOrder(
    User $student,
    int $tutorId,
    string $date,
    array $hours
  ): array {
    /*
         * 1. Validate tutor
         */
    $tutor = User::query()
      ->where('id', $tutorId)
      ->where('role_id', self::TUTOR_ROLE_ID)
      ->first();

    if ($tutor === null) {
      throw new RuntimeException(
        'Tutor not found.'
      );
    }

    /*
         * 2. Date cannot be in the past
         */
    if ($date < now()->format('Y-m-d')) {
      throw new RuntimeException(
        'You cannot book a date in the past.'
      );
    }

    /*
         * 3. Normalize hours
         */
    $hours = array_values(
      array_unique($hours)
    );

    sort($hours);

    /*
         * 4. Validate consecutive hours
         */
    $this->validateConsecutiveHours($hours);

    /*
         * 5. Validate tutor availability
         */
    $this->validateAvailability(
      $tutorId,
      $date,
      $hours
    );

    /*
         * 6. Calculate total
         */
    $totalAmount = count($hours) * $tutor->hourly_rate;

    /*
         * 7. Generate internal UUID
         */
    $reference = (string) Str::uuid();

    /*
         * 8. Store local PayPal order
         */
    $paypalOrder = PaypalOrder::create([
      'reference' => $reference,
      'user_id' => $student->id,
      'tutor_id' => $tutorId,
      'reservation_date' => $date,
      'hours' => $hours,
      'total_amount' => $totalAmount,
      'status' => 'pending',
      'paypal_order_id' => null,
    ]);

    try {
      /*
             * 9. Create PayPal order
             */
      $response = $this->client
        ->getOrdersController()
        ->createOrder([
          'body' => [
            'intent' => 'CAPTURE',

            'purchase_units' => [
              [
                /*
                                 * Same value as custom_id. reference_id is only
                                 * needed when an order has more than one purchase
                                 * unit, but we add it to match the reference
                                 * implementation.
                                 */
                'reference_id' => $reference,

                /*
                                 * Our internal reference.
                                 */
                'custom_id' => $reference,

                'amount' => [
                  'currency_code' => 'COP',
                  'value' => number_format(
                    $totalAmount,
                    2,
                    '.',
                    ''
                  ),
                ],
              ],
            ],

            'application_context' => [
              'return_url' => config('app.url')
                . '/paypal/success',

              'cancel_url' => config('app.url')
                . '/paypal/cancel',
            ],
          ],
        ]);

      $order = $response->getResult();

      /*
             * 10. Save PayPal order ID
             */
      $paypalOrder->update([
        'paypal_order_id' => $order->getId(),
      ]);

      /*
             * 11. Get approval URL
             */
      $approvalUrl = collect(
        $order->getLinks() ?? []
      )
        ->first(
          fn($link) =>
          $link->getRel() === 'approve'
        )
        ?->getHref();

      if ($approvalUrl === null) {
        $paypalOrder->update([
          'status' => 'cancelled',
        ]);

        throw new RuntimeException(
          'PayPal approval URL was not returned.'
        );
      }

      /*
             * 12. Return data to Flutter
             */
      return [
        'reference' => $reference,
        'order_id' => $order->getId(),
        'approval_url' => $approvalUrl,
        'amount' => $totalAmount,
        'currency' => 'COP',
      ];
    } catch (\Throwable $e) {
      /*
             * PayPal order creation failed.
             */
      $paypalOrder->update([
        'status' => 'cancelled',
      ]);

      throw new RuntimeException(
        'Unable to create PayPal order.',
        previous: $e
      );
    }
  }

  /**
   * Capture an approved PayPal order.
   *
   * Called from the webhook when PayPal sends
   * the CHECKOUT.ORDER.APPROVED event, so the
   * payment actually gets charged.
   *
   * NOTE: check the exact method name for your
   * installed version of paypal/paypal-server-sdk
   * (composer show paypal/paypal-server-sdk).
   * Some versions expose captureOrder(), others
   * ordersCapture() on OrdersController.
   */
  public function captureOrder(string $orderId): array
  {
    try {
      $response = $this->client
        ->getOrdersController()
        ->captureOrder([
          'id' => $orderId,
          'prefer' => 'return=representation',
        ]);

      return $response->getResult()->toArray();
    } catch (\Throwable $e) {
      throw new RuntimeException(
        'Unable to capture PayPal order: ' . $e->getMessage(),
        previous: $e
      );
    }
  }

  private function validateConsecutiveHours(
    array $hours
  ): void {
    for ($i = 1; $i < count($hours); $i++) {
      $previous = strtotime($hours[$i - 1]);
      $current = strtotime($hours[$i]);

      if (($current - $previous) !== 3600) {
        throw new RuntimeException(
          'The selected hours must be consecutive.'
        );
      }
    }
  }

  private function validateAvailability(
    int $tutorId,
    string $date,
    array $hours
  ): void {
    $startTime = $hours[0];

    $endTime = date(
      'H:i',
      strtotime(end($hours)) + 3600
    );

    $exists = Reservation::query()
      ->where('tutor_id', $tutorId)
      ->whereDate(
        'reservation_date',
        $date
      )
      ->where(
        'reservation_status',
        'confirmed'
      )
      ->where(function ($query) use (
        $startTime,
        $endTime
      ) {
        $query
          ->where(
            'start_time',
            '<',
            $endTime
          )
          ->where(
            'end_time',
            '>',
            $startTime
          );
      })
      ->exists();

    if ($exists) {
      throw new RuntimeException(
        'One or more selected hours are no longer available.'
      );
    }
  }

  /**
   * Handle the PAYMENT.CAPTURE.COMPLETED webhook event.
   *
   * Returns true if a reservation was actually created,
   * false if the order was already completed (idempotent
   * duplicate webhook delivery from PayPal), so the caller
   * (webhook controller) can decide whether to send
   * notifications or not.
   */
  public function handlePaymentCompleted(
    array $event
  ): bool {
    $customId = data_get(
      $event,
      'resource.custom_id'
    );

    if ($customId === null) {
      throw new RuntimeException(
        'PayPal custom_id was not found.'
      );
    }

    $paypalOrder = PaypalOrder::query()
      ->where('reference', $customId)
      ->first();

    if ($paypalOrder === null) {
      throw new RuntimeException(
        'PayPal order not found.'
      );
    }

    /*
     * Idempotency:
     * If PayPal sends the same webhook more than once,
     * we do not create another reservation.
     */
    if ($paypalOrder->status === 'completed') {
      return false;
    }

    $hours = $paypalOrder->hours;

    $this->validateAvailability(
      $paypalOrder->tutor_id,
      $paypalOrder->reservation_date->format('Y-m-d'),
      $hours
    );

    DB::transaction(function () use ($paypalOrder, $event, $hours) {

      $reservation = Reservation::create([
        'user_id' => $paypalOrder->user_id,
        'tutor_id' => $paypalOrder->tutor_id,
        'reservation_date' => $paypalOrder->reservation_date,
        'start_time' => $hours[0],
        'end_time' => date(
          'H:i',
          strtotime(end($hours)) + 3600
        ),
        'reservation_status' => 'confirmed',
      ]);

      ReservationDetail::create([
        'reservation_id' => $reservation->id,
        'transaction_id' => data_get(
          $event,
          'resource.id'
        ),
        'payer_id' => data_get(
          $event,
          'resource.payer.payer_id'
        ),
        'payer_email' => data_get(
          $event,
          'resource.payer.email_address'
        ),
        'payment_status' => 'completed',
        'amount' => $paypalOrder->total_amount,
        'response_json' => json_encode($event),
      ]);

      $paypalOrder->update([
        'status' => 'completed',
      ]);
    });

    return true;
  }

  /**
   * Get the payment history for a student.
   *
   * We look at ReservationDetail because that is where
   * the real payment_status is saved ('completed'), not
   * on the Reservation itself.
   */
  public function getMyPayments(User $student)
  {
    return ReservationDetail::with([
      'reservation.tutor:id,first_names,last_names,email,phone',
    ])
      ->whereHas('reservation', function ($query) use ($student) {
        $query->where('user_id', $student->id);
      })
      ->where('payment_status', 'completed')
      ->orderBy('created_at', 'desc')
      ->paginate(10);
  }
}
