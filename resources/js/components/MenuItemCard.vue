<script setup>
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { menuItemDisplayMeta, menuItemDisplayTitle } from '@/lib/menuDisplay';
import { Heart, ImageIcon, Minus, Plus } from 'lucide-vue-next';

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    orderItem: {
        type: Object,
        default: null,
    },
    isFavorite: {
        type: Boolean,
        default: false,
    },
    isAuthenticated: {
        type: Boolean,
        default: false,
    },
    canEditOrder: {
        type: Boolean,
        default: false,
    },
    disabledReason: {
        type: String,
        default: '',
    },
    actionLoading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['toggle-favorite', 'add-item', 'change-quantity']);
const imageFailed = ref(false);
const imageSrc = computed(() => props.item.image_display_url || props.item.image_url);

watch(imageSrc, () => {
    imageFailed.value = false;
});

const showImage = computed(() => Boolean(imageSrc.value) && !imageFailed.value);
const controlsDisabled = computed(() => !props.canEditOrder || props.actionLoading || props.item.is_active === false);
const selectedQuantity = computed(() => Number(props.orderItem?.quantity ?? 0));
const hasSelectedQuantity = computed(() => selectedQuantity.value > 0);
const priceStepperTone = computed(() => {
    if (hasSelectedQuantity.value) {
        return 'border-blue-700 bg-blue-700 text-white';
    }

    if (controlsDisabled.value) {
        return 'border-blue-100 bg-blue-50/60 text-[#404040]/60';
    }

    return 'border-transparent bg-blue-50 text-[#404040]';
});
const priceTextTone = computed(() => (hasSelectedQuantity.value ? 'text-white' : 'text-[#404040]'));
const minusButtonDisabled = computed(() => !props.orderItem || controlsDisabled.value);
const plusButtonDisabled = computed(() => controlsDisabled.value);
const displayTitle = computed(() => menuItemDisplayTitle(props.item));
const displayMeta = computed(() => menuItemDisplayMeta(props.item));
const formatStepperPrice = (value) => {
    const amount = Math.round(Number(value) || 0);

    return `${amount} ₽`;
};
const plusButtonLabel = computed(() => (props.orderItem
    ? `Увеличить количество: ${displayTitle.value}`
    : `Добавить в заказ: ${displayTitle.value}`));
</script>

