<?php

namespace App\Services\Telegram;

class KeyboardBuilder
{
    public function menuLabel(): string
    {
        return 'Открыть меню';
    }

    /**
     * @return array<string, mixed>
     */
    public function menuReplyButton(): array
    {
        // Reply keyboard button intentionally sends text command.
        // Telegram may open keyboard-launched Mini Apps without initData.
        return ['text' => $this->menuLabel()];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function menuInlineKeyboard(): ?array
    {
        $url = $this->secureWebAppUrl();

        if ($url === null) {
            return null;
        }

        return [
            'inline_keyboard' => [
                [
                    [
                        'text' => $this->menuLabel(),
                        'web_app' => ['url' => $url],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function menuButtonPayload(): ?array
    {
        $url = $this->secureWebAppUrl();

        if ($url === null) {
            return null;
        }

        return [
            'type' => 'web_app',
            'text' => $this->menuLabel(),
            'web_app' => [
                'url' => $url,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function navigation(): array
    {
        return [
            'keyboard' => [
                [$this->menuReplyButton()],
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
