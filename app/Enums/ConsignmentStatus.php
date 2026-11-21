<?php

namespace App\Enums;

enum ConsignmentStatus: string
{
    case Received = 'received';
    case InTransit = 'in_transit';
    case Arrived = 'arrived';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
}
