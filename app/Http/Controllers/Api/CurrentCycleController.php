<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsApiPayloads;
use App\Http\Controllers\Controller;
use App\Services\CurrentOrderCycleResolver;
use Illuminate\Http\Request;

class CurrentCycleController extends Controller
{
    use FormatsApiPayloads;

    public function __invoke(Request $request, CurrentOrderCycleResolver $resolver)
    {
        $cycle = $resolver->resolve();

        if ($cycle === null) {
            return response()->json([
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => $this->cyclePayload($cycle),
        ]);
    }
}
