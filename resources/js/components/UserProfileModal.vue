<script setup>
import { computed } from 'vue';
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { Button } from '@/components/ui/button';
import { Heart, History, LogOut, Refrigerator, ShoppingBag, UserRound, X } from 'lucide-vue-next';

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
});

const emit = defineEmits(['close', 'logout', 'show-favorites', 'show-order', 'show-fridge', 'show-history']);

const displayName = computed(() => {
    return props.user?.name || props.user?.full_name || props.user?.first_name || props.user?.email || 'Пользователь';
});

const identifier = computed(() => props.user?.email || props.user?.phone || props.user?.telegram_id || '');

const closeWhenChanged = (open) => {
    if (!open) {
        emit('close');
    }
};
</script>

<template>
    <DialogRoot :open="open" @update:open="closeWhenChanged">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-40 bg-slate-950/45" />
            <DialogContent class="fixed left-1/2 top-1/2 z-50 max-h-[calc(100dvh-2rem)] w-[min(calc(100%_-_2rem),30rem)] -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-3xl border border-slate-200 bg-white p-5 text-slate-900 shadow-xl outline-none sm:p-7">
                <DialogClose as-child>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="absolute right-3 top-3 size-11 rounded-xl text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                        aria-label="Закрыть профиль"
                    >
                        <X aria-hidden="true" class="size-5" />
                    </Button>
                </DialogClose>

                <div class="flex items-start gap-4 pr-12">
                    <div class="grid size-14 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-700">
                        <UserRound aria-hidden="true" class="size-7" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <DialogTitle
                            data-testid="profile-name"
                            class="line-clamp-2 break-words text-balance text-xl font-semibold leading-7 text-slate-950"
                            :title="displayName"
                        >
                            {{ displayName }}
                        </DialogTitle>
                        <DialogDescription v-if="identifier" class="mt-1 break-all text-pretty text-sm leading-5 text-slate-500">
                            {{ identifier }}
                        </DialogDescription>
                    </div>
                </div>

                <div class="mt-6 grid gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="h-14 justify-between rounded-xl border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        data-testid="profile-favorites-action"
                        @click="emit('show-favorites')"
                    >
                        <span class="inline-flex items-center gap-3">
                            <Heart aria-hidden="true" class="size-5 text-rose-600" />
                            Избранное
                        </span>
                        <span class="tabular-nums text-slate-500">{{ favoritesCount || '' }}</span>
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-14 justify-start rounded-xl border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        data-testid="profile-order-action"
                        @click="emit('show-order')"
                    >
                        <span class="inline-flex items-center gap-3">
                            <ShoppingBag aria-hidden="true" class="size-5 text-blue-700" />
                            Мой заказ
                        </span>
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-14 justify-start rounded-xl border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        data-testid="profile-fridge-action"
                        @click="emit('show-fridge')"
                    >
                        <Refrigerator aria-hidden="true" class="size-5 text-blue-700" />
                        Холодильник
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-14 justify-start rounded-xl border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        data-testid="profile-history-action"
                        @click="emit('show-history')"
                    >
                        <History aria-hidden="true" class="size-5 text-blue-700" />
                        История питания
                    </Button>
                </div>

                <Button
                    type="button"
                    variant="ghost"
                    class="mt-5 h-12 w-full justify-start rounded-xl px-4 text-sm font-semibold text-rose-700 hover:bg-rose-50 hover:text-rose-800"
                    @click="emit('logout')"
                >
                    <LogOut aria-hidden="true" class="size-5" />
                    Выйти
                </Button>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
