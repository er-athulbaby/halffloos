<?php

namespace Tests\Unit;

use App\Rules\AtLeastHalfOff;
use App\Support\Money;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class AtLeastHalfOffTest extends TestCase
{
    private function validate(string $price, string $retail): bool
    {
        return Validator::make(
            ['price' => $price],
            ['price' => [new AtLeastHalfOff(Money::fromString($retail))]]
        )->passes();
    }

    public function test_it_accepts_exactly_half_off(): void
    {
        $this->assertTrue($this->validate('1.000', '2.000'));
    }

    public function test_it_accepts_more_than_half_off(): void
    {
        $this->assertTrue($this->validate('0.500', '2.000'));
    }

    public function test_it_rejects_less_than_half_off(): void
    {
        $this->assertFalse($this->validate('1.500', '2.000'));
        $this->assertFalse($this->validate('1.001', '2.000'));
    }

    public function test_it_rejects_a_malformed_amount(): void
    {
        $this->assertFalse($this->validate('abc', '2.000'));
    }
}
