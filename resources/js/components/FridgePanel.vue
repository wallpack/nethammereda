<script setup>
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Skeleton } from '@/components/ui/skeleton';
import { fridgeStatusLabel } from '@/lib/formatters';
import { Refrigerator } from 'lucide-vue-next';

defineProps({
    fridgeItems: {
        type: Array,
        default: () => [],
    },
    fridgeHistory: {
        type: Array,
        default: () => [],
    },
    fridgeLoading: {
        type: Boolean,
        default: false,
    },
    actionLoading: {
        type: Boolean,
        default: false,
    },
    activeFridgeItemsCount: {
        type: Number,
        default: 0,
    },
    orderSkeletonRows: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['eat-one', 'eat-all', 'discard']);
</script>

<template>
    <div class="mt-0 min-h-0 flex-1 space-y-3 overflow-y-auto px-5 pb-5 pt-5">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-[15px] font-black text-[#111827]">Мой холодильник</h2>
            <Badge variant="outline" class="rounded-[8px] border-[#dce5f6] bg-[#f2f6ff] text-xs font-bold text-[#0f52ff]">
                {{ activeFridgeItemsCount }} шт.
            </Badge>
        </div>

        <div v-if="fridgeLoading" class="space-y-2">
            <div
                v-for="skeleton in orderSkeletonRows"
                :key="`fridge-skeleton-${skeleton}`"
                class="space-y-2 rounded-[10px] border border-[#e5ebf7] bg-white p-3"
            >
                <Skeleton class="h-5 w-3/4 rounded-md bg-slate-200/80" />
                <Skeleton class="h-4 w-1/2 rounded-md bg-slate-200/75" />
                <Skeleton class="h-8 w-full rounded-md bg-slate-200/80" />
            </div>
        </div>

        <div
            v-else-if="fridgeItems.length === 0"
            class="rounded-[12px] border border-[#e5ebf7] bg-[#f8faff] px-5 py-10 text-center"
        >
            <Refrigerator class="mx-auto size-7 text-[#a3aec6]" />
            <p class="mt-3 text-base font-bold text-[#172033]">Холодильник пока пуст</p>
            <p class="mt-1 text-sm font-medium text-[#7080a3]">После доставки позиции появятся здесь автоматически.</p>
        </div>

        <div v-else class="space-y-2">
            <article
                v-for="item in fridgeItems"
                :key="item.id"
                class="rounded-[10px] border border-[#e5ebf7] bg-white p-3"
            >
                <p class="text-sm font-bold leading-snug text-[#172033]">{{ item.title_snapshot }}</p>
                <p class="mt-1 text-xs font-semibold text-[#7080a3]">
                    {{ fridgeStatusLabel(item.status) }} · остаток {{ item.quantity_remaining }}/{{ item.quantity_total }}
                </p>

                <div class="mt-3 grid grid-cols-3 gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 rounded-[8px] border-[#dce5f6] bg-white text-xs font-bold text-[#25314d] hover:bg-[#f4f7ff]"
                        :disabled="actionLoading"
                        @click="emit('eat-one', item.id)"
                    >
                        Съел 1
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 rounded-[8px] border-[#dce5f6] bg-white text-xs font-bold text-[#25314d] hover:bg-[#f4f7ff]"
                        :disabled="actionLoading"
                        @click="emit('eat-all', item.id)"
                    >
                        Съел всё
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 rounded-[8px] border-rose-100 bg-rose-50 text-xs font-bold text-rose-600 hover:bg-rose-100"
                        :disabled="actionLoading"
                        @click="emit('discard', item.id)"
                    >
                        Выбросил
                    </Button>
                </div>
            </article>
        </div>

        <Separator class="bg-[#e5ebf7]" />

        <div class="space-y-2">
            <p class="text-sm font-bold text-[#172033]">История</p>

            <div v-if="fridgeHistory.length === 0" class="rounded-[10px] border border-[#e5ebf7] bg-[#f8faff] py-6 text-center text-sm font-medium text-[#7080a3]">
                    История пока пустая
            </div>

            <ul v-else class="space-y-2 text-sm text-[#25314d]">
                <li
                    v-for="item in fridgeHistory"
                    :key="item.id"
                    class="flex items-center justify-between gap-3 rounded-[8px] border border-[#e5ebf7] bg-[#f8faff] px-3 py-2"
                >
                    <span class="line-clamp-2 font-semibold">{{ item.title_snapshot }}</span>
                    <Badge variant="outline" class="rounded-[7px] border-[#dce5f6] bg-white text-[#7080a3]">
                        {{ fridgeStatusLabel(item.status) }}
                    </Badge>
                </li>
            </ul>
        </div>
    </div>
</template>
