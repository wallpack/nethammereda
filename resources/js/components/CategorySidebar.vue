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
        class="max-w-full py-1 lg:hidden"
        aria-label="Категории блюд"
    >
        <div v-if="loading" class="grid grid-cols-3 gap-1.5" aria-busy="true">
            <Skeleton v-for="item in 6" :key="`category-pill-${item}`" class="h-9 w-full rounded-full bg-white/80" />
        </div>
        <div v-else class="grid grid-cols-3 gap-1.5">
            <button
                type="button"
                class="inline-flex h-9 w-full min-w-0 items-center justify-between gap-1.5 whitespace-nowrap rounded-full border px-2.5 text-[11px] font-semibold transition-[background-color,border-color,color,transform] duration-150 active:scale-[0.98]"
                :class="selectedCategory === null ? 'border-blue-600 bg-blue-600 text-white shadow-sm ring-1 ring-blue-500/20' : 'border-slate-200 bg-white text-slate-700 shadow-sm hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700'"
                :aria-pressed="selectedCategory === null"
                @click="emit('update:selectedCategory', null)"
            >
                <span
                    class="order-2 rounded-full px-1.5 py-0.5 text-[11px] tabular-nums"
                    :class="selectedCategory === null ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'"
                >
                    {{ items.length }}
                </span>
                <span class="order-1 min-w-0 truncate">Все блюда</span>
            </button>
            <button
                v-for="category in categories"
                :key="category.id"
                type="button"
                class="inline-flex h-9 w-full min-w-0 items-center justify-between gap-1.5 whitespace-nowrap rounded-full border px-2.5 text-[11px] font-semibold transition-[background-color,border-color,color,transform] duration-150 active:scale-[0.98]"
                :class="selectedCategory === category.id ? 'border-blue-600 bg-blue-600 text-white shadow-sm ring-1 ring-blue-500/20' : 'border-slate-200 bg-white text-slate-700 shadow-sm hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700'"
                :aria-pressed="selectedCategory === category.id"
                @click="emit('update:selectedCategory', category.id)"
            >
                <span
                    class="order-2 rounded-full px-1.5 py-0.5 text-[11px] tabular-nums"
                    :class="selectedCategory === category.id ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'"
                >
                    {{ categoryItemCount(category.id) }}
                </span>
                <span class="order-1 min-w-0 truncate">{{ category.name }}</span>
            </button>
        </div>
    </nav>

    <nav
        class="scrollbar-none hidden max-w-full overflow-x-auto py-1 lg:block"
        aria-label="Категории блюд"
    >
        <div v-if="loading" class="flex gap-2" aria-busy="true">
            <Skeleton v-for="item in 5" :key="`desktop-category-pill-${item}`" class="h-10 w-28 shrink-0 rounded-full bg-white/80" />
        </div>
        <div v-else class="flex w-max min-w-full items-center gap-2">
            <button
                type="button"
                class="inline-flex h-10 shrink-0 items-center gap-2 rounded-full border px-4 text-sm font-semibold transition-[background-color,border-color,color,transform] duration-150 active:scale-[0.98]"
                :class="selectedCategory === null ? 'border-blue-600 bg-blue-600 text-white shadow-sm ring-1 ring-blue-500/20' : 'border-slate-200 bg-white text-slate-700 shadow-sm hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700'"
                :aria-pressed="selectedCategory === null"
                @click="emit('update:selectedCategory', null)"
            >
                Все блюда
                <Badge
                    variant="outline"
                    class="border-0 px-2 text-xs tabular-nums"
                    :class="selectedCategory === null ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'"
                >
                    {{ items.length }}
                </Badge>
            </button>
            <button
                v-for="category in categories"
                :key="category.id"
                type="button"
                class="inline-flex h-10 shrink-0 items-center gap-2 rounded-full border px-4 text-sm font-semibold transition-[background-color,border-color,color,transform] duration-150 active:scale-[0.98]"
                :class="selectedCategory === category.id ? 'border-blue-600 bg-blue-600 text-white shadow-sm ring-1 ring-blue-500/20' : 'border-slate-200 bg-white text-slate-700 shadow-sm hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700'"
                :aria-pressed="selectedCategory === category.id"
                @click="emit('update:selectedCategory', category.id)"
            >
                {{ category.name }}
                <Badge
                    variant="outline"
                    class="border-0 px-2 text-xs tabular-nums"
                    :class="selectedCategory === category.id ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600'"
                >
                    {{ categoryItemCount(category.id) }}
                </Badge>
            </button>
        </div>
    </nav>
</template>
