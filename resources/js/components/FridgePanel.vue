<script setup>
import {
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogOverlay,
    AlertDialogPortal,
    AlertDialogRoot,
    AlertDialogTitle,
    AlertDialogTrigger,
} from 'reka-ui';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { expiryLabel, fridgeStatusLabel } from '@/lib/formatters';
import { Refrigerator } from 'lucide-vue-next';

defineProps({
    fridgeItems: {
        type: Array,
        default: () => [],
    },
    fridgeLoading: {
        type: Boolean,
        default: false,
    },
    actionLoading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    activeFridgeItemsCount: {
        type: Number,
        default: 0,
    },
    showHeading: {
        type: Boolean,
        default: true,
    },
    orderSkeletonRows: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['eat-one', 'eat-all', 'discard']);
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden px-4 pt-4 sm:px-5 sm:pt-5" :aria-busy="fridgeLoading || actionLoading">
        <div v-if="showHeading" class="flex shrink-0 items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-lg font-semibold text-slate-950">Холодильник</h2>
                <p class="mt-0.5 text-pretty text-sm text-slate-500">Доставленные блюда, доступные сейчас</p>
            </div>
            <Skeleton v-if="fridgeLoading" class="h-7 w-14 rounded-lg bg-slate-100" />
            <Badge v-else variant="outline" class="shrink-0 rounded-lg border-blue-100 bg-blue-50 text-xs font-medium tabular-nums text-blue-700">
                {{ activeFridgeItemsCount }} шт.
            </Badge>
        </div>

        <Alert
            v-if="error"
            variant="destructive"
            class="mt-4 shrink-0 rounded-xl border-red-200 bg-red-50 text-red-700"
            role="alert"
            aria-live="assertive"
        >
            <AlertDescription>{{ error }}</AlertDescription>
        </Alert>

        <div v-if="fridgeLoading" class="mt-4 min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain pb-5 pr-1">
            <div
                v-for="skeleton in orderSkeletonRows"
                :key="`fridge-skeleton-${skeleton}`"
                class="space-y-2 rounded-2xl border border-slate-200 bg-white p-3"
            >
                <Skeleton class="h-5 w-3/4 rounded-md bg-slate-100" />
                <Skeleton class="h-4 w-1/2 rounded-md bg-slate-100" />
                <Skeleton class="h-10 w-full rounded-md bg-slate-100" />
            </div>
        </div>

        <div
            v-else-if="fridgeItems.length === 0"
            class="mt-5 flex min-h-0 flex-1 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center"
        >
            <Refrigerator aria-hidden="true" class="size-7 text-slate-300" />
            <p class="mt-3 text-balance text-base font-semibold text-slate-900">В холодильнике пока ничего нет.</p>
            <p class="mt-1 text-pretty text-sm leading-6 text-slate-500">Оформите заказ на текущую неделю. После доставки блюда появятся здесь автоматически.</p>
        </div>

        <div v-else class="mt-4 min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain pb-5 pr-1">
            <article
                v-for="item in fridgeItems"
                :key="item.id"
                class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="line-clamp-2 break-words text-sm font-semibold leading-5 text-slate-950">{{ item.title_snapshot }}</p>
                        <p class="mt-1 text-xs font-medium tabular-nums text-slate-500">
                            {{ fridgeStatusLabel(item.status) }} · остаток {{ item.quantity_remaining }}/{{ item.quantity_total }}
                        </p>
                    </div>
                    <Badge variant="outline" class="shrink-0 rounded-lg border-slate-200 bg-slate-50 text-xs font-medium tabular-nums text-slate-600">
                        {{ item.quantity_remaining }} шт.
                    </Badge>
                </div>
                <p class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-xs font-medium tabular-nums text-slate-600">{{ expiryLabel(item.expires_at) }}</p>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-10 rounded-xl border-slate-200 bg-white text-xs font-semibold text-slate-700 transition-[background-color,transform] duration-150 hover:bg-slate-50 active:scale-[0.98]"
                        :disabled="actionLoading"
                        @click="emit('eat-one', item.id)"
                    >
                        Съел 1
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-10 rounded-xl border-slate-200 bg-white text-xs font-semibold text-slate-700 transition-[background-color,transform] duration-150 hover:bg-slate-50 active:scale-[0.98]"
                        :disabled="actionLoading"
                        @click="emit('eat-all', item.id)"
                    >
                        Съел всё
                    </Button>
                    <AlertDialogRoot>
                        <AlertDialogTrigger as-child>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                class="col-span-2 h-10 w-full rounded-xl border-rose-200 bg-white text-xs font-semibold text-rose-700 transition-[background-color,transform] duration-150 hover:bg-rose-50 active:scale-[0.98]"
                                :disabled="actionLoading"
                            >
                                Выбросить
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogPortal>
                            <AlertDialogOverlay class="fixed inset-0 z-40 bg-slate-950/45" />
                            <AlertDialogContent class="fixed left-1/2 top-1/2 z-50 w-[min(calc(100%_-_2rem),420px)] -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-slate-200 bg-white p-5 text-slate-900 shadow-xl outline-none">
                                <AlertDialogTitle class="text-balance text-lg font-semibold text-slate-950">
                                    Выбросить блюдо?
                                </AlertDialogTitle>
                                <AlertDialogDescription class="mt-2 text-pretty text-sm leading-6 text-slate-600">
                                    «{{ item.title_snapshot }}» исчезнет из активного холодильника. Действие нельзя отменить.
                                </AlertDialogDescription>
                                <div class="mt-5 flex justify-end gap-2">
                                    <AlertDialogCancel as-child>
                                        <Button type="button" variant="outline" class="h-11 rounded-xl border-slate-200 px-4 font-semibold">
                                            Отмена
                                        </Button>
                                    </AlertDialogCancel>
                                    <AlertDialogAction as-child>
                                        <Button
                                            type="button"
                                            class="h-11 rounded-xl bg-rose-700 px-4 font-semibold text-white transition-[background-color,transform] duration-150 hover:bg-rose-800 active:scale-[0.98]"
                                            :disabled="actionLoading"
                                            @click="emit('discard', item.id)"
                                        >
                                            Подтвердить выброс
                                        </Button>
                                    </AlertDialogAction>
                                </div>
                            </AlertDialogContent>
                        </AlertDialogPortal>
                    </AlertDialogRoot>
                </div>
            </article>
        </div>
    </div>
</template>
