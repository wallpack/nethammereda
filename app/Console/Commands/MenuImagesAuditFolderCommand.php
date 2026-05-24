<?php

namespace App\Console\Commands;

use App\Services\MenuAudit\ImageAuditService;
use Illuminate\Console\Command;

class MenuImagesAuditFolderCommand extends Command
{
    protected $signature = 'menu-images:audit-folder
        {--path=image for proj : Папка с локальными изображениями}';

    protected $description = 'Проверить локальную папку картинок меню и создать отчеты';

    public function handle(ImageAuditService $service): int
    {
        $summary = $service->audit((string) $this->option('path'));

        $this->info('Проверка папки картинок завершена.');
        $this->line("Папка: {$summary['source_path']}");
        $this->line("Валидных картинок: {$summary['valid_files']}");
        $this->line("Битых картинок: {$summary['invalid_files']}");
        $this->line("Групп точных дублей: {$summary['exact_duplicate_groups']}");
        $this->line("Возможных визуальных дублей: {$summary['possible_duplicate_pairs']}");
        $this->line('Отчеты: storage/app/menu-audit/image-*.csv');

        return self::SUCCESS;
    }
}
