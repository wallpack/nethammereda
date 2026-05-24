<script setup>
import BrandLogo from '@/components/BrandLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Refrigerator, ShoppingBag, UserRound } from 'lucide-vue-next';

defineProps({
    loading: {
        type: Boolean,
        default: false,
    },
    isAuthenticated: {
        type: Boolean,
        default: false,
    },
    totalPositions: {
        type: Number,
        default: 0,
    },
    activeFridgeItemsCount: {
        type: Number,
        default: 0,
    },
    displayUserName: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['open-auth', 'open-profile', 'open-order', 'open-fridge']);
</script>

<template>
    <header class="sticky top-0 z-30 border-b border-slate-200 bg-white">
        <div class="header-inner flex min-h-16 items-center justify-between gap-3 py-2">
            <BrandLogo />

            <div v-if="loading" data-testid="header-auth-loading" class="flex items-center gap-2" aria-label="Загрузка профиля" aria-busy="true">
                <Skeleton class="hidden h-11 w-28 rounded-xl bg-slate-100 xl:block" />
                <Skeleton class="size-11 rounded-xl bg-slate-100" />
            </div>

            <nav
                v-else-if="isAuthenticated"
                class="hidden min-w-0 shrink-0 items-center gap-2 xl:flex"
                aria-label="Быстрые действия"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="relative h-11 rounded-xl border-slate-200 bg-white px-3 text-slate-700 shadow-none transition-[background-color,border-color,transform] duration-150 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 active:scale-[0.98]"
                    aria-label="Открыть мой заказ"
                    @click="emit('open-order')"
                >
                    <ShoppingBag aria-hidden="true" class="size-5" />
                    <span class="text-sm font-semibold">Мой заказ</span>
                    <Badge
                        v-if="totalPositions"
                        class="ml-1 flex size-5 items-center justify-center rounded-full bg-blue-700 px-0 text-[11px] font-semibold tabular-nums text-white"
                    >
                        {{ totalPositions }}
                    </Badge>
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    class="relative h-11 rounded-xl border-slate-200 bg-white px-3 text-slate-700 shadow-none transition-[background-color,border-color,transform] duration-150 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 active:scale-[0.98]"
                    aria-label="Открыть холодильник"
                    @click="emit('open-fridge')"
                >
                    <Refrigerator aria-hidden="true" class="size-5" />
                    <span class="text-sm font-semibold">Холодильник</span>
                    <Badge
                        v-if="activeFridgeItemsCount"
                        class="ml-1 flex size-5 items-center justify-center rounded-full bg-blue-700 px-0 text-[11px] font-semibold tabular-nums text-white"
                    >
                        {{ activeFridgeItemsCount }}
                    </Badge>
                </Button>

                <Button
                    type="button"
                    variant="outline"
                    class="h-11 w-64 min-w-0 max-w-72 justify-start rounded-xl border-slate-200 bg-white px-1.5 pr-3 text-slate-700 shadow-none transition-[background-color,border-color,transform] duration-150 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 active:scale-[0.98]"
                    :aria-label="`Открыть профиль: ${displayUserName}`"
                    :title="displayUserName"
                    @click="emit('open-profile')"
                >
                    <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-600">
                        <UserRound aria-hidden="true" class="size-4" />
                    </span>
                    <span class="min-w-0 truncate text-left text-sm font-semibold">{{ displayUserName }}</span>
                </Button>
            </nav>

            <Button
                v-if="isAuthenticated && !loading"
                type="button"
                variant="outline"
                class="size-11 shrink-0 rounded-xl border-slate-200 bg-white px-0 text-slate-700 shadow-none transition-[background-color,border-color,transform] duration-150 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 active:scale-[0.98] xl:hidden"
                :aria-label="`Открыть профиль: ${displayUserName}`"
                :title="displayUserName"
                @click="emit('open-profile')"
            >
                <span class="grid size-8 place-items-center rounded-lg bg-slate-100 text-slate-600">
                    <UserRound aria-hidden="true" class="size-4" />
                </span>
            </Button>

            <Button
                v-if="!isAuthenticated && !loading"
                type="button"
                class="h-11 rounded-xl bg-blue-700 px-5 text-sm font-semibold text-white shadow-sm transition-[background-color,transform] duration-150 hover:bg-blue-800 active:scale-[0.98]"
                @click="emit('open-auth')"
            >
                <UserRound aria-hidden="true" class="size-4" />
                Войти
            </Button>
        </div>
    </header>
</template>
