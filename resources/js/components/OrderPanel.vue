<script setup>
import { computed, ref, watch } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatPrice, orderStatusLabel } from '@/lib/formatters';
import { CheckCircle2, Loader2, Minus, Plus, ShoppingBag, UtensilsCrossed, X } from 'lucide-vue-next';

const props = defineProps({
    order: {
        type: Object,
        default: null,
    },
    orderItems: {
        type: Array,
        default: () => [],
    },
    menuItemsById: {
        type: Map,
        default: () => new Map(),
    },
    totalPositions: {
        type: Number,
        default: 0,
    },
    showHeading: {
        type: Boolean,
        default: true,
    },
    canEditOrder: {
        type: Boolean,
        default: false,
    },
    canReopenOrder: {
        type: Boolean,
        default: false,
    },
    readOnlyReason: {
        type: String,
        default: '',
    },
    loading: {
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
    weeklyDeadlineLabel: {
        type: String,
        required: true,
    },
    orderSkeletonRows: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['change-quantity', 'reopen-order', 'submit-order']);
const failedOrderImages = ref(new Set());

const isSubmittedOrder = computed(() => props.order?.status === 'submitted');
const orderItemImage = (orderItem) => {
    if (failedOrderImages.value.has(orderItem.menu_item_id)) {
        return null;
    }

    const menuItem = props.menuItemsById.get(orderItem.menu_item_id);

    return menuItem?.image_display_url || menuItem?.image_url || null;
};
const markOrderItemImageFailed = (orderItem) => {
    failedOrderImages.value = new Set([...failedOrderImages.value, orderItem.menu_item_id]);
};
const orderItemWeight = (orderItem) => props.menuItemsById.get(orderItem.menu_item_id)?.weight ?? null;
const orderItemTotal = (orderItem) => formatPrice(Number(orderItem.price_snapshot) * Number(orderItem.quantity));
const positionsLabel = computed(() => {
    const count = props.totalPositions;

    if (count % 10 === 1 && count % 100 !== 11) {
        return 'позиция';
    }

    if ([2, 3, 4].includes(count % 10) && ![12, 13, 14].includes(count % 100)) {
        return 'позиции';
    }

    return 'позиций';
});

watch(() => props.menuItemsById, () => {
    failedOrderImages.value = new Set();
});
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col px-5 pb-5 pt-5" :aria-busy="loading || actionLoading">
        <div v-if="showHeading" class="flex shrink-0 items-center justify-between gap-2">
            <div>
                <h2 class="text-lg font-semibold text-slate-950">Мой заказ</h2>
                <Skeleton v-if="loading" class="mt-2 h-4 w-20 rounded-md bg-slate-100" />
                <p v-else class="mt-0.5 text-sm text-slate-500">
                    <span class="tabular-nums">{{ totalPositions }}</span> {{ positionsLabel }}
                </p>
            </div>
            <Skeleton v-if="loading" class="h-7 w-20 rounded-lg bg-slate-100" />
            <Badge
                v-else
                variant="outline"
                class="rounded-lg text-xs font-medium"
                :class="isSubmittedOrder ? 'border-blue-100 bg-blue-50 text-blue-700' : 'border-slate-200 bg-slate-50 text-slate-600'"
            >
                {{ orderStatusLabel(order?.status ?? 'draft') }}
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

        <Alert
            v-if="!loading && !canEditOrder"
            class="mt-4 shrink-0 rounded-xl"
            :class="isSubmittedOrder ? 'border-slate-200 bg-slate-50 text-slate-700' : 'border-amber-100 bg-amber-50 text-amber-900'"
            role="status"
        >
            <AlertDescription class="space-y-3 text-sm">
                <span class="flex items-start gap-2">
                    <CheckCircle2 v-if="isSubmittedOrder" aria-hidden="true" class="mt-0.5 size-4 shrink-0 text-blue-700" />
                    <span>
                        <span :class="isSubmittedOrder ? 'font-medium' : ''">{{ readOnlyReason }}</span>
                        <span v-if="!isSubmittedOrder" class="mt-1 block text-xs text-amber-800">
                            Дедлайн: {{ weeklyDeadlineLabel }}
                        </span>
                    </span>
                </span>
                <Button
                    v-if="canReopenOrder"
                    type="button"
                    variant="outline"
                    class="h-10 rounded-xl border-blue-200 bg-white px-4 text-sm font-semibold text-blue-700 shadow-sm transition-[background-color,transform] duration-150 hover:bg-blue-50 active:scale-[0.98]"
                    :disabled="actionLoading"
                    @click="emit('reopen-order')"
                >
                    <Loader2 v-if="actionLoading" aria-hidden="true" class="size-4 animate-spin" />
                    Редактировать заказ
                </Button>
            </AlertDescription>
        </Alert>

        <div v-if="loading" class="mt-4 min-h-0 flex-1 space-y-3 overflow-y-auto">
            <div
                v-for="skeleton in orderSkeletonRows"
                :key="`order-skeleton-${skeleton}`"
                class="space-y-2 rounded-xl border border-slate-200 bg-white p-3"
            >
                <Skeleton class="h-5 w-3/4 rounded-md bg-slate-100" />
                <Skeleton class="h-4 w-1/2 rounded-md bg-slate-100" />
                <Skeleton class="h-9 w-24 rounded-md bg-slate-100" />
            </div>
        </div>

        <div
            v-else-if="!order || orderItems.length === 0"
            class="mt-5 flex flex-1 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center"
        >
            <ShoppingBag aria-hidden="true" class="size-7 text-slate-300" />
            <p class="mt-3 text-balance text-base font-semibold text-slate-900">Вы ещё ничего не добавили</p>
            <p class="mt-1 text-pretty text-sm leading-6 text-slate-500">Откройте каталог, чтобы выбрать блюда.</p>
        </div>

        <div v-else class="mt-4 min-h-0 flex-1 divide-y divide-slate-100 overflow-y-auto pr-1">
            <article
                v-for="item in orderItems"
                :key="item.id"
                class="relative grid grid-cols-[64px_minmax(0,1fr)] gap-3 py-3 first:pt-0 last:pb-0"
            >
                <img
                    v-if="orderItemImage(item)"
                    :src="orderItemImage(item)"
                    :alt="item.title_snapshot"
                    class="size-16 rounded-xl border border-slate-100 bg-slate-50 object-contain p-1"
                    loading="lazy"
                    decoding="async"
                    @error="markOrderItemImageFailed(item)"
                />
                <div v-else class="grid size-16 place-items-center rounded-xl border border-slate-200 bg-slate-50 text-slate-400">
                    <UtensilsCrossed aria-hidden="true" class="size-5" />
                </div>

                <div class="min-w-0" :class="canEditOrder ? 'pr-7' : ''">
                    <Button
                        v-if="canEditOrder"
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        class="absolute right-0 top-0 size-9 rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-700"
                        :disabled="actionLoading"
                        :aria-label="`Удалить блюдо: ${item.title_snapshot}`"
                        @click="emit('change-quantity', item, 0)"
                    >
                        <X aria-hidden="true" class="size-4" />
                    </Button>

                    <p class="line-clamp-2 text-sm font-semibold leading-5 text-slate-900">{{ item.title_snapshot }}</p>
                    <p class="mt-1 text-xs font-medium tabular-nums text-slate-500">
                        {{ orderItemWeight(item) || 'Порция' }}
                    </p>

                    <div class="mt-2 flex items-center justify-between gap-3">
                        <p class="text-base font-semibold tabular-nums text-slate-950">{{ orderItemTotal(item) }}</p>

                        <div v-if="canEditOrder" class="inline-flex h-10 items-center rounded-xl border border-slate-200 bg-white p-0.5">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                class="size-9 rounded-lg text-blue-700 hover:bg-blue-50 hover:text-blue-700"
                                :disabled="actionLoading"
                                :aria-label="`Уменьшить количество: ${item.title_snapshot}`"
                                @click="emit('change-quantity', item, item.quantity - 1)"
                            >
                                <Minus aria-hidden="true" class="size-4" />
                            </Button>
                            <span class="min-w-7 text-center text-sm font-semibold tabular-nums text-slate-950">{{ item.quantity }}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                class="size-9 rounded-lg text-blue-700 hover:bg-blue-50 hover:text-blue-700"
                                :disabled="actionLoading"
                                :aria-label="`Увеличить количество: ${item.title_snapshot}`"
                                @click="emit('change-quantity', item, item.quantity + 1)"
                            >
                                <Plus aria-hidden="true" class="size-4" />
                            </Button>
                        </div>
                        <span v-else class="text-sm font-medium tabular-nums text-slate-500">{{ item.quantity }} шт.</span>
                    </div>
                </div>
            </article>
        </div>

        <div v-if="loading" class="mt-5 shrink-0 border-t border-slate-200 pt-4">
            <Skeleton class="h-7 w-full rounded-md bg-slate-100" />
        </div>

        <div v-else class="mt-5 shrink-0 rounded-xl bg-slate-50 px-4 py-3">
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-slate-600">Итого</p>
                <strong class="text-xl font-semibold tabular-nums text-slate-950">{{ formatPrice(order?.total_price ?? 0) }}</strong>
            </div>
        </div>

        <Button
            v-if="!loading && canEditOrder"
            type="button"
            class="mt-4 h-12 w-full rounded-xl bg-blue-700 text-sm font-semibold text-white shadow-sm transition-[background-color,transform] duration-150 hover:bg-blue-800 active:scale-[0.98] disabled:bg-slate-200 disabled:text-slate-500"
            :disabled="!orderItems.length || actionLoading"
            @click="emit('submit-order')"
        >
            <Loader2 v-if="actionLoading" aria-hidden="true" class="size-4 animate-spin" />
            {{ orderItems.length ? 'Оформить заказ' : 'Добавьте блюда' }}
        </Button>

    </div>
</template>
