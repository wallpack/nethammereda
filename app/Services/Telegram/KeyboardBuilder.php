<?php

namespace App\Services\Telegram;

class KeyboardBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function navigation(): array
    {
        $catalogButton = ['text' => 'Открыть меню'];
        $webAppUrl = $this->secureWebAppUrl();

        if ($webAppUrl !== null) {
            $catalogButton['web_app'] = ['url' => $webAppUrl];
        }

        return [
            'keyboard' => [
                [$catalogButton],
                [
                    ['text' => 'Мой заказ'],
                    ['text' => 'Холодильник'],
                ],
                [
                    ['text' => 'Статус'],
                    ['text' => 'История'],
                ],
                [
                    ['text' => 'Помощь'],
                ],
            ],
            'resize_keyboard' => true,
            'is_persistent' => true,
        ];
    }

    public function secureWebAppUrl(): ?string
    {
        $url = (string) config('services.telegram.webapp_url');

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return parse_url($url, PHP_URL_SCHEME) === 'https' ? $url : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function webAppAction(string $label): ?array
    {
        $url = $this->secureWebAppUrl();

        if ($url === null) {
            return null;
        }

        return [
            'inline_keyboard' => [
                [
                    [
                        'text' => $label,
                        'web_app' => ['url' => $url],
                    ],
                ],
            ],
        ];
    }
}
