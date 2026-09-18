<?php

namespace App\Services;

use App\Models\PaypalOrder;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Resend\Laravel\Facades\Resend;
use Twilio\Rest\Client;

class NotificationService
{
  /**
   * Temporary email allowed by Resend testing mode.
   */
  private string $resendTestEmail = 'luchogo170@gmail.com';

  /**
   * Send reservation confirmation email.
   */
  public function sendReservationEmail(
    PaypalOrder $paypalOrder,
    Reservation $reservation
  ): bool {
    try {
      $user = User::find($paypalOrder->user_id);

      if (!$user) {
        Log::warning('Reservation email failed: student not found.', [
          'user_id' => $paypalOrder->user_id,
          'reservation_id' => $reservation->id,
        ]);

        return false;
      }

      $recipient = $this->resendTestEmail;

      $tutor = User::find($paypalOrder->tutor_id);

      $tutorName = $tutor
        ? trim("{$tutor->first_names} {$tutor->last_names}")
        : 'Your tutor';

      $studentName = trim(
        "{$user->first_names} {$user->last_names}"
      );

      $html = View::make('emails.reservation-confirmed', [
        'studentName' => $studentName,
        'tutorName' => $tutorName,
        'date' => $paypalOrder->reservation_date->format('Y-m-d'),
        'hours' => implode(', ', $paypalOrder->hours),
        'amount' => number_format(
          (float) $paypalOrder->total_amount,
          2
        ),
        'currency' => 'USD',
      ])->render();

      Resend::emails()->send([
        'from' => config('mail.from.address'),
        'to' => $recipient,
        'subject' => 'Your reservation is confirmed',
        'html' => $html,
      ]);

      Log::info('Reservation email notification sent.', [
        'reservation_id' => $reservation->id,
        'to' => $recipient,
      ]);

      return true;
    } catch (\Throwable $e) {
      Log::error('Reservation email notification failed.', [
        'reservation_id' => $reservation->id ?? null,
        'paypal_order_id' => $paypalOrder->paypal_order_id ?? null,
        'error' => $e->getMessage(),
      ]);

      return false;
    }
  }

  /**
   * Send reservation confirmation WhatsApp.
   */
  public function sendReservationWhatsApp(
    PaypalOrder $paypalOrder,
    Reservation $reservation
  ): bool {
    try {
      $user = User::find($paypalOrder->user_id);

      if (!$user || !$user->phone) {
        Log::warning(
          'Reservation WhatsApp failed: student phone not found.',
          [
            'reservation_id' => $reservation->id,
          ]
        );

        return false;
      }

      $phone = trim($user->phone);

      if (!str_starts_with($phone, '+')) {
        $phone = '+57' . $phone;
      }

      $whatsappTo = 'whatsapp:' . $phone;

      $sid = config('services.twilio.sid');
      $token = config('services.twilio.token');
      $from = config('services.twilio.whatsapp_from');

      if (!$sid || !$token || !$from) {
        Log::error(
          'Reservation WhatsApp failed: Twilio configuration is incomplete.',
          [
            'reservation_id' => $reservation->id,
          ]
        );

        return false;
      }

      $tutor = User::find($paypalOrder->tutor_id);

      $tutorName = $tutor
        ? trim("{$tutor->first_names} {$tutor->last_names}")
        : 'Your tutor';

      $studentName = trim(
        "{$user->first_names} {$user->last_names}"
      );

      $date = $paypalOrder->reservation_date->format('Y-m-d');

      $hours = implode(', ', $paypalOrder->hours);

      $amount = number_format(
        (float) $paypalOrder->total_amount,
        2
      );

      $messageBody =
        "🎉 *Reservation Confirmed!*\n\n" .
        "Hello, {$studentName}! 👋\n\n" .
        "Your tutoring session has been confirmed.\n\n" .
        "👨‍🏫 *Tutor:* {$tutorName}\n" .
        "📅 *Date:* {$date}\n" .
        "🕐 *Time:* {$hours}\n" .
        "💻 *Location:* Online\n" .
        "💰 *Total:* \${$amount} USD\n" .
        "💳 *Payment:* Confirmed\n\n" .
        "Please be ready a few minutes before your session.\n\n" .
        "Thank you for choosing *Tutor Reservation*! 🙌";

      $twilio = new Client($sid, $token);

      $message = $twilio->messages->create(
        $whatsappTo,
        [
          'from' => $from,
          'body' => $messageBody,
        ]
      );

      Log::info('Reservation WhatsApp notification sent.', [
        'reservation_id' => $reservation->id,
        'sid' => $message->sid,
        'status' => $message->status,
      ]);

      return true;
    } catch (\Throwable $e) {
      Log::error('Reservation WhatsApp notification failed.', [
        'reservation_id' => $reservation->id ?? null,
        'paypal_order_id' => $paypalOrder->paypal_order_id ?? null,
        'error' => $e->getMessage(),
      ]);

      return false;
    }
  }

