<?php

namespace App\Console\Commands;

use App\Services\MenuSupplierNameBackfillService;
use Illuminate\Console\Command;

class MenuBackfillSupplierNamesCommand extends Command
{
    protected $signature = 'menu:backfill-supplier-names {--source= : Абсолютный или относительный путь к supplier CSV/XLSX}';

    protected $description = 'Заполнить supplier_name у menu_items из supplier прайса и обновить короткие title для каталога';

    public function handle(MenuSupplierNameBackfillService $service): int
    {
        $result = $service->run($this->option('source'));

        $this->info('Backfill supplier_name завершен.');
        $this->line("Источник: {$result['source_path']}");
        $this->line("Всего menu_items: {$result['menu_items_total']}");
        $this->line("Строк сопоставлено: {$result['matched']}");
        $this->line("Строк обновлено: {$result['applied']}");
        $this->line("Требуют ручной проверки: {$result['review']}");
        $this->line("supplier_name заполнено: {$result['supplier_name_filled']}");
        $this->line("supplier_name пусто: {$result['supplier_name_missing']}");
        $this->line("Отчет applied: {$result['reports']['applied']}");
        $this->line("Отчет review: {$result['reports']['needs_review']}");
        $this->line("Отчет missing: {$result['reports']['missing_supplier_name']}");

        return self::SUCCESS;
    }
}
