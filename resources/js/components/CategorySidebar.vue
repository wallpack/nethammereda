<script setup>
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';

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
</script>

<template>
    <nav
        data-testid="category-sidebar"
        class="max-w-full min-w-0 py-0.5"
        aria-label="Категории блюд"
    >
        <div v-if="loading" class="flex gap-2 xl:flex-col xl:gap-1" aria-busy="true">
            <Skeleton
                v-for="item in 7"
                :key="`category-pill-${item}`"
                class="h-10 w-24 shrink-0 rounded-lg bg-white sm:w-28 xl:h-9 xl:w-full"
            />
        </div>
        <div
            v-else
            data-testid="category-chip-row"
            class="flex max-w-full min-w-0 flex-wrap items-start gap-1.5 xl:max-h-[calc(100dvh-8.25rem)] xl:flex-col xl:flex-nowrap xl:gap-1 xl:overflow-y-auto xl:rounded-2xl xl:border xl:border-slate-200/60 xl:bg-white xl:p-2"
        >
            <button
                type="button"
                class="inline-flex h-9 max-w-full flex-none shrink-0 items-center gap-2 whitespace-nowrap rounded-lg border border-transparent px-3 text-xs font-medium transition-[background-color,border-color,color,transform] duration-150 active:scale-[0.98] max-[1279px]:h-10 max-[639px]:px-2.5 sm:px-3.5 sm:text-sm xl:w-full xl:max-w-none xl:justify-between xl:px-2.5 xl:text-[13px]"
                :class="selectedCategory === null ? 'bg-blue-50 text-blue-800' : 'bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-950 xl:bg-transparent'"
                :aria-pressed="selectedCategory === null"
                @click="emit('update:selectedCategory', null)"
            >
                <span class="max-w-[20rem] truncate">Все блюда</span>
                <Badge
                    variant="outline"
                    class="h-5 border-0 px-1.5 text-[11px] tabular-nums max-[639px]:px-1 max-[639px]:text-[10px] sm:h-5 sm:px-1.5 sm:text-[11px]"
                    :class="selectedCategory === null ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-600'"
                >
                    {{ items.length }}
                </Badge>
            </button>
            <button
                v-for="category in categories"
                :key="category.id"
                type="button"
                class="inline-flex h-9 max-w-full flex-none shrink-0 items-center gap-2 whitespace-nowrap rounded-lg border border-transparent px-3 text-xs font-medium transition-[background-color,border-color,color,transform] duration-150 active:scale-[0.98] max-[1279px]:h-10 max-[639px]:px-2.5 sm:px-3.5 sm:text-sm xl:w-full xl:max-w-none xl:justify-between xl:px-2.5 xl:text-[13px]"
                :class="selectedCategory === category.id ? 'bg-blue-50 text-blue-800' : 'bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-950 xl:bg-transparent'"
                :aria-pressed="selectedCategory === category.id"
                @click="emit('update:selectedCategory', category.id)"
            >
                <span class="max-w-[20rem] truncate">{{ category.name }}</span>
                <Badge
                    variant="outline"
                    class="h-5 border-0 px-1.5 text-[11px] tabular-nums max-[639px]:px-1 max-[639px]:text-[10px] sm:h-5 sm:px-1.5 sm:text-[11px]"
                    :class="selectedCategory === category.id ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-600'"
                >
                    {{ categoryItemCount(category.id) }}
                </Badge>
            </button>
        </div>
    </nav>
</template>
