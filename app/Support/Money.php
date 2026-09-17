<?php

namespace App\Support;

use InvalidArgumentException;

final readonly class Money
{
    private function __construct(public int $fils)
    {
        if ($fils < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }
    }

    public static function fromFils(int $fils): self
    {
        return new self($fils);
    }

    /** Parses a BHD string such as "2.500", "0.5" or "2". */
    public static function fromString(string $bhd): self
    {
        $trimmed = trim($bhd);

        if (! preg_match('/^\d+(\.\d{1,3})?$/', $trimmed)) {
            throw new InvalidArgumentException("Not a valid BHD amount: {$bhd}");
        }

        [$dinars, $fraction] = array_pad(explode('.', $trimmed), 2, '0');

        return new self(((int) $dinars * 1000) + (int) str_pad($fraction, 3, '0'));
    }

    public function fils(): int
    {
        return $this->fils;
    }

    public function format(): string
    {
        return sprintf('BHD %d.%03d', intdiv($this->fils, 1000), $this->fils % 1000);
    }

    public function isAtMostHalfOf(self $other): bool
    {
        return $this->fils * 2 <= $other->fils;
    }

    public function percentOffFrom(self $original): int
    {
        if ($original->fils === 0) {
            return 0;
        }

        return (int) floor((($original->fils - $this->fils) / $original->fils) * 100);
    }
}
