<script setup>
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import MenuItemCard from '@/components/MenuItemCard.vue';
import { Heart, Search, X } from 'lucide-vue-next';

const props = defineProps({
    loading: {
        type: Boolean,
        default: false,
    },
    filteredItems: {
        type: Array,
        default: () => [],
    },
    menuSkeletonRows: {
        type: Array,
        default: () => [],
    },
    search: {
        type: String,
        default: '',
    },
    orderItemByMenuItem: {
        type: Map,
        default: () => new Map(),
    },
    favoriteIds: {
        type: Set,
        default: () => new Set(),
    },
    favoritesOnly: {
        type: Boolean,
        default: false,
    },
    favoritesCount: {
        type: Number,
        default: 0,
    },
    isAuthenticated: {
        type: Boolean,
        default: false,
    },
    canEditOrder: {
        type: Boolean,
        default: false,
    },
    disabledReason: {
        type: String,
        default: '',
    },
    actionLoading: {
        type: Boolean,
        default: false,
    },
    hasActiveFilters: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'update:search',
    'toggle-favorite',
    'toggle-favorites-filter',
    'clear-filters',
    'add-item',
    'change-quantity',
]);

const searchModel = computed({
    get: () => props.search,
    set: (value) => emit('update:search', value),
});

const resultsLabel = computed(() => {
    const count = props.filteredItems.length;
    const ending = count % 10 === 1 && count % 100 !== 11
        ? 'блюдо'
        : ([2, 3, 4].includes(count % 10) && ![12, 13, 14].includes(count % 100) ? 'блюда' : 'блюд');

    return `${count} ${ending}`;
});

const emptyTitle = computed(() => {
    if (props.favoritesOnly && props.favoritesCount === 0) {
        return 'В избранном пока ничего нет.';
    }

    if (!props.hasActiveFilters) {
        return 'Меню на эту неделю пока пусто';
    }

    return 'Ничего не найдено';
});

const emptyDescription = computed(() => {
    if (props.favoritesOnly && props.favoritesCount === 0) {
        return 'Нажимайте сердечко на блюдах, чтобы сохранить их здесь.';
    }

    if (!props.hasActiveFilters) {
        return 'Блюда появятся здесь после публикации меню.';
    }

    return 'Измените поиск или выберите другую категорию.';
});

const clearFiltersLabel = computed(() => props.favoritesOnly && props.favoritesCount === 0
    ? 'Показать всё меню'
    : 'Сбросить фильтры');

const orderItemFor = (menuItemId) => props.orderItemByMenuItem.get(menuItemId) ?? null;
const isFavorite = (menuItemId) => props.favoriteIds.has(menuItemId);
</script>

<template>
    <section class="min-w-0" aria-labelledby="menu-heading">
        <div class="mb-4 flex flex-col gap-3 sm:mb-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold text-blue-700">Что нового</p>
                <h2 id="menu-heading" tabindex="-1" class="mt-1 text-balance text-2xl font-semibold tracking-[-0.03em] text-slate-950 outline-none sm:text-3xl">
                    Меню недели
                </h2>
                <p v-if="!loading" class="mt-1 text-pretty text-sm text-slate-500">
                    {{ resultsLabel }} доступно для заказа
                </p>
                <Skeleton v-else class="mt-2 h-4 w-44 rounded-md bg-slate-100" />
            </div>

            <div class="flex flex-col gap-2 sm:flex-row lg:max-w-lg lg:flex-1 lg:justify-end">
                <label class="relative min-w-0 flex-1 md:hidden">
                    <span class="sr-only">Найти блюдо</span>
                    <Search aria-hidden="true" class="pointer-events-none absolute left-3.5 top-1/2 size-5 -translate-y-1/2 text-slate-400" />
                    <Input
                        id="menu-search"
                        v-model="searchModel"
                        type="search"
                        placeholder="Название или состав"
                        class="h-12 rounded-2xl border-slate-200 bg-white pl-11 pr-4 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus-visible:border-blue-600 focus-visible:ring-blue-600/15"
                    />
                </label>

                <Button
                    v-if="isAuthenticated"
                    type="button"
                    variant="outline"
                    class="h-10 rounded-full border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition-[background-color,border-color,transform] duration-150 hover:border-blue-200 hover:bg-blue-50 active:scale-[0.98] sm:h-11"
                    :class="favoritesOnly ? 'border-blue-200 bg-blue-50 text-blue-700' : ''"
                    :aria-pressed="favoritesOnly"
                    @click="emit('toggle-favorites-filter')"
                >
                    <Heart aria-hidden="true" class="size-4" :class="favoritesOnly ? 'fill-current' : ''" />
                    Избранное
                    <span v-if="favoritesCount" class="tabular-nums text-xs opacity-75">{{ favoritesCount }}</span>
                </Button>
            </div>
        </div>

        <div v-if="loading" class="dishes-grid" aria-busy="true" aria-label="Загрузка блюд">
            <Card
                v-for="skeleton in menuSkeletonRows"
                :key="`menu-skeleton-${skeleton}`"
                class="overflow-hidden rounded-[1.45rem] border-slate-200/80 bg-white shadow-sm"
            >
                <CardContent class="space-y-3 p-0">
                    <div class="p-2.5 pb-0">
                        <Skeleton class="h-64 w-full rounded-[1.15rem] bg-slate-100 sm:h-64 lg:h-[15.5rem] xl:h-[16.25rem]" />
                    </div>
                    <div class="space-y-3 px-5 pb-5 pt-2">
                        <Skeleton class="h-4 w-20 rounded-md bg-slate-100" />
                        <Skeleton class="h-5 w-4/5 rounded-md bg-slate-100" />
                        <Skeleton class="h-4 w-full rounded-md bg-slate-100" />
                        <div class="flex items-center justify-between pt-3">
                            <Skeleton class="h-7 w-24 rounded-md bg-slate-100" />
                            <Skeleton class="h-11 w-28 rounded-xl bg-slate-100" />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card v-else-if="filteredItems.length === 0" class="rounded-[1.5rem] border-slate-200/80 bg-white/90 shadow-sm">
            <CardContent class="flex flex-col items-center px-5 py-12 text-center">
                <Search aria-hidden="true" class="size-7 text-slate-300" />
                <p class="mt-4 text-balance text-lg font-semibold text-slate-900">{{ emptyTitle }}</p>
                <p class="mt-2 max-w-sm text-pretty text-sm leading-6 text-slate-500">
                    {{ emptyDescription }}
                </p>
                <Button
                    v-if="hasActiveFilters"
                    type="button"
                    variant="outline"
                    class="mt-5 h-11 rounded-xl border-slate-200 px-4 text-sm font-semibold"
                    @click="emit('clear-filters')"
                >
                    <X aria-hidden="true" class="size-4" />
                    {{ clearFiltersLabel }}
                </Button>
            </CardContent>
        </Card>

        <div v-else class="dishes-grid">
            <MenuItemCard
                v-for="item in filteredItems"
                :key="item.id"
                :item="item"
                :order-item="orderItemFor(item.id)"
                :is-favorite="isFavorite(item.id)"
                :is-authenticated="isAuthenticated"
                :can-edit-order="canEditOrder"
                :disabled-reason="disabledReason"
                :action-loading="actionLoading"
                @toggle-favorite="emit('toggle-favorite', $event)"
                @add-item="emit('add-item', $event)"
                @change-quantity="(...args) => emit('change-quantity', ...args)"
            />
        </div>
    </section>
</template>
