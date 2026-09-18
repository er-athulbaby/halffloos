<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_stores_fils_exactly(): void
    {
        $this->assertSame(2500, Money::fromFils(2500)->fils());
    }

    public function test_it_parses_a_three_decimal_string(): void
    {
        $this->assertSame(2500, Money::fromString('2.500')->fils());
        $this->assertSame(500, Money::fromString('0.5')->fils());
        $this->assertSame(2000, Money::fromString('2')->fils());
    }

    public function test_it_formats_with_three_decimals(): void
    {
        $this->assertSame('BHD 2.500', Money::fromFils(2500)->format());
        $this->assertSame('BHD 0.500', Money::fromFils(500)->format());
        $this->assertSame('BHD 0.050', Money::fromFils(50)->format());
    }

    public function test_it_knows_when_it_is_at_most_half_of_another(): void
    {
        $original = Money::fromFils(2000);

        $this->assertTrue(Money::fromFils(1000)->isAtMostHalfOf($original));
        $this->assertTrue(Money::fromFils(500)->isAtMostHalfOf($original));
        $this->assertFalse(Money::fromFils(1001)->isAtMostHalfOf($original));
    }

    public function test_it_calculates_percent_off(): void
    {
        $this->assertSame(75, Money::fromFils(500)->percentOffFrom(Money::fromFils(2000)));
        $this->assertSame(50, Money::fromFils(1000)->percentOffFrom(Money::fromFils(2000)));
    }

    public function test_it_rejects_negative_amounts(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::fromFils(-1);
    }

    public function test_it_floors_non_exact_percentages(): void
    {
        $this->assertSame(33, Money::fromFils(667)->percentOffFrom(Money::fromFils(1000)));
    }

    public function test_money_cast_rejects_raw_int(): void
    {
        $cast = new \App\Casts\MoneyCast();
        $model = \Mockery::mock(\Illuminate\Database\Eloquent\Model::class);

        $this->expectException(\InvalidArgumentException::class);
        $cast->set($model, 'price', 2500, []);
    }
}
