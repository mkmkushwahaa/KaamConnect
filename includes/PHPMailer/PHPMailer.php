<?php
namespace PHPMailer\PHPMailer;

/**
 * Standard PHPMailer class implementation.
 */
class PHPMailer
{
    const CHARSET_UTF8 = 'utf-8';
    const ENCRYPTION_STARTTLS = 'tls';
    const ENCRYPTION_SMTPS = 'ssl';

    public $Priority = null;
    public $CharSet = 'utf-8';
    public $ContentType = 'text/plain';
    public $Encoding = '8bit';
    public $ErrorInfo = '';
    public $From = '';
    public $FromName = '';
    public $Sender = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $WordWrap = 0;
    public $Mailer = 'mail';
    public $Host = 'localhost';
    public $Port = 25;
    public $Helo = '';
    public $SMTPSecure = '';
    public $SMTPAutoTLS = true;
    public $SMTPAuth = false;
    public $Username = '';
    public $Password = '';
    public $AuthType = '';
    public $Timeout = 10;
    public $SMTPDebug = 0;
    public $SMTPOptions = [];

    protected $smtp = null;
    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $ReplyTo = [];
    protected $exceptions = false;

    public function __construct(bool $exceptions = null)
    {
        $this->exceptions = (bool)$exceptions;
    }

    public function isSMTP(): void
    {
        $this->Mailer = 'smtp';
    }

    public function isHTML(bool $isHtml = true): void
    {
        $this->ContentType = $isHtml ? 'text/html' : 'text/plain';
    }

    public function setFrom(string $address, string $name = '', bool $auto = true): bool
    {
        $this->From = $address;
        $this->FromName = $name;
        if ($auto && empty($this->Sender)) {
            $this->Sender = $address;
        }
        return true;
    }

    public function addAddress(string $address, string $name = ''): bool
    {
        $this->to[] = [$address, $name];
        return true;
    }

    public function send(): bool
    {
        try {
            if (empty($this->From)) {
                throw new Exception('Missing From address');
            }
            if (empty($this->to)) {
                throw new Exception('Missing recipient address');
            }

            if ($this->Mailer === 'smtp') {
                return $this->smtpSend();
            } else {
                return $this->mailSend();
            }
        } catch (Exception $e) {
            $this->ErrorInfo = $e->getMessage();
            if ($this->exceptions) {
                throw $e;
            }
            return false;
        }
    }

    protected function smtpSend(): bool
    {
        $this->smtp = new SMTP();
        $this->smtp->Timeout = $this->Timeout;

        $host = $this->Host;
        $port = $this->Port;

        if ($this->SMTPSecure === self::ENCRYPTION_SMTPS && !str_starts_with($host, 'ssl://')) {
            $host = 'ssl://' . $host;
        }

        if (!$this->smtp->connect($host, $port, $this->Timeout, $this->SMTPOptions)) {
            $err = $this->smtp->getError();
            $detail = !empty($err['error']) ? ': ' . $err['error'] : '';
            throw new Exception('Could not connect to SMTP host: ' . $this->Host . ':' . $this->Port . $detail);
        }

        $helo = !empty($this->Helo) ? $this->Helo : (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost');
        if (!$this->smtp->hello($helo)) {
            $last = trim($this->smtp->getLastReply());
            throw new Exception('SMTP error: HELO/EHLO failed' . ($last ? " ($last)" : ''));
        }

        if ($this->SMTPSecure === self::ENCRYPTION_STARTTLS) {
            if (!$this->smtp->startTLS()) {
                $err = $this->smtp->getError();
                $detail = !empty($err['error']) ? ': ' . $err['error'] : '';
                throw new Exception('SMTP error: STARTTLS failed' . $detail);
            }
            if (!$this->smtp->hello($helo)) {
                $last = trim($this->smtp->getLastReply());
                throw new Exception('SMTP error: Post-TLS HELO failed' . ($last ? " ($last)" : ''));
            }
        }

        if ($this->SMTPAuth) {
            if (!$this->smtp->authenticate($this->Username, $this->Password, $this->AuthType ?: 'LOGIN')) {
                $err = $this->smtp->getError();
                $detail = !empty($err['error']) ? ': ' . $err['error'] : '';
                throw new Exception('SMTP error: Authentication failed' . $detail);
            }
        }

        if (!$this->smtp->mail($this->From)) {
            $last = trim($this->smtp->getLastReply());
            throw new Exception('SMTP error: MAIL FROM rejected' . ($last ? " ($last)" : ''));
        }

        foreach ($this->to as $recipient) {
            if (!$this->smtp->recipient($recipient[0])) {
                $last = trim($this->smtp->getLastReply());
                throw new Exception('SMTP error: RCPT TO rejected for ' . $recipient[0] . ($last ? " ($last)" : ''));
            }
        }

        $header = $this->createHeader();
        $body = $this->Body;

        if (!$this->smtp->data($header . "\r\n\r\n" . $body)) {
            $last = trim($this->smtp->getLastReply());
            throw new Exception('SMTP error: DATA send failed' . ($last ? " ($last)" : ''));
        }

        $this->smtp->quit();
        $this->smtp->close();
        return true;
    }

    protected function mailSend(): bool
    {
        $toArr = [];
        foreach ($this->to as $t) {
            $toArr[] = !empty($t[1]) ? '"' . addslashes($t[1]) . '" <' . $t[0] . '>' : $t[0];
        }
        $toStr = implode(', ', $toArr);

        $headers = [
            'From: ' . (!empty($this->FromName) ? '"' . addslashes($this->FromName) . '" <' . $this->From . '>' : $this->From),
            'MIME-Version: 1.0',
            'Content-Type: ' . $this->ContentType . '; charset=' . $this->CharSet,
            'Content-Transfer-Encoding: ' . $this->Encoding,
            'X-Mailer: KaamConnect-Mailer'
        ];

        return @mail($toStr, $this->Subject, $this->Body, implode("\r\n", $headers));
    }

    protected function createHeader(): string
    {
        $lines = [];
        $lines[] = 'Date: ' . date('D, j M Y H:i:s O');
        $lines[] = 'From: ' . (!empty($this->FromName) ? '"' . addslashes($this->FromName) . '" <' . $this->From . '>' : $this->From);

        $toArr = [];
        foreach ($this->to as $t) {
            $toArr[] = !empty($t[1]) ? '"' . addslashes($t[1]) . '" <' . $t[0] . '>' : $t[0];
        }
        $lines[] = 'To: ' . implode(', ', $toArr);
        $lines[] = 'Subject: ' . $this->Subject;
        $lines[] = 'MIME-Version: 1.0';
        $lines[] = 'Content-Type: ' . $this->ContentType . '; charset=' . $this->CharSet;
        $lines[] = 'Content-Transfer-Encoding: ' . $this->Encoding;
        $lines[] = 'X-Mailer: KaamConnect-PHPMailer-Engine';

        return implode("\r\n", $lines);
    }
}

