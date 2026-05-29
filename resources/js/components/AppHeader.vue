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

const navItems = [
    { key: 'catalog', label: 'Каталог' },
    { key: 'fridge', label: 'Холодильник' },
    { key: 'history', label: 'История' },
];

const navItemLabel = (item) => {
    if (item.key === 'fridge' && props.activeFridgeItemsCount) {
        return `Холодильник · ${props.activeFridgeItemsCount}`;
    }

    return item.label;
};

const navItemClass = (key) => (
    props.activeView === key
        ? 'bg-blue-800 text-white shadow-[0_8px_22px_rgb(30_64_175/0.33)]'
        : 'text-slate-700 hover:bg-blue-50 hover:text-blue-800'
);
</script>

<template>
    <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-[#fcfaf6]/95 backdrop-blur">
        <div class="header-inner flex min-h-[4.25rem] items-center gap-3.5 py-2.5">
            <div class="flex min-w-0 items-center gap-2 xl:gap-4">
                <div class="min-w-0">
                    <BrandLogo />
                    <p class="-mt-0.5 hidden truncate text-[11px] font-medium uppercase tracking-[0.14em] text-slate-400 lg:block">
                        корпоративное питание
                    </p>
                </div>

                <nav
                    v-if="isAuthenticated && !loading"
                    class="hidden items-center gap-2 xl:flex"
                    aria-label="Разделы приложения"
                >
                    <button
                        v-for="item in navItems"
                        :key="item.key"
                        type="button"
                        class="inline-flex h-10 items-center rounded-full px-4.5 text-[15px] font-semibold tracking-[-0.01em] transition-[background-color,color,box-shadow,transform] duration-150 active:scale-[0.98]"
                        :class="navItemClass(item.key)"
                        :aria-current="activeView === item.key ? 'page' : undefined"
                        :aria-label="`Открыть раздел: ${item.label}`"
                        @click="emit('navigate', item.key)"
                    >
                        {{ navItemLabel(item) }}
                    </button>
                </nav>
            </div>

            <label class="relative hidden min-w-0 flex-1 md:block md:max-w-sm lg:max-w-md">
                <span class="sr-only">Найти блюдо</span>
                <Search aria-hidden="true" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <Input
                    v-model="searchModel"
                    type="search"
                    placeholder="Поиск по меню"
                    class="h-10 rounded-full border-slate-200 bg-white pl-9 pr-4 text-sm text-slate-900 shadow-none placeholder:text-slate-400 focus-visible:border-blue-400 focus-visible:bg-white focus-visible:ring-blue-300/25"
                />
            </label>

            <div v-if="loading" data-testid="header-auth-loading" class="ml-auto flex items-center gap-2" aria-label="Загрузка профиля" aria-busy="true">
                <Skeleton class="hidden h-10 w-28 rounded-full bg-slate-100 xl:block" />
                <Skeleton class="size-10 rounded-full bg-slate-100" />
            </div>

            <Button
                v-else-if="isAuthenticated"
                type="button"
                variant="outline"
                class="ml-auto hidden h-10 w-56 min-w-0 max-w-64 justify-center rounded-full border-slate-200 bg-white px-3 text-slate-700 shadow-none transition-[background-color,border-color,transform] duration-150 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-900 active:scale-[0.98] xl:inline-flex"
                :aria-label="`Открыть профиль: ${displayUserName}`"
                :title="displayUserName"
                @click="emit('open-profile')"
            >
                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-600">
                    <UserRound aria-hidden="true" class="size-4" />
                </span>
                <span class="min-w-0 truncate text-center text-sm font-semibold">{{ displayUserName }}</span>
            </Button>

            <Button
                v-if="isAuthenticated && !loading"
                type="button"
                variant="outline"
                class="ml-auto size-10 shrink-0 rounded-full border-slate-200 bg-white px-0 text-slate-700 shadow-none transition-[background-color,border-color,transform] duration-150 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-900 active:scale-[0.98] xl:hidden"
                :aria-label="`Открыть профиль: ${displayUserName}`"
                :title="displayUserName"
                @click="emit('open-profile')"
            >
                <span class="grid size-8 place-items-center rounded-full bg-slate-100 text-slate-600">
                    <UserRound aria-hidden="true" class="size-4" />
                </span>
            </Button>

            <Button
                v-if="!isAuthenticated && !loading"
                type="button"
                class="ml-auto h-10 rounded-full bg-blue-700 px-4 text-sm font-semibold text-white shadow-sm transition-[background-color,transform] duration-150 hover:bg-blue-800 active:scale-[0.98]"
                @click="emit('open-auth')"
            >
                <UserRound aria-hidden="true" class="size-4" />
                Войти
            </Button>
        </div>
    </header>
</template>
