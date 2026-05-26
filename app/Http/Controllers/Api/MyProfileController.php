<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMyProfileRequest;
use Illuminate\Http\JsonResponse;

class MyProfileController extends Controller
{
    public function update(UpdateMyProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $user->fill($request->validated());
        $user->save();

        return response()->json([
            'data' => $user->fresh(),
        ]);
    }
}
