<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class PaymentDiscount
{
    public static function percentage(string $value): int
    {
        if (! preg_match('/^\d{1,3}(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages(['discount_percent' => __('Enter a percentage from 0 to 100 with up to two decimal places.')]);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $points = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
        if ($points > 10000) {
            throw ValidationException::withMessages(['discount_percent' => __('The discount cannot exceed 100%.')]);
        }

        return $points;
    }

    public static function savings(int $base, int $points): int
    {
        return intdiv($base * $points + 5000, 10000);
    }

    public static function pesos(string $value): int
    {
        if (! preg_match('/^\d{1,7}(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages(['discount_php' => __('Enter a peso discount with up to two decimal places.')]);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }
}
