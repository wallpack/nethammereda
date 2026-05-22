<script setup>
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import MenuItemCard from '@/components/MenuItemCard.vue';
import { Search, SlidersHorizontal } from 'lucide-vue-next';

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
    isAuthenticated: {
        type: Boolean,
        default: false,
    },
    isOpenForOrdering: {
        type: Boolean,
        default: false,
    },
    actionLoading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'update:search',
    'toggle-favorite',
    'add-item',
    'change-quantity',
]);

const searchModel = computed({
    get: () => props.search,
    set: (value) => emit('update:search', value),
});

const orderItemFor = (menuItemId) => props.orderItemByMenuItem.get(menuItemId) ?? null;
const isFavorite = (menuItemId) => props.favoriteIds.has(menuItemId);
</script>

<template>
    <Card class="overflow-visible border-0 bg-transparent text-slate-900 shadow-none">
        <div class="mb-6 flex flex-col gap-5 2xl:flex-row 2xl:items-end 2xl:justify-between">
            <div>
                <h1 class="text-[30px] font-bold leading-tight tracking-[-0.4px] text-[#111827] md:text-[32px]">
                    Каталог блюд
                </h1>
                <p class="mt-1.5 text-[16px] font-medium text-[#66769f]">
                    {{ filteredItems.length }} блюд на ваш выбор
                </p>
            </div>

            <div class="flex w-full flex-col gap-3 sm:flex-row 2xl:max-w-[560px]">
                <div class="relative min-w-0 flex-1">
                    <Search class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-[#7080a3]" />
                    <Input
                        id="menu-search"
                        v-model="searchModel"
                        type="search"
                        placeholder="Поиск блюд, ингредиентов..."
                        class="h-[52px] rounded-[12px] border-[#e1e8f5] bg-white pl-12 pr-4 text-[15px] font-medium text-[#1f2a44] shadow-[0_10px_28px_rgba(21,39,75,0.04)] placeholder:text-[#7080a3] focus-visible:border-[#0f52ff] focus-visible:ring-[#0f52ff]/15"
                    />
                </div>

                <Button
                    type="button"
                    variant="outline"
                    class="h-[52px] rounded-[12px] border-[#e1e8f5] bg-white px-6 text-[15px] font-bold text-[#25314d] shadow-[0_10px_28px_rgba(21,39,75,0.04)] hover:bg-[#f4f7ff] hover:text-[#0f52ff]"
                >
                    <SlidersHorizontal class="mr-2 size-5 text-[#0f52ff]" />
                    Фильтры
                </Button>
            </div>
        </div>

        <CardContent class="space-y-5 bg-transparent p-0">
            <div v-if="loading" class="dishes-grid">
                <Card
                    v-for="skeleton in menuSkeletonRows"
                    :key="`menu-skeleton-${skeleton}`"
                    class="border-slate-200 bg-white/90"
                >
                    <CardContent class="space-y-3 p-3">
                        <Skeleton class="aspect-square w-full rounded-xl bg-slate-200/80" />
                        <Skeleton class="h-5 w-3/4 rounded-md bg-slate-200/80" />
                        <Skeleton class="h-4 w-full rounded-md bg-slate-200/70" />
                        <Skeleton class="h-4 w-2/3 rounded-md bg-slate-200/70" />
                        <div class="flex items-center justify-between">
                            <Skeleton class="h-5 w-16 rounded-md bg-slate-200/80" />
                            <Skeleton class="h-9 w-28 rounded-lg bg-slate-200/80" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card
                v-else-if="filteredItems.length === 0"
                class="border-slate-200 bg-slate-50"
            >
                <CardContent class="py-14 text-center text-slate-600">
                    <p class="text-base font-semibold text-slate-800">Ничего не найдено</p>
                    <p class="mt-2 text-sm text-slate-500">Попробуйте изменить поисковый запрос или выбрать другую категорию.</p>
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
                    :is-open-for-ordering="isOpenForOrdering"
                    :action-loading="actionLoading"
                    @toggle-favorite="emit('toggle-favorite', $event)"
                    @add-item="emit('add-item', $event)"
                    @change-quantity="(...args) => emit('change-quantity', ...args)"
                />
            </div>
        </CardContent>
    </Card>
</template>
