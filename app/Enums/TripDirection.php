<?php

namespace App\Enums;

/**
 * Outbound trips travel up the route order (Nairobi towards Mandera);
 * inbound trips travel down it (towards Nairobi).
 */
enum TripDirection: string
{
    case Outbound = 'outbound';
    case Inbound = 'inbound';

    public static function between(int $fromRouteOrder, int $toRouteOrder): self
    {
        return $toRouteOrder > $fromRouteOrder ? self::Outbound : self::Inbound;
    }
}
