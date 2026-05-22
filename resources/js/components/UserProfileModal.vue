<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Heart, LogOut, Settings, ShoppingCart, User, X } from 'lucide-vue-next';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    user: {
        type: Object,
        default: null,
    },
    favoritesCount: {
        type: Number,
        default: 0,
    },
    ordersCount: {
        type: Number,
        default: 0,
    },
    lastOrderLabel: {
        type: String,
        default: '',
    },
    notice: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'logout', 'show-favorites', 'show-orders', 'show-settings']);

const displayName = computed(() => {
    return props.user?.name || props.user?.full_name || props.user?.first_name || props.user?.email || 'Пользователь';
});

const identifier = computed(() => {
    return props.user?.email || props.user?.phone || props.user?.telegram_id || '';
});

const onKeydown = (event) => {
    if (props.open && event.key === 'Escape') {
        emit('close');
    }
};

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-[100] grid place-items-center bg-slate-950/45 px-4 py-8 backdrop-blur-[2px]"
                role="presentation"
                @click.self="emit('close')"
            >
                <section
                    class="relative w-full max-w-[560px] rounded-[22px] bg-white px-6 pb-7 pt-8 text-slate-900 shadow-[0_28px_80px_rgba(15,23,42,0.22)] sm:px-9"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="profile-modal-title"
                >
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        class="absolute right-4 top-4 h-10 w-10 rounded-full border border-[#e5ebf7] bg-white text-[#66769f] shadow-none hover:bg-[#f4f7ff] hover:text-[#111827]"
                        aria-label="Закрыть профиль"
                        @click="emit('close')"
                    >
                        <X class="size-5" />
                    </Button>

                    <div class="flex items-start gap-4 pr-10">
                        <div class="grid h-16 w-16 shrink-0 place-items-center rounded-full bg-[#f1f5ff] text-[#2459d9]">
                            <User class="size-8" />
                        </div>
                        <div class="min-w-0">
                            <h2 id="profile-modal-title" class="truncate text-[28px] font-black leading-tight tracking-[-0.4px] text-[#111827]">
                                {{ displayName }}
                            </h2>
                            <p v-if="identifier" class="mt-1 truncate text-sm font-semibold text-[#66769f]">
                                {{ identifier }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-7 grid gap-3 sm:grid-cols-2">
                        <button
                            type="button"
                            class="rounded-[16px] border border-[#e5ebf7] bg-[#f8faff] p-4 text-left transition hover:border-[#cdd8ef] hover:bg-white"
                            @click="emit('show-favorites')"
                        >
                            <span class="grid h-10 w-10 place-items-center rounded-[12px] bg-white text-rose-500 shadow-[0_8px_18px_rgba(21,39,75,0.07)]">
                                <Heart class="size-5" />
                            </span>
                            <span class="mt-3 block text-base font-black text-[#111827]">Избранное</span>
                            <span class="mt-1 block text-sm font-semibold text-[#66769f]">
                                {{ favoritesCount ? `${favoritesCount} блюд` : 'Подборка любимых блюд' }}
                            </span>
                        </button>

                        <button
                            type="button"
                            class="rounded-[16px] border border-[#e5ebf7] bg-[#f8faff] p-4 text-left transition hover:border-[#cdd8ef] hover:bg-white"
                            @click="emit('show-orders')"
                        >
                            <span class="grid h-10 w-10 place-items-center rounded-[12px] bg-white text-[#2459d9] shadow-[0_8px_18px_rgba(21,39,75,0.07)]">
                                <ShoppingCart class="size-5" />
                            </span>
                            <span class="mt-3 block text-base font-black text-[#111827]">Мои заказы</span>
                            <span class="mt-1 block text-sm font-semibold text-[#66769f]">
                                {{ lastOrderLabel || (ordersCount ? `${ordersCount} активный` : 'История заказов') }}
                            </span>
                        </button>
                    </div>

                    <Badge
                        v-if="notice"
                        variant="outline"
                        class="mt-5 rounded-[10px] border-[#d7e2f7] bg-[#f4f7ff] px-3 py-2 text-sm font-semibold text-[#2459d9]"
                    >
                        {{ notice }}
                    </Badge>

                    <div class="mt-6 overflow-hidden rounded-[16px] border border-[#e5ebf7]">
                        <button
                            type="button"
                            class="flex h-14 w-full items-center justify-between border-b border-[#e5ebf7] bg-white px-4 text-left text-sm font-bold text-[#25314d] transition hover:bg-[#f8faff]"
                            @click="emit('show-favorites')"
                        >
                            <span class="inline-flex items-center gap-3">
                                <Heart class="size-5 text-rose-500" />
                                Избранное
                            </span>
                            <span class="text-[#7080a3]">{{ favoritesCount || '' }}</span>
                        </button>

                        <button
                            type="button"
                            class="flex h-14 w-full items-center justify-between border-b border-[#e5ebf7] bg-white px-4 text-left text-sm font-bold text-[#25314d] transition hover:bg-[#f8faff]"
                            @click="emit('show-orders')"
                        >
                            <span class="inline-flex items-center gap-3">
                                <ShoppingCart class="size-5 text-[#2459d9]" />
                                Мои заказы
                            </span>
                            <span class="text-[#7080a3]">{{ ordersCount || '' }}</span>
                        </button>

                        <button
                            type="button"
                            class="flex h-14 w-full items-center gap-3 border-b border-[#e5ebf7] bg-white px-4 text-left text-sm font-bold text-[#25314d] transition hover:bg-[#f8faff]"
                            @click="emit('show-settings')"
                        >
                            <Settings class="size-5 text-[#7080a3]" />
                            Настройки
                        </button>

                        <button
                            type="button"
                            class="flex h-14 w-full items-center gap-3 bg-white px-4 text-left text-sm font-bold text-rose-600 transition hover:bg-rose-50"
                            @click="emit('logout')"
                        >
                            <LogOut class="size-5" />
                            Выйти
                        </button>
                    </div>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>
