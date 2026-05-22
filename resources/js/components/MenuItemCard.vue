<script setup>
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatPrice, nutritionLine, shelfLifeLabel } from '@/lib/formatters';
import { Clock3, Heart, Minus, Plus } from 'lucide-vue-next';

defineProps({
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
    isOpenForOrdering: {
        type: Boolean,
        default: false,
    },
    actionLoading: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['toggle-favorite', 'add-item', 'change-quantity']);
</script>

<template>
    <Card class="menu-card overflow-hidden rounded-[12px] border border-[#e5ebf7] bg-white text-slate-900 shadow-[0_10px_28px_rgba(21,39,75,0.04)] transition-[border-color,box-shadow,transform] duration-200 hover:-translate-y-0.5 hover:border-[#cfdaee] hover:shadow-[0_18px_38px_rgba(21,39,75,0.10)]">
        <CardContent class="flex h-full flex-col gap-0 p-0">
            <div class="relative aspect-[4/3] overflow-hidden bg-[#eef3fb]">
                <img
                    v-if="item.image_url"
                    :src="item.image_url"
                    :alt="item.title"
                    class="size-full object-cover"
                    loading="eager"
                    decoding="async"
                />
                <div v-else class="flex size-full items-center justify-center px-4 text-center text-xs font-medium text-[#7080a3]">
                    Фото скоро загрузим
                </div>

                <button
                    type="button"
                    class="absolute right-3 top-3 inline-flex h-9 w-9 items-center justify-center rounded-[9px] border border-[#e1e8f5] bg-white/95 text-[#7080a3] shadow-[0_8px_18px_rgba(21,39,75,0.10)] transition hover:text-rose-500"
                    :class="isFavorite ? 'border-rose-100 bg-rose-50 text-rose-500' : ''"
                    :aria-label="isFavorite ? `Убрать из избранного: ${item.title}` : `Добавить в избранное: ${item.title}`"
                    :aria-pressed="isFavorite"
                    @click="emit('toggle-favorite', item.id)"
                >
                    <Heart class="size-4" :class="isFavorite ? 'fill-current' : ''" />
                </button>

                <div
                    v-if="shelfLifeLabel(item)"
                    class="absolute bottom-3 left-3 inline-flex h-7 items-center gap-1 rounded-[8px] bg-white/95 px-2.5 text-[12px] font-bold text-[#25314d] shadow-[0_8px_18px_rgba(21,39,75,0.10)]"
                >
                    <Clock3 class="size-3.5 text-[#0f52ff]" />
                    {{ shelfLifeLabel(item) }}
                </div>
            </div>

            <div class="flex flex-1 flex-col p-4">
                <h3 class="line-clamp-2 min-h-[46px] text-[16px] font-bold leading-[1.42] text-[#172033]">{{ item.title }}</h3>
                <p class="mt-2 text-[13px] font-semibold leading-5 text-[#6b7aa0]">{{ item.weight }}</p>
                <p class="mt-2 min-h-9 text-[12px] font-medium leading-[18px] text-[#65769b]">
                    {{ nutritionLine(item) }}
                </p>

                <div class="mt-auto flex min-h-12 items-center justify-between gap-3 pt-5">
                    <p class="text-[21px] font-black leading-none text-[#111827]">{{ formatPrice(item.price) }}</p>
                    <Button
                        v-if="!orderItem"
                        type="button"
                        size="sm"
                        :disabled="isAuthenticated && (!isOpenForOrdering || actionLoading)"
                        class="h-10 rounded-[8px] border-0 bg-[#0f52ff] px-4 text-[13px] font-bold text-white shadow-[0_10px_20px_rgba(15,82,255,0.22)] hover:bg-[#0648ec]"
                        @click="emit('add-item', item.id)"
                    >
                        <Plus class="mr-1 size-4" />
                        Добавить
                    </Button>
                    <div
                        v-else
                        class="inline-flex h-10 items-center gap-1 rounded-[8px] border border-[#e1e8f5] bg-white px-1.5 shadow-[0_8px_16px_rgba(21,39,75,0.05)]"
                    >
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="h-8 w-8 rounded-[7px] text-[#0f52ff] hover:bg-[#edf3ff] hover:text-[#0f52ff]"
                            :disabled="!isOpenForOrdering || actionLoading"
                            :aria-label="`Уменьшить количество: ${item.title}`"
                            @click="emit('change-quantity', orderItem, orderItem.quantity - 1)"
                        >
                            <Minus class="size-4" />
                        </Button>
                        <span class="min-w-7 text-center text-[15px] font-bold text-[#111827]">{{ orderItem.quantity }}</span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="h-8 w-8 rounded-[7px] text-[#0f52ff] hover:bg-[#edf3ff] hover:text-[#0f52ff]"
                            :disabled="!isOpenForOrdering || actionLoading"
                            :aria-label="`Увеличить количество: ${item.title}`"
                            @click="emit('change-quantity', orderItem, orderItem.quantity + 1)"
                        >
                            <Plus class="size-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
