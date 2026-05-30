<script setup>
import { ref, watch } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatPrice } from '@/lib/formatters';
import { Loader2, Minus, Plus, ShoppingBag, UtensilsCrossed, X } from 'lucide-vue-next';

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
    isAuthenticated: {
        type: Boolean,
        default: true,
    },
    showHeading: {
        type: Boolean,
        default: true,
    },
    panelTitle: {
        type: String,
        default: 'Мой заказ',
    },
    statusLine: {
        type: String,
        default: '',
    },
    canEditOrder: {
        type: Boolean,
        default: false,
    },
    canReopenOrder: {
        type: Boolean,
        default: false,
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
    orderSkeletonRows: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['change-quantity', 'reopen-order', 'submit-order', 'open-auth']);
const failedOrderImages = ref(new Set());

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

watch(() => props.menuItemsById, () => {
    failedOrderImages.value = new Set();
});
</script>

<template>
    <div class="flex h-full min-h-0 flex-1 flex-col overflow-hidden px-4 pt-4 xl:px-5 xl:pt-5" :aria-busy="loading || actionLoading">
        <div v-if="showHeading" class="shrink-0">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="text-balance text-xl font-semibold text-slate-950">{{ panelTitle }}</h2>
                    <Skeleton v-if="loading" class="mt-2 h-4 w-20 rounded-md bg-slate-100" />
                </div>
            </div>
        </div>

        <Alert
            v-if="error"
            variant="destructive"
                class="mt-3 shrink-0 rounded-xl border-red-200 bg-red-50 text-red-700"
            role="alert"
            aria-live="assertive"
        >
            <AlertDescription>{{ error }}</AlertDescription>
        </Alert>

        <div v-if="loading" class="scrollbar-none mt-4 min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain pb-4 pr-1">
            <div
                v-for="skeleton in orderSkeletonRows"
                :key="`order-skeleton-${skeleton}`"
                class="space-y-2 rounded-2xl border border-slate-200 bg-white p-3"
            >
                <Skeleton class="h-16 w-full rounded-xl bg-slate-100" />
                <Skeleton class="h-5 w-3/4 rounded-md bg-slate-100" />
                <Skeleton class="h-4 w-1/2 rounded-md bg-slate-100" />
            </div>
        </div>

        <div
            v-else-if="!order || orderItems.length === 0"
            data-testid="order-panel-empty-state"
            class="flex min-h-0 flex-1 flex-col items-center justify-center px-5 py-8 text-center"
        >
            <ShoppingBag aria-hidden="true" class="size-7 text-slate-300" />
            <p class="mt-3 text-balance text-base font-semibold text-slate-900">Корзина пуста</p>
            <p class="mt-1 text-pretty text-sm leading-6 text-slate-500">Добавьте блюда из каталога.</p>
        </div>

        <div data-testid="order-panel-items-scroll" v-else class="scrollbar-none mt-4 min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain pb-4 pr-1">
            <article
                v-for="item in orderItems"
                :key="item.id"
                class="relative grid grid-cols-[4rem_minmax(0,1fr)] gap-3.5 rounded-2xl bg-white p-3 ring-1 ring-inset ring-slate-100 xl:grid-cols-[4.5rem_minmax(0,1fr)]"
                data-testid="order-panel-item"
            >
                <div data-testid="order-panel-item-image-wrap" class="grid size-16 place-items-center rounded-2xl bg-white xl:size-[4.5rem]">
                    <img
                        v-if="orderItemImage(item)"
                        data-testid="order-panel-item-image"
                        :src="orderItemImage(item)"
                        :alt="item.title_snapshot"
                        class="size-full rounded-2xl object-contain p-1"
                        loading="lazy"
                        decoding="async"
                        @error="markOrderItemImageFailed(item)"
                    />
                    <span v-else data-testid="order-panel-item-image" class="grid size-full place-items-center rounded-2xl bg-white text-slate-400 ring-1 ring-inset ring-slate-100">
                        <UtensilsCrossed aria-hidden="true" class="size-5" />
                    </span>
                </div>

                <div class="min-w-0 self-stretch">
                    <Button
                        v-if="canEditOrder"
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        class="absolute right-2.5 top-2.5 size-8 rounded-full text-slate-400 hover:bg-rose-50 hover:text-rose-700"
                        :disabled="actionLoading"
                        :aria-label="`Удалить блюдо: ${item.title_snapshot}`"
                        @click="emit('change-quantity', item, 0)"
                    >
                        <X aria-hidden="true" class="size-4" />
                    </Button>

                    <p data-testid="order-panel-item-title" class="line-clamp-2 break-words pr-8 text-sm font-semibold leading-5 text-slate-900">{{ item.title_snapshot }}</p>
                    <p data-testid="order-panel-item-weight" class="mt-1 text-xs font-medium tabular-nums text-slate-500">
                        {{ orderItemWeight(item) || 'Порция' }}
                    </p>

                    <div class="mt-3 flex items-center justify-between gap-3">
                        <div v-if="canEditOrder" data-testid="order-panel-item-stepper" class="inline-flex h-9 items-center rounded-full border border-slate-200 bg-white p-0.5">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                class="size-8 rounded-full text-slate-900 hover:bg-blue-50 hover:text-blue-900"
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
                                class="size-8 rounded-full text-blue-700 hover:bg-blue-50 hover:text-blue-900"
                                :disabled="actionLoading"
                                :aria-label="`Увеличить количество: ${item.title_snapshot}`"
                                @click="emit('change-quantity', item, item.quantity + 1)"
                            >
                                <Plus aria-hidden="true" class="size-4" />
                            </Button>
                        </div>
                        <span v-else data-testid="order-panel-item-stepper" class="rounded-full bg-slate-50 px-3 py-1.5 text-sm font-medium tabular-nums text-slate-600">{{ item.quantity }} шт.</span>

                        <p data-testid="order-panel-item-price" class="ml-auto shrink-0 whitespace-nowrap text-base font-semibold tabular-nums text-slate-950">{{ orderItemTotal(item) }}</p>
                    </div>
                </div>
            </article>
        </div>

        <div
            data-testid="order-panel-footer"
            class="safe-cart-footer sticky bottom-0 z-10 -mx-4 mt-auto shrink-0 border-t border-slate-200 bg-white px-4 pb-4 pt-3 xl:-mx-5 xl:px-5"
        >
            <Skeleton v-if="loading" class="h-12 w-full rounded-xl bg-slate-100" />
            <template v-else>
                <div class="rounded-2xl bg-slate-50 px-4 py-3">
                    <div class="flex items-center justify-between gap-4">
                        <p class="text-sm font-medium text-slate-600">Итого</p>
                        <strong class="whitespace-nowrap text-xl font-semibold tabular-nums text-slate-950">
                            {{ formatPrice(order?.total_price ?? 0) }}
                        </strong>
                    </div>
                </div>

                <Button
                    v-if="canEditOrder && orderItems.length"
                    type="button"
                    class="mt-3 h-[3.25rem] min-h-12 w-full rounded-full bg-blue-700 text-sm font-semibold text-white shadow-sm transition-[background-color,transform] duration-150 hover:bg-blue-800 active:scale-[0.98] disabled:bg-slate-200 disabled:text-slate-500"
                    :disabled="actionLoading"
                    @click="emit('submit-order')"
                >
                    <Loader2 v-if="actionLoading" aria-hidden="true" class="size-4 animate-spin" />
                    Оформить заказ
                </Button>

                <Button
                    v-else-if="canReopenOrder"
                    type="button"
                    variant="outline"
                    class="mt-3 h-12 w-full rounded-full border-blue-200 bg-white px-4 text-sm font-semibold text-blue-800 shadow-sm transition-[background-color,transform] duration-150 hover:bg-blue-50 active:scale-[0.98]"
                    :disabled="actionLoading"
                    @click="emit('reopen-order')"
                >
                    <Loader2 v-if="actionLoading" aria-hidden="true" class="size-4 animate-spin" />
                    Редактировать заказ
                </Button>
            </template>
        </div>
    </div>
</template>