  /**
   * Send reservation cancellation email.
   */
  public function sendCancellationEmail(
    Reservation $reservation
  ): bool {
    try {
      $user = User::find($reservation->user_id);

      if (!$user) {
        Log::warning(
          'Cancellation email failed: student not found.',
          [
            'user_id' => $reservation->user_id,
            'reservation_id' => $reservation->id,
          ]
        );

        return false;
      }

      $recipient = $this->resendTestEmail;

      $tutor = User::find($reservation->tutor_id);

      $tutorName = $tutor
        ? trim("{$tutor->first_names} {$tutor->last_names}")
        : 'Your tutor';

      $studentName = trim(
        "{$user->first_names} {$user->last_names}"
      );

      $html = View::make('emails.reservation-cancelled', [
        'studentName' => $studentName,
        'tutorName' => $tutorName,
        'date' => $reservation->reservation_date->format('Y-m-d'),
        'hours' => "{$reservation->start_time} - {$reservation->end_time}",
        'amount' => number_format(
          (float) $reservation->total_amount,
          2
        ),
        'currency' => 'USD',
        'reason' => $reservation->cancellation_reason,
      ])->render();

      Resend::emails()->send([
        'from' => config('mail.from.address'),
        'to' => $recipient,
        'subject' => 'Your reservation was cancelled',
        'html' => $html,
      ]);

      Log::info('Cancellation email notification sent.', [
        'reservation_id' => $reservation->id,
        'to' => $recipient,
      ]);

      return true;
    } catch (\Throwable $e) {
      Log::error('Cancellation email notification failed.', [
        'reservation_id' => $reservation->id ?? null,
        'error' => $e->getMessage(),
      ]);

      return false;
    }
  }

  /**
   * Send reservation cancellation WhatsApp.
   */
  public function sendCancellationWhatsApp(
    Reservation $reservation
  ): bool {
    try {
      $user = User::find($reservation->user_id);

      if (!$user || !$user->phone) {
        Log::warning(
          'Cancellation WhatsApp failed: student phone not found.',
          [
            'reservation_id' => $reservation->id,
          ]
        );

        return false;
      }

      $phone = trim($user->phone);

      if (!str_starts_with($phone, '+')) {
        $phone = '+57' . $phone;
      }

      $whatsappTo = 'whatsapp:' . $phone;

      $sid = config('services.twilio.sid');
      $token = config('services.twilio.token');
      $from = config('services.twilio.whatsapp_from');

      if (!$sid || !$token || !$from) {
        Log::error(
          'Cancellation WhatsApp failed: Twilio configuration is incomplete.',
          [
            'reservation_id' => $reservation->id,
          ]
        );

        return false;
      }

      $tutor = User::find($reservation->tutor_id);

      $tutorName = $tutor
        ? trim("{$tutor->first_names} {$tutor->last_names}")
        : 'Your tutor';

      $date = $reservation->reservation_date->format('Y-m-d');

      $hours =
        "{$reservation->start_time} - {$reservation->end_time}";

      $amount = number_format(
        (float) $reservation->total_amount,
        2
      );

      $reason = $reservation->cancellation_reason
        ?: 'Not specified';

      $messageBody =
        "❌ *Reservation Cancelled*\n\n" .
        "Hello! 👋\n\n" .
        "Your tutoring reservation has been cancelled.\n\n" .
        "👨‍🏫 *Tutor:* {$tutorName}\n" .
        "📅 *Date:* {$date}\n" .
        "🕐 *Time:* {$hours}\n" .
        "💻 *Location:* Online\n" .
        "💰 *Total:* \${$amount} USD\n" .
        "📝 *Reason:* {$reason}\n\n" .
        "If you have any questions, please contact us.\n\n" .
        "Thank you for choosing *Tutor Reservation*!";

      $twilio = new Client($sid, $token);

      $message = $twilio->messages->create(
        $whatsappTo,
        [
          'from' => $from,
          'body' => $messageBody,
        ]
      );

      Log::info('Cancellation WhatsApp notification sent.', [
        'reservation_id' => $reservation->id,
        'sid' => $message->sid,
        'status' => $message->status,
      ]);

      return true;
    } catch (\Throwable $e) {
      Log::error('Cancellation WhatsApp notification failed.', [
        'reservation_id' => $reservation->id ?? null,
        'error' => $e->getMessage(),
      ]);

      return false;
    }
  }
}
