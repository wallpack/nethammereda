<?php

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OrderCannotBeChangedByUserException extends RuntimeException implements ShouldntReport
{
    public static function forNonDraftOrder(): self
    {
        return new self('Only draft orders can be changed.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 422);
    }
}
