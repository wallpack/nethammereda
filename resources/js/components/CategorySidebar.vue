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
        class="max-w-full min-w-0 py-1"
        aria-label="Категории блюд"
    >
        <div v-if="loading" class="flex gap-2" aria-busy="true">
            <Skeleton v-for="item in 6" :key="`category-pill-${item}`" class="h-9 w-24 shrink-0 rounded-full bg-white/80 sm:h-10 sm:w-28" />
        </div>
        <div
            v-else
            data-testid="category-chip-row"
            class="flex max-w-full min-w-0 flex-wrap items-start gap-2"
        >
            <button
                type="button"
                class="inline-flex h-9 max-w-full flex-none shrink-0 items-center gap-2 whitespace-nowrap rounded-full border px-3 text-[11px] font-semibold transition-[background-color,border-color,color,transform] duration-150 active:scale-[0.98] max-[639px]:px-2.5 sm:h-10 sm:px-4 sm:text-sm"
                :class="selectedCategory === null ? 'border-blue-600 bg-blue-600 text-white shadow-sm ring-1 ring-blue-500/20' : 'border-slate-200 bg-white text-slate-700 shadow-sm hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700'"
                :aria-pressed="selectedCategory === null"
                @click="emit('update:selectedCategory', null)"
            >
                <span class="max-w-[20rem] overflow-hidden text-ellipsis whitespace-nowrap">Все блюда</span>
                <Badge
                    variant="outline"
                    class="h-5 border-0 px-1.5 text-[11px] tabular-nums max-[639px]:px-1 max-[639px]:text-[10px] sm:h-6 sm:px-2 sm:text-xs"
                    :class="selectedCategory === null ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'"
                >
                    {{ items.length }}
                </Badge>
            </button>
            <button
                v-for="category in categories"
                :key="category.id"
                type="button"
                class="inline-flex h-9 max-w-full flex-none shrink-0 items-center gap-2 whitespace-nowrap rounded-full border px-3 text-[11px] font-semibold transition-[background-color,border-color,color,transform] duration-150 active:scale-[0.98] max-[639px]:px-2.5 sm:h-10 sm:px-4 sm:text-sm"
                :class="selectedCategory === category.id ? 'border-blue-600 bg-blue-600 text-white shadow-sm ring-1 ring-blue-500/20' : 'border-slate-200 bg-white text-slate-700 shadow-sm hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700'"
                :aria-pressed="selectedCategory === category.id"
                @click="emit('update:selectedCategory', category.id)"
            >
                <span class="max-w-[20rem] overflow-hidden text-ellipsis whitespace-nowrap">{{ category.name }}</span>
                <Badge
                    variant="outline"
                    class="h-5 border-0 px-1.5 text-[11px] tabular-nums max-[639px]:px-1 max-[639px]:text-[10px] sm:h-6 sm:px-2 sm:text-xs"
                    :class="selectedCategory === category.id ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'"
                >
                    {{ categoryItemCount(category.id) }}
                </Badge>
            </button>
            <slot name="append" />
        </div>
    </nav>
</template>
