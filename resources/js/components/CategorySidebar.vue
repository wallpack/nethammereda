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
        class="max-w-full min-w-0 py-1"
        aria-label="Категории блюд"
    >
        <div v-if="loading" class="flex gap-2 xl:flex-col xl:gap-2.5" aria-busy="true">
            <Skeleton
                v-for="item in 6"
                :key="`category-pill-${item}`"
                class="h-9 w-24 shrink-0 rounded-full bg-white/80 sm:h-10 sm:w-28 xl:h-11 xl:w-full xl:rounded-xl"
            />
        </div>
        <div
            v-else
            data-testid="category-chip-row"
            class="flex max-w-full min-w-0 flex-wrap items-start gap-2 xl:flex-col xl:gap-2.5 xl:rounded-[1.5rem] xl:border xl:border-slate-200/85 xl:bg-white/92 xl:p-3 xl:shadow-[0_14px_36px_rgb(148_163_184/0.14)]"
        >
            <button
                type="button"
                class="inline-flex h-9 max-w-full flex-none shrink-0 items-center gap-2 whitespace-nowrap rounded-full border px-3 text-[11px] font-semibold transition-[background-color,border-color,color,transform] duration-150 active:scale-[0.98] max-[639px]:px-2.5 sm:h-10 sm:px-4 sm:text-sm xl:h-11 xl:w-full xl:max-w-none xl:justify-between xl:rounded-xl xl:px-3"
                :class="selectedCategory === null ? 'border-slate-900 bg-slate-900 text-white shadow-sm ring-1 ring-slate-900/20 xl:border-amber-200 xl:bg-amber-50 xl:text-amber-900 xl:ring-amber-200/60' : 'border-slate-200 bg-white text-slate-700 shadow-sm hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900'"
                :aria-pressed="selectedCategory === null"
                @click="emit('update:selectedCategory', null)"
            >
                <span class="max-w-[20rem] overflow-hidden text-ellipsis whitespace-nowrap">Все блюда</span>
                <Badge
                    variant="outline"
                    class="h-5 border-0 px-1.5 text-[11px] tabular-nums max-[639px]:px-1 max-[639px]:text-[10px] sm:h-6 sm:px-2 sm:text-xs"
                    :class="selectedCategory === null ? 'bg-white/20 text-white xl:bg-amber-100 xl:text-amber-800' : 'bg-slate-100 text-slate-600'"
                >
                    {{ items.length }}
                </Badge>
            </button>
            <slot name="append" />
            <button
                v-for="category in categories"
                :key="category.id"
                type="button"
                class="inline-flex h-9 max-w-full flex-none shrink-0 items-center gap-2 whitespace-nowrap rounded-full border px-3 text-[11px] font-semibold transition-[background-color,border-color,color,transform] duration-150 active:scale-[0.98] max-[639px]:px-2.5 sm:h-10 sm:px-4 sm:text-sm xl:h-11 xl:w-full xl:max-w-none xl:justify-between xl:rounded-xl xl:px-3"
                :class="selectedCategory === category.id ? 'border-slate-900 bg-slate-900 text-white shadow-sm ring-1 ring-slate-900/20 xl:border-amber-200 xl:bg-amber-50 xl:text-amber-900 xl:ring-amber-200/60' : 'border-slate-200 bg-white text-slate-700 shadow-sm hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900'"
                :aria-pressed="selectedCategory === category.id"
                @click="emit('update:selectedCategory', category.id)"
            >
                <span class="max-w-[20rem] overflow-hidden text-ellipsis whitespace-nowrap">{{ category.name }}</span>
                <Badge
                    variant="outline"
                    class="h-5 border-0 px-1.5 text-[11px] tabular-nums max-[639px]:px-1 max-[639px]:text-[10px] sm:h-6 sm:px-2 sm:text-xs"
                    :class="selectedCategory === category.id ? 'bg-white/20 text-white xl:bg-amber-100 xl:text-amber-800' : 'bg-slate-100 text-slate-600'"
                >
                    {{ categoryItemCount(category.id) }}
                </Badge>
            </button>
        </div>
    </nav>
</template>
