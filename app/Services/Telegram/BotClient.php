<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        try {
            $response = $this->request()
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/sendMessage", $payload);
        } catch (Throwable $e) {
            Log::warning('Telegram sendMessage transport failed', [
                'exception' => $e::class,
                'chat_id' => $chatId,
            ]);

            return;
        }

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

        try {
            $response = $this->request()
                ->asJson()
                ->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", $payload);
        } catch (Throwable $e) {
            Log::warning('Telegram answerCallbackQuery transport failed', [
                'exception' => $e::class,
                'callback_query_id' => $callbackQueryId,
            ]);

            return;
        }

        if (! $response->ok() || ($response->json('ok') === false)) {
            Log::warning('Telegram answerCallbackQuery failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'callback_query_id' => $callbackQueryId,
            ]);
        }
    }

    private function request(): PendingRequest
    {
        return app(TelegramHttpClientFactory::class)->make();
    }
}
