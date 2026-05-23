<?php

namespace App\Services\Telegram;

class WebAppAuthValidator
{
    /**
     * @return array<string, mixed>|null
     */
    public function validate(string $initData): ?array
    {
        $botToken = (string) config('services.telegram.bot_token');
        if ($botToken === '') {
            return null;
        }

        parse_str($initData, $payload);

        $incomingHash = $payload['hash'] ?? null;
        if (! is_string($incomingHash) || $incomingHash === '') {
            return null;
        }

        unset($payload['hash']);
        ksort($payload);

        $checkString = collect($payload)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $calculatedHash = hash_hmac('sha256', $checkString, $secretKey);

        if (! hash_equals($calculatedHash, $incomingHash)) {
            return null;
        }

        $authDateValue = $payload['auth_date'] ?? null;
        if (! is_string($authDateValue) || ! ctype_digit($authDateValue)) {
            return null;
        }

        $authDate = (int) $authDateValue;
        $now = time();
        $maxAge = max(1, (int) config('services.telegram.webapp_auth_ttl', 86400));

        if ($authDate <= 0 || $authDate > ($now + 30) || ($now - $authDate) > $maxAge) {
            return null;
        }

        $user = [];
        if (isset($payload['user']) && is_string($payload['user'])) {
            $decoded = json_decode($payload['user'], true);
            if (is_array($decoded)) {
                $user = $decoded;
            }
        }

        return [
            'user' => $user,
            'payload' => $payload,
        ];
    }
}
