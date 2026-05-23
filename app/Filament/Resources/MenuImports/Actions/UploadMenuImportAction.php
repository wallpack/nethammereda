<?php

namespace App\Filament\Resources\MenuImports\Actions;

use App\Enums\MenuImportStatus;
use App\Models\MenuImport;
use App\Models\User;
use App\Services\MenuImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class UploadMenuImportAction
{
    public static function make(): Action
    {
        return Action::make('uploadMenu')
            ->label('Загрузить меню')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('primary')
            ->modalHeading('Загрузить меню')
            ->modalDescription('Загрузите CSV или XLSX с колонками Категория, Название и Цена. Если в файле есть ошибки, каталог не изменится.')
            ->modalSubmitActionLabel('Загрузить')
            ->schema([
                FileUpload::make('file')
                    ->label('Файл меню')
                    ->disk('local')
                    ->directory('menu-imports')
                    ->visibility('private')
                    ->acceptedFileTypes([
                        'text/csv',
                        'text/plain',
                        'application/csv',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->maxSize(5120)
                    ->rules(['extensions:csv,xlsx'])
                    ->storeFileNamesIn('original_filename')
                    ->required()
                    ->helperText('Поддерживаются CSV и XLSX до 5 МБ. Файл хранится в приватном storage.'),
            ])
            ->action(function (array $data): void {
                $storedPath = self::storedPathFromData($data);
                $originalFilename = self::originalFilenameFromData($data, $storedPath);

                if ($storedPath === null) {
                    Notification::make()
                        ->title('Файл меню не загружен')
                        ->body('Выберите CSV или XLSX файл и повторите импорт.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $user = auth()->user();
                    $import = app(MenuImportService::class)->importStoredFile(
                        storedPath: $storedPath,
                        originalFilename: $originalFilename,
                        importedBy: $user instanceof User ? $user : null,
                    );
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('Не удалось загрузить меню')
                        ->body(self::safeMessage($exception))
                        ->danger()
                        ->send();

                    return;
                }

                self::notifyResult($import);
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function storedPathFromData(array $data): ?string
    {
        $file = $data['file'] ?? null;

        if (is_array($file)) {
            $file = reset($file);
        }

        return is_string($file) && filled($file) ? $file : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function originalFilenameFromData(array $data, ?string $storedPath): string
    {
        $originalFilename = $data['original_filename'] ?? null;

        if (is_array($originalFilename)) {
            $originalFilename = reset($originalFilename);
        }

        if (is_string($originalFilename) && filled($originalFilename)) {
            return $originalFilename;
        }

        return basename((string) $storedPath);
    }

    private static function notifyResult(MenuImport $import): void
    {
        if ($import->status === MenuImportStatus::Failed) {
            Notification::make()
                ->title('Импорт не применен')
                ->body($import->errorSummary() ?? 'Исправьте ошибки в файле и загрузите меню заново.')
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Меню импортировано')
            ->body("Строк: {$import->rows_total}, обновлено или создано: {$import->rows_valid}.")
            ->success()
            ->send();
    }

    private static function safeMessage(Throwable $exception): string
    {
        if ($exception instanceof InvalidArgumentException || $exception instanceof RuntimeException) {
            return filled($exception->getMessage())
                ? $exception->getMessage()
                : 'Проверьте формат файла и повторите импорт.';
        }

        return 'Проверьте формат файла и повторите импорт.';
    }
}
