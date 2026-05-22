<script setup>
import { Badge } from '@/components/ui/badge';
import {
    Croissant,
    LayoutGrid,
    Salad,
    Soup,
    UtensilsCrossed,
} from 'lucide-vue-next';

const props = defineProps({
    categories: {
        type: Array,
        default: () => [],
    },
    items: {
        type: Array,
        default: () => [],
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
    <aside class="catalog-sidebar lg:sticky lg:top-24 lg:self-start">
        <nav
            class="rounded-[18px] border border-[#e5ebf7] bg-white p-4 shadow-[0_14px_38px_rgba(21,39,75,0.06)]"
            aria-label="Категории блюд"
        >
            <button
                type="button"
                class="flex h-[56px] w-full items-center justify-between rounded-[12px] px-4 text-left text-[15px] font-bold transition"
                :class="selectedCategory === null ? 'bg-[#0f52ff] text-white shadow-[0_12px_24px_rgba(15,82,255,0.24)]' : 'text-[#25314d] hover:bg-[#f4f7ff]'"
                :aria-pressed="selectedCategory === null"
                @click="emit('update:selectedCategory', null)"
            >
                <span class="flex items-center gap-3">
                    <LayoutGrid class="size-5" />
                    Все категории
                </span>
                <Badge
                    variant="outline"
                    class="rounded-[8px] border-0 px-2 text-[12px] font-bold"
                    :class="selectedCategory === null ? 'bg-white/18 text-white' : 'bg-[#f0f4ff] text-[#66769f]'"
                >
                    {{ items.length }}
                </Badge>
            </button>

            <div class="mt-3 space-y-1">
                <button
                    v-for="category in categories"
                    :key="category.id"
                    type="button"
                    class="flex h-[56px] w-full items-center justify-between rounded-[12px] px-4 text-left text-[15px] font-semibold transition"
                    :class="selectedCategory === category.id ? 'bg-[#edf3ff] text-[#0f52ff]' : 'text-[#25314d] hover:bg-[#f7f9fe]'"
                    :aria-pressed="selectedCategory === category.id"
                    @click="emit('update:selectedCategory', category.id)"
                >
                    <span class="flex items-center gap-3">
                        <component
                            :is="categoryIcon(category.name)"
                            class="size-5"
                            :class="selectedCategory === category.id ? 'text-[#0f52ff]' : 'text-[#60719a]'"
                        />
                        {{ category.name }}
                    </span>
                    <Badge
                        variant="outline"
                        class="rounded-[8px] border-0 bg-[#f0f4ff] px-2 text-[12px] font-bold text-[#66769f]"
                    >
                        {{ categoryItemCount(category.id) }}
                    </Badge>
                </button>
            </div>
        </nav>
    </aside>
</template>
