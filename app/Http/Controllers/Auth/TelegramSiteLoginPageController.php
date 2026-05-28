<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Telegram\LoginWidgetAuthValidator;
use App\Services\Telegram\SiteLoginService;
use App\Services\Telegram\TelegramLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class TelegramSiteLoginPageController extends Controller
{
    public function show(TelegramLinkService $telegramLinkService): View
    {
        $botUsername = $telegramLinkService->botUsername();
        $loginAvailable = $botUsername !== null;

        return view('auth.telegram-login', [
            'botUsername' => $botUsername,
            'loginAvailable' => $loginAvailable,
            'callbackUrl' => route('auth.telegram.callback'),
        ]);
    }

    public function callback(
        Request $request,
        LoginWidgetAuthValidator $validator,
        SiteLoginService $siteLoginService,
    ): RedirectResponse {
        $validationReason = 'unknown';
        $validated = $validator->validate($request->query(), $validationReason);

        if ($validated === null) {
            Log::warning('telegram_site_login_failed', [
                'reason' => $validationReason,
            ]);

            return redirect('/?telegram_login=error');
        }

        $resolveReason = 'unknown';
        $user = $siteLoginService->resolveUser($validated, $resolveReason);

        if ($user === null) {
            Log::warning('telegram_site_login_failed', [
                'reason' => $resolveReason,
            ]);

            return redirect('/?telegram_login=error');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(
            'telegram_site_login_token',
            $siteLoginService->issueToken($user, 'telegram-site-login'),
        );

        Log::info('telegram_site_login_success', [
            'user_id' => $user->id,
        ]);

        return redirect('/?telegram_login=success');
    }

    public function consumeToken(Request $request): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $token = (string) $request->session()->pull('telegram_site_login_token', '');

        if ($token === '') {
            return response()->json([
                'message' => 'Telegram login token not found.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'token' => $token,
            ],
        ]);
    }
}
