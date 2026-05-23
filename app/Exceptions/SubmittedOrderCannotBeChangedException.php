<?php

namespace App\Exceptions;

use App\Models\Order;

class SubmittedOrderCannotBeChangedException extends OrderCannotBeChangedByUserException
{
    public static function forOrder(Order $order): self
    {
        return new self('Submitted orders cannot be changed.');
    }
}
