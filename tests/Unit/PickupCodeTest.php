<?php

namespace Tests\Unit;

use App\Support\PickupCode;
use PHPUnit\Framework\TestCase;

class PickupCodeTest extends TestCase
{
    public function test_it_is_six_characters(): void
    {
        $this->assertSame(6, strlen(PickupCode::generate()));
    }

    public function test_it_avoids_ambiguous_characters(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $this->assertDoesNotMatchRegularExpression('/[O0I1L]/', PickupCode::generate());
        }
    }

    public function test_it_is_uppercase_alphanumeric(): void
    {
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', PickupCode::generate());
    }
}
