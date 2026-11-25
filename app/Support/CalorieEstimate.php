<?php

namespace App\Support;

class CalorieEstimate
{
    public const CONSENT_VERSION = '2026-10-06-v2';

    public const ACTIVITY = ['sedentary' => 1.2, 'light' => 1.375, 'moderate' => 1.55, 'high' => 1.725, 'very_active' => 1.9];

    public static function calculate(array $data): array
    {
        $resting = 10 * $data['weight_kg'] + 6.25 * $data['height_cm'] - 5 * $data['age'] + ($data['equation'] === 'male' ? 5 : -161);

        return ['resting' => (int) round($resting), 'maintenance' => (int) (round($resting * self::ACTIVITY[$data['activity']] / 50) * 50)];
    }
}
