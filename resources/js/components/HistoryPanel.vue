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
    <div class="min-h-0 flex-1 px-5 pb-5 pt-5" :aria-busy="fridgeLoading">
        <div v-if="showHeading" class="mb-4">
            <h2 class="text-lg font-semibold text-slate-950">История питания</h2>
            <p class="mt-0.5 text-sm text-slate-500">Съеденные и списанные блюда</p>
        </div>

        <div v-if="fridgeLoading" class="space-y-3">
            <Skeleton
                v-for="skeleton in orderSkeletonRows"
                :key="`history-skeleton-${skeleton}`"
                class="h-16 w-full rounded-xl bg-slate-100"
            />
        </div>

        <div
            v-else-if="fridgeHistory.length === 0"
            class="flex flex-col items-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center"
        >
            <History aria-hidden="true" class="size-7 text-slate-300" />
            <p class="mt-3 text-balance text-base font-semibold text-slate-900">История пока пуста</p>
            <p class="mt-1 text-pretty text-sm leading-6 text-slate-500">Действия с блюдами из холодильника появятся здесь.</p>
        </div>

        <ul v-else class="space-y-2 text-sm text-slate-700">
            <li
                v-for="item in fridgeHistory"
                :key="item.id"
                class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3"
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
