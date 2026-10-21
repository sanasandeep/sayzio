<?php

namespace App\Modules\User\Support;

/**
 * How far, never where.
 *
 * Attendees who turn on discoverability may also hand over a coordinate.
 * That has been stored since Task #5008 and never read back, so the first
 * thing to decide was what reading it back is allowed to mean.
 *
 * Agreeing to be listed at an event is not the same as agreeing to be
 * placed on a map of it. A pin is a different promise: it says where you
 * are standing, continuously, to a room of people you have not met. So
 * this returns a band -- same building, short walk, across town -- and
 * there is deliberately no method here that returns a position. A
 * coordinate never leaves the server.
 *
 * Bands are coarse near the top on purpose. "Here" covering 150m means a
 * venue reads as one place rather than splitting a hall into people who
 * are nearer and further, which is both useless and faintly unpleasant.
 */
class Hallway
{
    /** Metres, and the label each ceiling carries. Ascending. */
    public const BANDS = [
        150     => 'here',
        1000    => 'close',
        10000   => 'nearby',
        100000  => 'far',
    ];

    /** Beyond the last ceiling. */
    public const BAND_DISTANT = 'distant';

    /** Mean radius of the earth, metres. */
    private const EARTH_RADIUS = 6371000;

    /**
     * The band one person falls into relative to another, or null when
     * either of them gave no coordinate -- which is the common case and
     * must read as "unknown", never as "far away".
     */
    public static function band(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): ?string
    {
        $metres = self::metresBetween($lat1, $lng1, $lat2, $lng2);

        if ($metres === null) {
            return null;
        }

        foreach (self::BANDS as $ceiling => $label) {
            if ($metres <= $ceiling) {
                return $label;
            }
        }

        return self::BAND_DISTANT;
    }

    /**
     * Haversine. Kept private: the point of this class is that a distance
     * in metres is an intermediate value, not something a caller can put
     * in a response. A metre-accurate distance between two people is a
     * position given two readings, which is the thing being avoided.
     */
    private static function metresBetween(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): ?float
    {
        if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
            return null;
        }

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
