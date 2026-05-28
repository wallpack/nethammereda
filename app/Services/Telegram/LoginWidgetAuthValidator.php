<?php

namespace App\Services\Telegram;

class LoginWidgetAuthValidator
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     telegram_id: string,
     *     first_name: string|null,
     *     last_name: string|null,
     *     username: string|null,
     *     photo_url: string|null,
     *     auth_date: int
     * }|null
     */
    public function validate(array $payload): ?array
    {
        $botToken = (string) config('services.telegram.bot_token');
        if ($botToken === '') {
            return null;
        }

        $incomingHash = $this->normalizedHash($payload['hash'] ?? null);
        if ($incomingHash === null) {
            return null;
        }

        $dataToCheck = $this->buildDataToCheck($payload);
        if ($dataToCheck === []) {
            return null;
        }

        $checkString = collect($dataToCheck)
            ->map(fn (string $value, string $key): string => "{$key}={$value}")
            ->implode("\n");

        $secretKey = hash('sha256', $botToken, true);
        $calculatedHash = hash_hmac('sha256', $checkString, $secretKey);

        if (! hash_equals($calculatedHash, $incomingHash)) {
            return null;
        }

        $authDateValue = $dataToCheck['auth_date'] ?? null;
        if (! is_string($authDateValue) || ! ctype_digit($authDateValue)) {
            return null;
        }

        $authDate = (int) $authDateValue;
        $now = time();
        $maxAge = max(1, (int) config('services.telegram.login_auth_ttl', 86400));

        if ($authDate <= 0 || $authDate > ($now + 30) || ($now - $authDate) > $maxAge) {
            return null;
        }

        $telegramId = $dataToCheck['id'] ?? null;
        if (! is_string($telegramId) || ! ctype_digit($telegramId) || $telegramId === '0') {
            return null;
        }

        return [
            'telegram_id' => $telegramId,
            'first_name' => $this->nullableString($dataToCheck['first_name'] ?? null),
            'last_name' => $this->nullableString($dataToCheck['last_name'] ?? null),
            'username' => $this->nullableString($dataToCheck['username'] ?? null),
            'photo_url' => $this->nullableString($dataToCheck['photo_url'] ?? null),
            'auth_date' => $authDate,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, string>
     */
    private function buildDataToCheck(array $payload): array
    {
        $normalized = [];

        foreach ($payload as $key => $value) {
            if (! is_string($key) || $key === '' || $key === 'hash') {
                continue;
            }

            $scalar = $this->scalarToString($value);
            if ($scalar === null) {
                continue;
            }

            $normalized[$key] = $scalar;
        }

        ksort($normalized);

        return $normalized;
    }

    private function normalizedHash(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $hash = strtolower(trim($value));

        if (! preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return null;
        }

        return $hash;
    }

    private function scalarToString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
