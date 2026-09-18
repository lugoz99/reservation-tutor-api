<?php

namespace App\Services;

use App\Models\PaypalOrder;
use App\Models\Reservation;
use App\Models\ReservationDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PaypalServerSdkLib\Authentication\ClientCredentialsAuthCredentialsBuilder;
use PaypalServerSdkLib\Environment;
use PaypalServerSdkLib\PaypalServerSdkClient;
use PaypalServerSdkLib\PaypalServerSdkClientBuilder;
use RuntimeException;
use Throwable;

class PaymentService
{
  private PaypalServerSdkClient $client;

  private const TUTOR_ROLE_ID = 2;
  private const MIN_HOURLY_RATE = 1;
  private const MAX_HOURLY_RATE = 1000;
  private const PAYPAL_CURRENCY = 'USD';

  public function __construct(
    private NotificationService $notificationService,
    private TutorCalendarService $tutorCalendarService
  ) {
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

  /**
   * Create a local PayPal order.
   *
   * The reservation is not created here.
   * The reservation is created after PayPal confirms the payment.
   */
  public function createOrder(
    User $student,
    int $tutorId,
    string $date,
    array $hours
  ): array {
    $tutor = $this->findTutor($tutorId);

    $this->validateTutorHourlyRate($tutor);
    $this->validateReservationDate($date);

    $hours = $this->normalizeHours($hours);

    $this->validateConsecutiveHours($hours);

    $this->validateAvailability(
      $tutorId,
      $date,
      $hours
    );

    $totalAmount = round(
      count($hours) * (float) $tutor->hourly_rate,
      2
    );

    if ($totalAmount <= 0) {
      throw new RuntimeException(
        'The reservation total amount must be greater than zero.'
      );
    }

    // The application already works directly with USD.
    $paypalAmount = $totalAmount;

    $reference = (string) Str::uuid();

    Log::info('Creating local PayPal order.', [
      'student_id' => $student->id,
      'tutor_id' => $tutorId,
      'date' => $date,
      'hours' => $hours,
      'total_amount' => $totalAmount,
      'currency' => self::PAYPAL_CURRENCY,
      'reference' => $reference,
    ]);

    $paypalOrder = PaypalOrder::create([
      'reference' => $reference,
      'user_id' => $student->id,
      'tutor_id' => $tutorId,
      'reservation_date' => $date,
      'hours' => $hours,
      'total_amount' => $totalAmount,
      'paypal_amount' => $paypalAmount,
      'paypal_currency' => self::PAYPAL_CURRENCY,
      'status' => 'pending',
      'paypal_order_id' => null,
    ]);

    try {
      $paypalResult = $this->createPaypalOrder(
        $reference,
        $paypalAmount
      );

      $orderId = $this->extractPaypalOrderId(
        $paypalResult
      );

      $approvalUrl = $this->extractApprovalUrl(
        $paypalResult
      );

      $paypalOrder->update([
        'paypal_order_id' => $orderId,
      ]);

      Log::info('PayPal order created successfully.', [
        'local_order_id' => $paypalOrder->id,
        'reference' => $reference,
        'paypal_order_id' => $orderId,
        'amount' => $totalAmount,
        'currency' => self::PAYPAL_CURRENCY,
      ]);

      return [
        'reference' => $reference,
        'order_id' => $orderId,
        'approval_url' => $approvalUrl,
        'amount' => $totalAmount,
        'currency' => self::PAYPAL_CURRENCY,
        'paypal_amount' => $paypalAmount,
        'paypal_currency' => self::PAYPAL_CURRENCY,
      ];
    } catch (Throwable $e) {
      $paypalOrder->update([
        'status' => 'cancelled',
      ]);

      Log::error('PayPal order creation failed.', [
        'local_order_id' => $paypalOrder->id,
        'reference' => $reference,
        'error' => $e->getMessage(),
      ]);

      report($e);

      throw new RuntimeException(
        $this->getPaypalErrorMessage($e),
        previous: $e
      );
    }
  }

  /**
   * Create the order directly in PayPal.
   */
  private function createPaypalOrder(
    string $reference,
    float $paypalAmount
  ): array {
    try {
      $response = $this->client
        ->getOrdersController()
        ->createOrder([
          'body' => [
            'intent' => 'CAPTURE',
            'purchase_units' => [
              [
                'reference_id' => $reference,
                'custom_id' => $reference,
                'amount' => [
                  'currency_code' => self::PAYPAL_CURRENCY,
                  'value' => number_format(
                    $paypalAmount,
                    2,
                    '.',
                    ''
                  ),
                ],
              ],
            ],
            'application_context' => [
              'return_url' => config('app.url') . '/paypal/success',
              'cancel_url' => config('app.url') . '/paypal/cancel',
            ],
          ],
        ]);

      $result = $response->getResult();

      // Convert SDK object to array.
      if (is_object($result)) {
        $result = json_decode(
          json_encode($result),
          true
        );
      }

      if (!is_array($result)) {
        throw new RuntimeException(
          'PayPal returned an invalid response.'
        );
      }

      if (!empty($result['name'])) {
        throw new RuntimeException(
          $this->getPayPalApiError($result)
        );
      }

      return $result;
    } catch (Throwable $e) {
      throw new RuntimeException(
        $this->getPaypalErrorMessage($e),
        previous: $e
      );
    }
  }

  /**
   * Get the PayPal order ID from the response.
   */
  private function extractPaypalOrderId(
    array $result
  ): string {
    $orderId = data_get($result, 'id');

    if (
      !is_string($orderId)
      || trim($orderId) === ''
    ) {
      throw new RuntimeException(
        'PayPal did not return an order ID.'
      );
    }

    return $orderId;
  }

  /**
   * Get the approval URL from the PayPal response.
   */
  private function extractApprovalUrl(
    array $result
  ): string {
    $links = data_get(
      $result,
      'links',
      []
    );

    if (!is_array($links)) {
      throw new RuntimeException(
        'PayPal returned an invalid links response.'
      );
    }

    foreach ($links as $link) {
      if (
        ($link['rel'] ?? null) === 'approve'
        && !empty($link['href'])
      ) {
        return $link['href'];
      }
    }

    throw new RuntimeException(
      'PayPal approval URL was not returned.'
    );
  }

  /**
   * Find a valid tutor.
   */
  private function findTutor(
    int $tutorId
  ): User {
    $tutor = User::query()
      ->where('id', $tutorId)
      ->where('role_id', self::TUTOR_ROLE_ID)
      ->first();

    if ($tutor === null) {
      throw new RuntimeException(
        'Tutor not found.'
      );
    }

    return $tutor;
  }

  /**
   * Validate the tutor hourly rate.
   */
  private function validateTutorHourlyRate(
    User $tutor
  ): void {
    if (
      $tutor->hourly_rate === null
      || (float) $tutor->hourly_rate < self::MIN_HOURLY_RATE
      || (float) $tutor->hourly_rate > self::MAX_HOURLY_RATE
    ) {
      throw new RuntimeException(
        'The tutor does not have a valid hourly rate configured in USD.'
      );
    }
  }

  /**
   * Do not allow reservations in the past.
   */
  private function validateReservationDate(
    string $date
  ): void {
    if ($date < now()->format('Y-m-d')) {
      throw new RuntimeException(
        'You cannot book a date in the past.'
      );
    }
  }

  /**
   * Remove duplicated hours and sort them.
   */
  private function normalizeHours(
    array $hours
  ): array {
    $hours = array_values(
      array_unique($hours)
    );

    sort($hours);

    if (count($hours) === 0) {
      throw new RuntimeException(
        'At least one hour must be selected.'
      );
    }

    return $hours;
  }

  /**
   * A reservation must contain consecutive hours.
   */
  private function validateConsecutiveHours(
    array $hours
  ): void {
    for (
      $i = 1;
      $i < count($hours);
      $i++
    ) {
      $previous = strtotime($hours[$i - 1]);
      $current = strtotime($hours[$i]);

      if (($current - $previous) !== 3600) {
        throw new RuntimeException(
          'The selected hours must be consecutive.'
        );
      }
    }
  }

  /**
   * Check tutor availability and existing reservations.
   */
  private function validateAvailability(
    int $tutorId,
    string $date,
    array $hours
  ): void {
    if (
      !$this->tutorCalendarService->supportsHours(
        $tutorId,
        $date,
        $hours
      )
    ) {
      throw new RuntimeException(
        'One or more selected hours are outside the tutor availability.'
      );
    }

    $startTime = $hours[0];
    $lastHour = end($hours);

    $endTime = date(
      'H:i',
      strtotime($lastHour) + 3600
    );

    $exists = Reservation::query()
      ->where('tutor_id', $tutorId)
      ->whereDate(
        'reservation_date',
        $date
      )
      ->whereIn(
        'reservation_status',
        ['pending', 'confirmed']
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
   * Capture an approved PayPal order.
   */
  public function captureOrder(
    string $orderId
  ): array {
    try {
      if (trim($orderId) === '') {
        throw new RuntimeException(
          'PayPal order ID is empty.'
        );
      }

      Log::info('Capturing PayPal order.', [
        'paypal_order_id' => $orderId,
      ]);

      $response = $this->client
        ->getOrdersController()
        ->captureOrder([
          'id' => $orderId,
          'prefer' => 'return=representation',
        ]);

      $result = $response->getResult();

      // Convert SDK object to array.
      if (is_object($result)) {
        $result = json_decode(
          json_encode($result),
          true
        );
      }

      if (!is_array($result)) {
        throw new RuntimeException(
          'PayPal returned an invalid capture response.'
        );
      }

      if (!empty($result['name'])) {
        throw new RuntimeException(
          $this->getPayPalApiError($result)
        );
      }

      Log::info('PayPal order captured successfully.', [
        'paypal_order_id' => $orderId,
        'status' => data_get(
          $result,
          'status'
        ),
      ]);

      return $result;
    } catch (Throwable $e) {
      Log::error('PayPal capture failed.', [
        'paypal_order_id' => $orderId,
        'error' => $e->getMessage(),
      ]);

      report($e);

      throw new RuntimeException(
        $this->getPaypalErrorMessage($e),
        previous: $e
      );
    }
  }

  /**
   * Validate the amount and currency received from PayPal.
   */
  private function validateCapturedAmount(
    PaypalOrder $paypalOrder,
    array $event
  ): void {
    $receivedAmount = data_get(
      $event,
      'resource.amount.value'
    );

    if ($receivedAmount === null) {
      throw new RuntimeException(
        'PayPal captured amount was not found.'
      );
    }

    $expected = number_format(
      (float) $paypalOrder->paypal_amount,
      2,
      '.',
      ''
    );

    $received = number_format(
      (float) $receivedAmount,
      2,
      '.',
      ''
    );

    if ($expected !== $received) {
      throw new RuntimeException(
        "PayPal amount mismatch. Expected {$expected} USD, received {$received} USD."
      );
    }

    $receivedCurrency = data_get(
      $event,
      'resource.amount.currency_code'
    );

    if (
      $receivedCurrency !== $paypalOrder->paypal_currency
    ) {
      throw new RuntimeException(
        "PayPal currency mismatch. Expected {$paypalOrder->paypal_currency}, received {$receivedCurrency}."
      );
    }
  }

  /**
   * Process the completed PayPal payment.
   *
   * This method creates the reservation only after
   * PayPal confirms the payment.
   */
  public function handlePaymentCompleted(
    array $event
  ): bool {
    $eventType = data_get(
      $event,
      'event_type'
    );

    Log::info('Processing completed PayPal payment.', [
      'event_type' => $eventType,
      'resource_id' => data_get(
        $event,
        'resource.id'
      ),
    ]);

    try {
      $customId = data_get(
        $event,
        'resource.custom_id'
      );

      if (
        !is_string($customId)
        || trim($customId) === ''
      ) {
        throw new RuntimeException(
          'PayPal custom_id was not found.'
        );
      }

      $paypalOrder = PaypalOrder::query()
        ->where(
          'reference',
          $customId
        )
        ->first();

      if ($paypalOrder === null) {
        throw new RuntimeException(
          "PayPal local order not found for reference: {$customId}"
        );
      }

      Log::info('Local PayPal order found.', [
        'local_order_id' => $paypalOrder->id,
        'reference' => $paypalOrder->reference,
        'paypal_order_id' => $paypalOrder->paypal_order_id,
        'status' => $paypalOrder->status,
      ]);

      // Validate the payment before creating the reservation.
      $this->validateCapturedAmount(
        $paypalOrder,
        $event
      );

      // Webhooks can be sent more than once.
      // Do not create the reservation twice.
      if ($paypalOrder->status === 'completed') {
        Log::info(
          'PayPal order was already processed.',
          [
            'local_order_id' => $paypalOrder->id,
            'reference' => $paypalOrder->reference,
          ]
        );

        return false;
      }

      $hours = $paypalOrder->hours;

      if (
        !is_array($hours)
        || count($hours) === 0
      ) {
        throw new RuntimeException(
          'No reservation hours were found in the PayPal order.'
        );
      }

      // Check that the selected hours are still available.
      $this->validateAvailability(
        $paypalOrder->tutor_id,
        $paypalOrder->reservation_date->format('Y-m-d'),
        $hours
      );

      /*
             * Create the reservation, payment detail and update
             * the PayPal order in one database transaction.
             */
      $reservation = DB::transaction(
        function () use (
          $paypalOrder,
          $event,
          $hours
        ) {
          $startTime = $hours[0];

          $endTime = date(
            'H:i',
            strtotime(end($hours)) + 3600
          );

          $reservation = Reservation::create([
            'user_id' => $paypalOrder->user_id,
            'tutor_id' => $paypalOrder->tutor_id,
            'reservation_date' => $paypalOrder->reservation_date,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'total_amount' => $paypalOrder->total_amount,
            'payment_status' => 'completed',
            'reservation_status' => 'confirmed',
          ]);

          Log::info(
            'Reservation created from PayPal payment.',
            [
              'reservation_id' => $reservation->id,
              'paypal_order_id' => $paypalOrder->paypal_order_id,
              'amount' => $paypalOrder->total_amount,
            ]
          );

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

            // Keep the original PayPal event for internal records.
            'response_json' => json_encode($event),
          ]);

          Log::info(
            'Reservation payment detail created.',
            [
              'reservation_id' => $reservation->id,
              'transaction_id' => data_get(
                $event,
                'resource.id'
              ),
            ]
          );

          $paypalOrder->update([
            'status' => 'completed',
          ]);

          Log::info(
            'Local PayPal order marked as completed.',
            [
              'local_order_id' => $paypalOrder->id,
              'reference' => $paypalOrder->reference,
            ]
          );

          return $reservation;
        }
      );

      /*
             * Notifications are sent after the transaction.
             * If a notification fails, the reservation remains valid.
             */
      try {
        $emailSent = $this->notificationService
          ->sendReservationEmail(
            $paypalOrder,
            $reservation
          );

        if (!$emailSent) {
          Log::warning(
            'Reservation email notification failed.',
            [
              'reservation_id' => $reservation->id,
              'paypal_order_id' => $paypalOrder->paypal_order_id,
            ]
          );
        }
      } catch (Throwable $e) {
        Log::error(
          'Reservation email notification failed.',
          [
            'reservation_id' => $reservation->id,
            'error' => $e->getMessage(),
          ]
        );

        report($e);
      }

      try {
        $whatsappSent = $this->notificationService
          ->sendReservationWhatsApp(
            $paypalOrder,
            $reservation
          );

        if (!$whatsappSent) {
          Log::warning(
            'Reservation WhatsApp notification failed.',
            [
              'reservation_id' => $reservation->id,
              'paypal_order_id' => $paypalOrder->paypal_order_id,
            ]
          );
        }
      } catch (Throwable $e) {
        Log::error(
          'Reservation WhatsApp notification failed.',
          [
            'reservation_id' => $reservation->id,
            'error' => $e->getMessage(),
          ]
        );

        report($e);
      }

      Log::info(
        'PayPal payment processed successfully.',
        [
          'local_order_id' => $paypalOrder->id,
          'paypal_order_id' => $paypalOrder->paypal_order_id,
          'reservation_id' => $reservation->id,
          'amount' => $paypalOrder->total_amount,
        ]
      );

      return true;
    } catch (Throwable $e) {
      Log::error(
        'Failed to process completed PayPal payment.',
        [
          'event_type' => $eventType,
          'paypal_order_id' => data_get(
            $event,
            'resource.supplementary_data.related_ids.order_id'
          ),
          'capture_id' => data_get(
            $event,
            'resource.id'
          ),
          'custom_id' => data_get(
            $event,
            'resource.custom_id'
          ),
          'error' => $e->getMessage(),
          'line' => $e->getLine(),
          'file' => $e->getFile(),
        ]
      );

      report($e);

      throw $e;
    }
  }

  /**
   * Get the payments of a student.
   *
   * Only return information that is useful for the student.
   * Internal PayPal data is not exposed in the API response.
   */
  public function getMyPayments(
    User $student,
    ?string $date = null
  ) {
    $payments = ReservationDetail::query()
      ->with([
        'reservation.tutor:id,first_names,last_names',
      ])
      ->whereHas(
        'reservation',
        function ($query) use (
          $student,
          $date
        ) {
          $query->where(
            'user_id',
            $student->id
          );

          if ($date !== null) {
            $query->whereDate(
              'reservation_date',
              $date
            );
          }
        }
      )
      ->where(
        'payment_status',
        'completed'
      )
      ->orderBy(
        'created_at',
        'desc'
      )
      ->paginate(10);

    // Transform the payment data before returning it.
    // This prevents internal PayPal information from
    // being exposed to the student.
    $payments->getCollection()->transform(
      function (ReservationDetail $payment) {
        $reservation = $payment->reservation;
        $tutor = $reservation?->tutor;

        return [
          'id' => $payment->id,
          'reservation_id' => $payment->reservation_id,
          'transaction_id' => $payment->transaction_id,
          'payment_status' => $payment->payment_status,
          'amount' => $payment->amount,
          'currency' => 'USD',

          'reservation' => $reservation
            ? [
              'id' => $reservation->id,
              'date' => $reservation->reservation_date->format('Y-m-d'),
              'start_time' => $reservation->start_time,
              'end_time' => $reservation->end_time,

              'tutor' => $tutor
                ? [
                  'id' => $tutor->id,
                  'name' => trim(
                    "{$tutor->first_names} {$tutor->last_names}"
                  ),
                ]
                : null,
            ]
            : null,
        ];
      }
    );

    return $payments;
  }

  /**
   * Build a useful error message from PayPal.
   */
  private function getPayPalApiError(
    array $result
  ): string {
    $name = data_get(
      $result,
      'name'
    );

    $message = data_get(
      $result,
      'message'
    );

    $debugId = data_get(
      $result,
      'debug_id'
    );

    $details = data_get(
      $result,
      'details',
      []
    );

    $parts = [];

    if ($name) {
      $parts[] = $name;
    }

    if (is_array($details)) {
      foreach ($details as $detail) {
        $issue = $detail['issue'] ?? null;
        $description = $detail['description'] ?? null;

        if (
          $issue !== null
          && $description !== null
        ) {
          $parts[] = "{$issue}: {$description}";
        } elseif ($description !== null) {
          $parts[] = $description;
        }
      }
    }

    if ($message) {
      $parts[] = $message;
    }

    if ($debugId) {
      $parts[] = "debug_id={$debugId}";
    }

    if (empty($parts)) {
      return 'PayPal rejected the request.';
    }

    return 'PayPal error: ' . implode(' | ', $parts);
  }

  /**
   * Return a safe and useful PayPal error message.
   */
  private function getPaypalErrorMessage(
    Throwable $exception
  ): string {
    $message = trim(
      $exception->getMessage()
    );

    if ($message === '') {
      return 'PayPal returned an unknown error.';
    }

    if (
      str_starts_with(
        $message,
        'PayPal error:'
      )
    ) {
      return $message;
    }

    if (
      str_contains(
        $message,
        'PayPal '
      )
    ) {
      return $message;
    }

    if (
      str_contains(
        $message,
        'UNAUTHORIZED'
      )
      || str_contains(
        $message,
        'AUTHENTICATION_FAILURE'
      )
    ) {
      return 'PayPal authentication failed. Check the PayPal credentials.';
    }

    if (
      str_contains(
        strtolower($message),
        'timeout'
      )
    ) {
      return 'PayPal did not respond in time. Please try again.';
    }

    return $message;
  }
}
