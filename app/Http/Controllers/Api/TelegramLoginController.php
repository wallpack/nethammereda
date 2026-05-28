<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TelegramLoginRequest;
use App\Services\Telegram\LoginWidgetAuthValidator;
use App\Services\Telegram\SiteLoginService;
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
        SiteLoginService $siteLoginService,
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

        $resolveReason = 'unknown';
        $user = $siteLoginService->resolveUser($validated, $resolveReason);

        if ($user === null) {
            Log::warning('telegram_site_login_failed', [
                'reason' => $resolveReason,
            ]);

            return response()->json([
                'message' => 'Пользователь деактивирован.',
            ], 403);
        }

        $token = $siteLoginService->issueToken($user, 'telegram-site-login');

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
