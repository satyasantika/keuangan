<?php

namespace App\Exceptions;

class AksiSedangDiprosesException extends KesalahanDomain
{
    public function __construct(string $message = 'Sedang diproses, coba lagi.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
