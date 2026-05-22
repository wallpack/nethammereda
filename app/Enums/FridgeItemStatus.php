<?php

namespace App\Enums;

enum FridgeItemStatus: string
{
    case InFridge = 'in_fridge';
    case Eaten = 'eaten';
    case Discarded = 'discarded';
    case Expired = 'expired';
}

