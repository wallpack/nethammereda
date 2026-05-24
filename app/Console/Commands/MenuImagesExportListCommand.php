<?php

namespace App\Console\Commands;

use App\Services\MenuImages\MenuImageListExporter;
use Illuminate\Console\Command;

class MenuImagesExportListCommand extends Command
{
    protected $signature = 'menu-images:export-list';

    protected $description = 'Экспортировать список блюд для ручной генерации картинок';

    public function handle(MenuImageListExporter $exporter): int
    {
        $result = $exporter->export();

        $this->info('Список блюд для картинок создан.');
        $this->line("Строк: {$result['count']}");
        $this->line("Файл: {$result['path']}");

        return self::SUCCESS;
    }
}
