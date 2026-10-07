<?php

namespace App\Exceptions;

class TransisiStatusTidakSahException extends KesalahanDomain
{
    public function __construct(string $message = 'Perubahan status tidak diizinkan.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
