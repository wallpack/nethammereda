<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Cancelled = 'cancelled';
}
