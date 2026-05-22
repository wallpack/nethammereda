<?php

namespace App\Enums;

enum OrderCycleStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case SentToSupplier = 'sent_to_supplier';
    case Delivered = 'delivered';
    case Archived = 'archived';
}
