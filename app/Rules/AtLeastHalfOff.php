<?php

namespace App\Rules;

use App\Support\Money;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

class AtLeastHalfOff implements ValidationRule
{
    public function __construct(private Money $retailValue) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $price = Money::fromString((string) $value);
        } catch (InvalidArgumentException) {
            $fail(__('Enter a price such as 0.500.'));

            return;
        }

        if (! $price->isAtMostHalfOf($this->retailValue)) {
            $fail(__('The price must be at most half of :retail.', [
                'retail' => $this->retailValue->format(),
            ]));
        }
    }
}
