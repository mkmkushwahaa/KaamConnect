<?php

declare(strict_types=1);


require_once __DIR__ . '/mail_config.local.php';
/**
 * KaamConnect - Mailer Service using PHPMailer
 * Technology: PHP 8+, PHPMailer SMTP Engine
 * Compatible with RFC 5321 & secure environment variable credentials
 */


require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Configure and return a new PHPMailer instance based on environment variables
 */
function getMailerInstance(): PHPMailer {
    $mail = new PHPMailer(true);

    // Character set and encoding
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';

    // Retrieve environment variables with safe defaults
    $smtpHost = getenv('SMTP_HOST') ?: '';
    $smtpPort = (int)(getenv('SMTP_PORT') ?: 587);
    $smtpUser = getenv('SMTP_USER') ?: '';
    $smtpPass = getenv('SMTP_PASS') ?: '';
    $smtpSecure = strtolower((string)(getenv('SMTP_SECURE') ?: 'tls'));
    $fromEmail = getenv('SMTP_FROM_EMAIL') ?: 'noreply@kaamconnect.com';
    $fromName = getenv('SMTP_FROM_NAME') ?: 'KaamConnect Security Team';

    if (!empty($smtpHost)) {
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->Port = $smtpPort;

        if (!empty($smtpUser)) {
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
        }

        if ($smtpSecure === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtpSecure === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ];
    } else {
        // Fallback to PHP mail() if SMTP_HOST is not set
        $mail->Mailer = 'mail';
    }

    $mail->setFrom($fromEmail, $fromName);
    return $mail;
}

/**
 * Send Password Reset Email with Token Link
 *
 * @param string $recipientEmail
 * @param string $recipientName
 * @param string $resetLink
 * @return array ['success' => bool, 'message' => string, 'simulated' => bool, 'reset_link' => string]
 */
function sendPasswordResetEmail(string $recipientEmail, string $recipientName, string $resetLink): array {
    $subject = 'Reset Your KaamConnect Password';
    $appName = 'KaamConnect';

    // Construct responsive HTML body with blue-and-white theme
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password - {$appName}</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; margin: 0; padding: 20px; color: #1e293b; }
        .email-container { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .email-header { background: linear-gradient(135deg, #1E40AF 0%, #2563EB 100%); color: #ffffff; padding: 28px 24px; text-align: center; }
        .email-header h1 { margin: 0; font-size: 24px; font-weight: 700; }
        .email-body { padding: 32px 24px; line-height: 1.6; }
        .btn-reset { display: inline-block; background-color: #2563EB; color: #ffffff !important; padding: 13px 28px; border-radius: 8px; font-weight: 600; text-decoration: none; margin: 20px 0; }
        .link-box { background: #f1f5f9; border-radius: 8px; padding: 12px; word-break: break-all; font-size: 13px; color: #475569; margin-top: 15px; }
        .notice-box { background: #fff7ed; border-left: 4px solid #f97316; padding: 12px 16px; border-radius: 4px; margin: 20px 0; font-size: 13px; color: #9a3412; }
        .email-footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px; text-align: center; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>KaamConnect</h1>
            <p style="margin: 6px 0 0; opacity: 0.85; font-size: 14px;">Password Recovery Notification</p>
        </div>
        <div class="email-body">
            <h2 style="margin-top: 0; font-size: 20px; color: #0f172a;">Hello, {$recipientName}</h2>
            <p>We received a request to reset the password associated with your KaamConnect account (<strong>{$recipientEmail}</strong>).</p>
            <p>Please click the button below to choose a new password:</p>
            
            <div style="text-align: center;">
                <a href="{$resetLink}" class="btn-reset" target="_blank">Reset My Password</a>
            </div>

            <div class="notice-box">
                <strong>Important:</strong> This reset link will automatically expire in <strong>15 minutes</strong> for security. If you did not request this password reset, please ignore this email or review your account activity.
            </div>

            <p style="font-size: 13px; color: #64748b; margin-top: 25px;">If the button above does not work, copy and paste the following URL into your web browser:</p>
            <div class="link-box">{$resetLink}</div>
        </div>
        <div class="email-footer">
            &copy; 2026 <strong>KaamConnect</strong>. All rights reserved.<br>
            Local Worker Hiring & Employment Marketplace. B.Tech Major Project.
        </div>
    </div>
</body>
</html>
HTML;

    $altBody = "Hello {$recipientName},\n\nWe received a request to reset your KaamConnect password.\n" .
               "Click or copy the link below to reset your password (valid for 15 minutes):\n" .
               "{$resetLink}\n\n" .
               "If you did not request this, please disregard this message.\n\n" .
               "-- KaamConnect Security Team";

    // Attempt delivery via PHPMailer
    $mail = getMailerInstance();
    $mail->addAddress($recipientEmail, $recipientName);
    $mail->Subject = $subject;
    $mail->isHTML(true);
    $mail->Body = $htmlBody;
    $mail->AltBody = $altBody;

    // Check if live SMTP is configured
    $hasSmtpConfig = !empty(getenv('SMTP_HOST'));

    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }
    $logEntry = sprintf(
        "[%s] PASSWORD RESET TO: %s (%s) | LINK: %s\n",
        date('Y-m-d H:i:s'),
        $recipientEmail,
        $recipientName,
        $resetLink
    );
    @file_put_contents($logDir . '/mail.log', $logEntry, FILE_APPEND);

    if ($hasSmtpConfig) {
        try {
            $sent = $mail->send();
            return [
                'success' => $sent,
                'message' => $sent ? 'Email delivered via SMTP.' :  'Unable to send email.',
                'simulated' => false,
            ];
        } catch (Exception $e) {
            error_log('SMTP Send Error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Unable to send password reset email. Please try again later.',
                'simulated' => false
            ];
        }
    } else {
        error_log('SMTP configuration missing.');

        return [
           'success' => false,
           'message' => 'Email service is not configured. Please try again later.',
           'simulated' => false
    ];
}
}

