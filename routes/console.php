<?php

use App\Services\FridgeExpiryService;
use App\Services\Telegram\UpdateHandler;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('telegram:webhook:info', function () {
    $token = (string) config('services.telegram.bot_token');

    if ($token === '') {
        $this->warn('TELEGRAM_BOT_TOKEN не задан.');

        return 1;
    }

    $response = Http::withOptions([
        'verify' => (bool) config('services.telegram.verify_ssl', true),
    ])->get("https://api.telegram.org/bot{$token}/getWebhookInfo");

    if (! $response->ok()) {
        $this->error("Ошибка getWebhookInfo: HTTP {$response->status()}");

        return 1;
    }

    $this->line($response->body());

    return 0;
})->purpose('Показать текущую конфигурацию Telegram webhook');

Artisan::command('telegram:webhook:set {url}', function (string $url) {
    $token = (string) config('services.telegram.bot_token');
    $secret = (string) config('services.telegram.webhook_secret');

    if ($token === '') {
        $this->warn('TELEGRAM_BOT_TOKEN не задан.');

        return 1;
    }

    if (! str_starts_with($url, 'https://')) {
        $this->warn('Telegram webhook принимает только публичный HTTPS URL.');

        return 1;
    }

    $payload = [
        'url' => $url,
        'allowed_updates' => ['message', 'callback_query'],
    ];

    if ($secret !== '') {
        $payload['secret_token'] = $secret;
    }

    $response = Http::withOptions([
        'verify' => (bool) config('services.telegram.verify_ssl', true),
    ])->asJson()->post("https://api.telegram.org/bot{$token}/setWebhook", $payload);

    if (! $response->ok()) {
        $this->error("Ошибка setWebhook: HTTP {$response->status()}");

        return 1;
    }

    $this->line($response->body());

    return 0;
})->purpose('Установить Telegram webhook');

Artisan::command('telegram:webhook:clear', function () {
    $token = (string) config('services.telegram.bot_token');

    if ($token === '') {
        $this->warn('TELEGRAM_BOT_TOKEN не задан.');

        return 1;
    }

    $response = Http::withOptions([
        'verify' => (bool) config('services.telegram.verify_ssl', true),
    ])->asJson()->post("https://api.telegram.org/bot{$token}/deleteWebhook", [
        'drop_pending_updates' => false,
    ]);

    if (! $response->ok()) {
        $this->error("Ошибка deleteWebhook: HTTP {$response->status()}");

        return 1;
    }

    $this->line($response->body());

    return 0;
})->purpose('Отключить Telegram webhook');

Artisan::command('telegram:poll {--once}', function () {
    $token = (string) config('services.telegram.bot_token');

    if ($token === '') {
        $this->warn('TELEGRAM_BOT_TOKEN не задан. Polling выключен.');

        return 1;
    }

    $offsetDisk = Storage::disk('local');
    $offsetPath = 'telegram/offset.txt';
    $offset = 0;

    if ($offsetDisk->exists($offsetPath)) {
        $offset = (int) trim((string) $offsetDisk->get($offsetPath));
    }

    $this->info("Запуск Telegram polling с offset {$offset}");

    $processBatch = function () use ($token, &$offset, $offsetDisk, $offsetPath) {
        $response = Http::withOptions([
            'verify' => (bool) config('services.telegram.verify_ssl', true),
        ])->get("https://api.telegram.org/bot{$token}/getUpdates", [
            'offset' => $offset,
            'timeout' => 25,
            'allowed_updates' => json_encode(['message', 'callback_query']),
        ]);

        if (! $response->ok()) {
            $this->error("Telegram getUpdates вернул HTTP {$response->status()}");

            return 0;
        }

        $payload = $response->json();
        if (! is_array($payload) || ! ($payload['ok'] ?? false)) {
            $this->error('Telegram getUpdates вернул некорректный payload');

            return 0;
        }

        $updates = $payload['result'] ?? [];
        if (! is_array($updates)) {
            return 0;
        }

        $count = 0;
        $handler = app(UpdateHandler::class);

        foreach ($updates as $update) {
            if (! is_array($update)) {
                continue;
            }

            $handler->handle($update);

            $updateId = (int) ($update['update_id'] ?? 0);
            if ($updateId > 0) {
                $offset = $updateId + 1;
            }

            $count++;
        }

        $offsetDisk->put($offsetPath, (string) $offset);

        return $count;
    };

    if ($this->option('once')) {
        $count = $processBatch();
        $this->info("Обработано {$count} обновлений, следующий offset {$offset}");

        return 0;
    }

    while (true) {
        try {
            $count = $processBatch();
            if ($count > 0) {
                $this->info("Обработано {$count} обновлений, следующий offset {$offset}");
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            sleep(2);
        }
    }
})->purpose('Запустить Telegram-бота в long-poll режиме (для локальной разработки)');

Artisan::command('fridge:expire', function () {
    $expired = app(FridgeExpiryService::class)->expireDueItems();

    $this->info("Expired fridge items: {$expired}");

    return 0;
})->purpose('Mark overdue fridge items as expired');

Schedule::command('fridge:expire')
    ->everyThirtyMinutes()
    ->withoutOverlapping();
