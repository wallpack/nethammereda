<script setup>
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import CategorySidebar from '@/components/CategorySidebar.vue';
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
    categories: {
        type: Array,
        default: () => [],
    },
    items: {
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
    selectedCategory: {
        type: [Number, String],
        default: null,
    },
});

const emit = defineEmits([
    'update:search',
    'toggle-favorite',
    'toggle-favorites-filter',
    'clear-filters',
    'update:selectedCategory',
    'add-item',
    'change-quantity',
]);

const searchModel = computed({
    get: () => props.search,
    set: (value) => emit('update:search', value),
});

const selectedCategoryKey = computed(() => (
    props.selectedCategory === null || props.selectedCategory === undefined
        ? null
        : String(props.selectedCategory)
));

const showCategorySections = computed(() => selectedCategoryKey.value === null);

const formatDishesCountLabel = (count) => {
    const normalizedCount = Math.max(0, Number(count) || 0);
    const mod10 = normalizedCount % 10;
    const mod100 = normalizedCount % 100;

    if (mod10 === 1 && mod100 !== 11) {
        return `${normalizedCount} блюдо`;
    }

    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
        return `${normalizedCount} блюда`;
    }

    return `${normalizedCount} блюд`;
};

const groupedFilteredItems = computed(() => {
    const groupsByKey = new Map();
    const orderedCategoryKeys = [];
    const fallbackKeys = [];

    props.categories.forEach((category) => {
        const key = String(category.id);
        orderedCategoryKeys.push(key);
        groupsByKey.set(key, {
            key,
            id: category.id,
            name: category.name,
            items: [],
        });
    });

    props.filteredItems.forEach((item) => {
        const key = item.category_id === null || item.category_id === undefined
            ? `fallback:${item.category?.name ?? 'Без категории'}`
            : String(item.category_id);

        if (!groupsByKey.has(key)) {
            fallbackKeys.push(key);
            groupsByKey.set(key, {
                key,
                id: item.category_id ?? key,
                name: item.category?.name?.trim() || 'Без категории',
                items: [],
            });
        }

        groupsByKey.get(key).items.push(item);
    });

    return [...orderedCategoryKeys, ...fallbackKeys]
        .map((key) => groupsByKey.get(key))
        .filter((group) => group && group.items.length > 0);
});

const selectedCategorySummary = computed(() => {
    if (showCategorySections.value) {
        return null;
    }

    const category = props.categories.find((entry) => String(entry.id) === selectedCategoryKey.value);
    const fallbackName = props.filteredItems[0]?.category?.name || 'Категория';

    return {
        name: category?.name || fallbackName,
        count: props.filteredItems.length,
    };
});

const renderGroups = computed(() => {
    if (showCategorySections.value) {
        return groupedFilteredItems.value;
    }

    return [
        {
            key: selectedCategoryKey.value ?? 'selected',
            id: selectedCategoryKey.value ?? 'selected',
            name: selectedCategorySummary.value?.name || 'Категория',
            items: props.filteredItems,
        },
    ];
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
            <div class="min-w-0">
                <h2 id="menu-heading" tabindex="-1" class="text-balance text-2xl font-semibold tracking-[-0.03em] text-slate-950 outline-none sm:text-3xl">
                    Каталог
                </h2>
                <div class="mt-3">
                    <CategorySidebar
                        :loading="loading"
                        :categories="categories"
                        :items="items"
                        :selected-category="selectedCategory"
                        @update:selected-category="emit('update:selectedCategory', $event)"
                    />
                </div>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row lg:max-w-md lg:flex-1 lg:justify-end">
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
                class="menu-card overflow-hidden rounded-[1.45rem] border-slate-200/80 bg-white shadow-sm max-[430px]:overflow-visible max-[430px]:rounded-none max-[430px]:border-transparent max-[430px]:bg-transparent max-[430px]:shadow-none"
            >
                <CardContent class="space-y-3 p-0">
                    <div class="p-2.5 pb-0 max-[430px]:p-0">
                        <Skeleton class="h-[16rem] w-full rounded-[1.15rem] bg-slate-100 sm:h-[17rem] lg:h-[17.25rem] xl:h-[17.75rem] max-[430px]:h-[8.25rem] max-[430px]:rounded-2xl" />
                    </div>
                    <div class="space-y-3 px-5 pb-5 pt-2 max-[430px]:space-y-2.5 max-[430px]:px-3 max-[430px]:pb-3 max-[430px]:pt-0">
                        <Skeleton class="h-4 w-20 rounded-md bg-slate-100" />
                        <Skeleton class="h-5 w-4/5 rounded-md bg-slate-100" />
                        <Skeleton class="h-4 w-full rounded-md bg-slate-100 max-[430px]:hidden" />
                        <div class="flex items-center justify-between pt-3 max-[430px]:pt-2.5">
                            <Skeleton class="h-7 w-24 rounded-md bg-slate-100" />
                            <Skeleton class="h-11 w-28 rounded-xl bg-slate-100 max-[430px]:size-10 max-[430px]:rounded-full" />
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

        <div v-else class="space-y-6 sm:space-y-7">
            <section
                v-for="group in renderGroups"
                :key="group.key"
                data-testid="menu-category-section"
                class="space-y-3"
            >
                <header
                    class="flex items-end justify-between gap-3"
                    :data-testid="showCategorySections ? undefined : 'menu-selected-category-summary'"
                >
                    <h3 data-testid="menu-category-heading" class="text-balance text-lg font-semibold tracking-[-0.02em] text-slate-900 sm:text-xl">
                        {{ group.name }}
                    </h3>
                    <p data-testid="menu-category-count" class="shrink-0 text-sm font-medium text-slate-500">
                        {{ formatDishesCountLabel(group.items.length) }}
                    </p>
                </header>

                <div class="dishes-grid">
                    <MenuItemCard
                        v-for="item in group.items"
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
        </div>
    </section>
</template>
