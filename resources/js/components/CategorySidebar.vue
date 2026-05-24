<script setup>
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Croissant, LayoutGrid, Salad, Soup, UtensilsCrossed } from 'lucide-vue-next';

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    items: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    selectedCategory: {
        type: [Number, String],
        default: null,
    },
});

const emit = defineEmits(['update:selectedCategory']);

const categoryItemCount = (categoryId) => {
    return props.items.filter((item) => item.category_id === categoryId).length;
};

const categoryIcon = (categoryName) => {
    const normalized = (categoryName ?? '').toLowerCase();

    if (normalized.includes('втор') || normalized.includes('горяч')) {
        return UtensilsCrossed;
    }

    if (normalized.includes('выпеч') || normalized.includes('блин')) {
        return Croissant;
    }

    if (normalized.includes('салат')) {
        return Salad;
    }

    if (normalized.includes('суп')) {
        return Soup;
    }

    return LayoutGrid;
};
</script>

<template>
    <nav
        class="scrollbar-none max-w-full overflow-x-auto pb-2 lg:hidden"
        aria-label="Категории блюд"
    >
        <div v-if="loading" class="flex gap-2" aria-busy="true">
            <Skeleton v-for="item in 3" :key="`category-pill-${item}`" class="h-11 w-28 shrink-0 rounded-full bg-slate-100" />
        </div>
        <div v-else class="flex w-max min-w-full gap-2">
            <button
                type="button"
                class="inline-flex h-11 shrink-0 items-center gap-2 rounded-2xl border px-4 text-sm font-medium transition-[background-color,border-color,color,transform] duration-150 active:scale-[0.98]"
                :class="selectedCategory === null ? 'border-blue-600 bg-blue-600 text-white shadow-sm ring-1 ring-blue-500/20' : 'border-slate-200 bg-white text-slate-700 hover:bg-blue-50 hover:text-blue-700'"
                :aria-pressed="selectedCategory === null"
                @click="emit('update:selectedCategory', null)"
            >
                Все
                <span
                    class="rounded-full px-2 py-0.5 tabular-nums"
                    :class="selectedCategory === null ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'"
                >
                    {{ items.length }}
                </span>
            </button>
            <button
                v-for="category in categories"
                :key="category.id"
                type="button"
                class="inline-flex h-11 shrink-0 items-center gap-2 rounded-2xl border px-4 text-sm font-medium transition-[background-color,border-color,color,transform] duration-150 active:scale-[0.98]"
                :class="selectedCategory === category.id ? 'border-blue-600 bg-blue-600 text-white shadow-sm ring-1 ring-blue-500/20' : 'border-slate-200 bg-white text-slate-700 hover:bg-blue-50 hover:text-blue-700'"
                :aria-pressed="selectedCategory === category.id"
                @click="emit('update:selectedCategory', category.id)"
            >
                {{ category.name }}
                <span
                    class="rounded-full px-2 py-0.5 tabular-nums"
                    :class="selectedCategory === category.id ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'"
                >
                    {{ categoryItemCount(category.id) }}
                </span>
            </button>
        </div>
    </nav>

    <aside class="catalog-sidebar hidden min-w-0 lg:sticky lg:top-24 lg:block lg:self-start">
        <nav v-if="loading" class="space-y-3 rounded-3xl border border-slate-200 bg-white p-3 shadow-sm" aria-label="Загрузка категорий" aria-busy="true">
            <Skeleton class="h-4 w-20 bg-slate-100" />
            <Skeleton v-for="item in 4" :key="`category-row-${item}`" class="h-12 w-full rounded-xl bg-slate-100" />
        </nav>
        <nav v-else class="rounded-3xl border border-slate-200 bg-white p-2 shadow-sm" aria-label="Категории блюд">
            <p class="px-3 pb-2 pt-2 text-xs font-medium text-slate-500">Категории</p>
            <button
                type="button"
                class="flex h-12 w-full items-center justify-between rounded-2xl px-3 text-left text-sm font-semibold transition-[background-color,color,transform] duration-150 active:scale-[0.98]"
                :class="selectedCategory === null ? 'bg-blue-600 text-white shadow-sm ring-1 ring-blue-500/20' : 'text-slate-700 hover:bg-blue-50 hover:text-blue-700'"
                :aria-pressed="selectedCategory === null"
                @click="emit('update:selectedCategory', null)"
            >
                <span class="flex items-center gap-2.5">
                    <LayoutGrid aria-hidden="true" class="size-4" />
                    Все блюда
                </span>
                <Badge
                    variant="outline"
                    class="border-0 px-2 tabular-nums"
                    :class="selectedCategory === null ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'"
                >
                    {{ items.length }}
                </Badge>
            </button>
            <button
                v-for="category in categories"
                :key="category.id"
                type="button"
                class="mt-1 flex h-12 w-full items-center justify-between rounded-2xl px-3 text-left text-sm font-medium transition-[background-color,color,transform] duration-150 active:scale-[0.98]"
                :class="selectedCategory === category.id ? 'bg-blue-600 text-white shadow-sm ring-1 ring-blue-500/20' : 'text-slate-700 hover:bg-blue-50 hover:text-blue-700'"
                :aria-pressed="selectedCategory === category.id"
                @click="emit('update:selectedCategory', category.id)"
            >
                <span class="flex items-center gap-2.5">
                    <component :is="categoryIcon(category.name)" aria-hidden="true" class="size-4" />
                    {{ category.name }}
                </span>
                <Badge
                    variant="outline"
                    class="border-0 px-2 tabular-nums"
                    :class="selectedCategory === category.id ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'"
                >
                    {{ categoryItemCount(category.id) }}
                </Badge>
            </button>
        </nav>
    </aside>
</template>
