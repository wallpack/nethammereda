<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\TelegramAuthRequest;
use App\Models\User;
use App\Services\Telegram\WebAppAuthValidator;
use Illuminate\Http\JsonResponse;

class TelegramAuthController extends Controller
{
    public function store(
        TelegramAuthRequest $request,
        WebAppAuthValidator $validator,
    ): JsonResponse {
        $validated = $validator->validate($request->string('init_data')->toString());

        if ($validated === null) {
            return response()->json([
                'message' => 'Некорректные данные Telegram авторизации.',
            ], 422);
        }

        $telegramUser = $validated['user'] ?? [];
        $telegramId = (string) ($telegramUser['id'] ?? '');

        if ($telegramId === '') {
            return response()->json([
                'message' => 'Не передан Telegram user id.',
            ], 422);
        }

        $name = trim(
            implode(
                ' ',
                array_filter([
                    $telegramUser['first_name'] ?? null,
                    $telegramUser['last_name'] ?? null,
                ]),
            ),
        );

        if ($name === '') {
            $name = $telegramUser['username'] ?? "telegram_{$telegramId}";
        }

        $user = User::query()->where('telegram_id', $telegramId)->first();

        if ($user !== null && ! $user->is_active) {
            return response()->json([
                'message' => 'Пользователь деактивирован.',
            ], 403);
        }

        if ($user === null) {
            $user = User::query()->create([
                'telegram_id' => $telegramId,
                'name' => $name,
                'is_active' => true,
                'role' => UserRole::User,
            ]);
        } else {
            $user->update(['name' => $name]);
        }

        $user->tokens()->where('name', 'telegram-webapp')->delete();
        $token = $user->createToken('telegram-webapp')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'telegram_id' => $user->telegram_id,
                    'role' => $user->role->value,
                ],
            ],
        ]);
    }
}
