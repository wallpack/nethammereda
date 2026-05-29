<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Support\MenuTextNormalizer;
use RuntimeException;

class MenuImportSyncService
{
    public function __construct(
        private readonly MenuTextNormalizer $textNormalizer,
    ) {}

    /**
     * @param  array<int, array{
     *     row_number: int,
     *     category: string,
     *     name: string,
     *     price: float,
     *     fields: array<string, mixed>
     * }>  $rows
     * @return array{
     *     imported_identity_count: int,
     *     active_count: int,
     *     active_matched_count: int,
     *     to_deactivate_count: int,
     *     to_deactivate: array<int, array{
     *         id: int,
     *         title: string,
     *         supplier_name: ?string,
     *         category: string
     *     }>
     * }
     */
    public function preview(array $rows): array
    {
        return $this->synchronizeInternal($rows, true);
    }

    /**
     * @param  array<int, array{
     *     row_number: int,
     *     category: string,
     *     name: string,
     *     price: float,
     *     fields: array<string, mixed>
     * }>  $rows
     * @return array{
     *     imported_identity_count: int,
     *     active_count: int,
     *     active_matched_count: int,
     *     to_deactivate_count: int,
     *     to_deactivate: array<int, array{
     *         id: int,
     *         title: string,
     *         supplier_name: ?string,
     *         category: string
     *     }>
     * }
     */
    public function synchronize(array $rows): array
    {
        return $this->synchronizeInternal($rows, false);
    }

    /**
     * @param  array<int, array{
     *     row_number: int,
     *     category: string,
     *     name: string,
     *     price: float,
     *     fields: array<string, mixed>
     * }>  $rows
     * @return array{
     *     imported_identity_count: int,
     *     active_count: int,
     *     active_matched_count: int,
     *     to_deactivate_count: int,
     *     to_deactivate: array<int, array{
     *         id: int,
     *         title: string,
     *         supplier_name: ?string,
     *         category: string
     *     }>
     * }
     */
    private function synchronizeInternal(array $rows, bool $dryRun): array
    {
        $importedIdentitySet = $this->importedIdentitySet($rows);

        if ($importedIdentitySet === []) {
            throw new RuntimeException('Импорт не содержит валидных блюд для синхронизации активного каталога.');
        }

        $activeItems = MenuItem::query()
            ->with('category:id,name')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $activeMatchedCount = 0;
        $toDeactivate = [];

        foreach ($activeItems as $item) {
            if ($this->itemMatchesImportedIdentitySet($item, $importedIdentitySet)) {
                $activeMatchedCount++;

                continue;
            }

            $toDeactivate[] = [
                'id' => (int) $item->id,
                'title' => (string) $item->title,
                'supplier_name' => filled($item->supplier_name) ? (string) $item->supplier_name : null,
                'category' => (string) ($item->category?->name ?? ''),
            ];
        }

        if (! $dryRun && $toDeactivate !== []) {
            MenuItem::query()
                ->whereIn('id', array_column($toDeactivate, 'id'))
                ->update(['is_active' => false]);
        }

        return [
            'imported_identity_count' => count($importedIdentitySet),
            'active_count' => $activeItems->count(),
            'active_matched_count' => $activeMatchedCount,
            'to_deactivate_count' => count($toDeactivate),
            'to_deactivate' => $toDeactivate,
        ];
    }

    /**
     * @param  array<int, array{
     *     row_number: int,
     *     category: string,
     *     name: string,
     *     price: float,
     *     fields: array<string, mixed>
     * }>  $rows
     * @return array<string, true>
     */
    private function importedIdentitySet(array $rows): array
    {
        $set = [];

        foreach ($rows as $row) {
            $key = $this->textNormalizer->menuItemMatchKey(
                (string) ($row['category'] ?? ''),
                (string) ($row['name'] ?? ''),
            );

            if ($key === '|') {
                continue;
            }

            $set[$key] = true;
        }

        return $set;
    }

    /**
     * @param  array<string, true>  $importedIdentitySet
     */
    private function itemMatchesImportedIdentitySet(MenuItem $item, array $importedIdentitySet): bool
    {
        $category = (string) ($item->category?->name ?? '');

        $titleIdentity = $this->textNormalizer->menuItemMatchKey($category, (string) $item->title);
        if ($titleIdentity !== '|' && isset($importedIdentitySet[$titleIdentity])) {
            return true;
        }

        $supplierName = (string) ($item->supplier_name ?? '');
        if ($supplierName !== '') {
            $supplierIdentity = $this->textNormalizer->menuItemMatchKey($category, $supplierName);

            return $supplierIdentity !== '|' && isset($importedIdentitySet[$supplierIdentity]);
        }

        return false;
    }
}
