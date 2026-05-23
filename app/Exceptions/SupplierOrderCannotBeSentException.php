<?php

namespace App\Exceptions;

use App\Models\OrderCycle;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class SupplierOrderCannotBeSentException extends RuntimeException implements ShouldntReport
{
    public static function forCycleStatus(OrderCycle $cycle): self
    {
        return new self('Перед отправкой поставщику нужно закрыть цикл заказов.');
    }

    public static function forEmptyOrder(): self
    {
        return new self('В закрытом цикле нет подтвержденных позиций для отправки поставщику.');
    }
}
