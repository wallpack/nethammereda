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
    <header class="sticky top-0 z-30 bg-transparent pt-2 max-sm:pt-1.5">
        <div
            data-testid="header-surface"
            class="header-inner flex min-h-[4.5rem] items-center gap-3 rounded-[1.5rem] border border-slate-200/60 bg-white px-3 py-2.5 shadow-sm md:min-h-[4.875rem] md:gap-5 md:px-4 max-sm:rounded-[1.25rem]"
        >
            <div class="flex min-w-[10rem] shrink-0 items-center sm:min-w-[12.5rem] xl:min-w-[14.5rem]">
                <button
                    type="button"
                    class="group -ml-1 min-w-0 rounded-2xl px-1 py-1 text-left outline-none transition-[background-color,transform] duration-150 hover:bg-[#f2f2f2] active:scale-[0.99] focus-visible:ring-3 focus-visible:ring-blue-600/15"
                    aria-label="Вернуться в каталог"
                    @click="emit('navigate', 'catalog')"
                >
                    <BrandLogo />
                    <p class="-mt-0.5 hidden truncate text-[11px] font-medium text-slate-500 lg:block">
                        корпоративное питание
                    </p>
                </button>
            </div>

            <label class="relative mx-auto hidden min-w-0 flex-1 md:block md:max-w-[66rem]">
                <span class="sr-only">Искать в меню</span>
                <Search aria-hidden="true" data-testid="global-search-icon" class="pointer-events-none absolute left-4 top-1/2 size-4 -translate-y-1/2 text-slate-400" />
                <Input
                    id="global-menu-search"
                    v-model="searchModel"
                    type="search"
                    placeholder="Искать в меню"
                    class="h-12 rounded-full border-transparent bg-[#f2f2f2] pl-11 pr-6 text-[0.95rem] font-medium text-[#404040] shadow-none placeholder:text-slate-400 focus-visible:border-blue-300 focus-visible:bg-[#f2f2f2] focus-visible:ring-blue-600/15"
                />
            </label>

            <div
                data-testid="header-account-actions"
                class="header-account-actions ml-auto flex shrink-0 items-center justify-end gap-2.5"
            >
                <div v-if="loading" data-testid="header-auth-loading" class="flex w-full shrink-0 items-center gap-2" aria-label="Загрузка профиля" aria-busy="true">
                    <Skeleton class="hidden h-14 flex-1 rounded-full bg-[#f2f2f2] sm:block" />
                    <Skeleton class="size-12 rounded-full bg-[#f2f2f2] sm:hidden" />
                </div>

                <Button
                    v-else-if="isAuthenticated"
                    type="button"
                    variant="outline"
                    class="hidden h-14 flex-1 min-w-0 shrink justify-center rounded-full border-transparent bg-[#f2f2f2] px-5 text-[#595959] shadow-none transition-[background-color,border-color,transform] duration-150 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-900 active:scale-[0.98] sm:inline-flex"
                    :aria-label="`Открыть профиль: ${displayUserName}`"
                    :title="displayUserName"
                    @click="emit('open-profile')"
                >
                    <UserRound aria-hidden="true" class="size-4 shrink-0 text-slate-500" />
                    <span class="min-w-0 truncate text-center text-sm font-semibold">{{ displayUserName }}</span>
                </Button>

                <Button
                    v-if="isAuthenticated && !loading"
                    type="button"
                    variant="outline"
                    class="size-12 shrink-0 rounded-full border-transparent bg-[#f2f2f2] px-0 text-[#595959] shadow-none transition-[background-color,border-color,transform] duration-150 hover:border-blue-200 hover:bg-blue-50 hover:text-blue-900 active:scale-[0.98] sm:hidden"
                    :aria-label="`Открыть профиль: ${displayUserName}`"
                    :title="displayUserName"
                    @click="emit('open-profile')"
                >
                    <UserRound aria-hidden="true" class="size-4 text-slate-500" />
                </Button>

                <Button
                    v-if="!isAuthenticated && !loading"
                    type="button"
                    class="h-14 flex-1 min-w-0 shrink rounded-full bg-[#f2f2f2] px-6 text-sm font-semibold text-[#404040] shadow-none transition-[background-color,color,transform] duration-150 hover:bg-blue-50 hover:text-blue-900 active:scale-[0.98] max-sm:h-12 max-sm:px-4"
                    @click="emit('open-auth')"
                >
                    <UserRound aria-hidden="true" class="size-4 text-slate-500" />
                    Войти
                </Button>
            </div>
        </div>
    </header>
</template>
