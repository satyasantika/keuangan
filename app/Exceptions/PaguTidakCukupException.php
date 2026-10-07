<?php

namespace App\Exceptions;

class PaguTidakCukupException extends KesalahanDomain
{
    public function __construct(string $message = 'Pagu unit tidak mencukupi.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
