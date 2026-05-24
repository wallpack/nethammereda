<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OrderCannotBeReopenedForEditingException extends RuntimeException implements ShouldntReport
{
    public static function forNonSubmittedOrder(): self
    {
        return new self('Only submitted orders can be reopened for editing.');
    }

    public static function forUnavailableCycle(): self
    {
        return new self('This order can no longer be edited.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 422);
    }
}
