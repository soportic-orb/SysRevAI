<?php

declare(strict_types=1);

namespace SysRevAI\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use SysRevAI\Models\User;

/**
 * SMTP mail sending via PHPMailer, configured from the encrypted settings.
 *
 * Used by:
 *   - the admin "send test email" action,
 *   - the admin-notification helpers (new user registered / pending
 *     validation), which fan out to every active owner / admin.
 */
final class MailService
{
    /** @return array{ok:bool,message:string} */
    public static function sendTest(string $to): array
    {
        $res = self::send($to, 'SysRevAI — SMTP test',
            'This is a test email from your SysRevAI installation. If you received it, SMTP is configured correctly.');
        return ['ok' => $res['ok'], 'message' => $res['ok']
            ? 'Test email sent to ' . $to . '.'
            : ($res['error'] ?? 'Unknown error')];
    }

    /**
     * Best-effort single send. Returns ['ok' => false, 'error' => …] when
     * PHPMailer is not installed, SMTP isn't configured, or the SMTP
     * server rejects the message. NEVER throws — callers chain it after
     * the primary action and don't want a mail outage to break it.
     *
     * @return array{ok:bool,error:?string}
     */
    public static function send(string $to, string $subject, string $body, ?string $bodyHtml = null): array
    {
        if (!class_exists(PHPMailer::class)) {
            return ['ok' => false, 'error' => 'PHPMailer is not installed.'];
        }
        $host = (string) (setting('smtp.host') ?? '');
        if ($host === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'SMTP is not configured.'];
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host     = $host;
            $mail->Port     = (int) (setting('smtp.port') ?? 587);
            $mail->SMTPAuth = (string) (setting('smtp.username') ?? '') !== '';
            $mail->Username = (string) (setting('smtp.username') ?? '');
            $mail->Password = (string) (setting('smtp.password') ?? '');

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
            $mail->Subject  = $subject;
            $mail->CharSet  = 'UTF-8';

            if ($bodyHtml !== null) {
                $mail->isHTML(true);
                $mail->Body    = $bodyHtml;
                $mail->AltBody = $body;
            } else {
                $mail->Body = $body;
            }

            $mail->send();
            return ['ok' => true, 'error' => null];
        } catch (PHPMailerException $e) {
            return ['ok' => false, 'error' => $mail->ErrorInfo ?: $e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fan a notification out to every active owner / admin. Drops
     * silently when SMTP isn't configured so an unconfigured install
     * never breaks the primary action (user registration, etc.).
     *
     * @return int  Number of recipients the message actually went to.
     */
    public static function notifyAdmins(string $subject, string $body, ?string $bodyHtml = null): int
    {
        try {
            $emails = User::adminEmails();
        } catch (\Throwable) {
            return 0;
        }
        if ($emails === []) {
            return 0;
        }
        $sent = 0;
        foreach ($emails as $to) {
            $res = self::send($to, $subject, $body, $bodyHtml);
            if ($res['ok']) {
                $sent++;
            }
        }
        return $sent;
    }
}
