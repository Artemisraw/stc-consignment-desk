<?php

namespace App\Enums;

enum TripStatus: string
{
    case Loading = 'loading';
    case InTransit = 'in_transit';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
