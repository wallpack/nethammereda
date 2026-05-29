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
    <section class="menu-shell min-w-0" aria-labelledby="menu-heading">
        <aside class="menu-shell__rail" data-testid="menu-category-rail">
            <div class="menu-shell__rail-inner">
                <p class="hidden px-1 text-xs font-semibold uppercase tracking-[0.13em] text-slate-400 xl:block">
                    Категории
                </p>
                <CategorySidebar
                    :loading="loading"
                    :categories="categories"
                    :items="items"
                    :selected-category="selectedCategory"
                    @update:selected-category="emit('update:selectedCategory', $event)"
                />
            </div>
        </aside>

        <div class="menu-shell__content">
            <div class="mb-3.5 flex flex-col gap-2.5 sm:mb-4">
                <div class="flex min-w-0 items-start justify-between gap-3">
                    <h2 id="menu-heading" tabindex="-1" class="text-balance text-2xl font-semibold tracking-[-0.03em] text-slate-950 outline-none sm:text-3xl">
                        Каталог
                    </h2>
                    <button
                        v-if="isAuthenticated"
                        type="button"
                        data-testid="menu-favorites-chip"
                        class="inline-flex h-10 shrink-0 items-center gap-2 rounded-full border px-3.5 text-sm font-semibold transition-[background-color,border-color,color,transform] duration-150 active:scale-[0.98]"
                        :class="favoritesOnly ? 'border-blue-200 bg-blue-50 text-blue-800 ring-1 ring-blue-200/70' : 'border-slate-200 bg-white text-slate-700 hover:border-blue-200 hover:bg-blue-50/70 hover:text-blue-800'"
                        :aria-pressed="favoritesOnly"
                        @click="emit('toggle-favorites-filter')"
                    >
                        <Heart aria-hidden="true" class="size-4" :class="favoritesOnly ? 'fill-current' : ''" />
                        Избранное
                        <span v-if="favoritesCount" class="tabular-nums text-xs opacity-75">{{ favoritesCount }}</span>
                    </button>
                </div>
                <p v-if="!loading" class="hidden text-sm text-slate-500 xl:block">
                    {{ filteredItems.length }} блюд в меню. Выберите категорию слева и добавьте блюда в корзину.
                </p>

                <label class="relative min-w-0 md:hidden">
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
            </div>

            <div v-if="loading" class="dishes-grid" aria-busy="true" aria-label="Загрузка блюд">
                <Card
                    v-for="skeleton in menuSkeletonRows"
                    :key="`menu-skeleton-${skeleton}`"
                    class="menu-card overflow-hidden rounded-[1.45rem] border-slate-200/80 bg-white shadow-sm max-[430px]:overflow-visible max-[430px]:rounded-none max-[430px]:border-transparent max-[430px]:bg-transparent max-[430px]:shadow-none"
                >
                    <CardContent class="space-y-3 p-0">
                        <div class="p-2 pb-0 max-[430px]:p-0">
                            <Skeleton class="h-[13.5rem] w-full rounded-[1.05rem] bg-slate-100 sm:h-[14.25rem] lg:h-[14.5rem] xl:h-[14.75rem] max-[430px]:h-[7.35rem] max-[430px]:rounded-2xl" />
                        </div>
                        <div class="space-y-2.5 px-4 pb-4 pt-1.5 max-[430px]:space-y-2 max-[430px]:px-3 max-[430px]:pb-3 max-[430px]:pt-0.5">
                            <Skeleton class="h-4 w-20 rounded-md bg-slate-100" />
                            <Skeleton class="h-5 w-4/5 rounded-md bg-slate-100" />
                            <Skeleton class="h-4 w-full rounded-md bg-slate-100 max-[430px]:hidden" />
                            <div class="flex items-center justify-between pt-2.5 max-[430px]:pt-2">
                                <Skeleton class="h-7 w-24 rounded-md bg-slate-100" />
                                <Skeleton class="h-10 w-[6.5rem] rounded-xl bg-slate-100 max-[430px]:size-10 max-[430px]:rounded-full" />
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

            <div v-else class="space-y-5 sm:space-y-6">
                <section
                    v-for="group in renderGroups"
                    :key="group.key"
                    data-testid="menu-category-section"
                    class="space-y-3"
                >
                    <header
                        class="flex items-end gap-3"
                        :data-testid="showCategorySections ? undefined : 'menu-selected-category-summary'"
                    >
                        <h3 data-testid="menu-category-heading" class="text-balance text-lg font-semibold tracking-[-0.02em] text-slate-900 sm:text-xl">
                            {{ group.name }}
                        </h3>
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
        </div>
    </section>
</template>
