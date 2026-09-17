<?php

namespace App\Exceptions;

use RuntimeException;

class ReservationFailed extends RuntimeException
{
    public static function soldOut(): self
    {
        return new self(__('Those are gone — someone just took the last of them.'));
    }

    public static function overCap(int $max): self
    {
        return new self(__('You can reserve at most :max of these.', ['max' => $max]));
    }

    public static function blocked(): self
    {
        return new self(__('Reservations are paused on your account after two missed collections.'));
    }

    public static function notAvailable(): self
    {
        return new self(__('This offer is no longer available.'));
    }

    public static function unknownCode(): self
    {
        return new self(__('No reservation found for that code.'));
    }

    public static function alreadyCollected(): self
    {
        return new self(__('That code was already collected.'));
    }
}
