<script setup>
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDateTime, fridgeStatusLabel } from '@/lib/formatters';
import { History } from 'lucide-vue-next';

defineProps({
    fridgeHistory: {
        type: Array,
        default: () => [],
    },
    fridgeLoading: {
        type: Boolean,
        default: false,
    },
    showHeading: {
        type: Boolean,
        default: true,
    },
    orderSkeletonRows: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden px-4 pt-4 sm:px-5 sm:pt-5" :aria-busy="fridgeLoading">
        <div v-if="showHeading" class="mb-4 shrink-0">
            <h2 class="text-lg font-semibold text-slate-950">История питания</h2>
            <p class="mt-0.5 text-sm text-slate-500">Съеденные и списанные блюда</p>
        </div>

        <div v-if="fridgeLoading" class="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain pb-5 pr-1">
            <Skeleton
                v-for="skeleton in orderSkeletonRows"
                :key="`history-skeleton-${skeleton}`"
                class="h-16 w-full rounded-xl bg-slate-100"
            />
        </div>

        <div
            v-else-if="fridgeHistory.length === 0"
            class="flex min-h-0 flex-1 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center"
        >
            <History aria-hidden="true" class="size-7 text-slate-300" />
            <p class="mt-3 text-balance text-base font-semibold text-slate-900">История пока пуста</p>
            <p class="mt-1 text-pretty text-sm leading-6 text-slate-500">Действия с блюдами из холодильника появятся здесь.</p>
        </div>

        <ul v-else class="min-h-0 flex-1 space-y-2 overflow-y-auto overscroll-contain pb-5 pr-1 text-sm text-slate-700">
            <li
                v-for="item in fridgeHistory"
                :key="item.id"
                class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm"
            >
                <div class="flex min-w-0 items-start justify-between gap-3">
                    <span class="min-w-0 line-clamp-2 font-medium text-slate-900">{{ item.title_snapshot }}</span>
                    <Badge variant="outline" class="shrink-0 rounded-lg border-slate-200 bg-white text-slate-500">
                        {{ fridgeStatusLabel(item.status) }}
                    </Badge>
                </div>
                <p v-if="formatDateTime(item.updated_at || item.expires_at)" class="mt-2 text-xs tabular-nums text-slate-500">
                    {{ formatDateTime(item.updated_at || item.expires_at) }}
                </p>
            </li>
        </ul>
    </div>
</template>
