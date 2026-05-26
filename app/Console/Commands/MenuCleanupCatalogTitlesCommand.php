<?php

namespace App\Console\Commands;

use App\Services\MenuCatalogTitleCleanupService;
use Illuminate\Console\Command;

class MenuCleanupCatalogTitlesCommand extends Command
{
    protected $signature = 'menu:cleanup-catalog-titles
        {--dry-run : Только отчеты, без изменения title}
        {--report-prefix=catalog-title-cleanup : Префикс имен CSV-отчетов в _menu_audit/reports}';

    protected $description = 'Проверить и почистить title для каталога на основе supplier_name без изменения supplier_name';

        public function handle(MenuCatalogTitleCleanupService $service): int
    {
        $apply = ! (bool) $this->option('dry-run');
        $prefix = (string) $this->option('report-prefix');
        $result = $service->run($apply, $prefix);

        $this->info('Cleanup catalog title завершен.');
        $this->line('Режим: '.($apply ? 'apply' : 'dry-run'));
        $this->line("Всего menu_items: {$result['total']}");
        $this->line("Проблемных title в review: {$result['reviewed']}");
        $this->line("Изменено title: {$result['changed']}");
        $this->line("Осталось на ручную проверку: {$result['needs_review']}");
        $this->line("Отчет review: {$result['reports']['review']}");
        $this->line("Отчет applied: {$result['reports']['applied']}");
        $this->line("Отчет needs review: {$result['reports']['needs_review']}");

        return self::SUCCESS;
    }
}
