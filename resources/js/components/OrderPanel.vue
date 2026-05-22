<script setup>
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatPrice, orderStatusLabel } from '@/lib/formatters';
import {
    Loader2,
    Minus,
    Plus,
    ShoppingCart,
    Trash2,
    UtensilsCrossed,
    X,
} from 'lucide-vue-next';

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
    isOpenForOrdering: {
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
    weeklyDeadlineLabel: {
        type: String,
        required: true,
    },
    orderSkeletonRows: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['clear-order', 'change-quantity', 'submit-order']);

const orderItemImage = (orderItem) => props.menuItemsById.get(orderItem.menu_item_id)?.image_url ?? null;
const orderItemWeight = (orderItem) => props.menuItemsById.get(orderItem.menu_item_id)?.weight ?? null;
const orderItemTotal = (orderItem) => formatPrice(Number(orderItem.price_snapshot) * Number(orderItem.quantity));
</script>

<template>
    <div class="mt-0 flex min-h-0 flex-1 flex-col px-5 pb-5 pt-5">
        <div class="flex shrink-0 items-center justify-between gap-2">
            <p class="text-[16px] font-black text-[#111827]">{{ totalPositions }} товаров</p>
            <button
                v-if="orderItems.length"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-[8px] px-2 py-1 text-[12px] font-bold text-[#ff3347] transition hover:bg-rose-50"
                :disabled="actionLoading"
                @click="emit('clear-order')"
            >
                Очистить
                <Trash2 class="size-4" />
            </button>
        </div>

        <Alert
            v-if="!isOpenForOrdering"
            class="mt-4 shrink-0 rounded-[10px] border-[#f2dbc4] bg-[#fff7ef] text-[#b06b2b]"
        >
            <AlertDescription>
                Редактирование заказа закрыто
                <span class="block text-xs text-[#c17e3d]">Прием заказов завершен {{ weeklyDeadlineLabel }}</span>
            </AlertDescription>
        </Alert>

        <div v-if="loading" class="mt-4 min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
            <div
                v-for="skeleton in orderSkeletonRows"
                :key="`order-skeleton-${skeleton}`"
                class="space-y-2 rounded-[10px] border border-[#e5ebf7] bg-white p-3"
            >
                <Skeleton class="h-5 w-3/4 rounded-md bg-slate-200/80" />
                <Skeleton class="h-4 w-1/2 rounded-md bg-slate-200/75" />
                <Skeleton class="h-8 w-24 rounded-md bg-slate-200/80" />
            </div>
        </div>

        <div
            v-else-if="!order || orderItems.length === 0"
            class="mt-4 rounded-[12px] border border-[#e5ebf7] bg-[#f8faff] px-5 py-10 text-center"
        >
            <ShoppingCart class="mx-auto size-7 text-[#a3aec6]" />
            <p class="mt-3 text-base font-bold text-[#172033]">Заказ пока пустой</p>
            <p class="mt-1 text-sm font-medium text-[#7080a3]">Добавьте блюда из каталога.</p>
        </div>

        <div v-else class="mt-4 min-h-0 flex-1 space-y-4 overflow-y-auto pr-1">
            <article
                v-for="item in orderItems"
                :key="item.id"
                class="group relative grid grid-cols-[64px_minmax(0,1fr)] gap-3 border-b border-[#eef2f8] pb-4 last:border-b-0"
            >
                <img
                    v-if="orderItemImage(item)"
                    :src="orderItemImage(item)"
                    :alt="item.title_snapshot"
                    class="h-16 w-16 rounded-[10px] border border-[#e5ebf7] object-cover"
                    loading="eager"
                    decoding="async"
                />
                <div v-else class="grid h-16 w-16 place-items-center rounded-[10px] border border-[#e5ebf7] bg-[#f0f4ff] text-[#7080a3]">
                    <UtensilsCrossed class="size-5" />
                </div>

                <div class="min-w-0 pr-6">
                    <button
                        v-if="isOpenForOrdering"
                        type="button"
                        class="absolute right-0 top-0 grid h-7 w-7 place-items-center rounded-[7px] text-[#66769f] transition hover:bg-rose-50 hover:text-[#ff3347]"
                        :aria-label="`Удалить блюдо: ${item.title_snapshot}`"
                        @click="emit('change-quantity', item, 0)"
                    >
                        <X class="size-4" />
                    </button>

                    <p class="line-clamp-2 text-[14px] font-bold leading-5 text-[#172033]">{{ item.title_snapshot }}</p>
                    <p class="mt-1 text-[12px] font-semibold text-[#7080a3]">
                        {{ orderItemWeight(item) || 'Порция' }}
                    </p>

                    <div class="mt-2 flex items-center justify-between gap-3">
                        <p class="text-[16px] font-black text-[#111827]">
                            {{ orderItemTotal(item) }}
                        </p>

                        <div class="inline-flex h-9 items-center gap-1 rounded-[9px] border border-[#e1e8f5] bg-white px-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                class="h-7 w-7 rounded-[7px] text-[#0f52ff] hover:bg-[#edf3ff] hover:text-[#0f52ff]"
                                :disabled="!isOpenForOrdering"
                                :aria-label="`Уменьшить количество: ${item.title_snapshot}`"
                                @click="emit('change-quantity', item, item.quantity - 1)"
                            >
                                <Minus class="size-4" />
                            </Button>
                            <span class="min-w-6 text-center text-[14px] font-bold text-[#111827]">{{ item.quantity }}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                class="h-7 w-7 rounded-[7px] text-[#0f52ff] hover:bg-[#edf3ff] hover:text-[#0f52ff]"
                                :disabled="!isOpenForOrdering"
                                :aria-label="`Увеличить количество: ${item.title_snapshot}`"
                                @click="emit('change-quantity', item, item.quantity + 1)"
                            >
                                <Plus class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </article>
        </div>

        <div class="mt-5 shrink-0 border-t border-[#e5ebf7] pt-5">
            <div class="flex items-center justify-between">
                <p class="text-[16px] font-bold text-[#172033]">Итого</p>
                <strong class="text-[24px] font-black leading-none text-[#111827]">{{ formatPrice(order?.total_price ?? 0) }}</strong>
            </div>
            <p class="mt-2 text-[12px] font-semibold text-[#7080a3]">Статус: {{ orderStatusLabel(order?.status ?? 'draft') }}</p>
        </div>

        <Button
            type="button"
            class="h-[52px] w-full rounded-[10px] bg-[#0f52ff] text-[15px] font-bold text-white shadow-[0_14px_26px_rgba(15,82,255,0.22)] hover:bg-[#0648ec] disabled:bg-[#d8e0ee] disabled:text-[#7383a6] disabled:shadow-none"
            :disabled="!isOpenForOrdering || !orderItems.length || actionLoading"
            @click="emit('submit-order')"
        >
            <Loader2 v-if="actionLoading" class="mr-2 size-4 animate-spin" />
            {{ orderItems.length ? 'Оформить заказ' : 'Добавьте блюда' }}
        </Button>
    </div>
</template>
