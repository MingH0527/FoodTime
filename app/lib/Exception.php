<?php
/**
 * PHPMailer exception handler.
 */
namespace PHPMailer\PHPMailer;

class Exception extends \Exception
{
    /**
     * Prettify error message output.
     * @return string
     */
    public function errorMessage()
    {
        return '<strong>' . htmlspecialchars($this->getMessage()) . '</strong><br />';
    }
}