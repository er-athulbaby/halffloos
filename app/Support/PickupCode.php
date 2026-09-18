<?php

namespace App\Support;

final class PickupCode
{
    /**
     * O, 0, I, 1 and L are omitted — a shop worker reads these aloud
     * across a counter, and confusing them wastes everyone's time.
     */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function generate(): string
    {
        $code = '';

        for ($i = 0; $i < 6; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}
