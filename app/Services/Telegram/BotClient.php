<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BotClient
{
    /**
     * @param  array<string, mixed>|null  $replyMarkup
     */
    public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): void
    {
        $token = (string) config('services.telegram.bot_token');
        if ($token === '') {
            return;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $response = Http::withOptions([
            'verify' => (bool) config('services.telegram.verify_ssl', true),
        ])->asJson()->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);

        if (! $response->ok() || ($response->json('ok') === false)) {
            Log::warning('Telegram sendMessage failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'chat_id' => $chatId,
            ]);
        }
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = ''): void
    {
        $token = (string) config('services.telegram.bot_token');
        if ($token === '') {
            return;
        }

        $payload = [
            'callback_query_id' => $callbackQueryId,
        ];

        if ($text !== '') {
            $payload['text'] = $text;
        }

        $response = Http::withOptions([
            'verify' => (bool) config('services.telegram.verify_ssl', true),
        ])->asJson()->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", $payload);

        if (! $response->ok() || ($response->json('ok') === false)) {
            Log::warning('Telegram answerCallbackQuery failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'callback_query_id' => $callbackQueryId,
            ]);
        }
    }
}
