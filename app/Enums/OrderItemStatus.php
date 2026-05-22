<?php

namespace App\Enums;

enum OrderItemStatus: string
{
    case Ordered = 'ordered';
    case Arrived = 'arrived';
    case Received = 'received';
    case Eaten = 'eaten';
    case Cancelled = 'cancelled';
}
