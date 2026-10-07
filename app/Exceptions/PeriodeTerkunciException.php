<?php

namespace App\Exceptions;

class PeriodeTerkunciException extends KesalahanDomain
{
    public function __construct(string $message = 'Periode terkunci.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
