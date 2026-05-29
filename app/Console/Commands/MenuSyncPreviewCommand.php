<?php

namespace App\Console\Commands;

use App\Enums\MenuImportStatus;
use App\Models\MenuImport;
use App\Services\MenuImportParser;
use App\Services\MenuImportRowValidator;
use App\Services\MenuImportSyncService;
use Illuminate\Console\Command;
use Throwable;

class MenuSyncPreviewCommand extends Command
{
    protected $signature = 'menu:sync-preview
        {--import-id= : ID успешного импорта для проверки; по умолчанию берется последний}';

    protected $description = 'Показать active menu_items, которые будут деактивированы при sync каталога после импорта';

    public function handle(
        MenuImportParser $parser,
        MenuImportRowValidator $validator,
        MenuImportSyncService $syncService,
    ): int {
        $import = $this->resolveImport();

        if (! $import instanceof MenuImport) {
            $this->error('Не найден успешный импорт для предпросмотра синхронизации.');

            return self::FAILURE;
        }

        try {
            $parsed = $parser->parse((string) $import->stored_path, $import->format);
            $validation = $validator->validate($parsed);
        } catch (Throwable $exception) {
            $this->error('Не удалось прочитать файл импорта: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($validation['errors'] !== []) {
            $this->error('Импорт-файл не прошел валидацию. Синхронизация не будет применена.');

            foreach (array_slice($validation['errors'], 0, 10) as $error) {
                $row = isset($error['row']) ? 'row '.(int) $error['row'] : 'row n/a';
                $message = (string) ($error['message'] ?? 'validation error');
                $this->line("- {$row}: {$message}");
            }

            return self::FAILURE;
        }

        try {
            $preview = $syncService->preview($validation['rows']);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Menu sync preview complete.');
        $this->line("import_id={$import->id}");
        $this->line('stored_path='.(string) ($import->stored_path ?? ''));
        $this->line("rows_valid={$validation['rows_valid']}");
        $this->line("imported_identity_count={$preview['imported_identity_count']}");
        $this->line("active_count={$preview['active_count']}");
        $this->line("active_matched_count={$preview['active_matched_count']}");
        $this->line("to_deactivate_count={$preview['to_deactivate_count']}");

        if ($preview['to_deactivate'] !== []) {
            $this->line('Will be deactivated:');

            foreach ($preview['to_deactivate'] as $item) {
                $id = (int) ($item['id'] ?? 0);
                $category = (string) ($item['category'] ?? '');
                $title = (string) ($item['title'] ?? '');
                $supplierName = (string) ($item['supplier_name'] ?? '');
                $supplierSuffix = $supplierName !== '' ? " | supplier={$supplierName}" : '';
                $this->line("- #{$id} {$category} | {$title}{$supplierSuffix}");
            }
        }

        return self::SUCCESS;
    }

    private function resolveImport(): ?MenuImport
    {
        $importIdOption = $this->option('import-id');

        if ($importIdOption !== null && $importIdOption !== '') {
            $importId = (int) $importIdOption;

            if ($importId <= 0) {
                return null;
            }

            return MenuImport::query()
                ->whereKey($importId)
                ->where('status', MenuImportStatus::Imported)
                ->first();
        }

        return MenuImport::query()
            ->where('status', MenuImportStatus::Imported)
            ->latest('id')
            ->first();
    }
}
