<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PasswordLoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PasswordLoginController extends Controller
{
    public function store(PasswordLoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->string('email')->toString())
            ->first();

        if (
            $user === null
            || $user->password === null
            || ! Hash::check($request->string('password')->toString(), $user->password)
        ) {
            Log::warning('Неуспешный вход по email/password', [
                'email' => $request->string('email')->toString(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Неверный email или пароль.',
            ], 422);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Пользователь деактивирован.',
            ], 403);
        }

        $user->tokens()->where('name', 'web-login')->delete();
        $token = $user->createToken('web-login')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'telegram_id' => $user->telegram_id,
                    'role' => $user->role->value,
                ],
            ],
        ]);
    }
}
