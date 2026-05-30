<script setup>
import { computed } from 'vue';
import {
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
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
import { Refrigerator, UtensilsCrossed } from 'lucide-vue-next';

const props = defineProps({
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
    fridgeMeta: {
        type: Object,
        default: () => ({}),
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

const quantityLabel = (quantity) => `${quantity} шт.`;
const fridgeItemImage = (item) => item.image_display_url
    || item.image_url
    || item.menu_item?.image_display_url
    || item.menu_item?.image_url
    || item.menuItem?.image_display_url
    || item.menuItem?.image_url
    || null;

const summaryCards = computed(() => {
    const totalPortionsFallback = props.fridgeItems.reduce(
        (sum, item) => sum + (Number(item.quantity_remaining) || 0),
        0,
    );
    const eatenToday = props.fridgeMeta?.eaten_today_count;
    const cards = [
        {
            key: 'active_count',
            label: 'В холодильнике',
            value: Number(props.fridgeMeta?.active_count ?? props.activeFridgeItemsCount),
        },
        {
            key: 'total_portions',
            label: 'Порций',
            value: Number(props.fridgeMeta?.total_portions ?? totalPortionsFallback),
        },
        {
            key: 'expiring_soon_count',
            label: 'Скоро истекает',
            value: Number(props.fridgeMeta?.expiring_soon_count ?? 0),
        },
    ];

    if (typeof eatenToday === 'number') {
        cards.push({
            key: 'eaten_today_count',
            label: 'Съедено сегодня',
            value: Number(eatenToday),
        });
    }

    return cards.filter((card) => Number.isFinite(card.value));
});
</script>

<template>
    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden px-4 pt-4 sm:px-5 sm:pt-5" :aria-busy="fridgeLoading || actionLoading">
        <div v-if="showHeading" class="flex shrink-0 items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="customer-heading text-balance text-xl leading-7 sm:text-2xl sm:leading-8">Мой холодильник</h2>
                <p class="customer-muted mt-0.5 text-pretty text-sm leading-5">Блюда, которые сейчас ждут вас.</p>
            </div>
            <Skeleton v-if="fridgeLoading" class="h-7 w-14 rounded-lg bg-slate-100" />
            <Badge v-else variant="outline" class="customer-badge h-auto min-h-6 shrink-0 px-3 py-1 text-xs leading-4">
                {{ activeFridgeItemsCount }} шт.
            </Badge>
        </div>

        <div
            v-if="!fridgeLoading && summaryCards.length"
            class="mt-4 grid shrink-0 grid-cols-2 gap-2.5 sm:grid-cols-3 2xl:grid-cols-4"
            aria-label="Сводка холодильника"
        >
            <div
                v-for="card in summaryCards"
                :key="card.key"
                data-testid="fridge-summary-card"
                class="customer-soft-card px-3.5 py-3"
            >
                <p class="customer-meta text-[11px] leading-4">{{ card.label }}</p>
                <p data-testid="fridge-summary-value" class="customer-stat-number mt-1">{{ card.value }}</p>
            </div>
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
            class="mt-5 flex min-h-0 flex-1 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/80 px-5 py-10 text-center"
        >
            <Refrigerator aria-hidden="true" class="size-7 text-slate-300" />
            <p class="customer-title mt-3 text-balance text-base leading-5">В вашем холодильнике пока ничего нет.</p>
            <p class="customer-muted mt-1 text-pretty text-sm leading-6">Когда заказ будет доставлен, блюда появятся здесь.</p>
        </div>

        <div data-testid="fridge-panel-scroll" v-else class="mt-4 min-h-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-contain pb-5 pr-1">
            <div class="grid gap-3 xl:grid-cols-2">
                <article
                    v-for="item in fridgeItems"
                    :key="item.id"
                    data-testid="fridge-item-card"
                    class="customer-row-card min-w-0 p-3.5 shadow-sm sm:p-4"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <div data-testid="fridge-card-image-wrap" class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-[1rem] bg-slate-50">
                            <img
                                v-if="fridgeItemImage(item)"
                                data-testid="fridge-card-image"
                                :src="fridgeItemImage(item)"
                                :alt="item.title_snapshot"
                                class="size-full rounded-[1rem] object-contain p-1"
                                loading="lazy"
                                decoding="async"
                            />
                            <span
                                v-else
                                data-testid="fridge-card-image-placeholder"
                                class="grid size-full place-items-center rounded-[1rem] bg-slate-100 text-slate-400"
                            >
                                <UtensilsCrossed aria-hidden="true" class="size-5" />
                            </span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p data-testid="fridge-card-title" class="customer-title line-clamp-2 break-words text-pretty text-[15px] leading-5">{{ item.title_snapshot }}</p>
                                </div>
                                <Badge variant="outline" data-testid="fridge-quantity-badge" class="customer-badge h-auto min-h-6 shrink-0 px-2.5 py-1 text-xs leading-4">
                                    {{ quantityLabel(item.quantity_remaining) }}
                                </Badge>
                            </div>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span data-testid="fridge-card-status" class="customer-meta rounded-full bg-slate-50 px-2.5 py-1 text-xs leading-4">
                                    {{ fridgeStatusLabel(item.status) }}
                                </span>
                                <span data-testid="fridge-card-expiry" class="customer-meta rounded-full bg-slate-50 px-2.5 py-1 text-xs leading-4 tabular-nums">
                                    {{ expiryLabel(item.expires_at) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div data-testid="fridge-card-actions" class="mt-4 grid gap-2 sm:grid-cols-[minmax(7.5rem,1fr)_auto_auto]">
                        <Button
                            type="button"
                            size="sm"
                            data-testid="fridge-eat-one-button"
                            class="customer-cta h-11 w-full px-4 text-sm shadow-sm disabled:bg-slate-200 disabled:text-slate-500 sm:h-10"
                            :disabled="actionLoading"
                            @click="emit('eat-one', item.id)"
                        >
                            Съел
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            data-testid="fridge-eat-all-button"
                            class="customer-secondary-action h-10 w-full rounded-full border border-blue-200 px-4 text-sm transition-[background-color,transform] duration-150 active:scale-[0.98]"
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
                                    data-testid="fridge-discard-button"
                                    class="customer-tertiary-action h-10 w-full rounded-full border px-4 text-sm transition-[background-color,border-color,color,transform] duration-150 active:scale-[0.98]"
                                    :disabled="actionLoading"
                                >
                                    Списать
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogPortal>
                                <AlertDialogOverlay class="fixed inset-0 z-40 bg-slate-950/45" />
                                <AlertDialogContent class="fixed left-1/2 top-1/2 z-50 w-[min(calc(100%_-_2rem),420px)] -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-slate-200 bg-white p-5 text-slate-900 shadow-xl outline-none">
                                    <AlertDialogTitle class="text-balance text-lg font-semibold text-slate-950">
                                        Списать блюдо из холодильника?
                                    </AlertDialogTitle>
                                    <div class="mt-5 flex justify-end gap-2">
                                        <AlertDialogCancel as-child>
                                            <Button type="button" variant="outline" class="h-11 rounded-xl border-slate-200 px-4 font-semibold">
                                                Отмена
                                            </Button>
                                        </AlertDialogCancel>
                                        <AlertDialogAction as-child>
                                            <Button
                                                type="button"
                                                class="h-11 rounded-xl bg-slate-900 px-4 font-semibold text-white transition-[background-color,transform] duration-150 hover:bg-slate-800 active:scale-[0.98]"
                                                :disabled="actionLoading"
                                                @click="emit('discard', item.id)"
                                            >
                                                Списать
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
    </div>
</template>
