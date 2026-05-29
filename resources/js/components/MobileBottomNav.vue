<script setup>
import { Badge } from '@/components/ui/badge';
import { BookOpen, Refrigerator, ShoppingBag, UserRound } from 'lucide-vue-next';

defineProps({
    activePanel: {
        type: String,
        default: null,
    },
    totalPositions: {
        type: Number,
        default: 0,
    },
    activeFridgeItemsCount: {
        type: Number,
        default: 0,
    },
    isProfileOpen: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['catalog', 'order', 'fridge', 'profile']);
</script>

<template>
    <nav
        class="safe-nav-bottom fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/80 bg-[#fcfaf6]/95 backdrop-blur xl:hidden"
        aria-label="Основная навигация"
    >
        <div class="mx-auto grid max-w-lg grid-cols-4 gap-1 px-2 pt-1.5">
            <button
                type="button"
                class="flex min-h-14 min-w-0 flex-col items-center justify-center gap-1 rounded-xl text-xs font-medium transition-[background-color,color,transform] duration-150 active:scale-[0.98]"
                :class="activePanel === null ? 'bg-blue-50 text-blue-800' : 'text-slate-500'"
                :aria-current="activePanel === null ? 'page' : undefined"
                aria-label="Открыть раздел: Каталог"
                @click="emit('catalog')"
            >
                <BookOpen aria-hidden="true" class="size-5" />
                <span class="max-w-full truncate">Каталог</span>
            </button>
            <button
                type="button"
                class="relative flex min-h-14 min-w-0 flex-col items-center justify-center gap-1 rounded-xl text-xs font-medium transition-[background-color,color,transform] duration-150 active:scale-[0.98]"
                :class="activePanel === 'order' ? 'bg-blue-50 text-blue-800' : 'text-slate-500'"
                :aria-current="activePanel === 'order' ? 'page' : undefined"
                aria-label="Открыть раздел: Корзина"
                @click="emit('order')"
            >
                <ShoppingBag aria-hidden="true" class="size-5" />
                <span class="max-w-full truncate">Корзина</span>
                <Badge
                    v-if="totalPositions"
                    class="absolute right-5 top-0.5 flex size-5 items-center justify-center rounded-full bg-blue-800 px-0 text-[11px] font-semibold tabular-nums text-white"
                >
                    {{ totalPositions }}
                </Badge>
            </button>
            <button
                type="button"
                class="relative flex min-h-14 min-w-0 flex-col items-center justify-center gap-1 rounded-xl text-xs font-medium transition-[background-color,color,transform] duration-150 active:scale-[0.98]"
                :class="activePanel === 'fridge' ? 'bg-blue-50 text-blue-800' : 'text-slate-500'"
                :aria-current="activePanel === 'fridge' ? 'page' : undefined"
                aria-label="Открыть раздел: Холодильник"
                @click="emit('fridge')"
            >
                <Refrigerator aria-hidden="true" class="size-5" />
                <span class="max-w-full truncate">Холодильник</span>
                <Badge
                    v-if="activeFridgeItemsCount"
                    class="absolute right-4 top-0.5 flex size-5 items-center justify-center rounded-full bg-blue-800 px-0 text-[11px] font-semibold tabular-nums text-white"
                >
                    {{ activeFridgeItemsCount }}
                </Badge>
            </button>
            <button
                type="button"
                class="flex min-h-14 min-w-0 flex-col items-center justify-center gap-1 rounded-xl text-xs font-medium transition-[background-color,color,transform] duration-150 active:scale-[0.98]"
                :class="isProfileOpen ? 'bg-blue-50 text-blue-800' : 'text-slate-500'"
                :aria-current="isProfileOpen ? 'page' : undefined"
                aria-label="Открыть раздел: Профиль"
                @click="emit('profile')"
            >
                <UserRound aria-hidden="true" class="size-5" />
                <span class="max-w-full truncate">Профиль</span>
            </button>
        </div>
    </nav>
</template>
