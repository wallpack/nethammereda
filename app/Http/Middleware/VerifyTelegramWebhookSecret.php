<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTelegramWebhookSecret
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.telegram.webhook_secret');

        abort_if($expected === '', 503, 'Telegram webhook secret is not configured');

        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        abort_unless(hash_equals($expected, $provided), 403, 'Invalid Telegram webhook secret');

        return $next($request);
    }
}
