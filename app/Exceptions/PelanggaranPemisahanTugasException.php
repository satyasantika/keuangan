<?php

namespace App\Exceptions;

class PelanggaranPemisahanTugasException extends KesalahanDomain
{
    public function __construct(string $message = 'Pemisahan tugas dilanggar: satu orang tidak boleh memegang dua tahap yang bertentangan.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
