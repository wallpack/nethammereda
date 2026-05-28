<?php

namespace App\Services\Telegram;

use App\Enums\UserRole;
use App\Models\User;

class SiteLoginService
{
    /**
     * @param  array{
     *     telegram_id: string,
     *     first_name: string|null,
     *     last_name: string|null,
     *     username: string|null,
     *     photo_url: string|null,
     *     auth_date: int
     * }  $validated
     */
    public function resolveUser(array $validated, ?string &$reason = null): ?User
    {
        $telegramId = $validated['telegram_id'];
        $displayName = $this->displayName($validated);

        $user = User::query()->where('telegram_id', $telegramId)->first();

        if ($user !== null && ! $user->is_active) {
            $reason = 'user_inactive';

            return null;
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

        $reason = 'ok';

        return $user;
    }

    public function issueToken(User $user, string $tokenName = 'telegram-site-login'): string
    {
        $user->tokens()->where('name', $tokenName)->delete();

        return $user->createToken($tokenName)->plainTextToken;
    }

    /**
     * @param  array{
     *     telegram_id: string,
     *     first_name: string|null,
     *     last_name: string|null,
     *     username: string|null,
     *     photo_url: string|null,
     *     auth_date: int
     * }  $validated
     */
    private function displayName(array $validated): string
    {
        $displayName = trim(
            implode(
                ' ',
                array_filter([
                    $validated['first_name'],
                    $validated['last_name'],
                ]),
            ),
        );

        if ($displayName !== '') {
            return $displayName;
        }

        return $validated['username'] ?? "telegram_{$validated['telegram_id']}";
    }
}
