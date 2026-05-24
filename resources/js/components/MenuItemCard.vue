<script setup>
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { formatPrice, nutritionLine } from '@/lib/formatters';
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
</script>

<template>
    <Card class="menu-card overflow-hidden rounded-3xl border border-slate-200 bg-white text-slate-900 shadow-sm transition-[border-color,box-shadow] duration-150 hover:border-slate-300 hover:shadow-md">
        <CardContent class="flex h-full flex-col p-0">
            <div class="relative p-2.5 pb-0 sm:p-3 sm:pb-0">
                <div class="relative aspect-[4/3] overflow-hidden rounded-2xl bg-slate-50 ring-1 ring-inset ring-slate-100">
                    <img
                        v-if="showImage"
                        :src="imageSrc"
                        :alt="item.title"
                        class="size-full object-contain p-3 sm:p-4"
                        loading="lazy"
                        decoding="async"
                        @error="imageFailed = true"
                    />
                    <div v-else class="flex size-full flex-col items-center justify-center gap-2 px-4 text-center text-slate-400">
                        <ImageIcon aria-hidden="true" class="size-7" />
                        <span class="text-sm font-medium">Фото блюда появится скоро</span>
                    </div>
                </div>

                <button
                    type="button"
                    class="absolute right-5 top-5 inline-flex size-11 items-center justify-center rounded-xl border border-slate-200/80 bg-white/95 text-slate-500 shadow-sm transition-[background-color,border-color,color,transform] duration-150 hover:text-rose-600 active:scale-[0.98]"
                    :class="isFavorite ? 'border-rose-200 bg-rose-50 text-rose-600' : ''"
                    :aria-label="isFavorite ? `Убрать из избранного: ${item.title}` : `Добавить в избранное: ${item.title}`"
                    :aria-pressed="isFavorite"
                    @click="emit('toggle-favorite', item.id)"
                >
                    <Heart aria-hidden="true" class="size-5" :class="isFavorite ? 'fill-current' : ''" />
                </button>
            </div>

            <div class="flex flex-1 flex-col px-5 pb-5 pt-4">
                <div class="flex min-w-0 items-center gap-2 text-xs font-medium text-slate-500">
                    <span class="truncate">{{ item.category?.name || 'Меню' }}</span>
                    <span v-if="item.weight" aria-hidden="true" class="shrink-0 text-slate-300">•</span>
                    <span v-if="item.weight" class="shrink-0 tabular-nums">{{ item.weight }}</span>
                </div>

                <h3 class="mt-2.5 line-clamp-2 text-balance text-lg font-semibold leading-6 text-slate-950">
                    {{ item.title }}
                </h3>
                <p v-if="item.description" class="mt-1.5 line-clamp-2 text-pretty text-sm leading-5 text-slate-500">
                    {{ item.description }}
                </p>
                <p class="mt-3 text-xs font-medium tabular-nums text-slate-500">
                    {{ nutritionLine(item) }}
                </p>

                <div class="mt-auto flex min-h-12 items-center justify-between gap-3 pt-5">
                    <p class="text-lg font-semibold tabular-nums text-slate-950">{{ formatPrice(item.price) }}</p>

                    <Button
                        v-if="!orderItem"
                        type="button"
                        size="sm"
                        :disabled="controlsDisabled"
                        :title="controlsDisabled ? disabledReason : undefined"
                        class="h-11 rounded-xl border border-blue-100 bg-blue-50 px-4 text-sm font-semibold text-blue-700 shadow-none transition-[background-color,border-color,transform] duration-150 hover:border-blue-200 hover:bg-blue-100 active:scale-[0.98] disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-500"
                        @click="emit('add-item', item.id)"
                    >
                        <Plus aria-hidden="true" class="size-4" />
                        Добавить
                    </Button>

                    <div
                        v-else-if="canEditOrder"
                        class="inline-flex h-11 items-center rounded-xl border border-slate-200 bg-white p-1"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="size-9 rounded-lg text-blue-700 hover:bg-blue-50 hover:text-blue-700"
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
                            class="size-9 rounded-lg text-blue-700 hover:bg-blue-50 hover:text-blue-700"
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
                        class="h-11 rounded-xl border-slate-200 bg-slate-50 px-3 text-sm font-semibold tabular-nums text-slate-700"
                    >
                        В заказе: {{ orderItem.quantity }}
                    </Badge>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
