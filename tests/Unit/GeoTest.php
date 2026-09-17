<?php

namespace Tests\Unit;

use App\Support\Geo;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
    public function test_distance_between_manama_and_riffa(): void
    {
        // Manama ~26.2285,50.5860 to Riffa ~26.1300,50.5550 is roughly 11.4km.
        $km = Geo::distanceKm(26.2285, 50.5860, 26.1300, 50.5550);

        $this->assertEqualsWithDelta(11.4, $km, 1.0);
    }

    public function test_distance_to_itself_is_zero(): void
    {
        $this->assertSame(0.0, round(Geo::distanceKm(26.2285, 50.5860, 26.2285, 50.5860), 6));
    }

    public function test_bounding_box_contains_the_centre(): void
    {
        $box = Geo::boundingBox(26.2285, 50.5860, 5.0);

        $this->assertLessThan(26.2285, $box['minLat']);
        $this->assertGreaterThan(26.2285, $box['maxLat']);
        $this->assertLessThan(50.5860, $box['minLng']);
        $this->assertGreaterThan(50.5860, $box['maxLng']);
    }
}
