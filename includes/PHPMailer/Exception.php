<?php
namespace PHPMailer\PHPMailer;

/**
 * PHPMailer custom exception class.
 */
class Exception extends \Exception
{
    /**
     * Prettify the error message.
     *
     * @return string
     */
    public function errorMessage(): string
    {
        return '<strong>' . htmlspecialchars($this->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</strong><br />\n";
    }
}

