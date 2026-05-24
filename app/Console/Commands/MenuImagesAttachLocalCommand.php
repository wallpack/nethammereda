<?php

namespace App\Console\Commands;

use App\Services\MenuImages\LocalMenuImageAttacher;
use Illuminate\Console\Command;

class MenuImagesAttachLocalCommand extends Command
{
    protected $signature = 'menu-images:attach-local
        {--path=image for proj : Папка с локальными изображениями}
        {--dry-run : Только создать отчеты, без изменения MenuItem}
        {--force : Перезаписать existing image_path}';

    protected $description = 'Безопасно сопоставить локальные картинки с блюдами меню';

    public function handle(LocalMenuImageAttacher $attacher): int
    {
        $summary = $attacher->attach(
            path: (string) $this->option('path'),
            dryRun: (bool) $this->option('dry-run'),
            force: (bool) $this->option('force'),
        );

        $mode = $this->option('dry-run') ? 'dry-run' : 'реальная привязка';
        $this->info("Режим: {$mode}");
        $this->line("Папка: {$summary['source_path']}");
        $this->line("Картинок просканировано: {$summary['images_scanned']}");
        $this->line("Блюд сопоставлено: {$summary['matched_items']}");
        $this->line("Привязок запланировано/выполнено: {$summary['attached_or_planned']}");
        $this->line("Пропущено из-за existing image_path: {$summary['skipped_existing']}");
        $this->line("Конфликтов: {$summary['conflicts']}");
        $this->line("Картинок без совпадения: {$summary['unmatched_images']}");
        $this->line("Блюд без локальной картинки: {$summary['menu_items_without_image']}");
        $this->line('Отчеты: storage/app/menu-audit/image-attach-*.csv');

        return self::SUCCESS;
    }
}
