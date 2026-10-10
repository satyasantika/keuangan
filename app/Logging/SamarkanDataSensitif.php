<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Processor Monolog yang menyamarkan nilai kunci sensitif di context/extra log.
 * Dipasang sebagai `tap` pada kanal log.
 */
class SamarkanDataSensitif implements ProcessorInterface
{
    public const SAMARAN = '[disamarkan]';

    private const POLA_KUNCI = '/pass(word)?|token|secret|app_authentication|recovery_code|authorization|cookie|api_key|otp/i';

    public function __invoke(Logger|LogRecord $target): ?LogRecord
    {
        if ($target instanceof Logger) {
            $target->pushProcessor($this);

            return null;
        }

        return $target->with(context: self::samarkan($target->context), extra: self::samarkan($target->extra));
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function samarkan(array $data): array
    {
        foreach ($data as $kunci => $nilai) {
            if (is_string($kunci) && preg_match(self::POLA_KUNCI, $kunci) === 1) {
                $data[$kunci] = self::SAMARAN;
            } elseif (is_array($nilai)) {
                $data[$kunci] = self::samarkan($nilai);
            }
        }

        return $data;
    }
}
