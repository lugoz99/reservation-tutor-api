<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Confirmed</title>
</head>

<body
    style="margin:0; padding:0; background-color:#f4f5f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
        style="background-color:#f4f5f7; padding:32px 16px;">
        <tr>
            <td align="center">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                    style="max-width:560px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.08);">

                    <!-- Header -->
                    <tr>
                        <td style="background-color:#2563eb; padding:32px 24px; text-align:center;">
                            <div
                                style="display:inline-block; background-color:rgba(255,255,255,0.15); border-radius:50%; width:56px; height:56px; line-height:56px; font-size:28px; margin-bottom:12px;">
                                ✅
                            </div>
                            <h1 style="margin:0; color:#ffffff; font-size:22px; font-weight:600;">
                                Reservation Confirmed
                            </h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:32px 24px;">
                            <p style="margin:0 0 20px; color:#1f2937; font-size:15px; line-height:22px;">
                                Hi {{ $studentName }},
                            </p>
                            <p style="margin:0 0 24px; color:#1f2937; font-size:15px; line-height:22px;">
                                Your payment was successful and your tutoring session is confirmed.
                                Here are the details:
                            </p>

                            <!-- Details card -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                                style="background-color:#f9fafb; border-radius:8px; border:1px solid #e5e7eb;">
                                <tr>
                                    <td style="padding:16px 20px; border-bottom:1px solid #e5e7eb;">
                                        <span
                                            style="display:block; color:#6b7280; font-size:12px; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Tutor</span>
                                        <span
                                            style="display:block; color:#111827; font-size:15px; font-weight:600;">{{ $tutorName }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 20px; border-bottom:1px solid #e5e7eb;">
                                        <span
                                            style="display:block; color:#6b7280; font-size:12px; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Date</span>
                                        <span
                                            style="display:block; color:#111827; font-size:15px; font-weight:600;">{{ $date }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 20px; border-bottom:1px solid #e5e7eb;">
                                        <span
                                            style="display:block; color:#6b7280; font-size:12px; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Hours</span>
                                        <span
                                            style="display:block; color:#111827; font-size:15px; font-weight:600;">{{ $hours }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:16px 20px;">
                                        <span
                                            style="display:block; color:#6b7280; font-size:12px; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:4px;">Total
                                            paid</span>
                                        <span
                                            style="display:block; color:#111827; font-size:18px; font-weight:700;">{{ $amount }}
                                            {{ $currency }}</span>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:24px 0 0; color:#6b7280; font-size:13px; line-height:20px;">
                                Thank you for booking with us. If you have any questions about your session, feel free
                                to reply to this email.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td
                            style="padding:20px 24px; background-color:#f9fafb; text-align:center; border-top:1px solid #e5e7eb;">
                            <p style="margin:0; color:#9ca3af; font-size:12px;">
                                This is an automated message, please do not reply directly to this address.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>

</html>
