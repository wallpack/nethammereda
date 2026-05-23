<?php

namespace App\Exceptions;

use App\Models\OrderCycle;
use DomainException;

class OrderCycleCannotBeMarkedDeliveredException extends DomainException
{
    public static function forCycleStatus(OrderCycle $cycle): self
    {
        return new self('Отметить доставку можно только для цикла со статусом «Отправлен поставщику».');
    }
}
