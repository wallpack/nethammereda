<script setup>
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { compactNumber, formatPrice } from '@/lib/formatters';
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
const showClosedStateCta = computed(() => !props.canEditOrder && !props.orderItem);
const caloriesLabel = computed(() => props.item.calories ? `${compactNumber(props.item.calories)} ккал` : null);
</script>

<template>
    <Card
        data-testid="menu-item-card"
        class="menu-card min-w-0 gap-0 overflow-hidden rounded-[1.15rem] border border-transparent bg-white py-0 text-slate-900 shadow-none ring-0 transition-[border-color,background-color] duration-150 hover:border-blue-100 max-[430px]:overflow-visible max-[430px]:rounded-none max-[430px]:border-transparent max-[430px]:bg-transparent max-[430px]:shadow-none max-[430px]:transition-none"
    >
        <CardContent class="flex h-full min-w-0 flex-col p-0">
            <div class="relative p-1.5 pb-0 max-[430px]:p-0 max-[430px]:pb-0">
                <div
                    data-testid="menu-item-image-area"
                    class="relative h-[10rem] overflow-hidden rounded-[0.95rem] bg-white sm:h-[10.5rem] lg:h-[10.75rem] xl:h-[9.75rem] 2xl:h-[10rem] max-[430px]:h-[7.35rem] max-[430px]:rounded-2xl"
                >
                    <img
                        v-if="showImage"
                        :src="imageSrc"
                        :alt="item.title"
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
                </div>

                <button
                    type="button"
                    class="absolute right-3 top-3 inline-flex size-9 items-center justify-center rounded-full border border-white/80 bg-white/95 text-slate-500 shadow-sm backdrop-blur transition-[background-color,border-color,color,transform] duration-150 hover:text-rose-600 active:scale-[0.98] max-[430px]:right-2 max-[430px]:top-2 max-[430px]:size-8 max-[430px]:border-white/70 max-[430px]:bg-white/85"
                    :class="isFavorite ? 'border-rose-200/80 bg-rose-50/90 text-rose-600' : ''"
                    :aria-label="isFavorite ? `Убрать из избранного: ${item.title}` : `Добавить в избранное: ${item.title}`"
                    :aria-pressed="isFavorite"
                    @click="emit('toggle-favorite', item.id)"
                >
                    <Heart aria-hidden="true" class="size-5 max-[430px]:size-4" :class="isFavorite ? 'fill-current' : ''" />
                </button>
            </div>

            <div class="flex flex-1 flex-col px-3.5 pb-3.5 pt-2.5 max-[430px]:px-3 max-[430px]:pb-3 max-[430px]:pt-0.5">
                <h3
                    :title="item.title"
                    :aria-label="`Название блюда: ${item.title}`"
                    class="line-clamp-2 min-h-[2.45rem] break-words text-balance text-[0.94rem] font-semibold leading-[1.28] text-slate-950 sm:text-[0.98rem] max-[430px]:line-clamp-2 max-[430px]:min-h-[2.2rem] max-[430px]:text-[0.84rem] max-[430px]:leading-[1.18] max-[430px]:[overflow-wrap:break-word] max-[430px]:[word-break:normal] max-[430px]:[hyphens:auto]"
                >
                    {{ item.title }}
                </h3>
                <p data-testid="menu-item-meta" class="mt-1.5 min-w-0 truncate text-[11px] font-medium tabular-nums text-slate-400 max-[430px]:hidden">
                    <span>{{ item.category?.name || 'Меню' }}</span>
                    <span v-if="item.weight"> · {{ item.weight }}</span>
                    <span v-if="caloriesLabel"> · {{ caloriesLabel }}</span>
                </p>

                <div class="mt-auto flex min-h-10 items-center justify-between gap-2 pt-2.5 max-[430px]:min-h-0 max-[430px]:gap-2 max-[430px]:pt-2">
                    <p class="shrink-0 whitespace-nowrap text-[1.08rem] font-bold tabular-nums text-slate-950 max-[430px]:text-[0.95rem]">{{ formatPrice(item.price) }}</p>

                    <template v-if="!orderItem">
                        <Badge
                            v-if="showClosedStateCta"
                            variant="outline"
                            class="h-9 shrink-0 rounded-full border-slate-200 bg-slate-50 px-2.5 text-[11px] font-semibold text-slate-500 max-[430px]:h-9 max-[430px]:px-2 max-[430px]:text-[11px]"
                        >
                            Приём закрыт
                        </Badge>

                        <Button
                            v-else
                            data-testid="menu-item-add-button"
                            type="button"
                            size="sm"
                            :disabled="controlsDisabled"
                            :title="controlsDisabled ? disabledReason : undefined"
                            :aria-label="`Добавить в заказ: ${item.title}`"
                            class="h-9 shrink-0 rounded-full bg-blue-700 px-3 text-xs font-semibold text-white shadow-sm transition-[background-color,transform] duration-150 hover:bg-blue-800 active:scale-[0.98] disabled:bg-slate-200 disabled:text-slate-500 2xl:px-3.5 max-[430px]:inline-flex max-[430px]:size-10 max-[430px]:min-h-10 max-[430px]:min-w-10 max-[430px]:shrink-0 max-[430px]:items-center max-[430px]:justify-center max-[430px]:gap-0 max-[430px]:rounded-full max-[430px]:p-0 max-[430px]:leading-none max-[430px]:text-[0px]"
                            @click="emit('add-item', item.id)"
                        >
                            <Plus aria-hidden="true" class="size-4 max-[430px]:m-0 max-[430px]:size-[18px]" />
                            Добавить
                        </Button>
                    </template>

                    <template v-else-if="canEditOrder">

                        <div
                            data-testid="menu-item-stepper"
                            class="inline-flex h-10 min-w-0 shrink-0 items-center rounded-full border border-slate-200 bg-white p-0.5 max-[430px]:grid max-[430px]:h-9 max-[430px]:w-[5.9rem] max-[430px]:max-w-full max-[430px]:grid-cols-[2rem_minmax(1.5rem,1fr)_2rem] max-[430px]:items-center max-[430px]:border-blue-100 max-[430px]:bg-blue-50/45"
                        >
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                class="size-9 rounded-full text-slate-900 hover:bg-blue-50 hover:text-blue-900 max-[430px]:size-8 max-[430px]:shrink-0 max-[430px]:items-center max-[430px]:justify-center max-[430px]:leading-none"
                                :disabled="actionLoading"
                                :aria-label="`Уменьшить количество: ${item.title}`"
                                @click="emit('change-quantity', orderItem, orderItem.quantity - 1)"
                            >
                                <Minus aria-hidden="true" class="size-4 max-[430px]:size-3.5" />
                            </Button>
                            <span class="min-w-8 flex-1 text-center text-sm font-semibold tabular-nums text-slate-950 max-[430px]:min-w-0 max-[430px]:text-[0.78rem]">{{ orderItem.quantity }}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                class="size-9 rounded-full text-slate-900 hover:bg-blue-50 hover:text-blue-900 max-[430px]:size-8 max-[430px]:shrink-0 max-[430px]:items-center max-[430px]:justify-center max-[430px]:leading-none"
                                :disabled="actionLoading"
                                :aria-label="`Увеличить количество: ${item.title}`"
                                @click="emit('change-quantity', orderItem, orderItem.quantity + 1)"
                            >
                                <Plus aria-hidden="true" class="size-4 max-[430px]:size-3.5" />
                            </Button>
                        </div>
                    </template>

                    <Badge
                        v-else
                        variant="outline"
                        class="h-9 shrink-0 rounded-full border-slate-200 bg-slate-50 px-2.5 text-xs font-semibold tabular-nums text-slate-700 max-[430px]:h-9 max-[430px]:px-2 max-[430px]:text-xs"
                    >
                        В заказе: {{ orderItem.quantity }}
                    </Badge>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
