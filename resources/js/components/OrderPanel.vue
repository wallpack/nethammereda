<script setup>
import { ref, watch } from 'vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatCartPrice } from '@/lib/formatters';
import { menuItemDisplayMeta, menuItemDisplayTitle } from '@/lib/menuDisplay';
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
    compactCart: {
        type: Boolean,
        default: false,
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
const orderItemTitle = (orderItem) => menuItemDisplayTitle({ title: orderItem.title_snapshot });
const orderItemWeight = (orderItem) => menuItemDisplayMeta(props.menuItemsById.get(orderItem.menu_item_id)) ?? null;
const orderItemTotal = (orderItem) => formatCartPrice(Number(orderItem.price_snapshot) * Number(orderItem.quantity));

watch(() => props.menuItemsById, () => {
    failedOrderImages.value = new Set();
});
</script>

<template>
    <div
        :class="[
            'flex h-full min-h-0 flex-1 flex-col overflow-hidden px-4 pt-4 xl:px-5 xl:pt-5',
            compactCart ? 'cart-panel-compact' : '',
        ]"
        :aria-busy="loading || actionLoading"
    >
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

        <div
            data-testid="order-panel-items-scroll"
            v-else
            :class="[
                'scrollbar-none min-h-0 flex-1 overflow-y-auto overscroll-contain pr-1',
                compactCart ? 'mt-2.5 space-y-2.5 pb-[9rem]' : 'mt-4 space-y-3 pb-4',
            ]"
        >
            <article
                v-for="item in orderItems"
                :key="item.id"
                :class="[
                    'relative grid bg-white',
                    compactCart
                        ? 'min-h-[6.625rem] grid-cols-[5.1875rem_minmax(0,1fr)] gap-3 py-2.5 pr-1'
                        : 'grid-cols-[4rem_minmax(0,1fr)] gap-3.5 rounded-2xl p-3 ring-1 ring-inset ring-slate-100 xl:grid-cols-[4.5rem_minmax(0,1fr)]',
                ]"
                data-testid="order-panel-item"
            >
                <div
                    data-testid="order-panel-item-image-wrap"
                    :class="[
                        'grid place-items-center',
                        compactCart
                            ? 'size-[5.1875rem] overflow-hidden rounded-[1rem] bg-slate-50'
                            : 'size-16 rounded-2xl bg-white xl:size-[4.5rem]',
                    ]"
                >
                    <img
                        v-if="orderItemImage(item)"
                        data-testid="order-panel-item-image"
                        :src="orderItemImage(item)"
                        :alt="orderItemTitle(item)"
                        :class="[
                            'size-full object-contain',
                            compactCart ? 'rounded-[1rem] p-1' : 'rounded-2xl p-1',
                        ]"
                        loading="lazy"
                        decoding="async"
                        @error="markOrderItemImageFailed(item)"
                    />
                    <span
                        v-else
                        data-testid="order-panel-item-image"
                        :class="[
                            'grid size-full place-items-center text-slate-400',
                            compactCart
                                ? 'rounded-[1rem] bg-slate-100'
                                : 'rounded-2xl bg-white ring-1 ring-inset ring-slate-100',
                        ]"
                    >
                        <UtensilsCrossed aria-hidden="true" class="size-5" />
                    </span>
                </div>

                <div :class="['min-w-0 self-stretch', compactCart ? 'grid min-h-[5.1875rem] content-start pt-0.5' : '']">
                    <Button
                        v-if="canEditOrder"
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        :class="[
                            'absolute rounded-full text-slate-400 hover:bg-rose-50 hover:text-rose-700',
                            compactCart ? 'right-0 top-0 size-7' : 'right-2.5 top-2.5 size-8',
                        ]"
                        :disabled="actionLoading"
                        :aria-label="`Удалить блюдо: ${orderItemTitle(item)}`"
                        @click="emit('change-quantity', item, 0)"
                    >
                        <X aria-hidden="true" :class="compactCart ? 'size-3.5' : 'size-4'" />
                    </Button>

                    <p
                        data-testid="order-panel-item-title"
                        :class="[
                            'line-clamp-2 break-words',
                            compactCart ? 'pr-7 text-[14px] font-semibold leading-4 tracking-normal text-[#595959]' : 'pr-8 text-sm font-semibold leading-5 text-slate-900',
                        ]"
                    >{{ orderItemTitle(item) }}</p>
                    <p
                        data-testid="order-panel-item-weight"
                        :class="[
                            'tabular-nums',
                            compactCart ? 'mt-px text-[13px] font-semibold leading-[15px] text-[#a6a6a6]' : 'mt-1 text-xs font-medium text-slate-500',
                        ]"
                    >
                        {{ orderItemWeight(item) || 'Порция' }}
                    </p>

                    <div
                        data-testid="order-panel-item-actions"
                        :class="[
                            compactCart
                                ? 'mt-1.5 grid grid-cols-[auto_1fr_auto] items-center gap-2.5'
                                : 'mt-3 flex items-center justify-between gap-3',
                        ]"
                    >
                        <div
                            v-if="canEditOrder"
                            data-testid="order-panel-item-stepper"
                            :class="[
                                compactCart
                                    ? 'grid h-6 w-[4.875rem] grid-cols-[1.5rem_1.875rem_1.5rem] items-center rounded-full bg-blue-700 p-0'
                                    : 'inline-flex h-9 items-center rounded-full border border-blue-700 bg-blue-700 p-0.5 text-white',
                            ]"
                        >
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :class="[
                                    'rounded-full',
                                    compactCart
                                        ? 'size-[1.375rem] justify-self-start text-white/85 hover:bg-blue-600 hover:text-white'
                                        : 'size-8 text-white/85 hover:bg-blue-600 hover:text-white',
                                ]"
                                :disabled="actionLoading"
                                :aria-label="`Уменьшить количество: ${orderItemTitle(item)}`"
                                @click="emit('change-quantity', item, item.quantity - 1)"
                            >
                                <Minus aria-hidden="true" class="size-4" />
                            </Button>
                            <span :class="['text-center tabular-nums', compactCart ? 'min-w-7 justify-self-center text-[14px] font-semibold leading-none text-white' : 'min-w-7 text-sm font-semibold text-white']">{{ item.quantity }}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :class="[
                                    'rounded-full',
                                    compactCart ? 'size-[1.375rem] justify-self-end text-white hover:bg-blue-600 hover:text-white' : 'size-8 text-white hover:bg-blue-600 hover:text-white',
                                ]"
                                :disabled="actionLoading"
                                :aria-label="`Увеличить количество: ${orderItemTitle(item)}`"
                                @click="emit('change-quantity', item, item.quantity + 1)"
                            >
                                <Plus aria-hidden="true" class="size-4" />
                            </Button>
                        </div>
                        <span
                            v-else
                            data-testid="order-panel-item-stepper"
                            :class="[
                                'rounded-full font-medium tabular-nums text-slate-600',
                                compactCart ? 'h-6 bg-blue-700 px-3 text-[13px] leading-6 text-white' : 'bg-blue-700 px-3 py-1.5 text-sm text-white',
                            ]"
                        >{{ item.quantity }} шт.</span>

                        <p
                            data-testid="order-panel-item-price"
                            :class="[
                                'shrink-0 whitespace-nowrap tabular-nums',
                                compactCart ? 'col-start-3 justify-self-end text-[16px] font-semibold leading-[18px] text-[#404040]' : 'ml-auto text-base font-semibold text-slate-950',
                            ]"
                        >{{ orderItemTotal(item) }}</p>
                    </div>
                </div>
            </article>
        </div>

        <div
            data-testid="order-panel-footer"
            :class="[
                'safe-cart-footer sticky bottom-0 z-10 -mx-4 mt-auto shrink-0 bg-white px-4 xl:-mx-5 xl:px-5',
                compactCart ? 'cart-panel-footer-overlay -mt-7 px-5 pb-[1.125rem] pt-4' : 'border-t border-slate-200 pb-4 pt-3',
            ]"
        >
            <Skeleton v-if="loading" class="h-12 w-full rounded-xl bg-slate-100" />
            <template v-else>
                <div :class="compactCart ? 'text-center' : 'rounded-2xl bg-slate-50 px-4 py-3'">
                    <div :class="['gap-1', compactCart ? 'grid justify-items-center' : 'flex items-center justify-between']">
                        <p
                            data-testid="order-panel-total-label"
                            :class="compactCart ? 'text-center text-[12px] font-semibold leading-4 text-[#a0a0a0]' : 'text-sm font-medium text-slate-600'"
                        >Итого</p>
                        <strong
                            data-testid="order-panel-total-price"
                            :class="[
                                'whitespace-nowrap font-bold tabular-nums',
                                compactCart ? 'block text-center text-[32px] leading-[36px] text-[#50545a]' : 'text-xl text-slate-950',
                            ]"
                        >
                            {{ formatCartPrice(order?.total_price ?? 0) }}
                        </strong>
                    </div>
                </div>

                <Button
                    v-if="canEditOrder && orderItems.length"
                    type="button"
                    :class="[
                        'w-full bg-blue-700 font-semibold text-white shadow-sm transition-[background-color,transform] duration-150 hover:bg-blue-800 active:scale-[0.98] disabled:bg-slate-200 disabled:text-slate-500',
                        compactCart
                            ? 'mt-4 h-[3.625rem] min-h-[3.625rem] rounded-full text-[15.5px] font-bold'
                            : 'mt-3 h-[3.25rem] min-h-12 rounded-full text-sm',
                    ]"
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
                    :class="[
                        'w-full border-blue-200 bg-white px-4 text-sm font-semibold text-blue-800 shadow-sm transition-[background-color,transform] duration-150 hover:bg-blue-50 active:scale-[0.98]',
                        compactCart ? 'mt-4 h-12 rounded-[1.25rem]' : 'mt-3 h-12 rounded-full',
                    ]"
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
