<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CurrentOrderCycleResolver;
use Illuminate\Http\Request;

class CurrentCycleController extends Controller
{
    public function __invoke(Request $request, CurrentOrderCycleResolver $resolver)
    {
        $cycle = $resolver->resolve();

        if ($cycle === null) {
            return response()->json([
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => [
                'id' => $cycle->id,
                'title' => $cycle->title,
                'starts_at' => $cycle->starts_at,
                'closes_at' => $cycle->closes_at,
                'status' => $cycle->status->value,
                'is_open_for_ordering' => $cycle->isOpenForOrdering(),
            ],
        ]);
    }
}
