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
const caloriesLabel = computed(() => props.item.calories ? `${compactNumber(props.item.calories)} ккал` : null);
</script>

<template>
    <Card class="menu-card overflow-hidden rounded-[1.45rem] border border-slate-200/80 bg-white text-slate-900 shadow-[0_10px_28px_rgb(15_23_42/0.055)] transition-[border-color,box-shadow,transform] duration-200 hover:-translate-y-px hover:border-blue-100 hover:shadow-[0_18px_36px_rgb(15_23_42/0.085)]">
        <CardContent class="flex h-full flex-col p-0">
            <div class="relative p-2.5 pb-0">
                <div
                    data-testid="menu-item-image-area"
                    class="relative h-64 overflow-hidden rounded-[1.15rem] bg-slate-50/80 sm:h-64 lg:h-[15.5rem] xl:h-[16.25rem]"
                >
                    <img
                        v-if="showImage"
                        :src="imageSrc"
                        :alt="item.title"
                        class="size-full object-contain p-5"
                        loading="lazy"
                        decoding="async"
                        @error="imageFailed = true"
                    />
                    <div v-else class="flex size-full flex-col items-center justify-center gap-2 px-4 text-center text-slate-400">
                        <span class="grid size-12 place-items-center rounded-2xl border border-slate-200 bg-white text-slate-400">
                            <ImageIcon aria-hidden="true" class="size-6" />
                        </span>
                        <span class="text-pretty text-sm font-medium">Фото блюда появится скоро</span>
                    </div>
                </div>

                <button
                    type="button"
                    class="absolute right-4 top-4 inline-flex size-10 items-center justify-center rounded-full border border-white/80 bg-white/95 text-slate-500 shadow-sm backdrop-blur transition-[background-color,border-color,color,transform] duration-150 hover:text-rose-600 active:scale-[0.98]"
                    :class="isFavorite ? 'border-rose-200 bg-rose-50 text-rose-600' : ''"
                    :aria-label="isFavorite ? `Убрать из избранного: ${item.title}` : `Добавить в избранное: ${item.title}`"
                    :aria-pressed="isFavorite"
                    @click="emit('toggle-favorite', item.id)"
                >
                    <Heart aria-hidden="true" class="size-5" :class="isFavorite ? 'fill-current' : ''" />
                </button>
            </div>

            <div class="flex flex-1 flex-col px-5 pb-5 pt-4">
                <h3 class="line-clamp-2 break-words text-balance text-[1.05rem] font-semibold leading-6 text-slate-950 sm:text-[1.12rem]">
                    {{ item.title }}
                </h3>
                <p class="mt-2.5 min-w-0 truncate text-xs font-medium tabular-nums text-slate-400">
                    <span>{{ item.category?.name || 'Меню' }}</span>
                    <span v-if="item.weight"> · {{ item.weight }}</span>
                    <span v-if="caloriesLabel"> · {{ caloriesLabel }}</span>
                </p>

                <div class="mt-auto flex min-h-12 items-center justify-between gap-3 pt-5">
                    <p class="shrink-0 whitespace-nowrap text-xl font-bold tabular-nums text-slate-950">{{ formatPrice(item.price) }}</p>

                    <Button
                        v-if="!orderItem"
                        type="button"
                        size="sm"
                        :disabled="controlsDisabled"
                        :title="controlsDisabled ? disabledReason : undefined"
                        class="h-11 shrink-0 rounded-full bg-blue-700 px-4.5 text-sm font-semibold text-white shadow-sm transition-[background-color,transform] duration-150 hover:bg-blue-800 active:scale-[0.98] disabled:bg-slate-200 disabled:text-slate-500"
                        @click="emit('add-item', item.id)"
                    >
                        <Plus aria-hidden="true" class="size-4" />
                        Добавить
                    </Button>

                    <div
                        v-else-if="canEditOrder"
                        class="inline-flex h-11 shrink-0 items-center rounded-full border border-slate-200 bg-white p-0.5"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="size-10 rounded-full text-blue-700 hover:bg-blue-50 hover:text-blue-700"
                            :disabled="actionLoading"
                            :aria-label="`Уменьшить количество: ${item.title}`"
                            @click="emit('change-quantity', orderItem, orderItem.quantity - 1)"
                        >
                            <Minus aria-hidden="true" class="size-4" />
                        </Button>
                        <span class="min-w-8 text-center text-sm font-semibold tabular-nums text-slate-950">{{ orderItem.quantity }}</span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="size-10 rounded-full text-blue-700 hover:bg-blue-50 hover:text-blue-700"
                            :disabled="actionLoading"
                            :aria-label="`Увеличить количество: ${item.title}`"
                            @click="emit('change-quantity', orderItem, orderItem.quantity + 1)"
                        >
                            <Plus aria-hidden="true" class="size-4" />
                        </Button>
                    </div>

                    <Badge
                        v-else
                        variant="outline"
                        class="h-11 shrink-0 rounded-full border-slate-200 bg-slate-50 px-3 text-sm font-semibold tabular-nums text-slate-700"
                    >
                        В заказе: {{ orderItem.quantity }}
                    </Badge>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
