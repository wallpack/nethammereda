<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\TelegramLoginRequest;
use App\Models\User;
use App\Services\Telegram\LoginWidgetAuthValidator;
use App\Services\Telegram\TelegramLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class TelegramLoginController extends Controller
{
    public function config(TelegramLinkService $telegramLinkService): JsonResponse
    {
        $botUsername = $telegramLinkService->botUsername();
        $botId = $telegramLinkService->botId();
        $loginAvailable = $botUsername !== null && $botId !== null;

        Log::info('telegram_site_login_config_loaded', [
            'login_available' => $loginAvailable,
        ]);

        return response()->json([
            'data' => [
                'bot_username' => $botUsername,
                'bot_id' => $botId,
                'login_available' => $loginAvailable,
            ],
        ]);
    }

    public function store(
        TelegramLoginRequest $request,
        LoginWidgetAuthValidator $validator,
    ): JsonResponse {
        $validationReason = 'unknown';
        $validated = $validator->validate($request->all(), $validationReason);

        if ($validated === null) {
            Log::warning('telegram_site_login_failed', [
                'reason' => $validationReason,
            ]);

            return response()->json([
                'message' => 'Не удалось войти через Telegram. Попробуйте ещё раз.',
            ], 422);
        }

        $telegramId = $validated['telegram_id'];

        $displayName = trim(
            implode(
                ' ',
                array_filter([
                    $validated['first_name'],
                    $validated['last_name'],
                ]),
            ),
        );

        if ($displayName === '') {
            $displayName = $validated['username'] ?? "telegram_{$telegramId}";
        }

        $user = User::query()->where('telegram_id', $telegramId)->first();

        if ($user !== null && ! $user->is_active) {
            Log::warning('telegram_site_login_failed', [
                'reason' => 'user_inactive',
            ]);

            return response()->json([
                'message' => 'Пользователь деактивирован.',
            ], 403);
        }

        if ($user === null) {
            $user = User::query()->create([
                'telegram_id' => $telegramId,
                'name' => $displayName,
                'is_active' => true,
                'role' => UserRole::User,
            ]);
        } elseif ($user->name !== $displayName) {
            $user->update(['name' => $displayName]);
        }

        $user->tokens()->where('name', 'telegram-site-login')->delete();
        $token = $user->createToken('telegram-site-login')->plainTextToken;

        Log::info('telegram_site_login_success', [
            'user_id' => $user->id,
        ]);

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'telegram_id' => $user->telegram_id,
                    'role' => $user->role->value,
                ],
            ],
        ]);
    }
}
