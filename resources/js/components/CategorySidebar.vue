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
        <div v-if="loading" class="flex gap-2 xl:flex-col xl:gap-2.5" aria-busy="true">
            <Skeleton
                v-for="item in 6"
                :key="`category-pill-${item}`"
                class="h-9 w-24 shrink-0 rounded-full bg-white/80 sm:h-9 sm:w-28 xl:h-10 xl:w-full xl:rounded-xl"
            />
        </div>
        <div
            v-else
            data-testid="category-chip-row"
            class="flex max-w-full min-w-0 flex-wrap items-start gap-1.5 xl:flex-col xl:gap-2 xl:rounded-[1.35rem] xl:border xl:border-slate-200/85 xl:bg-white/92 xl:p-2.5 xl:shadow-[0_10px_28px_rgb(148_163_184/0.12)]"
        >
            <button
                type="button"
                class="inline-flex h-9 max-w-full flex-none shrink-0 items-center gap-2 whitespace-nowrap rounded-full border px-3 text-[11px] font-semibold transition-[background-color,border-color,color,transform] duration-150 active:scale-[0.98] max-[639px]:px-2.5 sm:h-9 sm:px-4 sm:text-sm xl:h-10 xl:w-full xl:max-w-none xl:justify-between xl:rounded-xl xl:px-3"
                :class="selectedCategory === null ? 'border-blue-200 bg-blue-50 text-blue-800 ring-1 ring-blue-200/60' : 'border-slate-200 bg-white text-slate-700 shadow-sm hover:border-blue-200 hover:bg-blue-50 hover:text-blue-800'"
                :aria-pressed="selectedCategory === null"
                @click="emit('update:selectedCategory', null)"
            >
                <span class="max-w-[20rem] overflow-hidden text-ellipsis whitespace-nowrap">Все блюда</span>
                <Badge
                    variant="outline"
                    class="h-5 border-0 px-1.5 text-[11px] tabular-nums max-[639px]:px-1 max-[639px]:text-[10px] sm:h-6 sm:px-2 sm:text-xs"
                    :class="selectedCategory === null ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-600'"
                >
                    {{ items.length }}
                </Badge>
            </button>
            <slot name="append" />
            <button
                v-for="category in categories"
                :key="category.id"
                type="button"
                class="inline-flex h-9 max-w-full flex-none shrink-0 items-center gap-2 whitespace-nowrap rounded-full border px-3 text-[11px] font-semibold transition-[background-color,border-color,color,transform] duration-150 active:scale-[0.98] max-[639px]:px-2.5 sm:h-9 sm:px-4 sm:text-sm xl:h-10 xl:w-full xl:max-w-none xl:justify-between xl:rounded-xl xl:px-3"
                :class="selectedCategory === category.id ? 'border-blue-200 bg-blue-50 text-blue-800 ring-1 ring-blue-200/60' : 'border-slate-200 bg-white text-slate-700 shadow-sm hover:border-blue-200 hover:bg-blue-50 hover:text-blue-800'"
                :aria-pressed="selectedCategory === category.id"
                @click="emit('update:selectedCategory', category.id)"
            >
                <span class="max-w-[20rem] overflow-hidden text-ellipsis whitespace-nowrap">{{ category.name }}</span>
                <Badge
                    variant="outline"
                    class="h-5 border-0 px-1.5 text-[11px] tabular-nums max-[639px]:px-1 max-[639px]:text-[10px] sm:h-6 sm:px-2 sm:text-xs"
                    :class="selectedCategory === category.id ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-600'"
                >
                    {{ categoryItemCount(category.id) }}
                </Badge>
            </button>
        </div>
    </nav>
</template>
