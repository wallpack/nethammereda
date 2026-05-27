<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramLinkController extends Controller
{
    public function status(Request $request, TelegramLinkService $telegramLinkService): JsonResponse
    {
        $user = $request->user();
        $botUsername = $telegramLinkService->botUsername();
        $botLink = $telegramLinkService->botLink();

        return response()->json([
            'data' => [
                'linked' => filled($user?->telegram_id),
                'bot_username' => $botUsername,
                'bot_link' => $botLink,
                'link_available' => $botLink !== null,
            ],
        ]);
    }

    public function createToken(Request $request, TelegramLinkService $telegramLinkService): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => 'Требуется авторизация.',
            ], 401);
        }

        if (filled($user->telegram_id)) {
            return response()->json([
                'message' => 'Telegram уже привязан к вашему аккаунту.',
            ], 409);
        }

        $botLink = $telegramLinkService->botLink();
        if ($botLink === null) {
            return response()->json([
                'message' => 'Бот не настроен для привязки. Обратитесь к администратору.',
            ], 422);
        }

        $issued = $telegramLinkService->issueForUser($user);
        $deepLink = $telegramLinkService->buildDeepLink($issued['token']);

        if ($deepLink === null) {
            return response()->json([
                'message' => 'Не удалось сформировать deep-link для Telegram.',
            ], 422);
        }

        return response()->json([
            'data' => [
                'deep_link' => $deepLink,
                'bot_link' => $botLink,
                'expires_at' => $issued['expires_at'],
            ],
        ], 201);
    }
}
