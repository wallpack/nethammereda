<?php

namespace App\Console\Commands;

use App\Services\MenuAudit\MenuAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class MenuSupplierAuditCommand extends Command
{
    protected $signature = 'menu:audit-supplier
        {--url=https://belyeruchki.ru/menu : URL страницы меню поставщика}
        {--allow-insecure-tls : Разрешить локальный обход проверки TLS, если на машине нет корневого CA}';

    protected $description = 'Скачать меню поставщика и сверить с локальным меню';

    public function handle(MenuAuditService $service): int
    {
        $url = (string) $this->option('url');
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! is_string($scheme) || ! in_array(mb_strtolower($scheme), ['http', 'https'], true)) {
            $this->error('URL поставщика должен быть HTTP(S).');

            return self::FAILURE;
        }

        if ((bool) $this->option('allow-insecure-tls')) {
            $this->warn('Внимание: TLS-сертификат сайта не проверяется только для этого запуска.');
        }

        $response = Http::timeout(30)
            ->withOptions(['verify' => ! (bool) $this->option('allow-insecure-tls')])
            ->get($url);

        if (! $response->ok()) {
            $this->error("Не удалось скачать меню поставщика: HTTP {$response->status()}");

            return self::FAILURE;
        }

        $auditDirectory = storage_path('app/menu-audit');
        File::ensureDirectoryExists($auditDirectory);
        file_put_contents($auditDirectory.'/belyeruchki-menu.html', $response->body());

        $summary = $service->auditHtml($response->body());

        $this->info('Сверка меню поставщика завершена.');
        $this->line("Источник: {$url}");
        $this->line('Snapshot: storage/app/menu-audit/belyeruchki-menu.html');
        $this->line("Блюд на сайте: {$summary['site_items']}");
        $this->line("Блюд локально: {$summary['local_items']}");
        $this->line("Отсутствуют локально: {$summary['missing_in_local']}");
        $this->line("Лишние/не найдены на сайте: {$summary['extra_in_local']}");
        $this->line("Групп дублей локально: {$summary['duplicate_local_groups']}");
        $this->line("Category mismatches: {$summary['category_mismatches']}");
        $this->line('Отчеты: storage/app/menu-audit/*.csv и menu-audit-summary.json');

        return self::SUCCESS;
    }
}
