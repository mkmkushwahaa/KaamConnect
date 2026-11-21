<?php

declare(strict_types=1);

/**
 * KaamConnect - Mailer Service using PHPMailer
 * Technology: PHP 8+, PHPMailer SMTP Engine
 * Compatible with RFC 5321 & secure hosting environment configurations
 */

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Retrieve unified mail configuration from file, $_ENV, $_SERVER, or getenv()
 * Ensures full compatibility on hosting providers (e.g. InfinityFree) where putenv() is disabled.
 */
function getMailConfig(): array {
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $httpHost = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
    $isProduction = in_array($httpHost, [
        'kaamconnect.site.je',
        'www.kaamconnect.site.je'
    ], true);

    $mailConfigFile = __DIR__ . ($isProduction ? '/mail_config.hosting.php' : '/mail_config.local.php');
    $loaded = [];
    if (is_file($mailConfigFile)) {
        $fileResult = require $mailConfigFile;
        if (is_array($fileResult)) {
            $loaded = $fileResult;
        }
    }

    $getVal = function(string $key, $default = '') use ($loaded) {
        if (!empty($loaded[$key])) {
            return $loaded[$key];
        }
        $envKey = 'SMTP_' . strtoupper($key);
        if (!empty($_ENV[$envKey])) {
            return $_ENV[$envKey];
        }
        if (!empty($_SERVER[$envKey])) {
            return $_SERVER[$envKey];
        }
        $fromEnv = getenv($envKey);
        if ($fromEnv !== false && $fromEnv !== '') {
            return $fromEnv;
        }
        return $default;
    };

    $config = [
        'host'       => (string)$getVal('host', ''),
        'port'       => (int)$getVal('port', 587),
        'secure'     => strtolower((string)$getVal('secure', 'tls')),
        'user'       => (string)$getVal('user', ''),
        'pass'       => (string)$getVal('pass', ''),
        'from_email' => (string)$getVal('from_email', 'noreply@kaamconnect.site.je'),
        'from_name'  => (string)$getVal('from_name', 'KaamConnect Security Team'),
    ];

    return $config;
}

/**
 * Helper to mask sensitive email addresses in logs (e.g. mk0592497@gmail.com -> mk***97@gmail.com)
 */
function maskEmail(string $email): string {
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return '***';
    }
    $name = $parts[0];
    $domain = $parts[1];
    $len = strlen($name);
    if ($len <= 2) {
        $maskedName = substr($name, 0, 1) . '*';
    } elseif ($len <= 4) {
        $maskedName = substr($name, 0, 1) . '**' . substr($name, -1);
    } else {
        $maskedName = substr($name, 0, 2) . str_repeat('*', min(4, $len - 4)) . substr($name, -2);
    }
    return $maskedName . '@' . $domain;
}

/**
 * Categorize raw SMTP errors for safe, actionable diagnostics
 */
function categorizeMailError(string $msg): string {
    $lower = strtolower($msg);
    if (
        str_contains($lower, 'timed out') ||
        str_contains($lower, 'timeout') ||
        str_contains($lower, '110') ||
        str_contains($lower, '101') ||
        str_contains($lower, '111') ||
        str_contains($lower, 'could not connect to smtp host')
    ) {
        return 'CONNECTION_OR_HOSTING_PORT_BLOCKED';
    }
    if (
        str_contains($lower, 'authentication') ||
        str_contains($lower, '535') ||
        str_contains($lower, 'password rejected') ||
        str_contains($lower, 'username rejected')
    ) {
        return 'AUTHENTICATION_FAILURE';
    }
    if (
        str_contains($lower, 'starttls') ||
        str_contains($lower, 'crypto') ||
        str_contains($lower, 'ssl') ||
        str_contains($lower, 'tls')
    ) {
        return 'TLS_NEGOTIATION_ERROR';
    }
    if (
        str_contains($lower, 'configuration missing') ||
        str_contains($lower, 'smtp host is not defined')
    ) {
        return 'CONFIGURATION_MISSING';
    }
    return 'SMTP_GENERAL_ERROR';
}

/**
 * Low-level safe file log writer
 * Designed for resilience on shared/NFS hosting environments (e.g. InfinityFree)
 * Avoids LOCK_EX which fails on network filesystems, and includes root fallback.
 */
