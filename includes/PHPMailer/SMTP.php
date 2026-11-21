<?php
namespace PHPMailer\PHPMailer;

/**
 * RFC 5321 compliant SMTP client implementation for PHPMailer.
 */
class SMTP
{
    const VERSION = '6.9.1';
    const CRLF = "\r\n";
    const DEFAULT_PORT = 25;
    const MAX_LINE_LENGTH = 998;

    protected $smtp_conn = null;
    protected $error = [];
    protected $helo_rply = null;
    protected $server_caps = null;
    public $do_debug = 0;
    public $Timeout = 10;
    public $Timelimit = 30;

    public function connect(string $host, ?int $port = null, ?int $timeout = null, array $options = []): bool
    {
        $this->error = [];
        if ($this->connected()) {
            return true;
        }

        $port = $port ?: self::DEFAULT_PORT;
        $timeout = $timeout ?: $this->Timeout;

        $socket_context = stream_context_create($options);
        set_error_handler([$this, 'errorHandler']);
        $this->smtp_conn = stream_socket_client(
            $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $socket_context
        );
        restore_error_handler();

        if (!is_resource($this->smtp_conn)) {
            $this->error = [
                'error' => "Failed to connect to server {$host}:{$port} - $errstr ($errno)",
                'errno' => $errno,
                'errstr' => $errstr
            ];
            return false;
        }

        stream_set_timeout($this->smtp_conn, $timeout, 0);
        $announce = $this->get_lines();
        if (!$this->errorHandlerCheck($announce, 220)) {
            $this->error = [
                'error' => 'Invalid greeting from SMTP server: ' . trim($announce)
            ];
            return false;
        }
        return true;
    }

    public function startTLS(): bool
    {
        if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) {
            $this->error = ['error' => 'STARTTLS command rejected: ' . trim($this->last_reply)];
            return false;
        }

        $crypto_method = STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        set_error_handler([$this, 'errorHandler']);
        $crypto_ok = stream_socket_enable_crypto($this->smtp_conn, true, $crypto_method);
        restore_error_handler();

        if (!$crypto_ok) {
            $this->error = ['error' => 'TLS crypto negotiation failed.'];
            return false;
        }
        return true;
    }

    public function authenticate(string $user, string $pass, string $authtype = 'LOGIN'): bool
    {
        if ($authtype === 'PLAIN') {
            $ok = $this->sendCommand('AUTH PLAIN', 'AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass), 235);
            if (!$ok) {
                $this->error = ['error' => 'AUTH PLAIN rejected: ' . trim($this->last_reply)];
            }
            return $ok;
        }

        // Default LOGIN
        if (!$this->sendCommand('AUTH LOGIN', 'AUTH LOGIN', 334)) {
            $this->error = ['error' => 'AUTH LOGIN initiation rejected: ' . trim($this->last_reply)];
            return false;
        }
        if (!$this->sendCommand('User', base64_encode($user), 334)) {
            $this->error = ['error' => 'Username rejected: ' . trim($this->last_reply)];
            return false;
        }
        if (!$this->sendCommand('Password', base64_encode($pass), 235)) {
            $this->error = ['error' => 'Password rejected: ' . trim($this->last_reply)];
            return false;
        }
        return true;
    }

    public function connected(): bool
    {
        if (is_resource($this->smtp_conn)) {
            $sock_status = stream_get_meta_data($this->smtp_conn);
            if ($sock_status['eof']) {
                $this->close();
                return false;
            }
            return true;
        }
        return false;
    }

    public function close(): void
    {
        $this->error = [];
        $this->server_caps = null;
        $this->helo_rply = null;
        if (is_resource($this->smtp_conn)) {
            fclose($this->smtp_conn);
            $this->smtp_conn = null;
        }
    }

    public function hello(string $host = ''): bool
    {
        return $this->sendHello('EHLO', $host) || $this->sendHello('HELO', $host);
    }

    protected function sendHello(string $hello, string $host): bool
    {
        $noerror = $this->sendCommand($hello, $hello . ' ' . $host, 250);
        $this->helo_rply = $this->last_reply;
        return $noerror;
    }

    public function mail(string $from): bool
    {
        return $this->sendCommand('MAIL FROM', 'MAIL FROM:<' . $from . '>', 250);
    }

    public function recipient(string $address): bool
    {
        return $this->sendCommand('RCPT TO', 'RCPT TO:<' . $address . '>', [250, 251]);
    }

    public function data(string $msg_data): bool
    {
        if (!$this->sendCommand('DATA', 'DATA', 354)) {
            return false;
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $msg_data));
        foreach ($lines as $line) {
            if (isset($line[0]) && $line[0] === '.') {
                $line = '.' . $line;
            }
            $this->client_send($line . self::CRLF);
        }

        return $this->sendCommand('DATA END', '.', 250);
    }

    public function quit(): bool
    {
        return $this->sendCommand('QUIT', 'QUIT', 221);
    }

    protected $last_reply = '';

    protected function sendCommand(string $commandName, string $command, $expect): bool
    {
        if (!$this->connected()) {
            $this->error = ['error' => "Called $commandName without being connected"];
            return false;
        }
        $this->client_send($command . self::CRLF);
        $this->last_reply = $this->get_lines();
        $matches = [];
        // if (preg_match('/^([0-9]{3})[ -]\(.\*)$/m', $this->last_reply, $matches)) {

        if (preg_match('/^([0-9]{3})[ -](.*)$/m', $this->last_reply, $matches)) {
            $code = (int)$matches[1];
            if (is_array($expect)) {
                return in_array($code, $expect, true);
            }
            return $code === (int)$expect;
        }
        return false;
    }

    protected function client_send(string $data): int
    {
        return (int)fwrite($this->smtp_conn, $data);
    }

    protected function get_lines(): string
    {
        $data = '';
        $endtime = 0;
        if (!is_resource($this->smtp_conn)) {
            return '';
        }
        stream_set_timeout($this->smtp_conn, $this->Timeout);
        while (is_resource($this->smtp_conn) && !feof($this->smtp_conn)) {
            $str = @fgets($this->smtp_conn, 515);
            $data .= $str;
            if ((isset($str[3]) && $str[3] === ' ') || !isset($str[3])) {
                break;
            }
            $info = stream_get_meta_data($this->smtp_conn);
            if ($info['timed_out']) {
                break;
            }
        }
        return $data;
    }

    public function getError(): array
    {
        return $this->error;
    }

    public function getLastReply(): string
    {
        return (string)$this->last_reply;
    }

    public function errorHandler($errno, $errmsg, $errfile = '', $errline = 0): void
    {
        $this->error = ['error' => $errmsg, 'errno' => $errno, 'file' => $errfile, 'line' => $errline];
    }

    protected function errorHandlerCheck(string $reply, int $expect): bool
    {
        if (empty($reply)) return false;
        $code = (int)substr($reply, 0, 3);
        return $code === $expect;
    }
}

