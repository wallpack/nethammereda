<?php

namespace App\Console\Commands;

use App\Services\MenuDeduplicationService;
use Illuminate\Console\Command;

class MenuDeduplicateCommand extends Command
{
    protected $signature = 'menu:deduplicate
        {--dry-run : Только отчет, без изменений в БД}';

    protected $description = 'Нормализовать категории меню и объединить дубли блюд безопасно для order/fridge ссылок';

    public function handle(MenuDeduplicationService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $service->run($dryRun);

        $this->info('Menu deduplication завершен.');
        $this->line('Режим: '.($dryRun ? 'dry-run' : 'apply'));

        $categories = $result['categories'];
        $items = $result['items'];

        $this->line('Категории:');
        $this->line("  renamed={$categories['renamed']}");
        $this->line("  merged={$categories['merged']}");
        $this->line("  items_moved={$categories['items_moved']}");

        $this->line('Блюда:');
        $this->line("  duplicate_groups={$items['duplicate_groups']}");
        $this->line("  duplicate_items={$items['duplicate_items']}");
        $this->line("  primary_updates={$items['primary_updates']}");
        $this->line("  order_refs_moved={$items['order_refs_moved']}");
        $this->line("  order_refs_conflicts={$items['order_refs_conflicts']}");
        $this->line("  fridge_refs_moved={$items['fridge_refs_moved']}");
        $this->line("  fridge_refs_skipped={$items['fridge_refs_skipped']}");
        $this->line("  items_deleted={$items['items_deleted']}");
        $this->line("  items_deactivated={$items['items_deactivated']}");

        if ($categories['actions'] !== []) {
            $this->line('Category actions:');

            foreach ($categories['actions'] as $action) {
                $this->line("- {$action['target_category_name']} (#{$action['target_category_id']})");

                foreach ($action['actions'] as $line) {
                    $this->line("    {$line}");
                }
            }
        }

        if ($items['groups'] !== []) {
            $this->line('Duplicate groups:');

            foreach ($items['groups'] as $group) {
                $secondaryIds = implode(',', $group['secondary_ids']);
                $this->line("- {$group['category']} | {$group['title']} | primary=#{$group['primary_id']} | secondary={$secondaryIds}");

                foreach ($group['secondary_actions'] as $secondaryAction) {
                    $this->line(
                        "    item #{$secondaryAction['item_id']} => {$secondaryAction['action']} "
                        ."(order_refs={$secondaryAction['remaining_order_refs']}, fridge_refs={$secondaryAction['remaining_fridge_refs']})",
                    );
                }
            }
        }

        return self::SUCCESS;
    }
}
