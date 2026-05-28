<?php

namespace App\Services\Telegram;

use App\Models\TelegramLinkToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class TelegramLinkService
{
    public const START_PREFIX = 'link_';

    /**
     * @return array{token: string, expires_at: \Illuminate\Support\Carbon}
     */
    public function issueForUser(User $user): array
    {
        $this->expireActiveTokens($user);

        $token = $this->generateToken();
        $record = TelegramLinkToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes($this->tokenTtlMinutes()),
        ]);

        return [
            'token' => $token,
            'expires_at' => $record->expires_at,
        ];
    }

    public function consumeToken(string $rawToken, string $telegramId): string
    {
        if ($rawToken === '' || $telegramId === '') {
            return 'invalid';
        }

        $tokenHash = hash('sha256', $rawToken);

        return DB::transaction(function () use ($tokenHash, $telegramId): string {
            $linkToken = TelegramLinkToken::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if ($linkToken === null) {
                return 'invalid';
            }

            if ($linkToken->used_at !== null) {
                return 'used';
            }

            if ($linkToken->expires_at->isPast()) {
                return 'expired';
            }

            $user = User::query()
                ->lockForUpdate()
                ->find($linkToken->user_id);

            if ($user === null || ! $user->is_active) {
                return 'user_inactive';
            }

            $existingOwner = User::query()
                ->where('telegram_id', $telegramId)
                ->lockForUpdate()
                ->first();

            if ($existingOwner !== null && $existingOwner->id !== $user->id) {
                return 'telegram_conflict';
            }

            if ($user->telegram_id !== null && $user->telegram_id !== '' && $user->telegram_id !== $telegramId) {
                return 'user_already_linked';
            }

            if ($user->telegram_id !== $telegramId) {
                $user->telegram_id = $telegramId;
                $user->save();
            }

            $linkToken->used_at = now();
            $linkToken->used_by_telegram_id = $telegramId;
            $linkToken->save();

            return 'linked';
        });
    }

    public function extractTokenFromStartPayload(?string $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        $normalized = trim($payload);
        if (! str_starts_with($normalized, self::START_PREFIX)) {
            return null;
        }

        $rawToken = substr($normalized, strlen(self::START_PREFIX));

        if (! preg_match('/^[A-Za-z0-9_-]{20,128}$/', $rawToken)) {
            return null;
        }

        return $rawToken;
    }

    public function botUsername(): ?string
    {
        $username = trim((string) config('services.telegram.bot_username'));
        if ($username === '') {
            return null;
        }

        $username = ltrim($username, '@');

        if (! preg_match('/^[A-Za-z0-9_]{5,}$/', $username)) {
            return null;
        }

        return $username;
    }

    public function botId(): ?int
    {
        $configuredBotId = $this->configuredBotId();

        if ($configuredBotId !== null) {
            return $configuredBotId;
        }

        if (app()->environment('testing')) {
            return null;
        }

        return cache()->remember('telegram.bot_id_from_api', now()->addHours(12), function (): ?int {
            return $this->resolveBotIdFromApi();
        });
    }

    public function botLink(): ?string
    {
        $botUsername = $this->botUsername();

        if ($botUsername === null) {
            return null;
        }

        return "https://t.me/{$botUsername}";
    }

    public function buildDeepLink(string $rawToken): ?string
    {
        $botLink = $this->botLink();
        if ($botLink === null) {
            return null;
        }

        return "{$botLink}?start=".self::START_PREFIX.$rawToken;
    }

    private function tokenTtlMinutes(): int
    {
        return max(1, (int) config('services.telegram.link_token_ttl_minutes', 10));
    }

    private function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function expireActiveTokens(User $user): void
    {
        TelegramLinkToken::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }

    private function configuredBotId(): ?int
    {
        $botId = config('services.telegram.bot_id');

        if (! is_numeric($botId)) {
            return null;
        }

        $normalized = (int) $botId;

        return $normalized > 0 ? $normalized : null;
    }

    private function resolveBotIdFromApi(): ?int
    {
        $botToken = trim((string) config('services.telegram.bot_token'));

        if ($botToken === '') {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->timeout(8)
                ->retry(1, 200)
                ->get("https://api.telegram.org/bot{$botToken}/getMe");

            if (! $response->ok()) {
                return null;
            }

            $botId = $response->json('result.id');

            if (! is_numeric($botId)) {
                return null;
            }

            $normalized = (int) $botId;

            return $normalized > 0 ? $normalized : null;
        } catch (Throwable) {
            return null;
        }
    }
}
