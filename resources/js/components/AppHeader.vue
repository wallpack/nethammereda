<script setup>
import { computed } from 'vue';
import BrandLogo from '@/components/BrandLogo.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Search, UserRound } from 'lucide-vue-next';

const props = defineProps({
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
    search: {
        type: String,
        default: '',
    },
    activeView: {
        type: String,
        default: 'catalog',
    },
});

const emit = defineEmits(['open-auth', 'open-profile', 'navigate', 'update:search']);

const searchModel = computed({
    get: () => props.search,
    set: (value) => emit('update:search', value),
});
</script>

<template>
    <header class="sticky top-0 z-30 border-b border-slate-200/70 bg-white">
        <div class="header-inner flex min-h-[4.5rem] items-center gap-3 py-2.5 md:gap-4">
            <div class="flex min-w-[9.5rem] shrink-0 items-center sm:min-w-[12rem] xl:min-w-[13.5rem]">
                <div class="min-w-0">
                    <BrandLogo />
                    <p class="-mt-0.5 hidden truncate text-[11px] font-medium text-slate-500 lg:block">
                        корпоративное питание
                    </p>
                </div>
            </div>

            <label class="relative mx-auto hidden min-w-0 flex-1 md:block md:max-w-[46rem]">
                <span class="sr-only">Поиск по меню</span>
                <Search aria-hidden="true" class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-slate-400" />
                <Input
                    id="global-menu-search"
                    v-model="searchModel"
                    type="search"
                    placeholder="Поиск по меню"
                    class="h-12 rounded-full border-transparent bg-[#f2f2f2] pl-11 pr-5 text-base font-medium text-slate-950 shadow-none placeholder:text-slate-500 focus-visible:border-blue-300 focus-visible:bg-[#f2f2f2] focus-visible:ring-blue-600/15"
                />
            </label>

            <div v-if="loading" data-testid="header-auth-loading" class="ml-auto flex shrink-0 items-center gap-2" aria-label="Загрузка профиля" aria-busy="true">
                <Skeleton class="hidden h-12 w-44 rounded-full bg-[#f2f2f2] sm:block" />
                <Skeleton class="size-12 rounded-full bg-[#f2f2f2] sm:hidden" />
            </div>

            <Button
                v-else-if="isAuthenticated"
                type="button"
                variant="outline"
                class="ml-auto hidden h-12 w-56 min-w-0 max-w-64 shrink-0 justify-center rounded-full border-transparent bg-[#f2f2f2] px-3 text-slate-800 shadow-none transition-[background-color,border-color,transform] duration-150 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-900 active:scale-[0.98] sm:inline-flex"
                :aria-label="`Открыть профиль: ${displayUserName}`"
                :title="displayUserName"
                @click="emit('open-profile')"
            >
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-white text-slate-600 shadow-sm">
                    <UserRound aria-hidden="true" class="size-4" />
                </span>
                <span class="min-w-0 truncate text-center text-sm font-semibold">{{ displayUserName }}</span>
            </Button>

            <Button
                v-if="isAuthenticated && !loading"
                type="button"
                variant="outline"
                class="ml-auto size-12 shrink-0 rounded-full border-transparent bg-[#f2f2f2] px-0 text-slate-700 shadow-none transition-[background-color,border-color,transform] duration-150 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-900 active:scale-[0.98] sm:hidden"
                :aria-label="`Открыть профиль: ${displayUserName}`"
                :title="displayUserName"
                @click="emit('open-profile')"
            >
                <span class="grid size-9 place-items-center rounded-full bg-white text-slate-600 shadow-sm">
                    <UserRound aria-hidden="true" class="size-4" />
                </span>
            </Button>

            <Button
                v-if="!isAuthenticated && !loading"
                type="button"
                class="ml-auto h-12 shrink-0 rounded-full bg-blue-700 px-5 text-sm font-semibold text-white shadow-sm transition-[background-color,transform] duration-150 hover:bg-blue-800 active:scale-[0.98]"
                @click="emit('open-auth')"
            >
                <UserRound aria-hidden="true" class="size-4" />
                Войти
            </Button>
        </div>
    </header>
</template>
