<?php

namespace App\Support;

final class Geo
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * ponytail: bounding box in SQL, exact haversine in PHP. SQLite has no
     * trigonometric functions unless specially compiled, so a SQL haversine
     * works on MySQL and silently fails in development. Revisit past ~10k stores.
     */
    public static function boundingBox(float $lat, float $lng, float $radiusKm): array
    {
        $latDelta = rad2deg($radiusKm / self::EARTH_RADIUS_KM);
        $lngDelta = rad2deg($radiusKm / (self::EARTH_RADIUS_KM * cos(deg2rad($lat))));

        return [
            'minLat' => $lat - $latDelta,
            'maxLat' => $lat + $latDelta,
            'minLng' => $lng - $lngDelta,
            'maxLng' => $lng + $lngDelta,
        ];
    }

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
