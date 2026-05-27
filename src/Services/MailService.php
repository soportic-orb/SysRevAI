<?php

declare(strict_types=1);

namespace SysRevAI\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * SMTP mail sending via PHPMailer, configured from the encrypted settings.
 *
 * Notification templates and digesting arrive with the collaboration phase;
 * this ships the configuration test used by the admin panel.
 */
final class MailService
{
    /** @return array{ok:bool,message:string} */
    public static function sendTest(string $to): array
    {
        if (!class_exists(PHPMailer::class)) {
            return ['ok' => false, 'message' => 'PHPMailer is not installed yet. Run "composer install".'];
        }

        $host = (string) (setting('smtp.host') ?? '');
        if ($host === '') {
            return ['ok' => false, 'message' => 'SMTP host is not configured.'];
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $host;
            $mail->Port       = (int) (setting('smtp.port') ?? 587);
            $mail->SMTPAuth   = (string) (setting('smtp.username') ?? '') !== '';
            $mail->Username   = (string) (setting('smtp.username') ?? '');
            $mail->Password   = (string) (setting('smtp.password') ?? '');

            $encryption = (string) (setting('smtp.encryption') ?? 'tls');
            if ($encryption === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            }

            $fromEmail = (string) (setting('smtp.from_email') ?? ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')));
            $mail->setFrom($fromEmail, (string) (setting('smtp.from_name') ?? 'SysRevAI'));
            $mail->addAddress($to);
            $mail->Subject = 'SysRevAI — SMTP test';
            $mail->Body    = 'This is a test email from your SysRevAI installation. If you received it, SMTP is configured correctly.';

            $mail->send();
            return ['ok' => true, 'message' => 'Test email sent to ' . $to . '.'];
        } catch (PHPMailerException $e) {
            return ['ok' => false, 'message' => $mail->ErrorInfo ?: $e->getMessage()];
        }
    }
}
