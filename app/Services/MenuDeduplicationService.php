<?php

namespace App\Services;

use App\Models\FridgeItem;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Support\MenuTextNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MenuDeduplicationService
{
    public function __construct(
        private readonly MenuTextNormalizer $normalizer,
    ) {}

    /**
     * @return array{
     *     dry_run: bool,
     *     categories: array{renamed: int, merged: int, items_moved: int, actions: array<int, array<string, mixed>>},
     *     items: array{
     *         duplicate_groups: int,
     *         duplicate_items: int,
     *         primary_updates: int,
     *         order_refs_moved: int,
     *         order_refs_conflicts: int,
     *         fridge_refs_moved: int,
     *         fridge_refs_skipped: int,
     *         items_deleted: int,
     *         items_deactivated: int,
     *         groups: array<int, array<string, mixed>>
     *     }
     * }
     */
    public function run(bool $dryRun = true): array
    {
        return DB::transaction(function () use ($dryRun): array {
            $categoryResult = $this->normalizeCategories($dryRun);
            $itemResult = $this->deduplicateMenuItems($dryRun);

            return [
                'dry_run' => $dryRun,
                'categories' => $categoryResult,
                'items' => $itemResult,
            ];
        });
    }

    /**
     * @return array{renamed: int, merged: int, items_moved: int, actions: array<int, array<string, mixed>>}
     */
    private function normalizeCategories(bool $dryRun): array
    {
        $categories = MenuCategory::query()
            ->orderBy('id')
            ->get();

        $groups = [];

        foreach ($categories as $category) {
            $normalizedName = $this->normalizer->normalizeImportedCategoryName((string) $category->name);
            $groupKey = $this->normalizer->normalizeName($normalizedName);

            if ($groupKey === '') {
                continue;
            }

            $groups[$groupKey][] = [
                'category' => $category,
                'normalized_name' => $normalizedName,
            ];
        }

        $renamed = 0;
        $merged = 0;
        $itemsMoved = 0;
        $actions = [];

        foreach ($groups as $entries) {
            $entryCollection = collect($entries);

            if ($entryCollection->isEmpty()) {
                continue;
            }

            $primaryEntry = $entryCollection->sort(function (array $left, array $right): int {
                $leftExactName = (int) ((string) $left['category']->name === (string) $left['normalized_name']);
                $rightExactName = (int) ((string) $right['category']->name === (string) $right['normalized_name']);
                if ($leftExactName !== $rightExactName) {
                    return $rightExactName <=> $leftExactName;
                }

                $leftActive = (int) $left['category']->is_active;
                $rightActive = (int) $right['category']->is_active;
                if ($leftActive !== $rightActive) {
                    return $rightActive <=> $leftActive;
                }

                $leftSortOrder = (int) $left['category']->sort_order;
                $rightSortOrder = (int) $right['category']->sort_order;
                if ($leftSortOrder !== $rightSortOrder) {
                    return $leftSortOrder <=> $rightSortOrder;
                }

                return (int) $left['category']->id <=> (int) $right['category']->id;
            })->first();

            /** @var MenuCategory $primary */
            $primary = $primaryEntry['category'];
            $targetName = (string) ($primaryEntry['normalized_name'] ?? $primary->name);
            $secondaryEntries = $entryCollection
                ->reject(fn (array $entry): bool => (int) $entry['category']->id === (int) $primary->id)
                ->values();

            $groupActions = [];
            $originalPrimaryName = (string) $primary->name;

            if ($targetName !== '' && $originalPrimaryName !== $targetName) {
                $renamed++;
                $groupActions[] = "rename #{$primary->id}: {$originalPrimaryName} -> {$targetName}";

                if (! $dryRun) {
                    $primary->forceFill(['name' => $targetName])->save();
                }
            }

            $hasActiveInGroup = $entryCollection->contains(
                fn (array $entry): bool => (bool) $entry['category']->is_active,
            );

            if (! $primary->is_active && $hasActiveInGroup) {
                $groupActions[] = "activate #{$primary->id}";

                if (! $dryRun) {
                    $primary->forceFill(['is_active' => true])->save();
                }
            }

            foreach ($secondaryEntries as $secondaryEntry) {
                /** @var MenuCategory $secondary */
                $secondary = $secondaryEntry['category'];

                $itemsInSecondary = MenuItem::query()
                    ->where('category_id', $secondary->id)
                    ->count();

                $itemsMoved += $itemsInSecondary;
                $merged++;
                $groupActions[] = "merge category #{$secondary->id} into #{$primary->id} (items={$itemsInSecondary})";

                if (! $dryRun) {
                    if ($itemsInSecondary > 0) {
                        MenuItem::query()
                            ->where('category_id', $secondary->id)
                            ->update(['category_id' => $primary->id]);
                    }

                    $remaining = MenuItem::query()
                        ->where('category_id', $secondary->id)
                        ->count();

                    if ($remaining === 0) {
                        $secondary->delete();
                    }
                }
            }

            if ($groupActions !== []) {
                $actions[] = [
                    'normalized_key' => $this->normalizer->normalizeName($targetName !== '' ? $targetName : $originalPrimaryName),
                    'target_category_id' => $primary->id,
                    'target_category_name' => $targetName !== '' ? $targetName : $originalPrimaryName,
                    'actions' => $groupActions,
                ];
            }
        }

        return [
            'renamed' => $renamed,
            'merged' => $merged,
            'items_moved' => $itemsMoved,
            'actions' => $actions,
        ];
    }

    /**
     * @return array{
     *     duplicate_groups: int,
     *     duplicate_items: int,
     *     primary_updates: int,
     *     order_refs_moved: int,
     *     order_refs_conflicts: int,
     *     fridge_refs_moved: int,
     *     fridge_refs_skipped: int,
     *     items_deleted: int,
     *     items_deactivated: int,
     *     groups: array<int, array<string, mixed>>
     * }
     */
    private function deduplicateMenuItems(bool $dryRun): array
    {
        $items = MenuItem::query()
            ->with(['category:id,name'])
            ->withCount(['orderItems', 'fridgeItems'])
            ->orderBy('id')
            ->get();

        $grouped = [];

        foreach ($items as $item) {
            $categoryName = (string) ($item->category?->name ?? '');
            $groupKey = $this->normalizer->menuItemMatchKey($categoryName, (string) $item->title);
            $grouped[$groupKey][] = $item;
        }

        $duplicateGroups = 0;
        $duplicateItems = 0;
        $primaryUpdates = 0;
        $orderRefsMoved = 0;
        $orderRefsConflicts = 0;
        $fridgeRefsMoved = 0;
        $fridgeRefsSkipped = 0;
        $itemsDeleted = 0;
        $itemsDeactivated = 0;
        $groupsSummary = [];

        foreach ($grouped as $groupKey => $groupItems) {
            if (count($groupItems) <= 1) {
                continue;
            }

            $duplicateGroups++;
            $duplicateItems += count($groupItems) - 1;

            $groupCollection = collect($groupItems);
            $primary = $this->selectPrimaryMenuItem($groupCollection);

            if (! $primary instanceof MenuItem) {
                continue;
            }

            $secondaryItems = $groupCollection
                ->reject(fn (MenuItem $item): bool => (int) $item->id === (int) $primary->id)
                ->sortBy(fn (MenuItem $item): int => (int) $item->id)
                ->values();

            $primaryUpdateAttributes = $this->primaryUpdateAttributes($primary, $secondaryItems);
            if ($primaryUpdateAttributes !== []) {
                $primaryUpdates++;

                if (! $dryRun) {
                    $primary->forceFill($primaryUpdateAttributes)->save();
                }
            }

            $secondaryActions = [];

            foreach ($secondaryItems as $secondary) {
                $transfer = $this->transferReferences($primary, $secondary, $dryRun);

                $orderRefsMoved += $transfer['order_refs_moved'];
                $orderRefsConflicts += $transfer['order_refs_conflicts'];
                $fridgeRefsMoved += $transfer['fridge_refs_moved'];
                $fridgeRefsSkipped += $transfer['fridge_refs_skipped'];

                $remainingOrderRefs = $transfer['remaining_order_refs'];
                $remainingFridgeRefs = $transfer['remaining_fridge_refs'];
                $remainingRefs = $remainingOrderRefs + $remainingFridgeRefs;

                if ($remainingRefs === 0) {
                    $itemsDeleted++;
                    $secondaryActions[] = [
                        'item_id' => $secondary->id,
                        'action' => 'delete',
                        'remaining_order_refs' => 0,
                        'remaining_fridge_refs' => 0,
                    ];

                    if (! $dryRun) {
                        $secondary->delete();
                    }

                    continue;
                }

                $actionName = 'keep_inactive';

                if ($secondary->is_active) {
                    $itemsDeactivated++;
                    $actionName = 'deactivate';

                    if (! $dryRun) {
                        $secondary->forceFill(['is_active' => false])->save();
                    }
                }

                $secondaryActions[] = [
                    'item_id' => $secondary->id,
                    'action' => $actionName,
                    'remaining_order_refs' => $remainingOrderRefs,
                    'remaining_fridge_refs' => $remainingFridgeRefs,
                ];
            }

            $groupsSummary[] = [
                'group_key' => $groupKey,
                'category' => (string) ($primary->category?->name ?? ''),
                'title' => (string) $primary->title,
                'primary_id' => $primary->id,
                'secondary_ids' => $secondaryItems->pluck('id')->values()->all(),
                'secondary_actions' => $secondaryActions,
                'primary_updates' => $primaryUpdateAttributes,
            ];
        }

        return [
            'duplicate_groups' => $duplicateGroups,
            'duplicate_items' => $duplicateItems,
            'primary_updates' => $primaryUpdates,
            'order_refs_moved' => $orderRefsMoved,
            'order_refs_conflicts' => $orderRefsConflicts,
            'fridge_refs_moved' => $fridgeRefsMoved,
            'fridge_refs_skipped' => $fridgeRefsSkipped,
            'items_deleted' => $itemsDeleted,
            'items_deactivated' => $itemsDeactivated,
            'groups' => $groupsSummary,
        ];
    }

    /**
     * @param  Collection<int, MenuItem>  $items
     */
    private function selectPrimaryMenuItem(Collection $items): ?MenuItem
    {
        if ($items->isEmpty()) {
            return null;
        }

        /** @var MenuItem $primary */
        $primary = $items->sort(function (MenuItem $left, MenuItem $right): int {
            $hasImageComparison = (int) $this->hasImage($right) <=> (int) $this->hasImage($left);
            if ($hasImageComparison !== 0) {
                return $hasImageComparison;
            }

            $hasRefsComparison = (int) $this->hasReferences($right) <=> (int) $this->hasReferences($left);
            if ($hasRefsComparison !== 0) {
                return $hasRefsComparison;
            }

            $leftCreatedAt = $left->created_at?->timestamp ?? PHP_INT_MAX;
            $rightCreatedAt = $right->created_at?->timestamp ?? PHP_INT_MAX;
            if ($leftCreatedAt !== $rightCreatedAt) {
                return $leftCreatedAt <=> $rightCreatedAt;
            }

            $activeComparison = (int) $right->is_active <=> (int) $left->is_active;
            if ($activeComparison !== 0) {
                return $activeComparison;
            }

            return $left->id <=> $right->id;
        })->first();

        return $primary;
    }

    private function hasImage(MenuItem $item): bool
    {
        return filled($item->image_path) || filled($item->image_url);
    }

    private function hasReferences(MenuItem $item): bool
    {
        return ((int) ($item->order_items_count ?? 0) + (int) ($item->fridge_items_count ?? 0)) > 0;
    }

    /**
     * @param  Collection<int, MenuItem>  $secondaryItems
     * @return array<string, mixed>
     */
    private function primaryUpdateAttributes(MenuItem $primary, Collection $secondaryItems): array
    {
        $attributes = [];

        foreach ($secondaryItems as $secondary) {
            if (! filled($primary->image_path) && filled($secondary->image_path) && ! array_key_exists('image_path', $attributes)) {
                $attributes['image_path'] = $secondary->image_path;
            }

            if (! filled($primary->image_url) && filled($secondary->image_url) && ! array_key_exists('image_url', $attributes)) {
                $attributes['image_url'] = $secondary->image_url;
            }

            if (! filled($primary->supplier_name) && filled($secondary->supplier_name) && ! array_key_exists('supplier_name', $attributes)) {
                $attributes['supplier_name'] = $secondary->supplier_name;
            }

            if (! filled($primary->external_id) && filled($secondary->external_id) && ! array_key_exists('external_id', $attributes)) {
                $attributes['external_id'] = $secondary->external_id;
            }

            if (! filled($primary->supplier_code) && filled($secondary->supplier_code) && ! array_key_exists('supplier_code', $attributes)) {
                $attributes['supplier_code'] = $secondary->supplier_code;
            }

            if (! $primary->is_active && $secondary->is_active) {
                $attributes['is_active'] = true;
            }
        }

        return $attributes;
    }

    /**
     * @return array{
     *     order_refs_moved: int,
     *     order_refs_conflicts: int,
     *     fridge_refs_moved: int,
     *     fridge_refs_skipped: int,
     *     remaining_order_refs: int,
     *     remaining_fridge_refs: int
     * }
     */
    private function transferReferences(MenuItem $primary, MenuItem $secondary, bool $dryRun): array
    {
        $orderRows = OrderItem::query()
            ->where('menu_item_id', $secondary->id)
            ->orderBy('id')
            ->get(['id', 'order_id']);

        $orderRefsMoved = 0;
        $orderRefsConflicts = 0;
        $movedOrderItemIds = [];

        foreach ($orderRows as $orderRow) {
            $hasConflict = OrderItem::query()
                ->where('order_id', $orderRow->order_id)
                ->where('menu_item_id', $primary->id)
                ->exists();

            if ($hasConflict) {
                $orderRefsConflicts++;

                continue;
            }

            $orderRefsMoved++;
            $movedOrderItemIds[] = (int) $orderRow->id;

            if (! $dryRun) {
                OrderItem::query()
                    ->whereKey($orderRow->id)
                    ->update(['menu_item_id' => $primary->id]);
            }
        }

        $fridgeRows = FridgeItem::query()
            ->where('menu_item_id', $secondary->id)
            ->orderBy('id')
            ->get(['id', 'order_item_id']);

        $fridgeRefsMoved = 0;
        $fridgeRefsSkipped = 0;

        foreach ($fridgeRows as $fridgeRow) {
            $orderItemId = $fridgeRow->order_item_id;
            $safeToMove = $orderItemId === null
                || in_array((int) $orderItemId, $movedOrderItemIds, true)
                || OrderItem::query()
                    ->whereKey($orderItemId)
                    ->where('menu_item_id', $primary->id)
                    ->exists();

            if (! $safeToMove) {
                $fridgeRefsSkipped++;

                continue;
            }

            $fridgeRefsMoved++;

            if (! $dryRun) {
                FridgeItem::query()
                    ->whereKey($fridgeRow->id)
                    ->update(['menu_item_id' => $primary->id]);
            }
        }

        $remainingOrderRefs = $dryRun
            ? max(0, $orderRows->count() - $orderRefsMoved)
            : OrderItem::query()->where('menu_item_id', $secondary->id)->count();

        $remainingFridgeRefs = $dryRun
            ? max(0, $fridgeRows->count() - $fridgeRefsMoved)
            : FridgeItem::query()->where('menu_item_id', $secondary->id)->count();

        return [
            'order_refs_moved' => $orderRefsMoved,
            'order_refs_conflicts' => $orderRefsConflicts,
            'fridge_refs_moved' => $fridgeRefsMoved,
            'fridge_refs_skipped' => $fridgeRefsSkipped,
            'remaining_order_refs' => $remainingOrderRefs,
            'remaining_fridge_refs' => $remainingFridgeRefs,
        ];
    }
}