<template>
    <Card
        data-testid="menu-item-card"
        class="menu-card min-w-0 gap-0 overflow-hidden rounded-[1.15rem] border border-transparent bg-white py-0 text-slate-900 shadow-none ring-0 transition-[background-color] duration-150 max-[430px]:overflow-visible max-[430px]:rounded-none max-[430px]:border-transparent max-[430px]:bg-transparent max-[430px]:shadow-none max-[430px]:transition-none"
    >
        <CardContent class="flex h-full min-w-0 flex-col p-0">
            <div class="relative mx-auto w-[176px] max-w-full pt-1.5 max-[430px]:w-full max-[430px]:pt-0">
                <div
                    data-testid="menu-item-image-area"
                    class="relative size-[176px] max-w-full overflow-hidden rounded-[1rem] bg-white max-[430px]:size-auto max-[430px]:aspect-square max-[430px]:h-auto max-[430px]:w-full max-[430px]:rounded-2xl"
                >
                    <img
                        v-if="showImage"
                        :src="imageSrc"
                        :alt="displayTitle"
                        class="size-full scale-[1.12] object-contain p-1 sm:p-1.5 max-[430px]:scale-[1.05] max-[430px]:p-1"
                        loading="lazy"
                        decoding="async"
                        @error="imageFailed = true"
                    />
                    <div v-else class="flex size-full flex-col items-center justify-center gap-1.5 px-3 text-center text-slate-400/90 max-[430px]:gap-1 max-[430px]:px-1.5">
                        <span class="grid size-9 place-items-center rounded-xl border border-slate-200/70 bg-white text-slate-400 max-[430px]:size-7 max-[430px]:rounded-lg">
                            <ImageIcon aria-hidden="true" class="size-4 max-[430px]:size-3.5" />
                        </span>
                        <span class="text-pretty text-xs font-medium">Фото скоро</span>
                    </div>

                    <div
                        v-if="hasSelectedQuantity"
                        data-testid="menu-item-quantity-overlay"
                        class="pointer-events-none absolute inset-0 z-10 grid place-items-center rounded-[1rem] bg-slate-950/35 text-4xl font-semibold tabular-nums text-white transition-opacity duration-150 max-[430px]:rounded-2xl max-[430px]:text-3xl"
                        aria-hidden="true"
                    >
                        {{ selectedQuantity }}
                    </div>
                </div>

                <button
                    type="button"
                    class="absolute right-3 top-3 z-20 inline-flex size-9 items-center justify-center rounded-full border border-white/80 bg-white/95 text-slate-500 shadow-sm backdrop-blur transition-[background-color,border-color,color,transform] duration-150 hover:text-rose-600 active:scale-[0.98] max-[430px]:right-2 max-[430px]:top-2 max-[430px]:size-8 max-[430px]:border-white/70 max-[430px]:bg-white/85"
                    :class="isFavorite ? 'border-rose-200/80 bg-rose-50/90 text-rose-600' : ''"
                    :aria-label="isFavorite ? `Убрать из избранного: ${displayTitle}` : `Добавить в избранное: ${displayTitle}`"
                    :aria-pressed="isFavorite"
                    @click="emit('toggle-favorite', item.id)"
                >
                    <Heart aria-hidden="true" class="size-5 max-[430px]:size-4" :class="isFavorite ? 'fill-current' : ''" />
                </button>
            </div>

            <div data-testid="menu-item-copy-column" class="mx-auto flex w-[176px] max-w-full flex-1 flex-col pb-3.5 pt-2.5 max-[430px]:w-full max-[430px]:pb-3 max-[430px]:pt-0.5">
                <h3
                    :title="displayTitle"
                    :aria-label="`Название блюда: ${displayTitle}`"
                    class="line-clamp-2 min-h-8 break-words text-[13.25px] font-semibold leading-[16px] text-[#595959] [hyphens:auto] [overflow-wrap:anywhere] max-[430px]:line-clamp-2 max-[430px]:min-h-[2rem] max-[430px]:text-[12.75px] max-[430px]:leading-[15px] max-[430px]:[word-break:normal]"
                >
                    {{ displayTitle }}
                </h3>
                <p data-testid="menu-item-meta" class="mt-1 min-h-[15px] min-w-0 truncate text-[13px] font-semibold leading-[15px] tabular-nums text-[#a6a6a6] max-[430px]:hidden">
                    <span v-if="displayMeta">{{ displayMeta }}</span>
                    <span v-else aria-hidden="true">&nbsp;</span>
                </p>

                <div class="mt-auto pt-2.5 max-[430px]:pt-2">
                    <div
                        data-testid="menu-item-price-stepper"
                        class="grid h-9 w-[132px] grid-cols-[2.25rem_minmax(0,1fr)_2.25rem] items-center rounded-full border p-0.5 transition-[background-color,border-color,color] duration-150 max-[430px]:h-9 max-[430px]:w-[128px] max-[430px]:grid-cols-[2.125rem_minmax(0,1fr)_2.125rem]"
                        :class="priceStepperTone"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="size-8 rounded-full transition-[background-color,color,transform] duration-150 active:scale-[0.98] disabled:opacity-60 max-[430px]:size-8"
                            :class="hasSelectedQuantity ? 'text-white hover:bg-blue-600 hover:text-white' : controlsDisabled ? 'text-[#404040]/50' : 'text-[#404040] hover:bg-white hover:text-[#404040]'"
                            :disabled="minusButtonDisabled"
                            :title="minusButtonDisabled ? disabledReason : undefined"
                            :aria-label="`Уменьшить количество: ${displayTitle}`"
                            @click="orderItem && emit('change-quantity', orderItem, orderItem.quantity - 1)"
                        >
                            <Minus aria-hidden="true" class="size-5 max-[430px]:size-[1.125rem]" />
                        </Button>

                        <span
                            data-testid="menu-item-stepper-price"
                            class="min-w-0 truncate whitespace-nowrap text-center text-[13px] font-bold tabular-nums max-[430px]:min-w-0 max-[430px]:text-[0.78rem]"
                            :class="priceTextTone"
                        >
                            {{ formatStepperPrice(item.price) }}
                        </span>

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="size-8 rounded-full transition-[background-color,color,transform] duration-150 active:scale-[0.98] disabled:opacity-60 max-[430px]:size-8"
                            :class="hasSelectedQuantity ? 'text-white hover:bg-blue-600 hover:text-white' : controlsDisabled ? 'text-[#404040]/50' : 'text-[#404040] hover:bg-white hover:text-[#404040]'"
                            :disabled="plusButtonDisabled"
                            :title="plusButtonDisabled ? disabledReason : undefined"
                            :aria-label="plusButtonLabel"
                            @click="orderItem ? emit('change-quantity', orderItem, orderItem.quantity + 1) : emit('add-item', item.id)"
                        >
                            <Plus aria-hidden="true" class="size-5 max-[430px]:size-[1.125rem]" />
                        </Button>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
