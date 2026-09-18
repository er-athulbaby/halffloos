<?php

namespace App\Casts;

use App\Support\Money;
use InvalidArgumentException;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return $value === null ? null : Money::fromFils((int) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            throw new InvalidArgumentException("Money must be set via Money::fromFils() or Money::fromString(), not raw int/float. Received: {$value}");
        }

        return $value instanceof Money ? $value->fils() : Money::fromString((string) $value)->fils();
    }
}
