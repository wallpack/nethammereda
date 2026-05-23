<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OrderCannotBeSubmittedException extends RuntimeException implements ShouldntReport
{
    public static function forNonDraftOrder(): self
    {
        return new self('This order cannot be submitted.');
    }

    public static function forEmptyOrder(): self
    {
        return new self('Нельзя отправить пустой заказ.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 422);
    }
}
