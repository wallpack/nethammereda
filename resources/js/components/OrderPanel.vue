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
    statusDetail: {
        type: String,
        default: '',
    },
    disabledCheckoutLabel: {
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
            <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
                <div class="min-w-0 flex-1">
                    <h2 data-testid="order-panel-heading" class="customer-heading text-balance text-xl leading-7 sm:text-2xl sm:leading-8">{{ panelTitle }}</h2>
                    <p v-if="statusDetail" data-testid="order-cycle-status-detail" class="mt-0.5 truncate text-pretty text-xs font-medium leading-4 text-slate-500">{{ statusDetail }}</p>
                    <p v-if="!statusDetail && !compactCart && panelTitle !== 'Корзина'" class="customer-muted mt-0.5 text-pretty text-sm leading-5">Ваши выбранные блюда.</p>
                    <Skeleton v-if="loading" class="mt-2 h-4 w-20 rounded-md bg-slate-100" />
                </div>
                <span
                    v-if="statusLine"
                    data-testid="order-cycle-status"
                    class="mt-0.5 shrink-0 whitespace-nowrap rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold leading-4 text-slate-600"
                    :title="statusLine"
                    aria-label="Статус приёма заказов"
                    aria-live="polite"
                >{{ statusLine }}</span>
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
            <p class="customer-title mt-3 text-balance text-base leading-5">Корзина пуста</p>
            <p class="customer-muted mt-1 text-pretty text-sm leading-6">Добавьте блюда из каталога.</p>
        </div>

        <div
            data-testid="order-panel-items-scroll"
            v-else
            :class="[
                'scrollbar-none min-h-0 flex-1 overflow-y-auto overscroll-contain pr-1',
                compactCart ? 'mt-2.5 space-y-2.5 pb-[9rem]' : 'mx-auto mt-4 w-full max-w-[46rem] space-y-2 pb-4',
            ]"
        >
            <article
                v-for="item in orderItems"
                :key="item.id"
                :class="[
                    'relative grid bg-white',
                    compactCart
                        ? 'min-h-[6.625rem] grid-cols-[5.1875rem_minmax(0,1fr)] gap-3 py-2.5 pr-1'
                        : 'min-h-[6.25rem] grid-cols-[4.75rem_minmax(0,1fr)] gap-3 py-3 max-[360px]:grid-cols-[4rem_minmax(0,1fr)] max-[360px]:gap-2.5 sm:grid-cols-[5.1875rem_minmax(0,1fr)] sm:gap-3.5',
                ]"
                data-testid="order-panel-item"
            >
                <div
                    data-testid="order-panel-item-image-wrap"
                    :class="[
                        'grid place-items-center',
                        compactCart
                            ? 'size-[5.1875rem] overflow-hidden rounded-[1rem] bg-slate-50'
                            : 'size-[4.75rem] overflow-hidden rounded-[1rem] bg-slate-50 max-[360px]:size-16 sm:size-[5.1875rem]',
                    ]"
                >
                    <img
                        v-if="orderItemImage(item)"
                        data-testid="order-panel-item-image"
                        :src="orderItemImage(item)"
                        :alt="orderItemTitle(item)"
                        :class="[
                            'size-full object-contain',
                            compactCart ? 'rounded-[1rem] p-1' : 'rounded-[1rem] p-1',
                        ]"
                        width="96"
                        height="96"
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
                                : 'rounded-[1rem] bg-slate-100',
                        ]"
                    >
                        <UtensilsCrossed aria-hidden="true" class="size-5" />
                    </span>
                </div>

                <div :class="['min-w-0 self-stretch', compactCart ? 'grid min-h-[5.1875rem] content-start pt-0.5' : 'grid min-h-[4.75rem] content-start pt-0.5 sm:min-h-[5.1875rem]']">
                    <Button
                        v-if="canEditOrder && compactCart"
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        :class="[
                            'absolute rounded-full text-slate-400 hover:bg-rose-50 hover:text-rose-700',
                            compactCart ? 'right-0 top-0 size-7' : 'right-2.5 top-2.5 size-8',
                        ]"
                        data-testid="order-panel-item-remove"
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
                            compactCart ? 'pr-7 text-[14px] font-semibold leading-4 tracking-normal text-[#595959]' : 'pr-0 text-[15px] font-semibold leading-5 text-[#595959]',
                        ]"
                    >{{ orderItemTitle(item) }}</p>
                    <p
                        data-testid="order-panel-item-weight"
                        :class="[
                            'tabular-nums',
                            compactCart ? 'mt-px text-[13px] font-semibold leading-[15px] text-[#737373]' : 'mt-1 text-xs font-semibold text-[#737373]',
                        ]"
                    >
                        {{ orderItemWeight(item) || 'Порция' }}
                    </p>

                    <div
                        data-testid="order-panel-item-actions"
                        :class="[
                            compactCart
                                ? 'mt-1.5 grid grid-cols-[auto_1fr_auto] items-center gap-2.5'
                                : canEditOrder
                                    ? 'mt-3 grid grid-cols-[auto_minmax(0,1fr)_auto_auto] items-center gap-2.5 max-[360px]:gap-1.5'
                                    : 'mt-3 grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-2.5 max-[360px]:gap-1.5',
                        ]"
                    >
                        <div
                            v-if="canEditOrder"
                            data-testid="order-panel-item-stepper"
                            :class="[
                                compactCart
                                    ? 'grid h-7 w-[5.375rem] grid-cols-[1.625rem_2.125rem_1.625rem] items-center rounded-full bg-blue-700 p-0'
                                    : 'grid h-8 w-[5.375rem] grid-cols-[1.625rem_2.125rem_1.625rem] items-center rounded-full bg-blue-700 p-0 text-white',
                            ]"
                        >
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :class="[
                                    'rounded-full',
                                    compactCart
                                        ? 'size-6 justify-self-start text-white/85 hover:bg-blue-600 hover:text-white'
                                        : 'size-7 justify-self-start text-white/85 hover:bg-blue-600 hover:text-white',
                                ]"
                                :disabled="actionLoading"
                                :aria-label="`Уменьшить количество: ${orderItemTitle(item)}`"
                                @click="emit('change-quantity', item, item.quantity - 1)"
                            >
                                <Minus aria-hidden="true" class="size-4" />
                            </Button>
                            <span :class="['text-center tabular-nums', compactCart ? 'min-w-7 justify-self-center text-[14px] font-semibold leading-none text-white' : 'min-w-7 justify-self-center text-sm font-semibold leading-none text-white']">{{ item.quantity }}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :class="[
                                    'rounded-full',
                                    compactCart ? 'size-6 justify-self-end text-white hover:bg-blue-600 hover:text-white' : 'size-7 justify-self-end text-white hover:bg-blue-600 hover:text-white',
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
                                compactCart ? 'h-7 bg-blue-700 px-3 text-[13px] leading-7 text-white' : 'h-8 bg-blue-700 px-3 text-sm leading-8 text-white',
                            ]"
                        >{{ item.quantity }} шт.</span>

                        <p
                            data-testid="order-panel-item-price"
                            :class="[
                                'shrink-0 whitespace-nowrap tabular-nums',
                                compactCart ? 'col-start-3 justify-self-end text-[16px] font-semibold leading-[18px] text-[#404040]' : 'justify-self-end text-[16px] font-semibold leading-[18px] text-[#404040]',
                            ]"
                        >{{ orderItemTotal(item) }}</p>

                        <Button
                            v-if="canEditOrder && !compactCart"
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            data-testid="order-panel-item-remove"
                            class="size-9 justify-self-end rounded-full text-slate-400 transition-[background-color,color,transform] duration-150 hover:bg-rose-50 hover:text-rose-700 active:scale-[0.98]"
                            :disabled="actionLoading"
                            :aria-label="`Удалить блюдо: ${orderItemTitle(item)}`"
                            @click="emit('change-quantity', item, 0)"
                        >
                            <X aria-hidden="true" class="size-4" />
                        </Button>
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
                <div v-if="isAuthenticated" :class="compactCart ? 'text-center' : 'rounded-2xl bg-slate-50 px-4 py-3'">
                    <div :class="['gap-1', compactCart ? 'grid justify-items-center' : 'flex items-center justify-between']">
                        <p
                            data-testid="order-panel-total-label"
                            :class="compactCart ? 'text-center text-[12px] font-semibold leading-4 text-[#737373]' : 'text-[12px] font-semibold leading-4 text-[#737373]'"
                        >Итого</p>
                        <strong
                            data-testid="order-panel-total-price"
                            :class="[
                                'whitespace-nowrap font-bold tabular-nums',
                                compactCart ? 'block text-center text-[32px] leading-[36px] text-[#404040]' : 'text-2xl leading-8 text-[#404040]',
                            ]"
                        >
                            {{ formatCartPrice(order?.total_price ?? 0) }}
                        </strong>
                    </div>
                </div>

                <Button
                    v-if="!isAuthenticated && !disabledCheckoutLabel"
                    type="button"
                    data-testid="order-panel-guest-checkout-button"
                    :class="[
                        'w-full bg-blue-700 font-semibold text-white shadow-sm transition-[background-color,transform] duration-150 hover:bg-blue-800 active:scale-[0.98] focus-visible:ring-blue-600/25 disabled:bg-slate-200 disabled:text-slate-500',
                        compactCart
                            ? 'mt-0 h-[3.625rem] min-h-[3.625rem] rounded-full text-[15.5px] font-bold'
                            : 'mt-0 h-[3.25rem] min-h-12 rounded-full text-sm',
                    ]"
                    @click="emit('open-auth')"
                >
                    Оформить заказ
                </Button>

                <Button
                    v-else-if="canEditOrder && orderItems.length"
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

                <template v-else-if="disabledCheckoutLabel">
                    <Button
                        type="button"
                        data-testid="order-panel-disabled-checkout-button"
                        :class="[
                            'w-full border border-slate-200 bg-slate-100 px-4 font-semibold text-slate-500 shadow-none disabled:cursor-not-allowed disabled:opacity-100',
                            compactCart
                                ? 'mt-4 h-[3.625rem] min-h-[3.625rem] rounded-full text-[15px] font-bold'
                                : 'mt-3 h-[3.25rem] min-h-12 rounded-full text-sm',
                        ]"
                        disabled
                    >
                        {{ disabledCheckoutLabel }}
                    </Button>
                </template>
            </template>
        </div>
    </div>
</template>
