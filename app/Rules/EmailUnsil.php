<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Surel pengguna wajib berdomain unsil.ac.id, kecuali di environment local/testing.
 */
class EmailUnsil implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (app()->environment(['local', 'testing'])) {
            return;
        }

        if (! is_string($value) || ! str_ends_with(strtolower($value), '@unsil.ac.id')) {
            $fail('Surel harus menggunakan domain @unsil.ac.id.');
        }
    }
}
