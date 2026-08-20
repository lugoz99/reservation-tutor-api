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
   * Send the "reservation confirmed" email.
   *
   * The HTML content comes from a Blade view
   * (resources/views/emails/reservation-confirmed.blade.php).
   * We render it to a string, then send it with the Resend API
   * directly — no Mailable class is needed for this.
   */
  public function sendReservationEmail(
    PaypalOrder $paypalOrder,
    Reservation $reservation
  ): void {
    try {
      $user = User::find($paypalOrder->user_id);

      if (!$user || !$user->email) {
        Log::warning('Student has no valid email', [
          'user_id' => $paypalOrder->user_id,
        ]);
        return;
      }

      $tutor = User::find($paypalOrder->tutor_id);
      $tutorName = $tutor
        ? trim("{$tutor->first_names} {$tutor->last_names}")
        : 'Your tutor';

      $html = View::make('emails.reservation-confirmed', [
        'studentName' => trim("{$user->first_names} {$user->last_names}"),
        'tutorName' => $tutorName,
        'date' => $paypalOrder->reservation_date->format('Y-m-d'),
        'hours' => implode(', ', $paypalOrder->hours),
        'amount' => number_format($paypalOrder->total_amount, 2),
        'currency' => 'COP',
      ])->render();

      Resend::emails()->send([
        // Must be a verified domain/sender in your Resend account
        'from' => config('mail.from.address'),
        'to' => $user->email,
        'subject' => 'Your reservation is confirmed',
        'html' => $html,
      ]);

      Log::info('Confirmation email sent', [
        'user_id' => $user->id,
        'reservation_id' => $reservation->id,
      ]);
    } catch (\Throwable $e) {
      Log::error('Error sending confirmation email', [
        'error' => $e->getMessage(),
        'reservation_id' => $reservation->id ?? null,
      ]);
    }
  }

  /**
   * Send the "reservation confirmed" WhatsApp message using Twilio.
   */
  public function sendReservationWhatsApp(
    PaypalOrder $paypalOrder,
    Reservation $reservation
  ): void {
    try {
      $user = User::find($paypalOrder->user_id);

      // Adjust 'phone' to the real column name in your users table
      if (!$user || !$user->phone) {
        Log::warning('Student has no valid phone number', [
          'user_id' => $paypalOrder->user_id,
        ]);
        return;
      }

      $sid = config('services.twilio.sid');
      $token = config('services.twilio.token');
      $from = config('services.twilio.whatsapp_from');

      $twilio = new Client($sid, $token);

      $messageBody = "✅ *Reservation Confirmed*\n\n"
        . "📅 Date: {$paypalOrder->reservation_date->format('Y-m-d')}\n"
        . "🕒 Hours: " . implode(', ', $paypalOrder->hours) . "\n"
        . "💵 Total: {$paypalOrder->total_amount} COP\n\n"
        . "Thank you for your trust 🙌";

      $twilio->messages->create(
        //"whatsapp:{$user->phone}",
        "whatsapp:+573233006974",
        [
          'from' => $from,
          'body' => $messageBody,
        ]
      );

    } catch (\Throwable $e) {
      Log::error('Error sending WhatsApp message', [
        'error' => $e->getMessage(),
        'reservation_id' => $reservation->id ?? null,
      ]);
    }
  }
}