function safeWriteLog(string $entry): bool {
    $baseDir = dirname(__DIR__); // Project root (/htdocs)
    $candidatePaths = [
        $baseDir . '/logs/mail_error.log',
        $baseDir . '/logs/mail_debug.log',
        $baseDir . '/mail_debug.log'
    ];

    $success = false;

    foreach ($candidatePaths as $filePath) {
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        // Try standard file_put_contents without LOCK_EX (NFS / shared hosting safe)
        $written = @file_put_contents($filePath, $entry, FILE_APPEND);
        if ($written !== false && $written > 0) {
            $success = true;
            break;
        }

        // Fallback: try fopen / fwrite
        $fp = @fopen($filePath, 'ab');
        if ($fp) {
            @fwrite($fp, $entry);
            @fclose($fp);
            $success = true;
            break;
        }
    }

    // Always mirror to standard PHP system error log
    error_log(trim($entry));
    return $success;
}

/**
 * Log all mail and password reset lifecycle events safely
 * Masks emails and redacts passwords to keep logs private and clean.
 */
function logMailActivity(string $recipientEmail, string $action, string $details = ''): void {
    $cfg = getMailConfig();
    if (!empty($cfg['pass'])) {
        $details = str_replace($cfg['pass'], '[REDACTED_PASSWORD]', $details);
    }

    $maskedEmail = maskEmail($recipientEmail);
    $httpHost = $_SERVER['HTTP_HOST'] ?? 'cli';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $entry = sprintf(
        "[%s] [%s] Host: %s | IP: %s | Recipient: %s | Details: %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($action),
        $httpHost,
        $ip,
        $maskedEmail,
        trim($details)
    );

    safeWriteLog($entry);
}

/**
 * Record detailed SMTP failures in private server-side log
 * Never exposes credentials, passwords, tokens, or links
 */
function logMailError(string $recipientEmail, string $rawError, array $context = []): void {
    $category = categorizeMailError($rawError);
    logMailActivity($recipientEmail, $category, $rawError);
}

/**
 * Configure and return a new PHPMailer instance based on environment variables
 */
function getMailerInstance(): PHPMailer {
    $mail = new PHPMailer(true);

    // Character set and encoding
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->Timeout = 15; // Safe timeout to prevent worker hangs on blocked ports

    $cfg = getMailConfig();

    if (!empty($cfg['host'])) {
        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->Port = $cfg['port'];

        if (!empty($cfg['user'])) {
            $mail->SMTPAuth = true;
            $mail->Username = $cfg['user'];
            $mail->Password = $cfg['pass'];
        }

        if ($cfg['secure'] === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($cfg['secure'] === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        // Strict SSL peer verification is maintained for production security
    } else {
        // Fallback to PHP mail() if SMTP_HOST is not set
        $mail->Mailer = 'mail';
    }

    $mail->setFrom($cfg['from_email'], $cfg['from_name']);
    return $mail;
}

/**
 * Send Password Reset Email with Token Link
 *
 * @param string $recipientEmail
 * @param string $recipientName
 * @param string $resetLink
 * @return array ['success' => bool, 'message' => string, 'simulated' => bool]
 */
function sendPasswordResetEmail(string $recipientEmail, string $recipientName, string $resetLink): array {
    $subject = 'Reset Your KaamConnect Password';
    $appName = 'KaamConnect';

    $cfg = getMailConfig();
    if (empty($cfg['host'])) {
        logMailError($recipientEmail, 'SMTP host is not defined in configuration.', ['stage' => 'config']);
        return [
            'success' => false,
            'message' => 'Email service is not configured. Please try again later.',
            'simulated' => false
        ];
    }

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

    try {
        $mail = getMailerInstance();
        $mail->addAddress($recipientEmail, $recipientName);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        $mail->AltBody = $altBody;

        $sent = $mail->send();
        if ($sent) {
            return [
                'success' => true,
                'message' => 'Email delivered via SMTP.',
                'simulated' => false,
            ];
        }

        logMailError($recipientEmail, 'PHPMailer send returned false without exception.');
        return [
            'success' => false,
            'message' => 'Unable to send password reset email. Please try again later.',
            'simulated' => false
        ];
    } catch (Exception $e) {
        logMailError($recipientEmail, $e->getMessage(), ['stage' => 'smtp_send']);
        return [
            'success' => false,
            'message' => 'Unable to send password reset email. Please try again later.',
            'simulated' => false
        ];
    }
}
